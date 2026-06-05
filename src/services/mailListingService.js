import { supabase } from '@/lib/customSupabaseClient';

export const fetchMessages = async (page = 1, pageSize = 10, filters = {}) => {
  try {
    let query = supabase
      .from('messages')
      .select('*', { count: 'exact' });

    if (filters.status && filters.status !== 'All') {
      query = query.eq('status', filters.status.toLowerCase());
    }

    if (filters.category && filters.category !== 'All') {
      query = query.eq('category', filters.category);
    }

    if (filters.search) {
      query = query.or(`subject.ilike.%${filters.search}%,reference.ilike.%${filters.search}%`);
    }

    const from = (page - 1) * pageSize;
    const to = from + pageSize - 1;

    const { data, count, error } = await query
      .order('created_at', { ascending: false })
      .range(from, to);

    if (error) throw error;

    const messages = data || [];
    const messageIds = messages.map((m) => m.id).filter(Boolean);

    const recCountMap = {};
    const attCountMap = {};

    if (messageIds.length) {
      const { data: recipients } = await supabase
        .from('message_recipients')
        .select('message_id')
        .in('message_id', messageIds);
      (recipients || []).forEach((r) => {
        recCountMap[r.message_id] = (recCountMap[r.message_id] || 0) + 1;
      });

      const { data: attachments } = await supabase
        .from('message_attachments')
        .select('message_id')
        .in('message_id', messageIds);
      (attachments || []).forEach((a) => {
        attCountMap[a.message_id] = (attCountMap[a.message_id] || 0) + 1;
      });
    }

    return {
      data: messages.map((msg) => ({
        ...msg,
        recipients_count: recCountMap[msg.id] || 0,
        attachments_count: attCountMap[msg.id] || 0,
      })),
      count,
    };
  } catch (error) {
    console.error('Error fetching messages:', error);
    throw error;
  }
};

export const getMessageDetails = async (messageId) => {
  try {
    const { data: message, error: msgError } = await supabase
      .from('messages')
      .select('*')
      .eq('id', messageId)
      .single();

    if (msgError) throw msgError;

    const { data: recipients, error: recError } = await supabase
      .from('message_recipients')
      .select('*')
      .eq('message_id', messageId);

    if (recError) throw recError;

    const { data: attachments, error: attError } = await supabase
      .from('message_attachments')
      .select('*')
      .eq('message_id', messageId);

    if (attError) throw attError;

    return { ...message, recipients, attachments };
  } catch (error) {
    console.error('Error fetching message details:', error);
    throw error;
  }
};

export const getRecipientDetails = async (recipientId) => {
  try {
    const { data: recipient, error: recError } = await supabase
      .from('message_recipients')
      .select('*')
      .eq('id', recipientId)
      .single();

    if (recError) throw recError;

    const { data: queueJobs, error: queueError } = await supabase
      .from('message_queue')
      .select('*')
      .eq('message_recipient_id', recipientId);

    if (queueError) throw queueError;

    const { data: logs, error: logsError } = await supabase
      .from('message_logs')
      .select('*')
      .eq('message_recipient_id', recipientId)
      .order('created_at', { ascending: false });

    if (logsError) throw logsError;

    return { ...recipient, queueJobs, logs };
  } catch (error) {
    console.error('Error fetching recipient details:', error);
    throw error;
  }
};

export const fetchMessageCategories = async () => {
  try {
    const { data, error } = await supabase
      .from('messages')
      .select('category')
      .not('category', 'is', null);

    if (error) throw error;
    
    const categories = [...new Set(data.map(d => d.category))];
    return categories.filter(Boolean);
  } catch (error) {
    console.error('Error fetching categories:', error);
    return [];
  }
};

export const resendToFailedRecipients = async (messageId) => {
  try {
    // 1. Find failed queue jobs for this message
    const { data: failedJobs, error: findError } = await supabase
      .from('message_queue')
      .select('*')
      .eq('message_id', messageId)
      .eq('status', 'failed');

    if (findError) throw findError;
    if (!failedJobs || failedJobs.length === 0) return 0;

    // 2. Reset them to pending
    const jobIds = failedJobs.map(j => j.id);
    const { error: updateError } = await supabase
      .from('message_queue')
      .update({
        status: 'pending',
        attempts: 0,
        last_error: null,
        locked_at: null,
        locked_by: null,
        run_after: null
      })
      .in('id', jobIds);

    if (updateError) throw updateError;
    return failedJobs.length;
  } catch (error) {
    console.error('Error resending to failed recipients:', error);
    throw error;
  }
};