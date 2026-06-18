import { Router } from 'express';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

const ADMIN_ROLES = new Set(['super_admin', 'admin', 'director']);

function requireAdmin(req, res, next) {
  const role = String(req.user?.role || '').toLowerCase();
  if (!ADMIN_ROLES.has(role)) {
    return res.status(403).json({ data: null, error: { message: 'Admin access required' } });
  }
  next();
}

router.use(requireAuth, requireAdmin);

// GET /activity-logs?search=&action=&entity=&limit=&offset=
router.get('/', async (req, res) => {
  try {
    const pool = getPool();
    const limit = Math.min(Number(req.query.limit) || 100, 500);
    const offset = Number(req.query.offset) || 0;

    const where = [];
    const params = [];

    if (req.query.action) {
      where.push('action = ?');
      params.push(String(req.query.action));
    }
    if (req.query.entity) {
      where.push('entity = ?');
      params.push(String(req.query.entity));
    }
    if (req.query.search) {
      const term = `%${req.query.search}%`;
      where.push('(user_name LIKE ? OR summary LIKE ? OR entity LIKE ? OR entity_id LIKE ?)');
      params.push(term, term, term, term);
    }

    const whereSql = where.length ? ` WHERE ${where.join(' AND ')}` : '';

    const [countRows] = await pool.query(`SELECT COUNT(*) AS cnt FROM activity_logs${whereSql}`, params);
    const [rows] = await pool.query(
      `SELECT * FROM activity_logs${whereSql} ORDER BY created_at DESC LIMIT ? OFFSET ?`,
      [...params, limit, offset]
    );

    res.json({ data: rows, count: countRows[0].cnt, error: null });
  } catch (err) {
    console.error('[activity-logs/list]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

// DELETE /activity-logs  body: { ids: [...] }  OR  { all: true }
router.delete('/', async (req, res) => {
  try {
    const pool = getPool();
    const { ids, all } = req.body || {};

    if (all === true) {
      await pool.query('DELETE FROM activity_logs');
      return res.json({ data: { cleared: true }, error: null });
    }

    const list = (Array.isArray(ids) ? ids : []).filter(Boolean);
    if (!list.length) {
      return res.status(400).json({ data: null, error: { message: 'No log ids provided' } });
    }
    const placeholders = list.map(() => '?').join(', ');
    await pool.query(`DELETE FROM activity_logs WHERE id IN (${placeholders})`, list);
    res.json({ data: { count: list.length }, error: null });
  } catch (err) {
    console.error('[activity-logs/delete]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

export default router;
