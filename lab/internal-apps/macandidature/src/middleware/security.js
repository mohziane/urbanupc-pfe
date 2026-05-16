import helmet from 'helmet';
import { doubleCsrf } from 'csrf-csrf';
import { config } from '../config.js';

export const helmetMw = helmet({
  contentSecurityPolicy: {
    useDefaults: true,
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc:  ["'self'"],
      styleSrc:   ["'self'", "'unsafe-inline'"],
      imgSrc:     ["'self'", 'data:'],
      connectSrc: ["'self'"],
      objectSrc:  ["'none'"],
      frameAncestors: ["'none'"],
      formAction: ["'self'"],
      baseUri:    ["'self'"],
      upgradeInsecureRequests: [],
    },
  },
  crossOriginEmbedderPolicy: false,
  referrerPolicy: { policy: 'strict-origin-when-cross-origin' },
  hsts: { maxAge: 63072000, includeSubDomains: true, preload: false },
  frameguard: { action: 'deny' },
});

export const csrfProtection = doubleCsrf({
  getSecret: () => config.sessionSecret,
  cookieName: '__Host-csrf',
  cookieOptions: {
    httpOnly: true,
    secure: true,
    sameSite: 'strict',
    path: '/',
  },
  size: 32,
  getTokenFromRequest: (req) => req.headers['x-csrf-token'],
});
