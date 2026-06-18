import { Router } from 'express';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { requireAuth } from '../middleware/auth.js';
import { getPool } from '../db/pool.js';
import { logActivity } from '../services/activityLog.js';

const router = Router();
const __dirname = path.dirname(fileURLToPath(import.meta.url));
const repoRoot = path.resolve(__dirname, '../../../../');
const apiEnvPath = path.join(repoRoot, 'apps/api/.env');
const frontendEnvPaths = [
  path.join(repoRoot, '.env.local'),
  path.join(repoRoot, '.env'),
];

function isAdmin(user) {
  return user?.role === 'super_admin' || user?.role === 'admin';
}

function readFrontendEnv() {
  for (const p of frontendEnvPaths) {
    if (fs.existsSync(p)) return fs.readFileSync(p, 'utf8');
  }
  const example = path.join(repoRoot, '.env.local.example');
  if (fs.existsSync(example)) return fs.readFileSync(example, 'utf8');
  return '';
}

function writeFrontendEnv(content) {
  const target = fs.existsSync(path.join(repoRoot, '.env.local'))
    ? path.join(repoRoot, '.env.local')
    : path.join(repoRoot, '.env');
  fs.writeFileSync(target, content, 'utf8');
  return target;
}

router.get('/env-files', requireAuth, (req, res) => {
  if (!isAdmin(req.user)) {
    return res.status(403).json({ error: 'Admin access required' });
  }

  const api = fs.existsSync(apiEnvPath)
    ? fs.readFileSync(apiEnvPath, 'utf8')
    : (fs.existsSync(path.join(repoRoot, 'apps/api/.env.local.example'))
      ? fs.readFileSync(path.join(repoRoot, 'apps/api/.env.local.example'), 'utf8')
      : '');

  res.json({ frontend: readFrontendEnv(), api });
});

router.put('/env-files', requireAuth, (req, res) => {
  if (!isAdmin(req.user)) {
    return res.status(403).json({ error: 'Admin access required' });
  }

  const { frontend, api } = req.body || {};
  try {
    if (typeof frontend === 'string') writeFrontendEnv(frontend);
    if (typeof api === 'string') fs.writeFileSync(apiEnvPath, api, 'utf8');
    res.json({ success: true });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

const ISO_DATE_RE = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?Z?$/;

function serializeForBackup(value) {
  if (value === null || value === undefined) return null;
  if (value instanceof Date) return value.toISOString();
  if (Buffer.isBuffer(value)) return value.toString('base64');
  if (typeof value === 'object') return value; // JSON columns -> keep as object
  return value;
}

function normalizeForInsert(value) {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && ISO_DATE_RE.test(value)) {
    // Convert ISO timestamp to MySQL DATETIME format.
    return value.replace('T', ' ').replace(/\.\d+/, '').replace('Z', '');
  }
  if (typeof value === 'object') return JSON.stringify(value);
  return value;
}

async function listTables(pool) {
  const [rows] = await pool.query('SHOW TABLES');
  return rows.map((r) => Object.values(r)[0]).filter(Boolean);
}

// GET /system/backup -> full JSON dump of every table
router.get('/backup', requireAuth, async (req, res) => {
  if (!isAdmin(req.user)) return res.status(403).json({ error: 'Admin access required' });
  try {
    const pool = getPool();
    const tables = await listTables(pool);
    const dump = { metadata: { timestamp: new Date().toISOString(), version: '2.0', type: 'full_db_backup' }, tables: {} };

    for (const table of tables) {
      const [rows] = await pool.query(`SELECT * FROM \`${table}\``);
      dump.tables[table] = rows.map((row) => {
        const out = {};
        for (const [k, v] of Object.entries(row)) out[k] = serializeForBackup(v);
        return out;
      });
    }

    logActivity({ req, action: 'backup', entity: 'system', summary: `Full database backup (${tables.length} tables)` });
    res.json(dump);
  } catch (err) {
    console.error('[system/backup]', err);
    res.status(500).json({ error: err.message });
  }
});

// POST /system/restore -> restore tables from a full JSON dump (destructive per included table)
router.post('/restore', requireAuth, async (req, res) => {
  if (!isAdmin(req.user)) return res.status(403).json({ error: 'Admin access required' });

  const payload = req.body || {};
  const tables = payload.tables;
  if (!tables || typeof tables !== 'object') {
    return res.status(400).json({ error: 'Invalid backup file: missing "tables".' });
  }

  const pool = getPool();
  const conn = await pool.getConnection();
  const results = { restored: [], skipped: [], errors: [] };

  try {
    await conn.query('SET FOREIGN_KEY_CHECKS = 0');
    const existing = new Set(await listTables(pool));

    for (const [table, rows] of Object.entries(tables)) {
      if (!/^[a-zA-Z0-9_]+$/.test(table)) { results.errors.push(`${table}: invalid name`); continue; }
      if (!existing.has(table)) { results.skipped.push(`${table} (not in DB)`); continue; }

      try {
        await conn.query(`DELETE FROM \`${table}\``);
        if (Array.isArray(rows) && rows.length) {
          const cols = Object.keys(rows[0]);
          const values = rows.map((row) => cols.map((c) => normalizeForInsert(row[c])));
          const colSql = cols.map((c) => `\`${c}\``).join(', ');
          // Batch inserts to avoid oversized packets.
          const BATCH = 200;
          for (let i = 0; i < values.length; i += BATCH) {
            const chunk = values.slice(i, i + BATCH);
            // eslint-disable-next-line no-await-in-loop
            await conn.query(`INSERT INTO \`${table}\` (${colSql}) VALUES ?`, [chunk]);
          }
        }
        results.restored.push(`${table} (${Array.isArray(rows) ? rows.length : 0})`);
      } catch (tableErr) {
        results.errors.push(`${table}: ${tableErr.message}`);
      }
    }

    await conn.query('SET FOREIGN_KEY_CHECKS = 1');
    logActivity({ req, action: 'restore', entity: 'system', summary: `Database restore: ${results.restored.length} table(s), ${results.errors.length} error(s)` });
    res.json({ success: results.errors.length === 0, results });
  } catch (err) {
    console.error('[system/restore]', err);
    try { await conn.query('SET FOREIGN_KEY_CHECKS = 1'); } catch { /* ignore */ }
    res.status(500).json({ error: err.message });
  } finally {
    conn.release();
  }
});

export default router;
