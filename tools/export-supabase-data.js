/**
 * One-time export of Supabase data.
 * Prefer SUPABASE_SERVICE_ROLE_KEY so RLS-protected tables (e.g. profiles) export fully.
 *
 * Usage: npm run export:supabase
 * Output: data/export/*.json
 */
import { createClient } from '@supabase/supabase-js';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const OUT_DIR = path.resolve(__dirname, '../data/export');

const SUPABASE_URL = process.env.VITE_SUPABASE_URL || 'https://xnfurysmtmxkfsdjghow.supabase.co';
const SUPABASE_KEY =
  process.env.SUPABASE_SERVICE_ROLE_KEY ||
  process.env.VITE_SUPABASE_SERVICE_ROLE_KEY ||
  process.env.VITE_SUPABASE_ANON_KEY ||
  'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InhuZnVyeXNtdG14a2ZzZGpnaG93Iiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA1NTMyMDEsImV4cCI6MjA4NjEyOTIwMX0.VBmIxYtdIyL68g3YZ-InQtw9hztEFm11yFtC_2vVbRk';

/** Supabase table name -> MySQL import filename */
const EXPORT_FILE_MAP = {
  whatsapp_message_log: 'whatsapp_message_logs',
};

const TABLES = [
  'profiles', 'shareholders', 'members', 'students', 'courses', 'registrations',
  'jobs', 'applications', 'events', 'event_images', 'event_registrations', 'event_meal_selections',
  'invitations', 'guests', 'invitation_templates', 'announcements', 'announcement_templates',
  'announcement_settings', 'announcement_recipients', 'system_settings', 'contact_messages',
  'otp_sessions', 'tasks', 'task_assignments', 'task_updates', 'task_attachments',
  'task_categories', 'task_message_templates', 'roles', 'permissions', 'role_permissions',
  'user_roles', 'messages', 'message_templates', 'message_settings', 'message_attachments',
  'message_recipients', 'message_queue', 'message_logs', 'whatsapp_message_log',
  'share_bookings', 'course_feedback', 'student_progress', 'certificates', 'invoices',
  'letters', 'email_logs', 'master_class_registrations', 'system_backups', 'backups',
];

const supabase = createClient(SUPABASE_URL, SUPABASE_KEY);
const PAGE = 1000;

async function exportTable(table) {
  const all = [];
  let from = 0;

  while (true) {
    const { data, error } = await supabase.from(table).select('*').range(from, from + PAGE - 1);
    if (error) {
      if (error.code === '42P01' || error.message?.includes('does not exist')) {
        return { rows: [], skipped: true };
      }
      throw new Error(`${table}: ${error.message}`);
    }
    if (!data?.length) break;
    all.push(...data);
    process.stdout.write(`  ${table}: ${all.length} rows\r`);
    if (data.length < PAGE) break;
    from += PAGE;
  }

  return { rows: all, skipped: false };
}

async function main() {
  fs.mkdirSync(OUT_DIR, { recursive: true });
  console.log('Exporting from Supabase to', OUT_DIR);
  console.log('Using', process.env.SUPABASE_SERVICE_ROLE_KEY || process.env.VITE_SUPABASE_SERVICE_ROLE_KEY ? 'service role key' : 'anon key');
  console.log('(Dashboard access not required — uses API keys)\n');

  const summary = [];

  for (const table of TABLES) {
    try {
      const { rows, skipped } = await exportTable(table);
      if (skipped) {
        console.log(`Skip ${table} (not found)`);
        continue;
      }
      fs.writeFileSync(
        path.join(OUT_DIR, `${EXPORT_FILE_MAP[table] || table}.json`),
        JSON.stringify(rows, null, 2)
      );
      console.log(`Exported ${table}: ${rows.length} rows`);
      summary.push({ table, count: rows.length });
    } catch (err) {
      console.warn(`Failed ${table}:`, err.message);
      summary.push({ table, error: err.message });
    }
  }

  fs.writeFileSync(path.join(OUT_DIR, '_summary.json'), JSON.stringify(summary, null, 2));
  console.log('\nDone. Next: npm run db:import');
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
