import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

function requireAdmin(req, res, next) {
  const role = String(req.user?.role || '').toLowerCase();
  if (!['admin', 'super_admin', 'director'].includes(role)) {
    return res.status(403).json({ data: null, error: { message: 'Forbidden' } });
  }
  next();
}

function serializeRow(row) {
  const out = {};
  for (const [k, v] of Object.entries(row)) {
    if (v instanceof Date) out[k] = v.toISOString();
    else out[k] = v;
  }
  return out;
}

function generateTempPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#';
  let pwd = '';
  for (let i = 0; i < 12; i += 1) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pwd;
}

router.use(requireAuth, requireAdmin);

router.get('/', async (_req, res) => {
  try {
    const pool = getPool();
    const [rows] = await pool.query(
      `SELECT p.*, u.status AS user_status
       FROM profiles p
       LEFT JOIN users u ON u.id = p.id
       ORDER BY p.created_at DESC`
    );
    res.json({ data: rows.map(serializeRow), error: null });
  } catch (err) {
    console.error('[users/list]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

router.post('/', async (req, res) => {
  try {
    const { email, full_name, phone, role, password } = req.body || {};
    if (!email || !full_name) {
      return res.status(400).json({ data: null, error: { message: 'Email and full name required' } });
    }

    const pool = getPool();
    const [dup] = await pool.query(
      'SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1',
      [email.trim()]
    );
    if (dup.length) {
      return res.status(409).json({ data: null, error: { message: 'User with this email already exists' } });
    }

    const id = randomUUID();
    const plainPassword = password || generateTempPassword();
    const hash = await bcrypt.hash(plainPassword, 10);
    const userRole = role || 'guest';

    await pool.query(
      `INSERT INTO users (id, email, password_hash, name, role, status, phone)
       VALUES (?, ?, ?, ?, ?, 'active', ?)`,
      [id, email.trim(), hash, full_name, userRole, phone || null]
    );

    await pool.query(
      `INSERT INTO profiles (id, email, full_name, phone, role)
       VALUES (?, ?, ?, ?, ?)`,
      [id, email.trim(), full_name, phone || null, userRole]
    );

    if (['admin', 'super_admin', 'director'].includes(userRole)) {
      await pool.query(
        'INSERT INTO admin_users (id, user_id, role) VALUES (?, ?, ?)',
        [randomUUID(), id, userRole]
      ).catch(() => null);
    }

    const [rows] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [id]);
    res.status(201).json({
      data: serializeRow(rows[0]),
      tempPassword: password ? undefined : plainPassword,
      error: null,
    });
  } catch (err) {
    console.error('[users/create]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

router.patch('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { full_name, phone, role, password } = req.body || {};
    const pool = getPool();

    const profileUpdates = {};
    if (full_name !== undefined) profileUpdates.full_name = full_name;
    if (phone !== undefined) profileUpdates.phone = phone;
    if (role !== undefined) profileUpdates.role = role;

    if (Object.keys(profileUpdates).length) {
      const keys = Object.keys(profileUpdates);
      const setSql = keys.map((k) => `\`${k}\` = ?`).join(', ');
      await pool.query(
        `UPDATE profiles SET ${setSql} WHERE id = ?`,
        [...keys.map((k) => profileUpdates[k]), id]
      );
    }

    const userUpdates = [];
    const userParams = [];
    if (full_name !== undefined) {
      userUpdates.push('name = ?');
      userParams.push(full_name);
    }
    if (phone !== undefined) {
      userUpdates.push('phone = ?');
      userParams.push(phone);
    }
    if (role !== undefined) {
      userUpdates.push('role = ?');
      userParams.push(role);
    }
    if (password) {
      userUpdates.push('password_hash = ?');
      userParams.push(await bcrypt.hash(password, 10));
    }
    if (userUpdates.length) {
      await pool.query(
        `UPDATE users SET ${userUpdates.join(', ')} WHERE id = ?`,
        [...userParams, id]
      );
    }

    const [rows] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [id]);
    if (!rows.length) {
      return res.status(404).json({ data: null, error: { message: 'User not found' } });
    }
    res.json({ data: serializeRow(rows[0]), error: null });
  } catch (err) {
    console.error('[users/update]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    if (req.user.sub === id) {
      return res.status(400).json({ data: null, error: { message: 'Cannot delete your own account' } });
    }

    const pool = getPool();
    await pool.query('DELETE FROM admin_users WHERE user_id = ?', [id]).catch(() => null);
    await pool.query('DELETE FROM profiles WHERE id = ?', [id]);
    await pool.query('DELETE FROM users WHERE id = ?', [id]);
    res.json({ data: { id }, error: null });
  } catch (err) {
    console.error('[users/delete]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

export default router;
