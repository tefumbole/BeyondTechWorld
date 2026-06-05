export const TASK_PLACEHOLDERS = [
  '{name}',
  '{email}',
  '{phone}',
  '{task_title}',
  '{deadline}',
  '{priority}',
  '{start_date}',
  '{login_link}',
  '{document_links}',
  '{task_message}',
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

export const DEFAULT_TASK_NOTIFICATION_TEMPLATE = `Hello {name},

You have been assigned a new task:
*{task_title}*

{task_message}

Deadline: {deadline}
Priority: {priority}

Open your task dashboard:
{login_link}`;
