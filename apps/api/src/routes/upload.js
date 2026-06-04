import { Router } from 'express';
import fs from 'node:fs';
import path from 'node:path';
import multer from 'multer';
import { requireAuth } from '../middleware/auth.js';

const router = Router();
const uploadRoot = path.resolve(process.env.UPLOAD_DIR || './uploads');

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

const storage = multer.diskStorage({
  destination: (req, _file, cb) => {
    const bucket = (req.params.bucket || 'default').replace(/[^a-z0-9_-]/gi, '');
    const dir = path.join(uploadRoot, bucket);
    ensureDir(dir);
    cb(null, dir);
  },
  filename: (_req, file, cb) => {
    cb(null, file.originalname.replace(/[^a-zA-Z0-9._-]/g, '_'));
  },
});

const upload = multer({ storage, limits: { fileSize: 25 * 1024 * 1024 } });

router.post('/:bucket', requireAuth, upload.single('file'), (req, res) => {
  const bucket = req.params.bucket.replace(/[^a-z0-9_-]/gi, '');
  const filename = req.body.path || req.file?.filename;
  if (!req.file) {
    return res.status(400).json({ error: 'No file uploaded' });
  }
  res.json({ path: filename, Key: filename, bucket });
});

router.get('/:bucket/:filename', (req, res) => {
  const bucket = req.params.bucket.replace(/[^a-z0-9_-]/gi, '');
  const filePath = path.join(uploadRoot, bucket, path.basename(req.params.filename));
  if (!fs.existsSync(filePath)) {
    return res.status(404).json({ error: 'Not found' });
  }
  res.sendFile(filePath);
});

export default router;
