import dotenv from 'dotenv';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { checkDatabaseConnection, getPool } from './pool.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../../.env') });

try {
  await checkDatabaseConnection();
  const pool = getPool();
  const [rows] = await pool.query('SELECT DATABASE() AS db, VERSION() AS version');
  console.log('Database:', rows[0].db);
  console.log('MySQL version:', rows[0].version);
  await pool.end();
  console.log('Connection test passed.');
} catch (err) {
  console.error('Connection failed:', err.message);
  process.exit(1);
}
