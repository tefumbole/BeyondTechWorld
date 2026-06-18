import { randomUUID } from 'node:crypto';
import { getPool } from '../db/pool.js';

// Tables that are too noisy or sensitive to log on every mutation.
const SKIP_ENTITIES = new Set([
  'activity_logs',
  'otp_sessions',
  'pending_registrations',
  'task_notification_queue',
  'sessions',
]);

function clientIp(req) {
  if (!req) return null;
  const fwd = req.headers?.['x-forwarded-for'];
  if (fwd) return String(fwd).split(',')[0].trim();
  return req.ip || req.connection?.remoteAddress || null;
}

/**
 * Record an activity log entry. Never throws — logging must not break requests.
 */
export async function logActivity({
  req = null,
  userId = null,
  userName = null,
  userRole = null,
  action,
  entity = null,
  entityId = null,
  summary = null,
  metadata = null,
}) {
  try {
    if (!action) return;
    if (entity && SKIP_ENTITIES.has(entity)) return;

    const user = req?.user || null;
    const resolvedUserId = userId || user?.sub || user?.id || null;
    const resolvedName = userName || user?.name || user?.email || user?.username || null;
    const resolvedRole = userRole || user?.role || null;

    const pool = getPool();
    await pool.query(
      `INSERT INTO activity_logs
        (id, user_id, user_name, user_role, action, entity, entity_id, summary, metadata, ip_address, created_at)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())`,
      [
        randomUUID(),
        resolvedUserId,
        resolvedName,
        resolvedRole,
        String(action).slice(0, 80),
        entity ? String(entity).slice(0, 120) : null,
        entityId ? String(entityId).slice(0, 120) : null,
        summary ? String(summary).slice(0, 500) : null,
        metadata ? JSON.stringify(metadata) : null,
        clientIp(req),
      ]
    );
  } catch (err) {
    // Swallow — never let logging interfere with the actual operation.
    console.warn('[activityLog] failed:', err.message);
  }
}

export { SKIP_ENTITIES };
