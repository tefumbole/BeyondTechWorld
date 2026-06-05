import dotenv from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { getPool } from './pool.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const EXPORT_DIR =
  process.argv[2] || path.resolve(__dirname, '../../../../data/export');

/** Supabase export filename -> MySQL table name */
const TABLE_NAME_MAP = {
  whatsapp_message_log: 'whatsapp_message_logs',
};

/** Import parents before children */
const IMPORT_PRIORITY = [
  'profiles.json',
  'users.json',
  'roles.json',
  'permissions.json',
  'role_permissions.json',
  'user_roles.json',
  'tasks.json',
  'task_categories.json',
  'task_message_templates.json',
  'task_assignments.json',
  'task_updates.json',
  'task_attachments.json',
  'messages.json',
  'message_templates.json',
  'message_settings.json',
  'message_attachments.json',
  'message_recipients.json',
  'message_queue.json',
  'message_logs.json',
  'shareholders.json',
];

function inferSqlType(value) {
  if (value === null || value === undefined) return 'TEXT NULL';
  if (typeof value === 'boolean') return 'TINYINT(1) NULL';
  if (typeof value === 'number') return Number.isInteger(value) ? 'BIGINT NULL' : 'DOUBLE NULL';
  if (typeof value === 'object') return 'JSON NULL';
  if (typeof value === 'string') {
    if (/^\d{4}-\d{2}-\d{2}T/.test(value)) return 'DATETIME NULL';
    if (value.length > 500) return 'LONGTEXT NULL';
  }
  return 'TEXT NULL';
}

function normalizeRow(tableName, row) {
  const record = { ...row };

  if (tableName === 'shareholders') {
    if (!record.full_name && record.name) record.full_name = record.name;
    if (!record.phone_number && record.phone) record.phone_number = record.phone;
    if (!record.full_phone_number && record.phone_number) {
      record.full_phone_number = record.phone_number;
    }
    if (record.email === '') record.email = null;
  }

  if (tableName === 'whatsapp_message_logs') {
    if (!record.recipient_phone && record.phone_number) {
      record.recipient_phone = record.phone_number;
    }
    if (!record.phone_number && record.recipient_phone) {
      record.phone_number = record.recipient_phone;
    }
    if (!record.sent_at && record.created_at) record.sent_at = record.created_at;
  }

  return record;
}

async function ensureTable(pool, tableName, sampleRow) {
  const [tables] = await pool.query('SHOW TABLES LIKE ?', [tableName]);
  if (!tables.length) {
    const cols = Object.keys(sampleRow);
    const colDefs = cols.map((col) => {
      const type = col === 'id' ? 'CHAR(36) NOT NULL PRIMARY KEY' : inferSqlType(sampleRow[col]);
      return `\`${col}\` ${type}`;
    });
    const sql = `CREATE TABLE IF NOT EXISTS \`${tableName}\` (${colDefs.join(', ')}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`;
    await pool.query(sql);
    console.log('Created table:', tableName);
    return;
  }

  const [columns] = await pool.query(`SHOW COLUMNS FROM \`${tableName}\``);
  const existing = new Set(columns.map((c) => c.Field));
  for (const col of Object.keys(sampleRow)) {
    if (!existing.has(col)) {
      const type = col === 'id' ? 'CHAR(36) NULL' : inferSqlType(sampleRow[col]);
      await pool.query(`ALTER TABLE \`${tableName}\` ADD COLUMN \`${col}\` ${type}`);
      console.log(`  Added column ${tableName}.${col}`);
    }
  }
}

async function importTable(pool, tableName, rows) {
  if (!rows?.length) {
    console.log('Skip empty:', tableName);
    return { imported: 0, errors: 0, total: 0 };
  }

  const sample = normalizeRow(tableName, rows[0]);
  await ensureTable(pool, tableName, sample);

  let imported = 0;
  let errors = 0;

  for (const row of rows) {
    const record = normalizeRow(tableName, row);
    const keys = Object.keys(record);
    const placeholders = keys.map(() => '?').join(', ');
    const values = keys.map((k) => {
      const v = record[k];
      if (v !== null && typeof v === 'object') return JSON.stringify(v);
      return v;
    });

    try {
      await pool.query(
        `INSERT INTO \`${tableName}\` (${keys.map((k) => `\`${k}\``).join(', ')})
         VALUES (${placeholders})
         ON DUPLICATE KEY UPDATE ${keys.filter((k) => k !== 'id').map((k) => `\`${k}\`=VALUES(\`${k}\`)`).join(', ') || 'id=id'}`,
        values
      );
      imported++;
    } catch (err) {
      errors++;
      if (errors <= 5) {
        console.warn(`  Row error in ${tableName}:`, err.message);
      }
    }
  }

  if (errors > 5) {
    console.warn(`  ... and ${errors - 5} more errors in ${tableName}`);
  }

  console.log(`Imported ${imported}/${rows.length} rows into ${tableName}${errors ? ` (${errors} failed)` : ''}`);
  return { imported, errors, total: rows.length };
}

async function main() {
  if (!fs.existsSync(EXPORT_DIR)) {
    console.error('Export directory not found:', EXPORT_DIR);
    console.error('Run: npm run export:supabase  (needs internet once)');
    process.exit(1);
  }

  const pool = getPool();
  const files = fs.readdirSync(EXPORT_DIR).filter((f) => f.endsWith('.json') && f !== '_summary.json');

  const sorted = files.sort((a, b) => {
    const ai = IMPORT_PRIORITY.indexOf(a);
    const bi = IMPORT_PRIORITY.indexOf(b);
    if (ai >= 0 && bi >= 0) return ai - bi;
    if (ai >= 0) return -1;
    if (bi >= 0) return 1;
    return a.localeCompare(b);
  });

  console.log(`Importing ${sorted.length} tables from ${EXPORT_DIR}\n`);

  const summary = [];

  for (const file of sorted) {
    const exportName = file.replace('.json', '');
    const tableName = TABLE_NAME_MAP[exportName] || exportName;
    const rows = JSON.parse(fs.readFileSync(path.join(EXPORT_DIR, file), 'utf8'));
    const result = await importTable(pool, tableName, rows);
    summary.push({ file, table: tableName, ...result });
  }

  const totalErrors = summary.reduce((n, s) => n + s.errors, 0);
  console.log(`\nImport complete. ${totalErrors} row errors total.`);

  await pool.end();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
