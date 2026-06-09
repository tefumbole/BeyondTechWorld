export const TASK_PLACEHOLDERS = [
  '{name}',
  '{email}',
  '{phone}',
  '{subject}',
  '{task_title}',
  '{description}',
  '{task_message}',
  '{deadline}',
  '{priority}',
  '{start_date}',
  '{login_link}',
  '{document_links}',
];

export function getAppBaseUrl() {
  if (typeof window !== 'undefined' && window.location?.origin) {
    return window.location.origin;
  }
  return import.meta.env.VITE_APP_URL || 'https://alpha-bridge.net';
}

export function buildTaskInviteUrl(inviteToken) {
  return `${getAppBaseUrl()}/task-invite/${inviteToken}`;
}

export function personalizeTaskContent(template, variables = {}) {
  if (!template) return '';
  let result = template;
  for (const [key, value] of Object.entries(variables)) {
    result = result.replace(new RegExp(`\\{${key}\\}`, 'gi'), value ?? '');
  }
  return result;
}

export const DEFAULT_TASK_NOTIFICATION_TEMPLATE = `📋 *NEW TASK ASSIGNMENT*
━━━━━━━━━━━━━━━

Hello *{name}*,

You have been assigned a new task:

▪️ *Task:* {subject}
▪️ *Priority:* {priority}
▪️ *Start:* {start_date}{start_time}
▪️ *Deadline:* {deadline}{deadline_time}

{description}

👉 Sign in to accept your task:
{login_link}

_Alpha Bridge Technologies Ltd_`;
