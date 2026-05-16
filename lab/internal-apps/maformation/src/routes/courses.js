import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { prisma } from '../lib/db.js';

const router = Router();

// List the courses the authenticated user is enrolled in.
router.get('/', requireAuth, async (req, res) => {
  const rows = await prisma.enrollment.findMany({
    where: { userId: req.user.id },
    include: { course: true },
    orderBy: { course: { code: 'asc' } },
  });
  res.json({ courses: rows.map((r) => r.course) });
});

// Single course detail — only if the user is enrolled (no IDOR by description ID).
router.get('/:courseId', requireAuth, async (req, res) => {
  const en = await prisma.enrollment.findUnique({
    where: { userId_courseId: { userId: req.user.id, courseId: req.params.courseId } },
    include: { course: true },
  });
  if (!en) return res.status(404).json({ error: 'not_found' });
  res.json({ course: en.course });
});

export default router;
