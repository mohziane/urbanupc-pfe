import { Router } from 'express';
import { requireAuth } from '../middleware/auth.js';
import { prisma } from '../lib/db.js';

const router = Router();

// Weekly schedule = slots for courses the user is enrolled in.
router.get('/', requireAuth, async (req, res) => {
  const enrolls = await prisma.enrollment.findMany({
    where: { userId: req.user.id },
    select: { courseId: true },
  });
  const ids = enrolls.map((e) => e.courseId);
  if (ids.length === 0) return res.json({ slots: [] });

  const slots = await prisma.scheduleSlot.findMany({
    where: { courseId: { in: ids } },
    include: { course: { select: { code: true, title: true } } },
    orderBy: [{ dayOfWeek: 'asc' }, { startTime: 'asc' }],
  });
  res.json({ slots });
});

export default router;
