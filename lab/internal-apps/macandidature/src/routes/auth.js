import { Router } from 'express';
import rateLimit from 'express-rate-limit';
import argon2 from 'argon2';
import jwt from 'jsonwebtoken';
import crypto from 'node:crypto';
import { z } from 'zod';

import { config } from '../config.js';
import { prisma } from '../lib/db.js';
import { sendMail } from '../lib/mail.js';
import { validate } from '../middleware/validate.js';
import { requireAuth } from '../middleware/auth.js';
import { audit, logger } from '../lib/logger.js';

const router = Router();

const loginLimiter = rateLimit({
  windowMs: 5 * 60 * 1000,
  max: 10,
  standardHeaders: true,
  legacyHeaders: false,
  handler: (req, res) => { audit('auth.rate_limited', { ip: req.ip }); res.status(429).json({ error: 'too_many_requests' }); },
});

const signupLimiter = rateLimit({
  windowMs: 60 * 60 * 1000,
  max: 5,
  standardHeaders: true,
  legacyHeaders: false,
});

const emailSchema = z.string().email().toLowerCase().max(254);

const signupSchema = z.object({
  email:     emailSchema,
  password:  z.string().min(12).max(128),
  firstName: z.string().min(1).max(60),
  lastName:  z.string().min(1).max(60),
});

const loginSchema = z.object({
  email:    emailSchema,
  password: z.string().min(1).max(128),
});

const verifySchema = z.object({
  token: z.string().regex(/^[a-f0-9]{64}$/),
});

function hashToken(t) {
  return crypto.createHash('sha256').update(t).digest('hex');
}

function newToken() {
  return crypto.randomBytes(32).toString('hex');
}

function signSession(c) {
  return jwt.sign(
    { sub: c.id, email: c.email, role: c.role, verified: c.emailVerified },
    config.jwtSecret,
    { algorithm: 'HS256', expiresIn: config.jwtTtl },
  );
}

router.post('/signup', signupLimiter, validate(signupSchema), async (req, res, next) => {
  try {
    const { email, password, firstName, lastName } = req.body;
    const existing = await prisma.candidate.findUnique({ where: { email } });
    // Always respond the same shape — do not leak account existence.
    if (existing) {
      audit('auth.signup_collision', { email, ip: req.ip });
      return res.status(202).json({ status: 'verification_email_sent' });
    }
    const passwordHash = await argon2.hash(password, config.argon2);
    const candidate = await prisma.candidate.create({
      data: { email, passwordHash, firstName, lastName },
    });

    const raw = newToken();
    await prisma.emailVerification.create({
      data: {
        candidateId: candidate.id,
        tokenHash: hashToken(raw),
        expiresAt: new Date(Date.now() + config.emailTokenTtlMs),
      },
    });
    // Email body — the link points at the public CorpNet URL once integrated.
    await sendMail({
      to: email,
      subject: 'Vérifiez votre adresse email — MaCandidature',
      body: `Bienvenue ${firstName}. Pour activer votre compte, cliquez ici : /candidat/verify?token=${raw}`,
    });
    audit('auth.signup_ok', { sub: candidate.id, email });
    res.status(202).json({ status: 'verification_email_sent' });
  } catch (e) { next(e); }
});

router.post('/verify', validate(verifySchema), async (req, res, next) => {
  try {
    const { token } = req.body;
    const tokenHash = hashToken(token);
    const v = await prisma.emailVerification.findFirst({
      where: { tokenHash, usedAt: null, expiresAt: { gt: new Date() } },
    });
    if (!v) {
      audit('auth.verify_invalid', { ip: req.ip });
      return res.status(400).json({ error: 'invalid_or_expired' });
    }
    await prisma.$transaction([
      prisma.emailVerification.update({ where: { id: v.id }, data: { usedAt: new Date() } }),
      prisma.candidate.update({ where: { id: v.candidateId }, data: { emailVerified: true } }),
    ]);
    audit('auth.email_verified', { sub: v.candidateId });
    res.json({ ok: true });
  } catch (e) { next(e); }
});

router.post('/login', loginLimiter, validate(loginSchema), async (req, res, next) => {
  try {
    const { email, password } = req.body;
    const c = await prisma.candidate.findUnique({ where: { email } });

    // Always do the work to avoid user-enumeration timing oracle.
    const fakeHash = '$argon2id$v=19$m=19456,t=2,p=1$ZmFrZWZha2VmYWtl$ZmFrZWZha2VmYWtlZmFrZWZha2VmYWtlZmFrZWZha2VmYWtl';
    if (!c) {
      await argon2.verify(fakeHash, password).catch(() => false);
      audit('auth.login_failed', { email, ip: req.ip, reason: 'no_account' });
      return res.status(401).json({ error: 'invalid_credentials' });
    }

    if (c.lockedUntil && c.lockedUntil > new Date()) {
      audit('auth.login_locked', { sub: c.id, ip: req.ip });
      return res.status(423).json({ error: 'account_locked' });
    }

    const ok = await argon2.verify(c.passwordHash, password).catch(() => false);
    if (!ok) {
      const fails = c.failedLogins + 1;
      const lock = fails >= config.lockout.maxFails;
      await prisma.candidate.update({
        where: { id: c.id },
        data: {
          failedLogins: lock ? 0 : fails,
          lockedUntil:  lock ? new Date(Date.now() + config.lockout.windowMs) : null,
        },
      });
      audit('auth.login_failed', { sub: c.id, ip: req.ip, fails, lock });
      return res.status(401).json({ error: 'invalid_credentials' });
    }

    await prisma.candidate.update({
      where: { id: c.id },
      data: { failedLogins: 0, lockedUntil: null, lastLoginAt: new Date() },
    });

    res.cookie('session', signSession(c), config.cookieOptions);
    audit('auth.login_ok', { sub: c.id, email, ip: req.ip });
    res.json({ user: { id: c.id, email: c.email, role: c.role, verified: c.emailVerified } });
  } catch (e) { next(e); }
});

router.post('/logout', (req, res) => {
  res.clearCookie('session', { ...config.cookieOptions, maxAge: 0 });
  res.json({ ok: true });
});

router.get('/me', requireAuth, async (req, res) => {
  const c = await prisma.candidate.findUnique({
    where: { id: req.user.id },
    select: { id: true, email: true, role: true, emailVerified: true, firstName: true, lastName: true },
  });
  res.json({ user: c });
});

export default router;
