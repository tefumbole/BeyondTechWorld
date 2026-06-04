import { Router } from 'express';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { optionalAuth, requireAuth } from '../middleware/auth.js';

const router = Router();
router.use(optionalAuth);

const TABLE_RE = /^[a-z][a-z0-9_]*$/;

function safeTable(name) {
  if (!TABLE_RE.test(name)) throw new Error(`Invalid table: ${name}`);
  return name;
}

function parseOrFilter(orStr) {
  // e.g. full_name.ilike.%foo%,email.ilike.%foo%
  return orStr.split(',').map((part) => {
    const match = part.trim().match(/^(\w+)\.(ilike|eq)\.(.+)$/i);
    if (!match) return null;
    const [, col, op, val] = match;
    let value = val;
    if (op.toLowerCase() === 'ilike') {
      value = val.replace(/^%/, '').replace(/%$/, '');
      return { col, op: 'ilike', value: `%${value}%` };
    }
    return { col, op: 'eq', value };
  }).filter(Boolean);
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
          params.push(f.value);
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
            } else {
              orParts.push(`\`${p.col}\` = ?`);
              params.push(p.value);
            }
          }
          clauses.push(`(${orParts.join(' OR ')})`);
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

function serializeRow(row) {
  const out = {};
  for (const [k, v] of Object.entries(row)) {
    if (v instanceof Date) out[k] = v.toISOString();
    else if (Buffer.isBuffer(v)) out[k] = v.toString();
    else if (typeof v === 'object' && v !== null && !Array.isArray(v)) out[k] = v;
    else out[k] = v;
  }
  return out;
}

router.post('/query', async (req, res) => {
  try {
    const body = req.body || {};
    const table = safeTable(body.table);
    const pool = getPool();
    const filters = body.filters || [];

    const mutating = ['insert', 'update', 'delete', 'upsert'].includes(body.action);
    if (mutating && !req.user) {
      return res.status(401).json({ data: null, error: { message: 'Unauthorized' } });
    }

    if (body.action === 'select') {
      const { sql: whereSql, params } = buildWhere(filters);
      let orderSql = '';
      if (body.order?.length) {
        const parts = body.order.map((o) => `\`${o.column}\` ${o.ascending === false ? 'DESC' : 'ASC'}`);
        orderSql = ` ORDER BY ${parts.join(', ')}`;
      }
      let limitSql = '';
      if (body.limit) {
        limitSql = ` LIMIT ${Number(body.limit)}`;
      }

      if (body.count && body.head) {
        const [rows] = await pool.query(`SELECT COUNT(*) AS cnt FROM \`${table}\`${whereSql}`, params);
        return res.json({ data: null, count: rows[0].cnt, error: null });
      }

      const cols = body.columns === '*' || !body.columns ? '*' : body.columns;
      const [rows] = await pool.query(
        `SELECT ${cols} FROM \`${table}\`${whereSql}${orderSql}${limitSql}`,
        params
      );
      const data = rows.map(serializeRow);

      if (body.single) {
        if (!data.length) {
          return res.json({
            data: null,
            error: body.maybeSingle ? null : { message: 'No rows found', code: 'PGRST116' },
            count: body.count ? 0 : undefined,
          });
        }
        return res.json({ data: data[0], error: null, count: body.count ? data.length : undefined });
      }

      return res.json({ data, error: null, count: body.count ? data.length : undefined });
    }

    if (body.action === 'insert') {
      const rows = Array.isArray(body.payload) ? body.payload : [body.payload];
      const inserted = [];

      for (const row of rows) {
        const record = { ...row };
        if (!record.id) record.id = randomUUID();
        const keys = Object.keys(record);
        const placeholders = keys.map(() => '?').join(', ');
        const values = keys.map((k) => {
          const v = record[k];
          if (v !== null && typeof v === 'object') return JSON.stringify(v);
          return v;
        });
        await pool.query(
          `INSERT INTO \`${table}\` (${keys.map((k) => `\`${k}\``).join(', ')}) VALUES (${placeholders})`,
          values
        );
        inserted.push(record);
      }

      if (body.single) {
        const [one] = await pool.query(`SELECT * FROM \`${table}\` WHERE id = ? LIMIT 1`, [inserted[0].id]);
        return res.json({ data: serializeRow(one[0] || inserted[0]), error: null });
      }
      return res.json({ data: inserted, error: null });
    }

    if (body.action === 'update') {
      const payload = body.payload || {};
      const { sql: whereSql, params: whereParams } = buildWhere(filters);
      const keys = Object.keys(payload);
      if (!keys.length) return res.json({ data: null, error: { message: 'Empty update' } });

      const setSql = keys.map((k) => `\`${k}\` = ?`).join(', ');
      const values = keys.map((k) => {
        const v = payload[k];
        if (v !== null && typeof v === 'object') return JSON.stringify(v);
        return v;
      });

      await pool.query(`UPDATE \`${table}\` SET ${setSql}${whereSql}`, [...values, ...whereParams]);

      if (body.single && filters.some((f) => f.col === 'id' && f.op === 'eq')) {
        const idFilter = filters.find((f) => f.col === 'id' && f.op === 'eq');
        const [rows] = await pool.query(`SELECT * FROM \`${table}\` WHERE id = ? LIMIT 1`, [idFilter.value]);
        return res.json({ data: rows[0] ? serializeRow(rows[0]) : null, error: null });
      }
      return res.json({ data: null, error: null });
    }

    if (body.action === 'delete') {
      const { sql: whereSql, params } = buildWhere(filters);
      await pool.query(`DELETE FROM \`${table}\`${whereSql}`, params);
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
        const values = keys.map((k) => {
          const v = record[k];
          if (v !== null && typeof v === 'object') return JSON.stringify(v);
          return v;
        });

        await pool.query(
          `INSERT INTO \`${table}\` (${keys.map((k) => `\`${k}\``).join(', ')}) VALUES (${placeholders})
           ON DUPLICATE KEY UPDATE ${updates}`,
          values
        );
        results.push(record);
      }
      return res.json({ data: results, error: null });
    }

    res.status(400).json({ error: `Unknown action: ${body.action}` });
  } catch (err) {
    console.error('[data/query]', err.message);
    res.json({ data: null, error: { message: err.message, code: err.code } });
  }
});

export { parseOrFilter, buildWhere, safeTable };
export default router;
