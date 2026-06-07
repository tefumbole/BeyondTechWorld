import { Router } from 'express';
import bcrypt from 'bcryptjs';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();

const STAFF_ROLES = [
  'super_admin',
  'admin',
  'director',
  'staff',
  'employee',
  'teacher',
  'manager',
  'user',
];

function requireAdmin(req, res, next) {
  const role = String(req.user?.role || '').toLowerCase();
  if (!['admin', 'super_admin', 'director'].includes(role)) {
    return res.status(403).json({ data: null, error: { message: 'Forbidden' } });
  }
  next();
}

function serializeUser(row) {
  return {
    id: row.id,
    email: row.email,
    username: row.username || null,
    full_name: row.full_name || row.name,
    name: row.full_name || row.name,
    phone: row.phone,
    role: row.role,
    status: row.status || row.user_status || 'active',
    created_at: row.created_at instanceof Date ? row.created_at.toISOString() : row.created_at,
    updated_at: row.updated_at instanceof Date ? row.updated_at.toISOString() : row.updated_at,
  };
}

function normalizeUsername(value) {
  return String(value || '')
    .trim()
    .toLowerCase()
    .replace(/[^a-z0-9._-]/g, '');
}

function generateTempPassword() {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#';
  let pwd = '';
  for (let i = 0; i < 12; i += 1) {
    pwd += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return pwd;
}

async function syncProfile(pool, userRow) {
  await pool.query(
    `INSERT INTO profiles (id, email, full_name, phone, role, status, username)
     VALUES (?, ?, ?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       email = VALUES(email),
       full_name = VALUES(full_name),
       phone = VALUES(phone),
       role = VALUES(role),
       status = VALUES(status),
       username = VALUES(username)`,
    [
      userRow.id,
      userRow.email,
      userRow.full_name || userRow.name,
      userRow.phone || null,
      userRow.role,
      userRow.status || 'active',
      userRow.username || null,
    ]
  );
}

router.use(requireAuth, requireAdmin);

router.get('/', async (req, res) => {
  try {
    const pool = getPool();
    const forAssignment = req.query.for === 'assignment';
    const roleFilter = req.query.role;

    let sql = `SELECT u.id, u.email, u.username, u.name, u.phone, u.role, u.status, u.created_at, u.updated_at
               FROM users u
               WHERE u.status = 'active'`;
    const params = [];

    if (forAssignment) {
      sql += ` AND u.role NOT IN ('inactive', 'disabled')`;
    }
    if (roleFilter) {
      sql += ' AND LOWER(u.role) = LOWER(?)';
      params.push(roleFilter);
    }

    sql += ' ORDER BY u.name ASC, u.email ASC';

    const [rows] = await pool.query(sql, params);
    res.json({ data: rows.map(serializeUser), error: null });
  } catch (err) {
    console.error('[users/list]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

router.post('/', async (req, res) => {
  try {
    const { email, username, full_name, phone, role, password } = req.body || {};
    if (!email || !full_name) {
      return res.status(400).json({ data: null, error: { message: 'Email and full name required' } });
    }
    if (!password || String(password).length < 8) {
      return res.status(400).json({ data: null, error: { message: 'Password must be at least 8 characters' } });
    }

    const pool = getPool();
    const [dup] = await pool.query(
      'SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1',
      [email.trim()]
    );
    if (dup.length) {
      return res.status(409).json({ data: null, error: { message: 'User with this email already exists' } });
    }

    const normalizedUsername = username ? normalizeUsername(username) : null;
    if (normalizedUsername) {
      if (normalizedUsername.length < 3) {
        return res.status(400).json({ data: null, error: { message: 'Username must be at least 3 characters' } });
      }
      const [dupUser] = await pool.query(
        'SELECT id FROM users WHERE LOWER(username) = LOWER(?) LIMIT 1',
        [normalizedUsername]
      );
      if (dupUser.length) {
        return res.status(409).json({ data: null, error: { message: 'Username is already taken' } });
      }
    }

    const id = randomUUID();
    const hash = await bcrypt.hash(String(password), 10);
    const userRole = role || 'user';

    await pool.query(
      `INSERT INTO users (id, email, username, password_hash, name, role, status, phone)
       VALUES (?, ?, ?, ?, ?, ?, 'active', ?)`,
      [id, email.trim(), normalizedUsername, hash, full_name, userRole, phone || null]
    );

    const userRow = {
      id,
      email: email.trim(),
      username: normalizedUsername,
      full_name,
      name: full_name,
      phone: phone || null,
      role: userRole,
      status: 'active',
    };
    await syncProfile(pool, userRow);

    if (['admin', 'super_admin', 'director'].includes(userRole)) {
      await pool.query(
        'INSERT INTO admin_users (id, user_id, role) VALUES (?, ?, ?)',
        [randomUUID(), id, userRole]
      ).catch(() => null);
    }

    res.status(201).json({
      data: serializeUser(userRow),
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
    const { full_name, phone, role, password, status, username } = req.body || {};
    const pool = getPool();

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
    if (status !== undefined) {
      userUpdates.push('status = ?');
      userParams.push(status);
    }
    if (username !== undefined) {
      const normalizedUsername = username ? normalizeUsername(username) : null;
      if (normalizedUsername && normalizedUsername.length < 3) {
        return res.status(400).json({ data: null, error: { message: 'Username must be at least 3 characters' } });
      }
      if (normalizedUsername) {
        const [dupUser] = await pool.query(
          'SELECT id FROM users WHERE LOWER(username) = LOWER(?) AND id != ? LIMIT 1',
          [normalizedUsername, id]
        );
        if (dupUser.length) {
          return res.status(409).json({ data: null, error: { message: 'Username is already taken' } });
        }
      }
      userUpdates.push('username = ?');
      userParams.push(normalizedUsername);
    }
    if (password) {
      if (String(password).length < 8) {
        return res.status(400).json({ data: null, error: { message: 'Password must be at least 8 characters' } });
      }
      userUpdates.push('password_hash = ?');
      userParams.push(await bcrypt.hash(String(password), 10));
    }
    if (userUpdates.length) {
      await pool.query(
        `UPDATE users SET ${userUpdates.join(', ')} WHERE id = ?`,
        [...userParams, id]
      );
    }

    const [rows] = await pool.query('SELECT * FROM users WHERE id = ? LIMIT 1', [id]);
    if (!rows.length) {
      return res.status(404).json({ data: null, error: { message: 'User not found' } });
    }

    await syncProfile(pool, rows[0]);
    res.json({ data: serializeUser(rows[0]), error: null });
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
    await pool.query('DELETE FROM profiles WHERE id = ?', [id]).catch(() => null);
    await pool.query('DELETE FROM users WHERE id = ?', [id]);
    res.json({ data: { id }, error: null });
  } catch (err) {
    console.error('[users/delete]', err);
    res.status(500).json({ data: null, error: { message: err.message } });
  }
});

export { STAFF_ROLES, serializeUser, syncProfile };
export default router;
