import dotenv from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { getPool } from './pool.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const EXPORT_DIR = process.argv[2] || path.resolve(process.cwd(), '../../data/export');

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
    return;
  }

  await ensureTable(pool, tableName, rows[0]);

  let imported = 0;
  for (const row of rows) {
    const record = { ...row };
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
      console.warn(`  Row error in ${tableName}:`, err.message);
    }
  }
  console.log(`Imported ${imported}/${rows.length} rows into ${tableName}`);
}

async function main() {
  if (!fs.existsSync(EXPORT_DIR)) {
    console.error('Export directory not found:', EXPORT_DIR);
    console.error('Run: npm run export:supabase  (needs internet once)');
    process.exit(1);
  }

  const pool = getPool();
  const files = fs.readdirSync(EXPORT_DIR).filter((f) => f.endsWith('.json'));

  console.log(`Importing ${files.length} tables from ${EXPORT_DIR}\n`);

  // profiles first, then rest
  const sorted = files.sort((a, b) => {
    if (a === 'profiles.json') return -1;
    if (b === 'profiles.json') return 1;
    return a.localeCompare(b);
  });

  for (const file of sorted) {
    const tableName = file.replace('.json', '');
    const rows = JSON.parse(fs.readFileSync(path.join(EXPORT_DIR, file), 'utf8'));
    await importTable(pool, tableName, rows);
  }

  console.log('\nImport complete.');
  await pool.end();
}

main().catch((err) => {
  console.error(err);
  process.exit(1);
});
