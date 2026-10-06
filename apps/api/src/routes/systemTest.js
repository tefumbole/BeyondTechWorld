import { Router } from 'express';
import { randomBytes, randomInt, randomUUID } from 'node:crypto';
import bcrypt from 'bcryptjs';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';
import { COUNTRY_DIAL_CODES, combinePhone, findCountry } from '../data/countryDialCodes.js';
import { allChecks, checkMap, menuSnapshot, pages } from '../../../../src/config/systemTestCatalog.js';
import { getAllPermissionIds } from '../../../../src/config/permissionCatalog.js';
import { formatPhoneNumber, getContactName, sendTextMessage } from '../services/wasenderWhatsAppService.js';
import { sendReportEmail } from '../services/systemTestMail.js';
import {
  adminFailureMessages,
  adminFullMessages,
  emailBody,
  loginMessage,
  otpMessage,
  testerResultMessages,
} from '../services/systemTestMessages.js';
import { COMPANY_NAME } from '../constants/branding.js';

const router = Router();
const TESTER_ROLE = 'System Tester';
const TESTER_SLUG = 'system_tester';
const SESSION_MS = 12 * 60 * 60 * 1000;
const FORM_MS = 30 * 60 * 1000;
const OTP_MS = 10 * 60 * 1000;
const MAX_OTP_TRIES = 5;

const EXCLUDED_PERMISSION = /(^menu\.system$)|(^menu\.roles$)|settings|backup|empty|mail|sms|role_permission|roles\./i;

let tablesReady = null;

function token() {
  return randomBytes(32).toString('hex');
}

function appBase() {
  return String(process.env.APP_BASE_URL || process.env.APP_URL || 'https://beyondtechworld.com').replace(/\/$/, '');
}

function publicDraft(draft) {
  if (!draft) return null;
  const { otp_hash, otp_expires, otp_attempts, ...safe } = draft;
  return safe;
}

async function ensureTables() {
  if (!tablesReady) {
    const pool = getPool();
    tablesReady = pool.query(`CREATE TABLE IF NOT EXISTS system_test_sessions (
      token CHAR(64) NOT NULL PRIMARY KEY,
      phone VARCHAR(30) DEFAULT NULL,
      verified TINYINT(1) NOT NULL DEFAULT 0,
      form_token CHAR(64) NOT NULL,
      form_expires DATETIME NOT NULL,
      expires_at DATETIME NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`).then(() => pool.query(`CREATE TABLE IF NOT EXISTS system_test_drafts (
      phone VARCHAR(30) NOT NULL PRIMARY KEY,
      payload JSON NOT NULL,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`)).then(() => pool.query(`CREATE TABLE IF NOT EXISTS system_test_reports (
      id VARCHAR(40) NOT NULL PRIMARY KEY,
      tester_name VARCHAR(120) NOT NULL,
      tester_phone VARCHAR(30) NOT NULL,
      payload JSON NOT NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`));
  }
  await tablesReady;
}

function parsePayload(value) {
  if (!value) return null;
  if (typeof value === 'object') return value;
  try {
    return JSON.parse(value);
  } catch {
    return null;
  }
}

async function readSession(rawToken) {
  if (!rawToken || !/^[a-f0-9]{64}$/i.test(rawToken)) return null;
  const pool = getPool();
  const [rows] = await pool.query('SELECT * FROM system_test_sessions WHERE token = ? LIMIT 1', [rawToken]);
  const row = rows[0];
  if (!row) return null;
  if (new Date(row.expires_at).getTime() < Date.now()) return { expired: true, row };
  return { expired: false, row };
}

async function issueSession(phone = null, verified = false) {
  const pool = getPool();
  const value = token();
  const form = token();
  const now = Date.now();
  await pool.query(
    `INSERT INTO system_test_sessions (token, phone, verified, form_token, form_expires, expires_at)
     VALUES (?, ?, ?, ?, ?, ?)`,
    [value, phone, verified ? 1 : 0, form, new Date(now + FORM_MS), new Date(now + SESSION_MS)],
  );
  return { token: value, formToken: form };
}

async function refreshForm(row) {
  const pool = getPool();
  const form = token();
  const now = Date.now();
  await pool.query(
    'UPDATE system_test_sessions SET form_token = ?, form_expires = ?, expires_at = ? WHERE token = ?',
    [form, new Date(now + FORM_MS), new Date(now + SESSION_MS), row.token],
  );
  return form;
}

function formFresh(row) {
  return row && new Date(row.form_expires).getTime() >= Date.now();
}

async function loadDraft(phone) {
  if (!phone) return null;
  const pool = getPool();
  const [rows] = await pool.query('SELECT payload FROM system_test_drafts WHERE phone = ? LIMIT 1', [phone]);
  return parsePayload(rows[0]?.payload);
}

async function saveDraft(phone, draft) {
  const pool = getPool();
  const next = { ...draft, tester_phone: phone, updated_at: new Date().toISOString() };
  await pool.query(
    `INSERT INTO system_test_drafts (phone, payload) VALUES (?, ?)
     ON DUPLICATE KEY UPDATE payload = VALUES(payload), updated_at = CURRENT_TIMESTAMP`,
    [phone, JSON.stringify(next)],
  );
  return next;
}

function blankDraft(name, phone) {
  return {
    tester_name: name,
    tester_phone: phone,
    summary: '',
    checks: {},
    notes: {},
    page: 1,
    otp_hash: null,
    otp_expires: null,
    otp_attempts: 0,
  };
}

function answeredCount(draft) {
  return Object.values(draft?.checks || {}).filter((value) => ['works', 'fails', 'skipped'].includes(value)).length;
}

function fillBlanks(draft, posted = {}) {
  const known = checkMap();
  const checks = { ...(draft.checks || {}) };
  const notes = { ...(draft.notes || {}) };
  let filledFromBrowser = false;
  for (const id of Object.keys(known)) {
    const current = checks[id];
    const incoming = posted.checks?.[id];
    if (!['works', 'fails', 'skipped'].includes(current) && ['works', 'fails', 'skipped'].includes(incoming)) {
      checks[id] = incoming;
      filledFromBrowser = true;
    }
    const note = String(posted.notes?.[id] || '').trim();
    if (!String(notes[id] || '').trim() && note) {
      notes[id] = note.slice(0, 500);
      filledFromBrowser = true;
    }
  }
  draft.checks = checks;
  draft.notes = notes;
  if (!String(draft.summary || '').trim() && String(posted.summary || '').trim()) {
    draft.summary = String(posted.summary).trim().slice(0, 5000);
    filledFromBrowser = true;
  }
  return { draft, filledFromBrowser };
}

function missingOnPage(page, draft) {
  const checks = draft.checks || {};
  return page.checks.filter((item) => !['works', 'fails', 'skipped'].includes(checks[item.id]));
}

function firstIncompletePage(draft) {
  const list = pages();
  for (const page of list) {
    if (missingOnPage(page, draft).length) return page.number;
  }
  return list.length || 1;
}

function reviewRows(draft) {
  const labels = { works: 'Works', fails: 'Does not work', skipped: 'Not tested' };
  const rows = [];
  let number = 0;
  pages().forEach((page) => {
    page.checks.forEach((item) => {
      number += 1;
      const result = draft.checks?.[item.id] || '';
      rows.push({
        number,
        id: item.id,
        page: page.number,
        section: item.section || 'Check',
        text: item.text,
        result,
        label: labels[result] || 'No answer',
        note: String(draft.notes?.[item.id] || '').trim(),
      });
    });
  });
  return rows;
}

function progress(draft) {
  const total = allChecks().length;
  const answered = answeredCount(draft);
  return {
    total,
    answered,
    percent: total ? Math.round((answered / total) * 100) : 0,
    pageCount: pages().length,
  };
}

async function sendCode(phone, draft) {
  const code = String(randomInt(0, 1000000)).padStart(6, '0');
  draft.otp_hash = await bcrypt.hash(code, 8);
  draft.otp_expires = Date.now() + OTP_MS;
  draft.otp_attempts = 0;
  await saveDraft(phone, draft);
  const formatted = formatPhoneNumber(phone);
  const sent = await sendTextMessage(formatted || phone, otpMessage(code), 'system-test-otp');
  return sent;
}

async function resolveName(phone, dial) {
  if (dial === '237') {
    return { name: '', source: '' };
  }
  const name = String(await getContactName(phone) || '').trim();
  return { name, source: name ? 'whatsapp' : '' };
}

function honeypot(body) {
  return String(body?.company_website || '').trim() !== '';
}

async function syncTesterPermissions() {
  const pool = getPool();
  const [existing] = await pool.query('SELECT id FROM roles WHERE name = ? LIMIT 1', [TESTER_ROLE]);
  if (!existing.length) {
    await pool.query(
      'INSERT INTO roles (id, name, description, is_default, created_at) VALUES (?, ?, ?, 0, NOW())',
      [randomUUID(), TESTER_ROLE, 'Temporary account for the public system test. No Settings menu.'],
    );
  }
  const allowed = getAllPermissionIds().filter((id) => !EXCLUDED_PERMISSION.test(id));
  for (const permission of allowed) {
    const [row] = await pool.query(
      'SELECT id FROM role_permissions WHERE role = ? AND permission = ? LIMIT 1',
      [TESTER_ROLE, permission],
    );
    if (!row.length) {
      await pool.query(
        'INSERT INTO role_permissions (id, role, permission, created_at) VALUES (?, ?, ?, NOW())',
        [randomUUID(), TESTER_ROLE, permission],
      );
    }
  }
  const [drop] = await pool.query('SELECT permission FROM role_permissions WHERE role = ?', [TESTER_ROLE]);
  for (const row of drop) {
    if (EXCLUDED_PERMISSION.test(row.permission)) {
      await pool.query('DELETE FROM role_permissions WHERE role = ? AND permission = ?', [TESTER_ROLE, row.permission]);
    }
  }
}

async function findUserByPhone(phone) {
  const digits = String(phone || '').replace(/\D/g, '');
  const tail = digits.slice(-9);
  const pool = getPool();
  const [rows] = await pool.query(
    `SELECT * FROM users
     WHERE status IS NULL OR status = 'active' OR status = ''
     ORDER BY created_at DESC`,
  );
  return rows.find((user) => {
    const value = String(user.phone || '').replace(/\D/g, '');
    return value === digits || (tail.length >= 8 && value.endsWith(tail));
  }) || null;
}

async function uniqueUsername(name) {
  const parts = String(name || '').trim().split(/\s+/);
  let base = String(parts[0] || '').toLowerCase().replace(/[^a-z]/g, '');
  if (base.length < 3) base = 'tester';
  const pool = getPool();
  let username = base;
  let i = 2;
  for (;;) {
    const [rows] = await pool.query('SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1', [username]);
    if (!rows.length) return username;
    username = `${base}${i}`;
    i += 1;
  }
}

async function uniqueEmail(username) {
  const pool = getPool();
  let email = `${username}@testers.beyondtechworld.com`;
  let i = 2;
  for (;;) {
    const [rows] = await pool.query('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [email]);
    if (!rows.length) return email;
    email = `${username}${i}@testers.beyondtechworld.com`;
    i += 1;
  }
}

async function ensureAccount(name, phone) {
  await syncTesterPermissions();
  const pool = getPool();
  const existing = await findUserByPhone(phone);
  if (existing) {
    const role = String(existing.role || '').toLowerCase().replace(/\s+/g, '_');
    if (role !== TESTER_SLUG) {
      return { username: existing.username || existing.email, password: null, created: false, existing: true };
    }
    if (!String(existing.username || '').trim()) {
      const password = `Be-${randomInt(100000, 999999)}`;
      const username = await uniqueUsername(name);
      const hash = await bcrypt.hash(password, 10);
      await pool.query(
        'UPDATE users SET username = ?, password_hash = ?, name = ?, status = ? WHERE id = ?',
        [username, hash, name, 'active', existing.id],
      );
      return { username, password, created: true, existing: false };
    }
    return { username: existing.username, password: null, created: false, existing: true };
  }

  const username = await uniqueUsername(name);
  const password = `Be-${randomInt(100000, 999999)}`;
  const email = await uniqueEmail(username);
  const hash = await bcrypt.hash(password, 10);
  const id = randomUUID();
  await pool.query(
    `INSERT INTO users (id, email, username, password_hash, name, role, status, phone)
     VALUES (?, ?, ?, ?, ?, ?, 'active', ?)`,
    [id, email, username, hash, name, TESTER_SLUG, phone],
  );
  try {
    await pool.query(
      `INSERT INTO profiles (id, email, username, full_name, phone, role, status)
       VALUES (?, ?, ?, ?, ?, ?, 'active')
       ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), phone = VALUES(phone), role = VALUES(role), username = VALUES(username)`,
      [id, email, username, name, phone, TESTER_SLUG],
    );
  } catch (err) {
    console.warn('[system-test] profile row skipped:', err.message);
  }
  await pool.query(
    'INSERT INTO user_roles (id, user_id, role, created_at) VALUES (?, ?, ?, NOW())',
    [randomUUID(), id, TESTER_ROLE],
  );
  return { username, password, created: true, existing: false };
}

function staffAllowed(user) {
  const role = String(user?.role || '').toLowerCase().replace(/\s+/g, '_');
  const blocked = new Set(['', 'customer', 'user', 'task_assignee', 'applicant', 'student', 'shareholder']);
  return !blocked.has(role);
}

async function adminContacts() {
  const pool = getPool();
  const phones = [];
  const emails = [];
  const contact = String(process.env.CONTACT_EMAIL || 'info@beyondtechworld.com').trim().toLowerCase();
  if (contact.includes('@')) emails.push(contact);
  const seedEmail = String(process.env.SEED_ADMIN_EMAIL || '').trim().toLowerCase();
  if (seedEmail.includes('@') && !emails.includes(seedEmail)) emails.push(seedEmail);
  const seedPhone = String(process.env.SEED_ADMIN_PHONE || process.env.ADMIN_PHONE || '').trim();
  if (seedPhone) phones.push(seedPhone);

  try {
    const [rows] = await pool.query(
      `SELECT phone, email, role FROM users
       WHERE status = 'active'
         AND LOWER(REPLACE(role, ' ', '_')) IN ('admin', 'super_admin', 'owner', 'administrator', 'director')`,
    );
    for (const row of rows) {
      const phone = String(row.phone || '').trim();
      const email = String(row.email || '').trim().toLowerCase();
      if (phone && !phones.includes(phone)) phones.push(phone);
      if (email.includes('@') && !emails.includes(email)) emails.push(email);
    }
  } catch (err) {
    console.warn('[system-test] admin lookup failed:', err.message);
  }
  return { phones: phones.slice(0, 5), emails: emails.slice(0, 8) };
}

function samePhone(a, b) {
  const left = String(a || '').replace(/\D/g, '');
  const right = String(b || '').replace(/\D/g, '');
  return left && right && (left === right || left.slice(-9) === right.slice(-9));
}

async function publish(draft) {
  const rows = reviewRows(draft);
  const counts = {
    works: rows.filter((row) => row.result === 'works').length,
    fails: rows.filter((row) => row.result === 'fails').length,
    skipped: rows.filter((row) => row.result === 'skipped').length,
  };
  const report = {
    id: `st-${Date.now().toString(36)}`,
    tester_name: draft.tester_name,
    tester_phone: draft.tester_phone,
    summary: String(draft.summary || ''),
    counts,
    rows,
    created_at: new Date().toISOString(),
    organisation: COMPANY_NAME,
  };
  const pool = getPool();
  await pool.query(
    'INSERT INTO system_test_reports (id, tester_name, tester_phone, payload) VALUES (?, ?, ?, ?)',
    [report.id, report.tester_name, report.tester_phone, JSON.stringify(report)],
  );
  await pool.query('DELETE FROM system_test_drafts WHERE phone = ?', [draft.tester_phone]);

  const reportUrl = `${appBase()}/admin/help/results/${report.id}`;
  const testerSent = await sendMany(draft.tester_phone, testerResultMessages(report));
  const contacts = await adminContacts();
  const adminPhones = [];
  for (const phone of contacts.phones) {
    if (samePhone(phone, draft.tester_phone)) continue;
    adminPhones.push(phone);
    await sendMany(phone, adminFailureMessages(report));
    await sendMany(phone, adminFullMessages(report, reportUrl));
  }
  let mailed = false;
  try {
    const mail = await sendReportEmail({
      to: contacts.emails,
      subject: `${counts.fails} do not work — ${report.tester_name}`,
      text: emailBody(report, reportUrl),
    });
    mailed = Boolean(mail.success);
  } catch (err) {
    console.error('[system-test] email failed:', err.message);
  }
  return { report, testerSent, adminPhones, mailed, recipients: contacts.emails };
}

async function sendMany(phone, messages) {
  let ok = false;
  for (const message of messages) {
    const sent = await sendTextMessage(formatPhoneNumber(phone) || phone, message, 'system-test');
    if (sent?.success) ok = true;
  }
  return ok;
}

router.use(async (_req, res, next) => {
  try {
    await ensureTables();
    next();
  } catch (err) {
    console.error('[system-test] tables', err);
    res.status(500).json({ error: 'The test could not open the database.' });
  }
});

router.get('/meta', (_req, res) => {
  res.json({
    countries: COUNTRY_DIAL_CODES,
    pages: pages(),
    menus: menuSnapshot(),
    total: allChecks().length,
    organisation: COMPANY_NAME,
  });
});

router.post('/session', async (req, res) => {
  const current = await readSession(req.body?.token);
  if (current && !current.expired) {
    const formToken = await refreshForm(current.row);
    return res.json({
      token: current.row.token,
      formToken,
      verified: Boolean(current.row.verified),
      phone: current.row.verified ? current.row.phone : null,
    });
  }
  const fresh = await issueSession();
  res.json({ token: fresh.token, formToken: fresh.formToken, verified: false, expired: Boolean(current?.expired) });
});

router.get('/state', async (req, res) => {
  const current = await readSession(req.get('x-test-token') || req.query.token);
  if (!current || current.expired || !current.row.verified || !current.row.phone) {
    return res.status(409).json({
      code: 'session_expired',
      error: 'Confirm your WhatsApp number again. Answers kept in this browser are not lost.',
    });
  }
  const draft = await loadDraft(current.row.phone);
  const formToken = formFresh(current.row) ? current.row.form_token : await refreshForm(current.row);
  res.json({
    formToken,
    draft: publicDraft(draft),
    progress: progress(draft || blankDraft('', current.row.phone)),
  });
});

router.post('/lookup', async (req, res) => {
  if (honeypot(req.body)) return res.json({ ok: true });
  const country = findCountry(req.body?.country_code);
  if (!country) return res.status(400).json({ error: 'Choose a country.', field: 'country_code' });
  const phone = combinePhone(country.dial, req.body?.phone_local);
  if (!phone) {
    return res.status(400).json({
      error: 'Enter the phone number without the country code, for example 675321739.',
      field: 'phone_local',
    });
  }
  const intent = req.body?.intent === 'retrieve' ? 'retrieve' : 'start';
  const posted = {
    checks: req.body?.checks || {},
    notes: req.body?.notes || {},
    summary: req.body?.summary || '',
  };
  let lookedUp = { name: '', source: '' };
  if (intent === 'start') lookedUp = await resolveName(phone, country.dial);

  if (intent === 'retrieve') {
    let draft = await loadDraft(phone);
    const hadServer = Boolean(draft);
    if (!draft) draft = blankDraft(String(req.body?.tester_name || '').trim() || 'Tester', phone);
    const merged = fillBlanks(draft, posted);
    draft = merged.draft;
    if (answeredCount(draft) === 0) {
      return res.status(404).json({
        error: 'No saved test was found for this number, and this browser has no answers to restore.',
        field: 'phone_local',
      });
    }
    const name = String(draft.tester_name || '').trim();
    if (!name || name === 'Tester') {
      await saveDraft(phone, draft);
      return res.json({
        step: 'name',
        phone,
        suggestedName: '',
        nameSource: '',
        recoveredFromBrowser: merged.filledFromBrowser,
        hadServer,
      });
    }
    const sent = await sendCode(phone, draft);
    if (!sent?.success) {
      return res.status(502).json({ error: 'The code could not be sent on WhatsApp. Check the number and try again.', field: 'phone_local' });
    }
    return res.json({
      step: 'verify',
      phone,
      recoveredFromBrowser: merged.filledFromBrowser && !hadServer,
      hadServer,
    });
  }

  const existing = await loadDraft(phone);
  if (existing && answeredCount(existing) > 0) {
    return res.status(409).json({
      code: 'draft_exists',
      error: 'A saved test already exists for this number. Retrieve it to continue, or retrieve it and choose start again.',
      field: 'phone_local',
    });
  }
  res.json({
    step: 'name',
    phone,
    suggestedName: lookedUp.name,
    nameSource: lookedUp.source,
    countryName: country.name,
  });
});

router.post('/confirm-name', async (req, res) => {
  if (honeypot(req.body)) return res.json({ ok: true });
  const phone = formatPhoneNumber(req.body?.phone) || combinePhone(req.body?.country_code, req.body?.phone_local);
  const name = String(req.body?.tester_name || '').trim();
  if (!phone) return res.status(400).json({ error: 'Enter your phone number again.', field: 'phone_local' });
  if (!name || name.length > 120) return res.status(400).json({ error: 'Enter the name to use for this test.', field: 'tester_name' });
  let draft = await loadDraft(phone);
  if (!draft) draft = blankDraft(name, phone);
  const merged = fillBlanks(draft, req.body || {});
  draft = merged.draft;
  draft.tester_name = name;
  if (answeredCount(draft) > 0) draft.page = firstIncompletePage(draft);
  const sent = await sendCode(phone, draft);
  if (!sent?.success) {
    return res.status(502).json({ error: 'The code could not be sent on WhatsApp. Check the number and try again.', field: 'tester_name' });
  }
  res.json({ step: 'verify', phone });
});

router.post('/verify', async (req, res) => {
  const phone = formatPhoneNumber(req.body?.phone) || String(req.body?.phone || '');
  const draft = await loadDraft(phone);
  if (!draft?.otp_hash) {
    return res.status(400).json({ error: 'Ask for a new code to open the test.', field: 'code' });
  }
  if (Number(draft.otp_expires) < Date.now()) {
    return res.status(400).json({ error: 'That code has expired. Ask for a new one.', field: 'code' });
  }
  if (Number(draft.otp_attempts) >= MAX_OTP_TRIES) {
    return res.status(400).json({ error: 'Too many tries. Ask for a new code.', field: 'code' });
  }
  const code = String(req.body?.code || '').replace(/\D/g, '');
  const match = await bcrypt.compare(code, draft.otp_hash);
  if (!match) {
    draft.otp_attempts = Number(draft.otp_attempts || 0) + 1;
    await saveDraft(phone, draft);
    return res.status(400).json({ error: 'That code does not match. Check the WhatsApp message and try again.', field: 'code' });
  }

  let next = draft;
  if (req.body?.action === 'replace') next = blankDraft(draft.tester_name, phone);
  next.otp_hash = null;
  next.otp_expires = null;
  next.otp_attempts = 0;
  await saveDraft(phone, next);
  const session = await issueSession(phone, true);

  let login = null;
  try {
    login = await ensureAccount(next.tester_name, phone);
  } catch (err) {
    console.error('[system-test] tester account failed:', err.message);
  }
  let loginDelivery = null;
  if (login?.password) {
    const sent = await sendTextMessage(
      formatPhoneNumber(phone) || phone,
      loginMessage(next.tester_name, login.username, login.password, `${appBase()}/login`),
      'system-test-login',
    );
    loginDelivery = { username: login.username, password: login.password, sent: Boolean(sent?.success) };
  } else if (login?.username) {
    loginDelivery = { username: login.username, password: null, sent: false, existing: true };
  }

  const openReview = missingOnPage({ checks: allChecks() }, next).length === 0 && answeredCount(next) === allChecks().length;
  res.json({
    token: session.token,
    formToken: session.formToken,
    draft: publicDraft(next),
    progress: progress(next),
    review: openReview,
    page: next.page || firstIncompletePage(next),
    login: loginDelivery,
  });
});

router.post('/resend', async (req, res) => {
  const phone = formatPhoneNumber(req.body?.phone) || String(req.body?.phone || '');
  const draft = await loadDraft(phone);
  if (!draft) return res.status(404).json({ error: 'No saved test was found for this number.' });
  const sent = await sendCode(phone, draft);
  if (!sent?.success) return res.status(502).json({ error: 'The code could not be sent on WhatsApp. Check the number and try again.' });
  res.json({ step: 'verify', phone });
});

function applyPosted(draft, posted = {}) {
  const checks = { ...(draft.checks || {}) };
  const notes = { ...(draft.notes || {}) };
  for (const id of Object.keys(checkMap())) {
    const incoming = posted.checks?.[id];
    if (['works', 'fails', 'skipped'].includes(incoming)) checks[id] = incoming;
    if (posted.notes && Object.prototype.hasOwnProperty.call(posted.notes, id)) {
      notes[id] = String(posted.notes[id] || '').trim().slice(0, 500);
    }
  }
  draft.checks = checks;
  draft.notes = notes;
  if (posted.summary !== undefined) draft.summary = String(posted.summary || '').trim().slice(0, 5000);
  return draft;
}

router.post('/save', async (req, res) => {
  if (honeypot(req.body)) return res.json({ ok: true });
  const current = await readSession(req.get('x-test-token') || req.body?.token);
  if (!current || current.expired || !current.row.verified || !current.row.phone) {
    return res.status(409).json({
      code: 'session_expired',
      error: 'Confirm your WhatsApp number again. Your answers are still in this browser.',
    });
  }
  let formToken = current.row.form_token;
  let recovered = false;
  if (!formFresh(current.row) || req.body?.formToken !== current.row.form_token) {
    formToken = await refreshForm(current.row);
    recovered = true;
  }
  const phone = current.row.phone;
  let draft = await loadDraft(phone);
  if (!draft) {
    return res.status(409).json({
      code: 'session_expired',
      error: 'Open your saved test again by confirming your WhatsApp number.',
    });
  }
  const list = pages();
  const pageCount = list.length;
  const pageNumber = Math.max(1, Math.min(pageCount, Number(req.body?.page || 1)));
  const page = list[pageNumber - 1];
  draft = applyPosted(draft, req.body || {});
  draft.page = pageNumber;
  let action = String(req.body?.action || 'later');
  if (req.body?.goto) action = 'goto';

  if (action === 'review' || action === 'submit') {
    const incomplete = firstIncompletePage(draft);
    const missing = list.reduce((sum, item) => sum + missingOnPage(item, draft).length, 0);
    if (missing) {
      draft.page = incomplete;
      await saveDraft(phone, draft);
      return res.status(400).json({
        error: `Answer every question before the review. ${missing} still need an answer. Not tested counts as an answer.`,
        page: incomplete,
        formToken,
        draft: publicDraft(draft),
        recovered,
      });
    }
    await saveDraft(phone, draft);
    if (action === 'submit') {
      const published = await publish(draft);
      return res.json({ step: 'thanks', formToken, ...published });
    }
    return res.json({
      step: 'review',
      formToken,
      draft: publicDraft(draft),
      rows: reviewRows(draft),
      progress: progress(draft),
      recovered,
    });
  }

  if (action === 'next') {
    const open = missingOnPage(page, draft);
    if (open.length) {
      await saveDraft(phone, draft);
      return res.status(400).json({
        error: `Answer every question on this page before the next one. ${open.length} still need an answer. You can choose Not tested, or save and continue later.`,
        page: pageNumber,
        formToken,
        draft: publicDraft(draft),
        recovered,
      });
    }
    draft.page = Math.min(pageCount, pageNumber + 1);
  } else if (action === 'prev') {
    draft.page = Math.max(1, pageNumber - 1);
  } else if (action === 'goto') {
    const target = Math.max(1, Math.min(pageCount, Number(req.body.goto)));
    if (target > pageNumber && missingOnPage(page, draft).length) {
      await saveDraft(phone, draft);
      return res.status(400).json({
        error: 'Answer every question on this page before moving ahead. You can choose Not tested, or save and continue later.',
        page: pageNumber,
        formToken,
        draft: publicDraft(draft),
        recovered,
      });
    }
    draft.page = target;
  }

  await saveDraft(phone, draft);
  if (!recovered) formToken = await refreshForm(current.row);
  res.json({
    saved: true,
    formToken,
    draft: publicDraft(draft),
    progress: progress(draft),
    page: draft.page,
    recovered,
  });
});

router.get('/reports', requireAuth, async (req, res) => {
  if (!staffAllowed(req.user)) return res.status(403).json({ error: 'Forbidden' });
  const pool = getPool();
  const [rows] = await pool.query(
    'SELECT id, tester_name, tester_phone, payload, created_at FROM system_test_reports ORDER BY created_at DESC LIMIT 200',
  );
  res.json({
    reports: rows.map((row) => {
      const payload = parsePayload(row.payload) || {};
      return {
        id: row.id,
        tester_name: row.tester_name,
        tester_phone: row.tester_phone,
        created_at: row.created_at,
        counts: payload.counts || {},
      };
    }),
  });
});

router.get('/reports/:id', requireAuth, async (req, res) => {
  if (!staffAllowed(req.user)) return res.status(403).json({ error: 'Forbidden' });
  if (!/^st-[a-z0-9]+$/i.test(req.params.id)) return res.status(404).json({ error: 'Not found' });
  const pool = getPool();
  const [rows] = await pool.query('SELECT payload FROM system_test_reports WHERE id = ? LIMIT 1', [req.params.id]);
  const report = parsePayload(rows[0]?.payload);
  if (!report) return res.status(404).json({ error: 'Not found' });
  res.json({ report });
});

export default router;
