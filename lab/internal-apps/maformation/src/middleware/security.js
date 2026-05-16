import helmet from 'helmet';
import { doubleCsrf } from 'csrf-csrf';
import { config } from '../config.js';

// helmet with strict CSP — no inline scripts, only self.
export const helmetMw = helmet({
  contentSecurityPolicy: {
    useDefaults: true,
    directives: {
      defaultSrc: ["'self'"],
      scriptSrc:  ["'self'"],
      styleSrc:   ["'self'", "'unsafe-inline'"], // form styles only
      imgSrc:     ["'self'", 'data:'],
      connectSrc: ["'self'"],
      objectSrc:  ["'none'"],
      frameAncestors: ["'none'"],
      formAction: ["'self'"],
      baseUri:    ["'self'"],
      upgradeInsecureRequests: [],
    },
  },
  crossOriginEmbedderPolicy: false, // ease behind reverse proxy
  referrerPolicy: { policy: 'strict-origin-when-cross-origin' },
  hsts: { maxAge: 63072000, includeSubDomains: true, preload: false },
  frameguard: { action: 'deny' },
});

// CSRF — double-submit cookie. Use the same JWT cookie name pattern but a
// dedicated csrf cookie. All state-changing routes require X-CSRF-Token.
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
