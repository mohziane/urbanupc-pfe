import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { audit } from '../lib/logger.js';

export function requireAuth(req, res, next) {
  const token = req.cookies?.session;
  if (!token) return res.status(401).json({ error: 'unauthenticated' });
  try {
    const claims = jwt.verify(token, config.jwtSecret, { algorithms: ['HS256'] });
    req.user = {
      id:        claims.sub,
      email:     claims.email,
      role:      claims.role,
      verified:  claims.verified,
    };
    return next();
  } catch (e) {
    audit('auth.token_invalid', { reason: e.message, ip: req.ip });
    return res.status(401).json({ error: 'invalid_token' });
  }
}

export function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user) return res.status(401).json({ error: 'unauthenticated' });
    if (!roles.includes(req.user.role)) {
      audit('authz.denied', { sub: req.user.id, role: req.user.role, need: roles });
      return res.status(403).json({ error: 'forbidden' });
    }
    return next();
  };
}

export function requireVerified(req, res, next) {
  if (!req.user.verified) return res.status(403).json({ error: 'email_unverified' });
  next();
}

export function assertOwner(resourceOwnerId, req) {
  if (req.user.role === 'ADMIN') return true;
  if (resourceOwnerId === req.user.id) return true;
  audit('authz.idor_attempt', { sub: req.user.id, target: resourceOwnerId, url: req.originalUrl });
  return false;
}
