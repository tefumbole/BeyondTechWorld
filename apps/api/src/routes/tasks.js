import { Router } from 'express';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { optionalAuth, requireAuth } from '../middleware/auth.js';
import { sendTextMessage, sendDocumentMessage, formatPhoneNumber } from '../services/wasenderWhatsAppService.js';

const router = Router();

const APP_BASE = process.env.APP_BASE_URL || 'https://alpha-bridge.net';

const BRAND_FOOTER = '_Alpha Bridge Technologies Ltd_';
const DIVIDER = '━━━━━━━━━━━━━━━';

const DEFAULT_TEMPLATE = `📋 *NEW TASK ASSIGNMENT*
${DIVIDER}

Hello *{name}*,

You have been assigned a new task:

▪️ *Task:* {subject}
▪️ *Priority:* {priority}
▪️ *Start:* {start_date}{start_time}
▪️ *Deadline:* {deadline}{deadline_time}

{description}

👉 Sign in to accept your task:
{login_link}

${BRAND_FOOTER}`;

function personalize(template, vars) {
  let result = template || '';
  for (const [key, value] of Object.entries(vars)) {
    result = result.replace(new RegExp(`\\{${key}\\}`, 'gi'), value ?? '');
  }
  return result;
}

function requireAdmin(req, res, next) {
  const role = String(req.user?.role || '').toLowerCase();
  if (!['admin', 'super_admin', 'director', 'manager'].includes(role)) {
    return res.status(403).json({ error: 'Forbidden' });
  }
  next();
}

function requireTaskManager(req, res, next) {
  if (!req.user?.sub) {
    return res.status(401).json({ error: 'Unauthorized' });
  }
  next();
}

async function loadAssignmentContext(pool, assignmentId) {
  const [rows] = await pool.query(
    `SELECT ta.*, t.id AS task_id, t.title, t.description, t.deadline, t.deadline_time,
            t.priority, t.start_date, t.start_time, t.created_by, t.notification_template,
            COALESCE(u.name, p.full_name) AS assignee_name,
            COALESCE(u.email, p.email) AS assignee_email,
            COALESCE(u.phone, p.phone) AS assignee_phone,
            COALESCE(cu.name, cp.full_name) AS creator_name,
            COALESCE(cu.phone, cp.phone) AS creator_phone
     FROM task_assignments ta
     JOIN tasks t ON t.id = ta.task_id
     LEFT JOIN users u ON u.id = ta.user_id
     LEFT JOIN profiles p ON p.id = ta.user_id
     LEFT JOIN users cu ON cu.id = t.created_by
     LEFT JOIN profiles cp ON cp.id = t.created_by
     WHERE ta.id = ?
     LIMIT 1`,
    [assignmentId]
  );
  return rows[0] || null;
}

function formatScheduleVars(row) {
  const fmtTime = (t) => (t ? ` at ${String(t).slice(0, 5)}` : '');
  return {
    start_date: row.start_date ? new Date(row.start_date).toLocaleDateString() : '',
    start_time: fmtTime(row.start_time),
    deadline: row.deadline ? new Date(row.deadline).toLocaleDateString() : '',
    deadline_time: fmtTime(row.deadline_time),
  };
}

async function searchAssignees(pool, query, category = 'all') {
  const q = String(query || '').trim();
  const like = `%${q}%`;
  const map = new Map();

  const add = (row) => {
    if (!row?.id || map.has(row.id)) return;
    map.set(row.id, {
      id: row.id,
      name: row.name || row.full_name || row.email || 'Unnamed',
      full_name: row.full_name || row.name || row.email || 'Unnamed',
      email: row.email || '',
      phone: row.phone || '',
      role: row.role || '',
      type: row.type || 'user',
    });
  };

  const profileFilter = q
    ? ` AND (LOWER(p.full_name) LIKE LOWER(?) OR LOWER(p.email) LIKE LOWER(?) OR p.phone LIKE ?)`
    : '';
  const profileParams = q ? [like, like, like] : [];

  if (category === 'all' || category === 'staff' || category === 'users') {
    const [profiles] = await pool.query(
      `SELECT p.id, p.full_name, p.email, p.phone, p.role, 'staff' AS type
       FROM profiles p
       WHERE COALESCE(p.status, 'active') = 'active'${profileFilter}
       ORDER BY p.full_name ASC
       LIMIT 50`,
      profileParams
    );
    profiles.forEach((r) => add({ ...r, name: r.full_name }));

    const userFilter = q
      ? ` AND (LOWER(u.name) LIKE LOWER(?) OR LOWER(u.email) LIKE LOWER(?) OR u.phone LIKE ?)`
      : '';
    const userParams = q ? [like, like, like] : [];
    const [users] = await pool.query(
      `SELECT u.id, u.name AS full_name, u.name, u.email, u.phone, u.role, 'staff' AS type
       FROM users u
       WHERE u.status = 'active'${userFilter}
       ORDER BY u.name ASC
       LIMIT 50`,
      userParams
    );
    users.forEach(add);
  }

  if (category === 'all' || category === 'customers' || category === 'shareholders') {
    const shFilter = q
      ? ` AND (LOWER(s.full_name) LIKE LOWER(?) OR LOWER(s.name) LIKE LOWER(?) OR LOWER(s.email) LIKE LOWER(?) OR s.full_phone_number LIKE ? OR s.phone_number LIKE ? OR s.phone LIKE ?)`
      : '';
    const shParams = q ? [like, like, like, like, like, like] : [];
    const [shareholders] = await pool.query(
      `SELECT COALESCE(s.user_id, s.id) AS id,
              COALESCE(s.full_name, s.name) AS full_name,
              COALESCE(s.full_name, s.name) AS name,
              s.email,
              COALESCE(s.full_phone_number, s.phone_number, s.phone) AS phone,
              'shareholder' AS role,
              'shareholder' AS type
       FROM shareholders s
       WHERE COALESCE(s.user_id, s.id) IS NOT NULL${shFilter}
       ORDER BY s.full_name ASC
       LIMIT 50`,
      shParams
    );
    shareholders.forEach(add);
  }

  if (category === 'all' || category === 'customers' || category === 'students') {
    const stFilter = q
      ? ` AND (LOWER(s.name) LIKE LOWER(?) OR LOWER(s.email) LIKE LOWER(?) OR s.phone LIKE ?)`
      : '';
    const stParams = q ? [like, like, like] : [];
    const [students] = await pool.query(
      `SELECT s.id, s.name AS full_name, s.name, s.email, s.phone, 'student' AS role, 'student' AS type
       FROM students s
       WHERE 1=1${stFilter}
       ORDER BY s.name ASC
       LIMIT 50`,
      stParams
    );
    students.forEach(add);
  }

  return [...map.values()]
    .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
    .slice(0, 50);
}

router.get('/assignees/search', requireAuth, requireTaskManager, async (req, res) => {
  try {
    const pool = getPool();
    const data = await searchAssignees(pool, req.query.q || '', req.query.category || 'all');
    res.json({ data, error: null });
  } catch (err) {
    console.error('[tasks/assignees/search]', err);
    res.status(500).json({ data: [], error: err.message });
  }
});

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
    const row = await loadAssignmentContext(pool, assignmentId);
    if (!row) {
      return res.status(404).json({ success: false, error: 'Assignment not found' });
    }

    const phone = formatPhoneNumber(row.assignee_phone);
    if (!phone) {
      return res.status(400).json({ success: false, error: 'Assignee has no valid phone number' });
    }

    const template = messageTemplate || row.notification_template || DEFAULT_TEMPLATE;
    const loginLink = `${APP_BASE}/task-invite/${row.invite_token}`;
    const docLinks = documentLinks || '';
    const descriptionText = personalize(row.description || '', {
      name: row.assignee_name,
      email: row.assignee_email,
      phone: row.assignee_phone,
    });

    const scheduleVars = formatScheduleVars(row);
    const text = personalize(template, {
      name: row.assignee_name || row.assignee_email,
      email: row.assignee_email || '',
      phone: row.assignee_phone || '',
      subject: row.title,
      task_title: row.title,
      description: descriptionText,
      task_message: descriptionText,
      deadline: scheduleVars.deadline,
      deadline_time: scheduleVars.deadline_time,
      priority: row.priority || 'Medium',
      start_date: scheduleVars.start_date,
      start_time: scheduleVars.start_time,
      login_link: loginLink,
      document_links: docLinks,
    });

    const [docs] = await pool.query(
      `SELECT file_name, file_url FROM task_attachments WHERE task_id = ? AND attachment_type = 'source'`,
      [row.task_id]
    );

    const textResult = await sendTextMessage(phone, text);
    if (!textResult.success) {
      return res.json({ success: false, error: textResult.error || 'Failed to send message' });
    }

    for (const doc of docs || []) {
      if (!doc.file_url) continue;
      const docResult = await sendDocumentMessage(phone, doc.file_url, null, doc.file_name || 'task-document.pdf');
      if (!docResult.success) {
        return res.json({ success: false, error: docResult.error || 'Message sent but PDF delivery failed' });
      }
    }

    res.json({ success: true, error: null });
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
        `SELECT file_name, file_url FROM task_attachments WHERE task_id = ? AND attachment_type = 'source'`,
        [row.task_id]
      );
      const loginLink = `${APP_BASE}/task-invite/${row.invite_token}`;
      const template = row.notification_template || DEFAULT_TEMPLATE;
      const descriptionText = personalize(row.description || '', {
        name: row.name,
        email: row.email,
        phone: row.phone,
      });

      const scheduleVars = formatScheduleVars(row);
      const text = personalize(template, {
        name: row.name || row.email,
        email: row.email || '',
        phone: row.phone || '',
        subject: row.title,
        task_title: row.title,
        description: descriptionText,
        task_message: descriptionText,
        deadline: scheduleVars.deadline,
        deadline_time: scheduleVars.deadline_time,
        priority: row.priority || 'Medium',
        start_date: scheduleVars.start_date,
        start_time: scheduleVars.start_time,
        login_link: loginLink,
        document_links: '',
      });

      const textResult = await sendTextMessage(phone, text);
      let result = textResult;
      if (textResult.success) {
        for (const doc of docs || []) {
          if (!doc.file_url) continue;
          result = await sendDocumentMessage(phone, doc.file_url, null, doc.file_name || 'task-document.pdf');
          if (!result.success) break;
        }
      }

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

router.post('/notify-accepted', requireAuth, requireTaskManager, async (req, res) => {
  try {
    const { assignmentId } = req.body || {};
    if (!assignmentId) {
      return res.status(400).json({ success: false, error: 'assignmentId required' });
    }

    const pool = getPool();
    const row = await loadAssignmentContext(pool, assignmentId);
    if (!row) {
      return res.status(404).json({ success: false, error: 'Assignment not found' });
    }

    const assigneePhone = formatPhoneNumber(row.assignee_phone);
    const adminPhone = formatPhoneNumber(row.creator_phone);
    const assigneeName = row.assignee_name || row.assignee_email || 'Team Member';
    const taskTitle = row.title;
    const loginLink = `${APP_BASE}/my-tasks`;

    const results = { admin: null, assignee: null };

    if (adminPhone) {
      const adminText = `📊 *TASK ACCEPTED*\n${DIVIDER}\n\n*${assigneeName}* has accepted the task:\n\n▪️ *Task:* ${taskTitle}\n\n${BRAND_FOOTER}`;
      results.admin = await sendTextMessage(adminPhone, adminText);
    }

    if (assigneePhone) {
      const assigneeText = `✅ *TASK ACCEPTED*\n${DIVIDER}\n\nHello *${assigneeName}*,\n\nYou have accepted the task:\n\n▪️ *Task:* ${taskTitle}\n▪️ *Realization:* 0%\n\n👉 Update your progress here:\n${loginLink}\n\n${BRAND_FOOTER}`;
      results.assignee = await sendTextMessage(assigneePhone, assigneeText);
    }

    res.json({
      success: true,
      adminSent: Boolean(results.admin?.success),
      assigneeSent: Boolean(results.assignee?.success),
    });
  } catch (err) {
    console.error('[tasks/notify-accepted]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/notify-completed', requireAuth, requireTaskManager, async (req, res) => {
  try {
    const { assignmentId } = req.body || {};
    if (!assignmentId) {
      return res.status(400).json({ success: false, error: 'assignmentId required' });
    }

    const pool = getPool();
    const row = await loadAssignmentContext(pool, assignmentId);
    if (!row) {
      return res.status(404).json({ success: false, error: 'Assignment not found' });
    }

    const adminPhone = formatPhoneNumber(row.creator_phone);
    if (!adminPhone) {
      return res.json({ success: false, error: 'Task creator has no phone number' });
    }

    const assigneeName = row.assignee_name || row.assignee_email || 'Assignee';
    const text = `✅ *TASK COMPLETED*\n${DIVIDER}\n\n*${assigneeName}* has completed their assignment:\n\n▪️ *Task:* ${row.title}\n\n👉 Review it on your dashboard:\n${APP_BASE}/admin/tasks/dashboard\n\n${BRAND_FOOTER}`;
    const result = await sendTextMessage(adminPhone, text);
    res.json({ success: result.success, error: result.error || null });
  } catch (err) {
    console.error('[tasks/notify-completed]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/notify-review', requireAuth, requireAdmin, async (req, res) => {
  try {
    const { assignmentId, progress, comment, adminName } = req.body || {};
    if (!assignmentId) {
      return res.status(400).json({ success: false, error: 'assignmentId required' });
    }

    const pool = getPool();
    const row = await loadAssignmentContext(pool, assignmentId);
    if (!row) {
      return res.status(404).json({ success: false, error: 'Assignment not found' });
    }

    const phone = formatPhoneNumber(row.assignee_phone);
    if (!phone) {
      return res.status(400).json({ success: false, error: 'Assignee has no valid phone number' });
    }

    const assigneeName = row.assignee_name || row.assignee_email || 'Team Member';
    const reviewer = adminName || row.creator_name || 'Admin';
    const progressVal = progress ?? row.progress ?? 0;
    const statusLabel = progressVal >= 100 ? 'Completed' : 'Needs revision';
    const commentBlock = comment?.trim() ? `\n▪️ *Comment:* ${comment.trim()}` : '';

    const text = `📝 *TASK REVIEW*\n${DIVIDER}\n\nHello *${assigneeName}*,\n\nYour task has been reviewed by *${reviewer}*:\n\n▪️ *Task:* ${row.title}\n▪️ *Status:* ${statusLabel}\n▪️ *Realization:* ${progressVal}%${commentBlock}\n\n👉 Please address the feedback:\n${APP_BASE}/my-tasks\n\n${BRAND_FOOTER}`;

    const result = await sendTextMessage(phone, text);
    res.json({ success: result.success, error: result.error || null });
  } catch (err) {
    console.error('[tasks/notify-review]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
