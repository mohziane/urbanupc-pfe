import fs from 'node:fs';

function readSecret(envFileKey, envInlineKey) {
  const filePath = process.env[envFileKey];
  if (filePath && fs.existsSync(filePath)) {
    return fs.readFileSync(filePath, 'utf8').trim();
  }
  const inline = process.env[envInlineKey];
  if (inline) return inline;
  throw new Error(`Missing secret: set ${envFileKey} (file) or ${envInlineKey} (env)`);
}

export const config = {
  env: process.env.NODE_ENV || 'development',
  port: Number(process.env.PORT) || 3000,
  appName: process.env.APP_NAME || 'macandidature',
  logLevel: process.env.LOG_LEVEL || 'info',

  jwtSecret: readSecret('JWT_SECRET_FILE', 'JWT_SECRET'),
  sessionSecret: readSecret('SESSION_SECRET_FILE', 'SESSION_SECRET'),

  uploads: {
    dir: '/app/uploads',
    maxBytes: 5 * 1024 * 1024,
    allowedMime: new Set(['application/pdf', 'image/png', 'image/jpeg']),
  },

  jwtTtl: '30m',
  cookieOptions: {
    httpOnly: true,
    secure: true,
    sameSite: 'strict',
    path: '/',
    maxAge: 30 * 60 * 1000,
  },

  // Argon2id parameters chosen per OWASP password storage cheatsheet (2024).
  argon2: { type: 2 /* argon2id */, memoryCost: 19_456, timeCost: 2, parallelism: 1 },

  // Account lockout to slow online brute force.
  lockout: { maxFails: 5, windowMs: 15 * 60 * 1000 },

  // Email verification token TTL.
  emailTokenTtlMs: 24 * 60 * 60 * 1000,

  // Inbox simulation: emails written to /app/logs/outbox.log (the "tester" reads them there).
  outboxFile: '/app/logs/outbox.log',
};
