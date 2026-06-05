import { Router } from 'express';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { optionalAuth, requireAuth } from '../middleware/auth.js';
import { sendTextMessage, formatPhoneNumber } from '../services/wasenderWhatsAppService.js';

const router = Router();

const APP_BASE = process.env.APP_BASE_URL || 'https://alpha-bridge.net';

const DEFAULT_TEMPLATE = `Hello {name},

You have been assigned a new task:
*{task_title}*

{task_message}

Deadline: {deadline}
Priority: {priority}

Open your task dashboard:
{login_link}`;

function personalize(template, vars) {
  let result = template || '';
  for (const [key, value] of Object.entries(vars)) {
    result = result.replace(new RegExp(`\\{${key}\\}`, 'gi'), value ?? '');
  }
  return result;
}

function requireAdmin(req, res, next) {
  const role = String(req.user?.role || '').toLowerCase();
  if (!['admin', 'super_admin', 'director'].includes(role)) {
    return res.status(403).json({ error: 'Forbidden' });
  }
  next();
}

router.get('/invite/:token', optionalAuth, async (req, res) => {
  try {
    const pool = getPool();
    const [rows] = await pool.query(
      `SELECT ta.id AS assignment_id, ta.status AS assignment_status, ta.user_id,
              t.id AS task_id, t.title, t.deadline, t.priority,
              u.name AS assignee_name, u.email AS assignee_email, u.phone AS assignee_phone
       FROM task_assignments ta
       JOIN tasks t ON t.id = ta.task_id
       LEFT JOIN users u ON u.id = ta.user_id
       WHERE ta.invite_token = ?
       LIMIT 1`,
      [req.params.token]
    );
    if (!rows.length) {
      return res.status(404).json({ error: 'Invalid or expired task invite link.' });
    }
    res.json({ invite: rows[0], loggedIn: Boolean(req.user) });
  } catch (err) {
    console.error('[tasks/invite]', err);
    res.status(500).json({ error: err.message });
  }
});

router.post('/notify-assignment', requireAuth, requireAdmin, async (req, res) => {
  try {
    const { assignmentId, messageTemplate, documentLinks } = req.body || {};
    if (!assignmentId) {
      return res.status(400).json({ success: false, error: 'assignmentId required' });
    }

    const pool = getPool();
    const [rows] = await pool.query(
      `SELECT ta.*, t.title, t.description, t.deadline, t.priority, t.start_date, t.notification_template,
              u.name, u.email, u.phone
       FROM task_assignments ta
       JOIN tasks t ON t.id = ta.task_id
       JOIN users u ON u.id = ta.user_id
       WHERE ta.id = ?
       LIMIT 1`,
      [assignmentId]
    );
    const row = rows[0];
    if (!row) {
      return res.status(404).json({ success: false, error: 'Assignment not found' });
    }

    const phone = formatPhoneNumber(row.phone);
    if (!phone) {
      return res.status(400).json({ success: false, error: 'Assignee has no valid phone number' });
    }

    const template = messageTemplate || row.notification_template || DEFAULT_TEMPLATE;
    const loginLink = `${APP_BASE}/task-invite/${row.invite_token}`;
    const docLinks = documentLinks || '';
    const taskMessage = personalize(row.description || '', {
      name: row.name,
      email: row.email,
      phone: row.phone,
      task_title: row.title,
      deadline: row.deadline ? new Date(row.deadline).toLocaleDateString() : '',
      priority: row.priority,
      start_date: row.start_date ? new Date(row.start_date).toLocaleDateString() : '',
      login_link: loginLink,
      document_links: docLinks,
      task_message: personalize(row.description || '', {
        name: row.name,
        email: row.email,
        phone: row.phone,
      }),
    });

    const text = personalize(template, {
      name: row.name || row.email,
      email: row.email || '',
      phone: row.phone || '',
      task_title: row.title,
      deadline: row.deadline ? new Date(row.deadline).toLocaleDateString() : '',
      priority: row.priority || '',
      start_date: row.start_date ? new Date(row.start_date).toLocaleDateString() : '',
      login_link: loginLink,
      document_links: docLinks,
      task_message: taskMessage,
    });

    const result = await sendTextMessage(phone, text);
    res.json({ success: result.success, error: result.error || null });
  } catch (err) {
    console.error('[tasks/notify-assignment]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/process-scheduled', requireAuth, requireAdmin, async (_req, res) => {
  try {
    const pool = getPool();
    const [due] = await pool.query(
      `SELECT q.*, t.title, t.description, t.deadline, t.priority, t.start_date, t.notification_template,
              u.name, u.email, u.phone, ta.invite_token
       FROM task_notification_queue q
       JOIN tasks t ON t.id = q.task_id
       JOIN task_assignments ta ON ta.id = q.assignment_id
       JOIN users u ON u.id = ta.user_id
       WHERE q.status = 'pending' AND q.scheduled_at <= NOW()
       ORDER BY q.scheduled_at ASC
       LIMIT 50`
    );

    let sent = 0;
    let failed = 0;

    for (const row of due) {
      const phone = formatPhoneNumber(row.phone);
      if (!phone) {
        await pool.query(
          `UPDATE task_notification_queue SET status = 'failed', last_error = ? WHERE id = ?`,
          ['No valid phone', row.id]
        );
        failed++;
        continue;
      }

      const [docs] = await pool.query(
        `SELECT file_url FROM task_attachments WHERE task_id = ? AND attachment_type = 'source'`,
        [row.task_id]
      );
      const documentLinks = docs.map((d) => d.file_url).filter(Boolean).join('\n');
      const loginLink = `${APP_BASE}/task-invite/${row.invite_token}`;
      const template = row.notification_template || DEFAULT_TEMPLATE;

      const text = personalize(template, {
        name: row.name || row.email,
        email: row.email || '',
        phone: row.phone || '',
        task_title: row.title,
        deadline: row.deadline ? new Date(row.deadline).toLocaleDateString() : '',
        priority: row.priority || '',
        start_date: row.start_date ? new Date(row.start_date).toLocaleDateString() : '',
        login_link: loginLink,
        document_links: documentLinks,
        task_message: personalize(row.description || '', {
          name: row.name,
          email: row.email,
          phone: row.phone,
        }),
      });

      const result = await sendTextMessage(phone, text);
      if (result.success) {
        await pool.query(
          `UPDATE task_notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?`,
          [row.id]
        );
        sent++;
      } else {
        await pool.query(
          `UPDATE task_notification_queue SET status = 'failed', last_error = ? WHERE id = ?`,
          [result.error || 'Send failed', row.id]
        );
        failed++;
      }
    }

    res.json({ success: true, processed: due.length, sent, failed });
  } catch (err) {
    console.error('[tasks/process-scheduled]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
