import { Router } from 'express';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { optionalAuth } from '../middleware/auth.js';
import { logActivity } from '../services/activityLog.js';

const router = Router();
router.use(optionalAuth);

const TABLE_RE = /^[a-z][a-z0-9_]*$/;

const ALWAYS_AUTH_SELECT = new Set(['users', 'otp_sessions', 'admin_users']);
const PUBLIC_INSERT = new Set([
  'shareholders',
  'registrations',
  'contact_messages',
  'applications',
  'students',
  'invitations',
  'event_registrations',
  'master_class_registrations',
  'share_bookings',
  'course_feedback',
]);

const SENSITIVE_COLUMNS = {
  users: ['password_hash'],
  otp_sessions: ['otp'],
};

function isAdmin(user) {
  return user?.role === 'super_admin' || user?.role === 'admin';
}

function safeTable(name) {
  if (!TABLE_RE.test(name)) throw new Error(`Invalid table: ${name}`);
  return name;
}

function safeColumns(cols) {
  if (cols === '*' || !cols) return '*';
  if (String(cols).includes('(')) return '*';
  const parts = String(cols)
    .split(',')
    .map((c) => c.trim())
    .filter((c) => c && /^[\w*]+$/.test(c));
  if (!parts.length) return '*';
  return parts.map((c) => (c === '*' ? '*' : `\`${c}\``)).join(', ');
}

function parseOrPart(part) {
  const trimmed = part.trim();
  const match = trimmed.match(/^(\w+)\.(ilike|eq|lte|gte|lt|gt|is)\.(.+)$/i);
  if (!match) return null;
  const [, col, op, rawVal] = match;
  const opLower = op.toLowerCase();

  if (opLower === 'is' && rawVal === 'null') {
    return { col, op: 'is', value: null };
  }
  if (opLower === 'ilike') {
    const inner = rawVal.replace(/^%/, '').replace(/%$/, '');
    return { col, op: 'ilike', value: `%${inner}%` };
  }
  if (['eq', 'lte', 'gte', 'lt', 'gt'].includes(opLower)) {
    return { col, op: opLower, value: rawVal };
  }
  return null;
}

function parseOrFilter(orStr) {
  return orStr.split(',').map(parseOrPart).filter(Boolean);
}

function buildWhere(filters) {
  const clauses = [];
  const params = [];

  for (const f of filters) {
    switch (f.op) {
      case 'eq':
        if (f.value === null) clauses.push(`\`${f.col}\` IS NULL`);
        else {
          clauses.push(`\`${f.col}\` = ?`);
          params.push(f.value === true ? 1 : f.value === false ? 0 : f.value);
        }
        break;
      case 'neq':
        clauses.push(`\`${f.col}\` <> ?`);
        params.push(f.value);
        break;
      case 'gt':
        clauses.push(`\`${f.col}\` > ?`);
        params.push(f.value);
        break;
      case 'gte':
        clauses.push(`\`${f.col}\` >= ?`);
        params.push(f.value);
        break;
      case 'lt':
        clauses.push(`\`${f.col}\` < ?`);
        params.push(f.value);
        break;
      case 'lte':
        clauses.push(`\`${f.col}\` <= ?`);
        params.push(f.value);
        break;
      case 'ilike':
        clauses.push(`LOWER(\`${f.col}\`) LIKE LOWER(?)`);
        params.push(f.value);
        break;
      case 'is':
        if (f.value === null) clauses.push(`\`${f.col}\` IS NULL`);
        else {
          clauses.push(`\`${f.col}\` IS ?`);
          params.push(f.value);
        }
        break;
      case 'not_null':
        clauses.push(`\`${f.col}\` IS NOT NULL`);
        break;
      case 'not_in':
        if (!f.values?.length) break;
        clauses.push(`\`${f.col}\` NOT IN (${f.values.map(() => '?').join(',')})`);
        params.push(...f.values);
        break;
      case 'in':
        if (!f.values?.length) clauses.push('1=0');
        else {
          clauses.push(`\`${f.col}\` IN (${f.values.map(() => '?').join(',')})`);
          params.push(...f.values);
        }
        break;
      case 'or':
        if (f.parts?.length) {
          const orParts = [];
          for (const p of f.parts) {
            if (p.op === 'ilike') {
              orParts.push(`LOWER(\`${p.col}\`) LIKE LOWER(?)`);
              params.push(p.value);
            } else if (p.op === 'is' && p.value === null) {
              orParts.push(`\`${p.col}\` IS NULL`);
            } else if (p.op === 'lte') {
              orParts.push(`\`${p.col}\` <= ?`);
              params.push(p.value);
            } else if (p.op === 'gte') {
              orParts.push(`\`${p.col}\` >= ?`);
              params.push(p.value);
            } else if (p.op === 'lt') {
              orParts.push(`\`${p.col}\` < ?`);
              params.push(p.value);
            } else if (p.op === 'gt') {
              orParts.push(`\`${p.col}\` > ?`);
              params.push(p.value);
            } else {
              orParts.push(`\`${p.col}\` = ?`);
              params.push(p.value);
            }
          }
          if (orParts.length) clauses.push(`(${orParts.join(' OR ')})`);
        }
        break;
      default:
        break;
    }
  }

  return {
    sql: clauses.length ? ` WHERE ${clauses.join(' AND ')}` : '',
    params,
  };
}

function serializeRow(row, table, user) {
  const out = {};
  const stripCols = !isAdmin(user) ? SENSITIVE_COLUMNS[table] : null;
  for (const [k, v] of Object.entries(row)) {
    if (stripCols?.includes(k)) continue;
    if (v instanceof Date) out[k] = v.toISOString();
    else if (Buffer.isBuffer(v)) out[k] = v.toString();
    else if (typeof v === 'object' && v !== null && !Array.isArray(v)) out[k] = v;
    else out[k] = v;
  }
  return out;
}

/** Convert ISO-8601 strings to MySQL DATETIME format (YYYY-MM-DD HH:MM:SS). */
function normalizeForMysql(value) {
  if (value === null || value === undefined) return value;
  if (value instanceof Date) {
    return value.toISOString().slice(0, 19).replace('T', ' ');
  }
  if (typeof value === 'string' && /^\d{4}-\d{2}-\d{2}T/.test(value)) {
    const parsed = new Date(value);
    if (!Number.isNaN(parsed.getTime())) {
      return parsed.toISOString().slice(0, 19).replace('T', ' ');
    }
  }
  return value;
}

function serializePayloadValue(value) {
  if (value !== null && typeof value === 'object' && !(value instanceof Date)) {
    return JSON.stringify(value);
  }
  return normalizeForMysql(value);
}

router.post('/query', async (req, res) => {
  try {
    const body = req.body || {};
    const table = safeTable(body.table);
    const pool = getPool();
    const filters = body.filters || [];

    const mutating = ['insert', 'update', 'delete', 'upsert'].includes(body.action);

    if (body.action === 'select' && ALWAYS_AUTH_SELECT.has(table) && !req.user) {
      return res.status(401).json({ data: null, error: { message: 'Unauthorized' } });
    }

    if (mutating && body.action !== 'insert' && !req.user) {
      return res.status(401).json({ data: null, error: { message: 'Unauthorized' } });
    }

    if (body.action === 'select' && table === 'users' && !req.user) {
      return res.status(401).json({ data: null, error: { message: 'Unauthorized' } });
    }

    if (body.action === 'insert' && !req.user && !PUBLIC_INSERT.has(table)) {
      return res.status(401).json({ data: null, error: { message: 'Unauthorized' } });
    }

    if (body.action === 'select') {
      const { sql: whereSql, params } = buildWhere(filters);
      let orderSql = '';
      if (body.order?.length) {
        const parts = body.order.map((o) => `\`${o.column}\` ${o.ascending === false ? 'DESC' : 'ASC'}`);
        orderSql = ` ORDER BY ${parts.join(', ')}`;
      }

      let totalCount;
      if (body.count) {
        const [countRows] = await pool.query(`SELECT COUNT(*) AS cnt FROM \`${table}\`${whereSql}`, params);
        totalCount = countRows[0].cnt;
        if (body.head) {
          return res.json({ data: null, count: totalCount, error: null });
        }
      }

      let limitSql = '';
      if (body.limit != null) {
        limitSql = ` LIMIT ${Number(body.limit)}`;
      }
      let offsetSql = '';
      if (body.offset != null) {
        offsetSql = ` OFFSET ${Number(body.offset)}`;
      }

      const cols = safeColumns(body.columns);
      const [rows] = await pool.query(
        `SELECT ${cols} FROM \`${table}\`${whereSql}${orderSql}${limitSql}${offsetSql}`,
        params
      );
      const data = rows.map((row) => serializeRow(row, table, req.user));

      if (body.single) {
        if (!data.length) {
          return res.json({
            data: null,
            error: body.maybeSingle ? null : { message: 'No rows found', code: 'PGRST116' },
            count: body.count ? 0 : undefined,
          });
        }
        return res.json({
          data: data[0],
          error: null,
          count: body.count ? totalCount : undefined,
        });
      }

      return res.json({
        data,
        error: null,
        count: body.count ? totalCount : undefined,
      });
    }

    if (body.action === 'insert') {
      const rows = Array.isArray(body.payload) ? body.payload : [body.payload];
      const inserted = [];

      for (const row of rows) {
        const record = { ...row };
        if (!record.id) record.id = randomUUID();
        const keys = Object.keys(record);
        const placeholders = keys.map(() => '?').join(', ');
        const values = keys.map((k) => serializePayloadValue(record[k]));
        await pool.query(
          `INSERT INTO \`${table}\` (${keys.map((k) => `\`${k}\``).join(', ')}) VALUES (${placeholders})`,
          values
        );
        inserted.push(record);
      }

      logActivity({
        req,
        action: 'create',
        entity: table,
        entityId: inserted.length === 1 ? inserted[0].id : null,
        summary: `Created ${inserted.length} record(s) in ${table}`,
      });

      if (body.single) {
        const [one] = await pool.query(`SELECT * FROM \`${table}\` WHERE id = ? LIMIT 1`, [inserted[0].id]);
        return res.json({ data: serializeRow(one[0] || inserted[0], table, req.user), error: null });
      }
      return res.json({ data: inserted, error: null });
    }

    if (body.action === 'update') {
      const payload = body.payload || {};
      const { sql: whereSql, params: whereParams } = buildWhere(filters);
      const keys = Object.keys(payload);
      if (!keys.length) return res.json({ data: null, error: { message: 'Empty update' } });

      const setSql = keys.map((k) => `\`${k}\` = ?`).join(', ');
      const values = keys.map((k) => serializePayloadValue(payload[k]));

      await pool.query(`UPDATE \`${table}\` SET ${setSql}${whereSql}`, [...values, ...whereParams]);

      const idFilter = filters.find((f) => f.col === 'id' && f.op === 'eq');
      logActivity({
        req,
        action: 'update',
        entity: table,
        entityId: idFilter ? idFilter.value : null,
        summary: `Updated ${table}${idFilter ? ` (${idFilter.value})` : ''}`,
        metadata: { fields: keys },
      });
      if (idFilter) {
        const [rows] = await pool.query(`SELECT * FROM \`${table}\` WHERE id = ? LIMIT 1`, [idFilter.value]);
        const row = rows[0] ? serializeRow(rows[0], table, req.user) : null;
        if (body.single) {
          return res.json({ data: row, error: null });
        }
        return res.json({ data: row ? [row] : [], error: null });
      }
      return res.json({ data: null, error: null });
    }

    if (body.action === 'delete') {
      const { sql: whereSql, params } = buildWhere(filters);
      await pool.query(`DELETE FROM \`${table}\`${whereSql}`, params);
      const delIdFilter = filters.find((f) => f.col === 'id' && f.op === 'eq');
      logActivity({
        req,
        action: 'delete',
        entity: table,
        entityId: delIdFilter ? delIdFilter.value : null,
        summary: `Deleted from ${table}${delIdFilter ? ` (${delIdFilter.value})` : ''}`,
      });
      return res.json({ data: null, error: null });
    }

    if (body.action === 'upsert') {
      const rows = Array.isArray(body.payload) ? body.payload : [body.payload];
      const conflictCol = body.onConflict || 'id';
      const results = [];

      for (const row of rows) {
        const record = { ...row };
        if (!record[conflictCol]) record[conflictCol] = randomUUID();
        const keys = Object.keys(record);
        const placeholders = keys.map(() => '?').join(', ');
        const updates = keys.filter((k) => k !== conflictCol).map((k) => `\`${k}\` = VALUES(\`${k}\`)`).join(', ');
        const values = keys.map((k) => serializePayloadValue(record[k]));

        await pool.query(
          `INSERT INTO \`${table}\` (${keys.map((k) => `\`${k}\``).join(', ')}) VALUES (${placeholders})
           ON DUPLICATE KEY UPDATE ${updates}`,
          values
        );
        results.push(record);
      }
      logActivity({
        req,
        action: 'upsert',
        entity: table,
        entityId: results.length === 1 ? results[0][conflictCol] : null,
        summary: `Saved ${results.length} record(s) in ${table}`,
      });
      return res.json({ data: results, error: null });
    }

    res.status(400).json({ error: `Unknown action: ${body.action}` });
  } catch (err) {
    console.error('[data/query]', err.message);
    res.json({ data: null, error: { message: err.message, code: err.code } });
  }
});

export { parseOrFilter, buildWhere, safeTable, parseOrPart };
export default router;
