import jwt from 'jsonwebtoken';
import { config } from '../config.js';
import { audit } from '../lib/logger.js';

// Verify the auth JWT carried in the HttpOnly cookie.
export function requireAuth(req, res, next) {
  const token = req.cookies?.session;
  if (!token) return res.status(401).json({ error: 'unauthenticated' });

  try {
    const claims = jwt.verify(token, config.jwtSecret, { algorithms: ['HS256'] });
    req.user = {
      id:          claims.sub,
      samAccount:  claims.sam,
      displayName: claims.name,
      role:        claims.role,
    };
    return next();
  } catch (e) {
    audit('auth.token_invalid', { reason: e.message, ip: req.ip });
    return res.status(401).json({ error: 'invalid_token' });
  }
}

// RBAC — require any of the listed roles.
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

// Ownership guard helper — to be called inside route handlers
// after fetching the resource. NEVER trust the URL params alone.
export function assertOwner(resourceOwnerId, req) {
  if (req.user.role === 'ADMIN') return true;
  if (resourceOwnerId === req.user.id) return true;
  audit('authz.idor_attempt', { sub: req.user.id, target: resourceOwnerId, url: req.originalUrl });
  return false;
}
