import dotenv from 'dotenv';
dotenv.config();

import express from 'express';
import cors from 'cors';
import helmet from 'helmet';
import morgan from 'morgan';
import { checkDatabaseConnection, getPool } from './db/pool.js';

const app = express();
const PORT = Number(process.env.PORT || 3003);

app.set('trust proxy', true);
app.use(helmet());
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
    });
  } catch (err) {
    res.status(503).json({ ok: false, error: err.message });
  }
});

await checkDatabaseConnection();

app.listen(PORT, () => {
  console.log(`AlphaBridge API listening on port ${PORT}`);
});
