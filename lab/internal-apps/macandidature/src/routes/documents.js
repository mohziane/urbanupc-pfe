import { Router } from 'express';
import multer from 'multer';
import fs from 'node:fs';
import path from 'node:path';
import crypto from 'node:crypto';
import { z } from 'zod';

import { config } from '../config.js';
import { prisma } from '../lib/db.js';
import { requireAuth, requireVerified, assertOwner } from '../middleware/auth.js';
import { validate } from '../middleware/validate.js';
import { audit } from '../lib/logger.js';

const router = Router();

const storage = multer.diskStorage({
  destination: (_req, _file, cb) => cb(null, config.uploads.dir),
  filename: (_req, _file, cb) => cb(null, crypto.randomBytes(24).toString('hex')),
});

const upload = multer({
  storage,
  limits: { fileSize: config.uploads.maxBytes, files: 1 },
  fileFilter: (_req, file, cb) => {
    if (!config.uploads.allowedMime.has(file.mimetype)) return cb(new Error('unsupported_media_type'));
    cb(null, true);
  },
});

function sniffMagic(buf) {
  if (buf.length < 4) return null;
  if (buf.slice(0, 4).toString('ascii') === '%PDF') return 'application/pdf';
  if (buf[0] === 0x89 && buf[1] === 0x50 && buf[2] === 0x4e && buf[3] === 0x47) return 'image/png';
  if (buf[0] === 0xff && buf[1] === 0xd8 && buf[2] === 0xff) return 'image/jpeg';
  return null;
}

const categories = ['CV', 'LETTER', 'TRANSCRIPT', 'ID_PROOF', 'OTHER'];

router.get('/', requireAuth, async (req, res) => {
  const docs = await prisma.candidateDocument.findMany({
    where: { ownerId: req.user.id },
    orderBy: { createdAt: 'desc' },
    select: { id: true, filename: true, mimeType: true, sizeBytes: true, category: true, createdAt: true },
  });
  res.json({ documents: docs });
});

const uploadSchema = z.object({ category: z.enum(categories) });

router.post('/', requireAuth, requireVerified, upload.single('file'), validate(uploadSchema), async (req, res, next) => {
  try {
    if (!req.file) return res.status(400).json({ error: 'no_file' });

    const head = fs.readFileSync(req.file.path).subarray(0, 8);
    const real = sniffMagic(head);
    if (!real || real !== req.file.mimetype) {
      fs.unlink(req.file.path, () => {});
      audit('upload.mime_mismatch', { sub: req.user.id, declared: req.file.mimetype });
      return res.status(400).json({ error: 'invalid_file' });
    }

    const cleanName = path.basename(req.file.originalname).replace(/[^A-Za-z0-9._ -]/g, '_').slice(0, 120);
    const doc = await prisma.candidateDocument.create({
      data: {
        ownerId:    req.user.id,
        category:   req.body.category,
        filename:   cleanName,
        storageKey: path.basename(req.file.path),
        mimeType:   req.file.mimetype,
        sizeBytes:  req.file.size,
      },
    });
    audit('upload.ok', { sub: req.user.id, docId: doc.id, category: doc.category });
    res.json({ document: { id: doc.id, filename: doc.filename, category: doc.category, sizeBytes: doc.sizeBytes } });
  } catch (e) { next(e); }
});

const idSchema = z.object({ id: z.string().uuid() });

router.get('/:id', requireAuth, validate(idSchema, 'params'), async (req, res) => {
  const doc = await prisma.candidateDocument.findUnique({ where: { id: req.params.id } });
  if (!doc) return res.status(404).json({ error: 'not_found' });
  if (!assertOwner(doc.ownerId, req)) return res.status(404).json({ error: 'not_found' });

  const filePath = path.join(config.uploads.dir, doc.storageKey);
  if (path.dirname(filePath) !== path.resolve(config.uploads.dir)) {
    return res.status(400).json({ error: 'bad_path' });
  }
  res.setHeader('Content-Type', doc.mimeType);
  res.setHeader('Content-Disposition', `attachment; filename="${doc.filename.replace(/"/g, '')}"`);
  fs.createReadStream(filePath).pipe(res);
});

router.delete('/:id', requireAuth, requireVerified, validate(idSchema, 'params'), async (req, res) => {
  const doc = await prisma.candidateDocument.findUnique({ where: { id: req.params.id } });
  if (!doc) return res.status(404).json({ error: 'not_found' });
  if (!assertOwner(doc.ownerId, req)) return res.status(404).json({ error: 'not_found' });

  fs.unlink(path.join(config.uploads.dir, doc.storageKey), () => {});
  await prisma.candidateDocument.delete({ where: { id: doc.id } });
  audit('upload.deleted', { sub: req.user.id, docId: doc.id });
  res.json({ ok: true });
});

export default router;
