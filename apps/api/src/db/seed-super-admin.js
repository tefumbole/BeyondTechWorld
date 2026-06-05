/**
 * Upsert the primary Super Admin account (users + profiles).
 * Run: cd apps/api && npm run db:seed-admin
 */
import dotenv from 'dotenv';
import path from 'path';
import { fileURLToPath } from 'node:url';
import bcrypt from 'bcryptjs';
import { randomUUID } from 'node:crypto';
import { getPool } from './pool.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const email = process.env.SEED_ADMIN_EMAIL || 'admin@alpha-bridge.net';
const password = process.env.SEED_ADMIN_PASSWORD || 'system';
const phone = process.env.SEED_ADMIN_PHONE || '+237675321739';
const fullName = process.env.SEED_ADMIN_NAME || 'Super Administrator';
const role = 'super_admin';

async function main() {
  const pool = getPool();
  const hash = await bcrypt.hash(password, 10);

  const [existing] = await pool.query(
    'SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1',
    [email]
  );

  const userId = existing[0]?.id || randomUUID();

  if (existing.length) {
    await pool.query(
      `UPDATE users
       SET password_hash = ?, name = ?, role = ?, status = 'active', phone = ?
       WHERE id = ?`,
      [hash, fullName, role, phone, userId]
    );
    console.log('[seed] Updated existing user:', email);
  } else {
    await pool.query(
      `INSERT INTO users (id, email, password_hash, name, role, status, phone)
       VALUES (?, ?, ?, ?, ?, 'active', ?)`,
      [userId, email, hash, fullName, role, phone]
    );
    console.log('[seed] Created user:', email);
  }

  await pool.query(
    `INSERT INTO profiles (id, email, full_name, phone, role)
     VALUES (?, ?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE
       email = VALUES(email),
       full_name = VALUES(full_name),
       phone = VALUES(phone),
       role = VALUES(role)`,
    [userId, email, fullName, phone, role]
  );

  await pool.query(
    `INSERT INTO admin_users (id, user_id, role)
     VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE role = VALUES(role)`,
    [randomUUID(), userId, role]
  ).catch(async () => {
    const [rows] = await pool.query('SELECT id FROM admin_users WHERE user_id = ? LIMIT 1', [userId]);
    if (rows.length) {
      await pool.query('UPDATE admin_users SET role = ? WHERE user_id = ?', [role, userId]);
    } else {
      await pool.query(
        'INSERT INTO admin_users (id, user_id, role) VALUES (?, ?, ?)',
        [randomUUID(), userId, role]
      );
    }
  });

  console.log('[seed] Super Admin ready');
  console.log('  Email:   ', email);
  console.log('  Phone:   ', phone);
  console.log('  Role:    ', role);
  console.log('  Password:', password, '(change after first login)');
  process.exit(0);
}

main().catch((err) => {
  console.error('[seed] Failed:', err.message);
  process.exit(1);
});
