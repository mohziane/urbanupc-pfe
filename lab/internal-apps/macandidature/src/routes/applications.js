import { Router } from 'express';
import { z } from 'zod';
import { prisma } from '../lib/db.js';
import { requireAuth, requireVerified, assertOwner } from '../middleware/auth.js';
import { validate } from '../middleware/validate.js';
import { audit } from '../lib/logger.js';

const router = Router();

const createSchema = z.object({
  programCode:     z.string().min(2).max(30).regex(/^[A-Z0-9-]+$/),
  motivation:      z.string().min(50).max(4000),
  prevDegree:      z.string().min(2).max(200),
  prevInstitution: z.string().min(2).max(200),
});

router.get('/', requireAuth, async (req, res) => {
  const apps = await prisma.application.findMany({
    where: { candidateId: req.user.id },
    orderBy: { updatedAt: 'desc' },
  });
  res.json({ applications: apps });
});

router.post('/', requireAuth, requireVerified, validate(createSchema), async (req, res) => {
  const app = await prisma.application.create({
    data: { candidateId: req.user.id, ...req.body },
  });
  audit('application.created', { sub: req.user.id, appId: app.id });
  res.status(201).json({ application: app });
});

const idSchema = z.object({ id: z.string().uuid() });

router.get('/:id', requireAuth, validate(idSchema, 'params'), async (req, res) => {
  const app = await prisma.application.findUnique({ where: { id: req.params.id } });
  if (!app) return res.status(404).json({ error: 'not_found' });
  if (!assertOwner(app.candidateId, req)) return res.status(404).json({ error: 'not_found' });
  res.json({ application: app });
});

router.put('/:id', requireAuth, requireVerified, validate(idSchema, 'params'), validate(createSchema.partial()), async (req, res) => {
  const app = await prisma.application.findUnique({ where: { id: req.params.id } });
  if (!app) return res.status(404).json({ error: 'not_found' });
  if (!assertOwner(app.candidateId, req)) return res.status(404).json({ error: 'not_found' });
  if (app.status !== 'DRAFT') return res.status(409).json({ error: 'not_editable' });

  const updated = await prisma.application.update({ where: { id: app.id }, data: req.body });
  audit('application.updated', { sub: req.user.id, appId: updated.id });
  res.json({ application: updated });
});

router.post('/:id/submit', requireAuth, requireVerified, validate(idSchema, 'params'), async (req, res) => {
  const app = await prisma.application.findUnique({ where: { id: req.params.id } });
  if (!app) return res.status(404).json({ error: 'not_found' });
  if (!assertOwner(app.candidateId, req)) return res.status(404).json({ error: 'not_found' });
  if (app.status !== 'DRAFT') return res.status(409).json({ error: 'not_in_draft' });

  // Require at least a CV and a letter before submission — enforce by query.
  const required = await prisma.candidateDocument.count({
    where: { ownerId: req.user.id, category: { in: ['CV', 'LETTER'] } },
  });
  if (required < 2) return res.status(412).json({ error: 'documents_missing' });

  const submitted = await prisma.application.update({
    where: { id: app.id },
    data: { status: 'SUBMITTED', submittedAt: new Date() },
  });
  audit('application.submitted', { sub: req.user.id, appId: submitted.id });
  res.json({ application: submitted });
});

export default router;
