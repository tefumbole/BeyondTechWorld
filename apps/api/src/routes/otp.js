import { Router } from 'express';
import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';
import { requireAuth } from '../middleware/auth.js';
import { sendOtp, formatPhoneNumber } from '../services/wasenderWhatsAppService.js';

const router = Router();

async function getUserPhone(userId) {
  const pool = getPool();
  const [profiles] = await pool.query(
    'SELECT phone FROM profiles WHERE id = ? LIMIT 1',
    [userId]
  );
  const [users] = await pool.query(
    'SELECT phone FROM users WHERE id = ? LIMIT 1',
    [userId]
  );
  const raw = profiles[0]?.phone || users[0]?.phone || null;
  const formatted = formatPhoneNumber(raw);
  if (!formatted) {
    throw new Error('No valid phone number on your profile.');
  }
  return formatted;
}

router.post('/send', requireAuth, async (req, res) => {
  try {
    const userId = req.user.sub;
    const phone = await getUserPhone(userId);
    const otpCode = Math.floor(100000 + Math.random() * 900000).toString();
    const expiresAt = new Date(Date.now() + 10 * 60 * 1000);

    const pool = getPool();
    await pool.query(
      `INSERT INTO otp_sessions (id, phone, otp, expires_at, attempts, resend_count)
       VALUES (?, ?, ?, ?, 0, 0)`,
      [randomUUID(), phone, otpCode, expiresAt]
    );

    const sendResult = await sendOtp(phone, otpCode);
    if (!sendResult.success) {
      console.error('[otp/send]', sendResult.error);
      return res.status(502).json({
        success: false,
        error: sendResult.error || 'Failed to send WhatsApp OTP',
        maskedPhone: `${phone.substring(0, 6)}****${phone.slice(-2)}`,
      });
    }

    res.json({
      success: true,
      message: 'OTP sent successfully.',
      maskedPhone: `${phone.substring(0, 6)}****${phone.slice(-2)}`,
    });
  } catch (err) {
    console.error('[otp/send]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

router.post('/verify', requireAuth, async (req, res) => {
  try {
    const userId = req.user.sub;
    const { otp } = req.body || {};
    if (!otp || String(otp).replace(/\D/g, '').length !== 6) {
      return res.status(400).json({ success: false, error: 'Valid 6-digit OTP required' });
    }

    const cleanOtp = String(otp).replace(/\D/g, '');
    const phone = await getUserPhone(userId);
    const pool = getPool();

    const [rows] = await pool.query(
      `SELECT * FROM otp_sessions
       WHERE phone = ? AND verified_at IS NULL AND expires_at >= NOW()
       ORDER BY created_at DESC
       LIMIT 1`,
      [phone]
    );
    const session = rows[0];

    if (!session) {
      return res.json({ success: false, error: 'Invalid or expired OTP.' });
    }

    if (session.otp !== cleanOtp) {
      await pool.query(
        'UPDATE otp_sessions SET attempts = attempts + 1 WHERE id = ?',
        [session.id]
      );
      return res.json({ success: false, error: 'Incorrect verification code.' });
    }

    await pool.query(
      'UPDATE otp_sessions SET verified_at = NOW() WHERE id = ?',
      [session.id]
    );

    const [profiles] = await pool.query('SELECT * FROM profiles WHERE id = ? LIMIT 1', [userId]);
    res.json({
      success: true,
      message: 'OTP verified.',
      profile: profiles[0] || null,
    });
  } catch (err) {
    console.error('[otp/verify]', err);
    res.status(500).json({ success: false, error: err.message });
  }
});

export default router;
