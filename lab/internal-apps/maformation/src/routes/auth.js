import { Router } from 'express';
import rateLimit from 'express-rate-limit';
import jwt from 'jsonwebtoken';
import { z } from 'zod';
import { config } from '../config.js';
import { validate } from '../middleware/validate.js';
import { requireAuth } from '../middleware/auth.js';
import { ldapAuthenticate } from '../lib/ldap.js';
import { prisma } from '../lib/db.js';
import { audit } from '../lib/logger.js';

const router = Router();

const loginLimiter = rateLimit({
  windowMs: 5 * 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  handler: (req, res) => {
    audit('auth.rate_limited', { ip: req.ip });
    res.status(429).json({ error: 'too_many_requests' });
  },
});

const loginSchema = z.object({
  samAccount: z.string().min(2).max(64).regex(/^[A-Za-z0-9._-]+$/),
  password:   z.string().min(1).max(128),
});

router.post('/login', loginLimiter, validate(loginSchema), async (req, res) => {
  const { samAccount, password } = req.body;
  const ldapUser = await ldapAuthenticate(samAccount, password);

  if (!ldapUser) {
    audit('auth.login_failed', { samAccount, ip: req.ip });
    // Constant-time-ish: same shape & timing regardless
    return res.status(401).json({ error: 'invalid_credentials' });
  }

  // Mirror LDAP user into local DB (upsert).
  const user = await prisma.user.upsert({
    where:  { samAccount: ldapUser.samAccount },
    update: { displayName: ldapUser.displayName, email: ldapUser.email, role: ldapUser.role, lastLoginAt: new Date() },
    create: { samAccount: ldapUser.samAccount, displayName: ldapUser.displayName, email: ldapUser.email, role: ldapUser.role, lastLoginAt: new Date() },
  });

  const token = jwt.sign(
    { sub: user.id, sam: user.samAccount, name: user.displayName, role: user.role },
    config.jwtSecret,
    { algorithm: 'HS256', expiresIn: config.jwtTtl },
  );

  res.cookie('session', token, config.cookieOptions);
  audit('auth.login_ok', { sub: user.id, samAccount: user.samAccount, ip: req.ip });
  res.json({ user: { id: user.id, displayName: user.displayName, role: user.role } });
});

router.post('/logout', (req, res) => {
  if (req.user) audit('auth.logout', { sub: req.user.id });
  res.clearCookie('session', { ...config.cookieOptions, maxAge: 0 });
  res.json({ ok: true });
});

router.get('/me', requireAuth, (req, res) => {
  res.json({ user: req.user });
});

export default router;
