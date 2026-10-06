import nodemailer from 'nodemailer';

export async function sendReportEmail({ to, subject, text }) {
  const recipients = [...new Set((to || []).map((item) => String(item || '').trim().toLowerCase()).filter(Boolean))];
  const host = process.env.SMTP_HOST;
  const user = process.env.SMTP_USER;
  const pass = process.env.SMTP_PASSWORD || process.env.SMTP_PASS;
  if (!host || !user || !pass || !recipients.length) {
    return { success: false, error: 'Email is not configured' };
  }

  const transporter = nodemailer.createTransport({
    host,
    port: Number(process.env.SMTP_PORT || 587),
    secure: String(process.env.SMTP_PORT || '') === '465',
    auth: { user, pass },
  });

  await transporter.sendMail({
    from: process.env.SMTP_FROM || user,
    to: recipients.join(', '),
    subject,
    text,
  });
  return { success: true };
}
