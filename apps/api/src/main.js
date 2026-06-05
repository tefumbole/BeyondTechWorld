import dotenv from 'dotenv';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
dotenv.config({ path: path.resolve(__dirname, '../.env') });

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import morgan from 'morgan';
import { checkDatabaseConnection, getPool } from './db/pool.js';
import authRoutes, { seedAdminUser } from './routes/auth.js';
import otpRoutes from './routes/otp.js';
import dataRoutes from './routes/data.js';
import uploadRoutes from './routes/upload.js';
import usersRoutes from './routes/users.js';

const app = express();
const PORT = Number(process.env.PORT || 3003);
const uploadDir = path.resolve(process.env.UPLOAD_DIR || path.join(__dirname, '../uploads'));
fs.mkdirSync(uploadDir, { recursive: true });

app.set('trust proxy', true);
app.use(helmet({ crossOriginResourcePolicy: { policy: 'cross-origin' } }));
app.use(cors({
  origin: process.env.CORS_ORIGIN || '*',
  credentials: true,
}));
app.use(morgan('combined'));
app.use(express.json({ limit: '20mb' }));
app.use(express.urlencoded({ extended: true }));

app.get('/health', async (_req, res) => {
  try {
    const pool = getPool();
    await pool.query('SELECT 1');
    res.json({
      ok: true,
      service: 'alphabridge-api',
      database: process.env.DB_NAME,
      backend: 'mysql',
    });
  } catch (err) {
    res.status(503).json({ ok: false, error: err.message });
  }
});

app.use('/auth', authRoutes);
app.use('/auth/otp', otpRoutes);
app.use('/users', usersRoutes);
app.use('/data', dataRoutes);
app.use('/upload', uploadRoutes);

await checkDatabaseConnection();
await seedAdminUser();

app.listen(PORT, () => {
  console.log(`AlphaBridge API listening on port ${PORT}`);
  console.log(`Uploads: ${uploadDir}`);
});
