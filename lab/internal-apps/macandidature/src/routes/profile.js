import { Router } from 'express';
import { z } from 'zod';
import { prisma } from '../lib/db.js';
import { requireAuth, requireVerified } from '../middleware/auth.js';
import { validate } from '../middleware/validate.js';
import { audit } from '../lib/logger.js';

const router = Router();

router.get('/', requireAuth, async (req, res) => {
  const c = await prisma.candidate.findUnique({
    where: { id: req.user.id },
    select: { id: true, email: true, firstName: true, lastName: true, dateOfBirth: true, nationality: true, phone: true, emailVerified: true },
  });
  res.json({ profile: c });
});

const updateSchema = z.object({
  firstName:   z.string().min(1).max(60).optional(),
  lastName:    z.string().min(1).max(60).optional(),
  dateOfBirth: z.string().datetime().optional(),
  nationality: z.string().min(2).max(60).optional(),
  phone:       z.string().regex(/^\+?[0-9 .-]{6,20}$/).optional(),
});

router.put('/', requireAuth, requireVerified, validate(updateSchema), async (req, res) => {
  const data = { ...req.body };
  if (data.dateOfBirth) data.dateOfBirth = new Date(data.dateOfBirth);
  const c = await prisma.candidate.update({
    where: { id: req.user.id },
    data,
    select: { id: true, firstName: true, lastName: true, dateOfBirth: true, nationality: true, phone: true },
  });
  audit('profile.updated', { sub: req.user.id, fields: Object.keys(data) });
  res.json({ profile: c });
});

export default router;
