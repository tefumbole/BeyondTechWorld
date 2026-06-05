/**
 * Queue Worker Service
 * 
 * Overview:
 * This service manages the asynchronous background processing of message delivery 
 * (Email and WhatsApp) via a database-backed queue table (`message_queue`).
 */

import { supabase } from '@/lib/customSupabaseClient';
import { sendEmailMessage } from './emailService';
import { sendWhatsAppMessage } from './wasenderapiService';
import { personalizeContent } from './pdfGenerationService';

let globalWorkerInterval = null;
const WORKER_INTERVAL_MS = 15000; // 15 seconds

/**
 * Loads related message + recipient rows for queue jobs (MySQL has no join selects).
 */
export const hydrateQueueJobs = async (jobs) => {
  if (!jobs?.length) return [];

  const messageIds = [...new Set(jobs.map((j) => j.message_id).filter(Boolean))];
  const recipientIds = [...new Set(jobs.map((j) => j.message_recipient_id).filter(Boolean))];

  const messagesById = {};
  const recipientsById = {};

  if (messageIds.length) {
    const { data: messages } = await supabase
      .from('messages')
      .select('id, subject, body, generate_pdf')
      .in('id', messageIds);
    (messages || []).forEach((m) => { messagesById[m.id] = m; });
  }

  if (recipientIds.length) {
    const { data: recipients } = await supabase
      .from('message_recipients')
      .select('*')
      .in('id', recipientIds);
    (recipients || []).forEach((r) => { recipientsById[r.id] = r; });
  }

  return jobs.map((job) => ({
    ...job,
    messages: messagesById[job.message_id] || null,
    message_recipients: recipientsById[job.message_recipient_id] || null,
  }));
};

/**
 * Retrieves pending jobs that are eligible to run.
 */
export const getPendingQueueJobs = async (limit = 10) => {
  try {
    const now = new Date().toISOString();

    const { data, error } = await supabase
      .from('message_queue')
      .select('*')
      .in('status', ['pending'])
      .or(`run_after.lte.${now},run_after.is.null`)
      .is('locked_at', null)
      .order('created_at', { ascending: true })
      .limit(limit);

    if (error) throw error;
    return hydrateQueueJobs(data || []);
  } catch (error) {
    console.error('Error fetching pending jobs:', error);
    return [];
  }
};

/**
 * Locks a job to prevent duplicate processing.
 */
export const lockQueueJob = async (jobId) => {
  try {
    const workerId = `worker-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
    const { error } = await supabase
      .from('message_queue')
      .update({
        locked_at: new Date().toISOString(),
        locked_by: workerId,
        status: 'processing'
      })
      .eq('id', jobId)
      .is('locked_at', null);

    if (error) throw error;

    const { data, error: fetchError } = await supabase
      .from('message_queue')
      .select('*')
      .eq('id', jobId)
      .single();

    if (fetchError || !data || data.status !== 'processing') return null;
    const [hydrated] = await hydrateQueueJobs([data]);
    return hydrated || null;
  } catch (error) {
    return null;
  }
};

/**
 * Unlocks a job and sets its final status.
 */
export const unlockQueueJob = async (jobId, status, errorMsg = null, nextRunAfter = null) => {
  try {
    const updateData = {
      locked_at: null,
      locked_by: null,
      status: status,
      last_error: errorMsg,
      updated_at: new Date().toISOString()
    };

    if (nextRunAfter) {
      updateData.run_after = nextRunAfter;
    }

    const { error } = await supabase
      .from('message_queue')
      .update(updateData)
      .eq('id', jobId);

    if (error) throw error;
  } catch (error) {
    console.error(`Error unlocking job ${jobId}:`, error);
  }
};

/**
 * Updates recipient delivery status in message_recipients
 */
export const updateRecipientDeliveryStatus = async (recipientId, status, errorMsg = null) => {
  try {
    const updateData = { status };
    if (errorMsg) updateData.last_error = errorMsg;
    
    await supabase
      .from('message_recipients')
      .update(updateData)
      .eq('id', recipientId);
  } catch (error) {
    console.error(`Error updating recipient status ${recipientId}:`, error);
  }
};

/**
 * Handles job failure with exponential backoff.
 */
export const handleJobFailure = async (job, errorMsg) => {
  const maxAttempts = job.max_attempts || 3;
  const currentAttempts = (job.attempts || 0) + 1;
  
  let newStatus = 'pending';
  let nextRunAfter = null;

  if (currentAttempts >= maxAttempts) {
    newStatus = 'failed';
  } else {
    // Exponential backoff: 2min, 4min, 8min...
    const backoffMinutes = Math.pow(2, currentAttempts);
    const runAfterDate = new Date();
    runAfterDate.setMinutes(runAfterDate.getMinutes() + backoffMinutes);
    nextRunAfter = runAfterDate.toISOString();
  }

  try {
    await supabase
      .from('message_queue')
      .update({ attempts: currentAttempts })
      .eq('id', job.id);

    await unlockQueueJob(job.id, newStatus, errorMsg, nextRunAfter);
    
    if (newStatus === 'failed') {
      await updateRecipientDeliveryStatus(job.message_recipient_id, 'failed', errorMsg);
    }
    
    await supabase.from('message_logs').insert({
      message_id: job.message_id,
      message_recipient_id: job.message_recipient_id,
      queue_id: job.id,
      channel: job.channel,
      event_type: newStatus === 'failed' ? 'delivery_failed_permanent' : 'delivery_failed_retry',
      details: { error: errorMsg, attempt: currentAttempts }
    });
  } catch (err) {
    console.error('Error handling job failure:', err);
  }
};

/**
 * Processes an individual WhatsApp job.
 */
export const processWhatsAppJob = async (job) => {
  const { messages, message_recipients } = job;
  const phone = message_recipients.recipient_phone;
  
  if (!phone) throw new Error("No phone number provided");

  const result = await sendWhatsAppMessage(
    phone, 
    messages.body, 
    message_recipients, 
    message_recipients.reference_code,
    message_recipients.pdf_url
  );

  if (!result.success) {
    throw new Error(result.error || "WhatsApp sending failed");
  }
  return result;
};

/**
 * Processes an individual Email job.
 */
export const processEmailJob = async (job) => {
  const { messages, message_recipients } = job;
  const email = message_recipients.recipient_email;

  if (!email) throw new Error("No email address provided");

  const personalizedBody = personalizeContent(messages.body, message_recipients);

  const result = await sendEmailMessage(
    email,
    messages.subject,
    personalizedBody,
    message_recipients.reference_code,
    message_recipients.pdf_url
  );

  if (!result.success) {
    throw new Error(result.error || "Email sending failed");
  }
  return result;
};

/**
 * Core processor for a queued job.
 */
export const processQueueJob = async (job) => {
  const lockedJob = await lockQueueJob(job.id);
  if (!lockedJob) {
    return;
  }

  try {
    if (job.channel === 'whatsapp') {
      await processWhatsAppJob(job);
    } else if (job.channel === 'email') {
      await processEmailJob(job);
    } else {
      throw new Error(`Unknown channel: ${job.channel}`);
    }

    await unlockQueueJob(job.id, 'completed');
    await updateRecipientDeliveryStatus(job.message_recipient_id, 'delivered');
    
    await supabase.from('message_logs').insert({
      message_id: job.message_id,
      message_recipient_id: job.message_recipient_id,
      queue_id: job.id,
      channel: job.channel,
      event_type: 'delivery_success',
      details: { attempt: (job.attempts || 0) + 1 }
    });

  } catch (error) {
    await handleJobFailure(job, error.message);
  }
};

/**
 * Pulls a batch of pending jobs and processes them sequentially.
 */
export const processAllPendingJobs = async () => {
  const jobs = await getPendingQueueJobs(20);
  if (jobs.length === 0) return 0;
  
  for (const job of jobs) {
    await processQueueJob(job);
  }
  
  return jobs.length;
};

/**
 * Core background worker start function
 */
export const startBackgroundWorker = () => {
  if (globalWorkerInterval) {
    return;
  }
  
  console.log('Starting background queue worker...');
  globalWorkerInterval = setInterval(async () => {
    try {
      await processAllPendingJobs();
    } catch (error) {
      console.error('Error in background queue worker:', error);
    }
  }, WORKER_INTERVAL_MS);
};

export const stopBackgroundWorker = () => {
  if (globalWorkerInterval) {
    clearInterval(globalWorkerInterval);
    globalWorkerInterval = null;
    console.log('Background queue worker stopped.');
  }
};

export const autoStartWorker = () => {
  if (!globalWorkerInterval) {
    startBackgroundWorker();
  }
};

export const isWorkerRunning = () => {
  return globalWorkerInterval !== null;
};

export const stopGlobalWorker = () => {
  stopBackgroundWorker();
};

export const getQueueStats = async () => {
  try {
    const { data, error } = await supabase
      .from('message_queue')
      .select('status');

    if (error) throw error;

    const stats = {
      pending: 0,
      processing: 0,
      completed: 0,
      failed: 0,
      total: data.length
    };

    data.forEach(job => {
      if (stats[job.status] !== undefined) {
        stats[job.status]++;
      }
    });

    return stats;
  } catch (error) {
    console.error('Error fetching queue stats:', error);
    return { pending: 0, processing: 0, completed: 0, failed: 0, total: 0 };
  }
};