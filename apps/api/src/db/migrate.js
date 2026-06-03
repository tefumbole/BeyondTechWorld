import dotenv from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { getPool } from './pool.js';
import { getOrderedCreateStatements } from './schemaStatements.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

const schemaPath = path.join(__dirname, 'schema.sql');
const sql = fs.readFileSync(schemaPath, 'utf8');
const statements = getOrderedCreateStatements(sql);
const pool = getPool();

try {
  for (const statement of statements) {
    await pool.query(statement);
    const match = statement.match(/CREATE TABLE IF NOT EXISTS `?(\w+)`?/i);
    console.log('Created:', match?.[1] || 'table');
  }
  console.log('\nMigration complete.');
} catch (error) {
  if (error.code === 'ER_ACCESS_DENIED_ERROR') {
    console.error('\nMySQL access denied. Check apps/api/.env and Hostinger Remote MySQL whitelist for VPS IP 187.124.2.238');
  }
  throw error;
} finally {
  await pool.end();
}
