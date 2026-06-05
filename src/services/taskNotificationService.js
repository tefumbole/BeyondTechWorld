import { sendWhatsAppMessage } from './wasenderapiService';
import {
  personalizeTaskContent,
  buildTaskInviteUrl,
  DEFAULT_TASK_NOTIFICATION_TEMPLATE,
} from '@/utils/taskPersonalization';

const useMysql = import.meta.env.VITE_DATA_BACKEND === 'mysql';
const API_BASE = import.meta.env.VITE_API_URL || '/api';
const STORAGE_KEY = 'alpha_supabase_auth';

function getToken() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return null;
    const parsed = JSON.parse(raw);
    return parsed?.access_token || parsed?.currentSession?.access_token || null;
  } catch {
    return null;
  }
}

async function notifyViaApi(assignmentId, messageTemplate, documentLinks) {
  const token = getToken();
  const res = await fetch(`${API_BASE}/tasks/notify-assignment`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify({ assignmentId, messageTemplate, documentLinks }),
  });
  const json = await res.json().catch(() => ({}));
  return { success: Boolean(json.success), error: json.error };
}

export async function sendTaskAssignmentNotification({
  assigneePhone,
  assigneeName,
  assigneeEmail,
  taskTitle,
  taskDescription,
  deadline,
  priority,
  startDate,
  inviteToken,
  messageTemplate,
  documentLinks,
  assignmentId,
}) {
  if (useMysql && assignmentId) {
    return notifyViaApi(assignmentId, messageTemplate, documentLinks);
  }

  if (!assigneePhone) return { success: false, error: 'No phone number provided' };

  const loginLink = inviteToken ? buildTaskInviteUrl(inviteToken) : `${window.location.origin}/login`;
  const template = messageTemplate || DEFAULT_TASK_NOTIFICATION_TEMPLATE;
  const message = personalizeTaskContent(template, {
    name: assigneeName || assigneeEmail || 'Team Member',
    email: assigneeEmail || '',
    phone: assigneePhone || '',
    task_title: taskTitle,
    task_message: personalizeTaskContent(taskDescription || '', {
      name: assigneeName || '',
      email: assigneeEmail || '',
      phone: assigneePhone || '',
    }),
    deadline: deadline ? new Date(deadline).toLocaleDateString() : '',
    priority: priority || '',
    start_date: startDate ? new Date(startDate).toLocaleDateString() : '',
    login_link: loginLink,
    document_links: documentLinks || '',
  });

  return sendWhatsAppMessage(assigneePhone, message);
}

export const sendTaskReminderNotification = async (assigneePhone, assigneeName, taskTitle, deadline) => {
  if (!assigneePhone) return { success: false, error: 'No phone number provided' };

  const message = `*Reminder* ⏰\n\nHello ${assigneeName},\nThis is a reminder for your pending task:\n*${taskTitle}*\n\nDeadline: ${new Date(deadline).toLocaleDateString()}\n\nPlease update your progress on the dashboard.`;

  return sendWhatsAppMessage(assigneePhone, message);
};

export const sendAdminTaskAcceptedNotification = async (adminPhone, assigneeName, taskTitle) => {
  if (!adminPhone) return { success: false, error: 'No phone number provided' };

  const message = `Task Update 📊\n\n${assigneeName} has *accepted* the task:\n*${taskTitle}*`;

  return sendWhatsAppMessage(adminPhone, message);
};

export const sendAdminTaskCompletedNotification = async (adminPhone, assigneeName, taskTitle) => {
  if (!adminPhone) return { success: false, error: 'No phone number provided' };

  const message = `Task Completed ✅\n\n${assigneeName} has completed their assignment for:\n*${taskTitle}*`;

  return sendWhatsAppMessage(adminPhone, message);
};

export async function processScheduledTaskNotifications() {
  if (!useMysql) return { success: true, processed: 0 };

  const token = getToken();
  const res = await fetch(`${API_BASE}/tasks/process-scheduled`, {
    method: 'POST',
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  return res.json().catch(() => ({ success: false }));
}
