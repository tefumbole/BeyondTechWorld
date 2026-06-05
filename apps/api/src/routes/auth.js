import { Router } from 'express';
import bcrypt from 'bcryptjs';
import jwt from 'jsonwebtoken';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
const JWT_EXPIRY = '7d';

function buildSession(user, profile) {
  const expiresAt = Math.floor(Date.now() / 1000) + 7 * 24 * 60 * 60;
  const token = jwt.sign(
    { sub: user.id, email: user.email, role: profile?.role || user.role },
    process.env.JWT_SECRET,
    { expiresIn: JWT_EXPIRY }
  );

  return {
    access_token: token,
    refresh_token: token,
    expires_at: expiresAt,
    token_type: 'bearer',
    user: {
      id: user.id,
      email: user.email,
      phone: profile?.phone || user.phone || null,
      user_metadata: { full_name: profile?.full_name || user.name },
      app_metadata: { role: profile?.role || user.role },
    },
  };
}

router.post('/login', async (req, res) => {
  try {
    const { email, password } = req.body || {};
    if (!email || !password) {
      return res.status(400).json({ error: 'Email and password required' });
    }

    const pool = getPool();
    const [users] = await pool.query(
      'SELECT * FROM users WHERE LOWER(email) = LOWER(?) AND status = ? LIMIT 1',
      [email.trim(), 'active']
    );
    const user = users[0];

    if (!user) {
      return res.status(401).json({ error: 'Invalid login credentials' });
    }

    const valid = await bcrypt.compare(password, user.password_hash);
    if (!valid) {
      return res.status(401).json({ error: 'Invalid login credentials' });
    }

    const [profiles] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [user.id]);
    const profile = profiles[0] || null;
    const session = buildSession(user, profile);

    res.json({ session, user: session.user });
  } catch (err) {
    console.error('[auth/login]', err);
    res.status(500).json({ error: err.message });
  }
});

router.get('/session', requireAuth, async (req, res) => {
  try {
    const pool = getPool();
    const [users] = await pool.query('SELECT * FROM users WHERE id = ? LIMIT 1', [req.user.sub]);
    const user = users[0];
    if (!user) {
      return res.status(401).json({ error: 'User not found' });
    }

    const [profiles] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [user.id]);
    const session = buildSession(user, profiles[0] || null);
    res.json({ session });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

router.post('/logout', (_req, res) => {
  res.json({ success: true });
});

router.post('/refresh', requireAuth, async (req, res) => {
  try {
    const pool = getPool();
    const [users] = await pool.query('SELECT * FROM users WHERE id = ? LIMIT 1', [req.user.sub]);
    const user = users[0];
    if (!user) {
      return res.status(401).json({ error: 'User not found' });
    }
    const [profiles] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [user.id]);
    const session = buildSession(user, profiles[0] || null);
    res.json({ session });
  } catch (err) {
    res.status(500).json({ error: err.message });
  }
});

export async function seedAdminUser() {
  const email = process.env.SEED_ADMIN_EMAIL;
  const password = process.env.SEED_ADMIN_PASSWORD;
  const phone = process.env.SEED_ADMIN_PHONE || null;
  if (!email || !password) return;

  const pool = getPool();
  const [existing] = await pool.query('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1', [email]);
  if (existing.length) {
    if (phone) {
      await pool.query('UPDATE users SET phone = ? WHERE LOWER(email) = LOWER(?)', [phone, email]);
      await pool.query(
        'UPDATE profiles SET phone = ? WHERE LOWER(email) = LOWER(?)',
        [phone, email]
      );
    }
    return;
  }

  const id = randomUUID();
  const hash = await bcrypt.hash(password, 10);

  await pool.query(
    `INSERT INTO users (id, email, password_hash, name, role, status, phone)
     VALUES (?, ?, ?, ?, 'super_admin', 'active', ?)`,
    [id, email, hash, 'Super Administrator', phone]
  );

  await pool.query(
    `INSERT INTO profiles (id, email, full_name, role, phone)
     VALUES (?, ?, ?, 'super_admin', ?)
     ON DUPLICATE KEY UPDATE email = VALUES(email), role = 'super_admin', phone = COALESCE(VALUES(phone), phone)`,
    [id, email, 'Super Administrator', phone]
  );

  console.log('[seed] Admin user created:', email);
}

export default router;
