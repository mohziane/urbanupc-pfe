import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { prisma } from '../lib/db.js';

const router = Router();

// Student sees only their own grades — query is scoped by user ID, not by URL.
router.get('/', requireAuth, async (req, res) => {
  const grades = await prisma.grade.findMany({
    where: { userId: req.user.id },
    include: { course: { select: { code: true, title: true, ects: true } } },
    orderBy: { postedAt: 'desc' },
  });
  res.json({ grades });
});

export default router;
