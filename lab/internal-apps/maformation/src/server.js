import './bootstrap.js'; // must be first — injects DATABASE_URL from secret file
import express from 'express';
import cookieParser from 'cookie-parser';
import pinoHttp from 'pino-http';
import { randomUUID } from 'node:crypto';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { config } from './config.js';
import { logger } from './lib/logger.js';
import { helmetMw, csrfProtection } from './middleware/security.js';
import { errorHandler, notFound } from './middleware/error.js';

import authRoutes from './routes/auth.js';
import coursesRoutes from './routes/courses.js';
import scheduleRoutes from './routes/schedule.js';
import documentsRoutes from './routes/documents.js';
import gradesRoutes from './routes/grades.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
app.disable('x-powered-by');
app.set('trust proxy', 1); // behind internal nginx

// Request ID + structured access log
app.use((req, _res, next) => { req.id = req.headers['x-request-id'] || randomUUID(); next(); });
app.use(pinoHttp({ logger, customProps: (req) => ({ requestId: req.id }) }));

app.use(helmetMw);
app.use(express.json({ limit: '256kb' }));
app.use(express.urlencoded({ extended: false, limit: '256kb' }));
app.use(cookieParser(config.sessionSecret));

// CSRF — mounted after cookie-parser. Excluded from /api/auth/login because
// the login form provides the very first credential; the cookie is set by the response.
// All other state-changing routes go through doubleCsrfProtection.
app.get('/api/csrf', (req, res) => {
  const { generateToken } = csrfProtection;
  const token = generateToken(req, res);
  res.json({ csrfToken: token });
});
app.use((req, res, next) => {
  if (req.method === 'GET' || req.method === 'HEAD') return next();
  if (req.path === '/api/auth/login') return next();
  return csrfProtection.doubleCsrfProtection(req, res, next);
});

app.get('/healthz', (_req, res) => res.json({ ok: true, app: config.appName }));

app.use('/api/auth', authRoutes);
app.use('/api/courses', coursesRoutes);
app.use('/api/schedule', scheduleRoutes);
app.use('/api/documents', documentsRoutes);
app.use('/api/grades', gradesRoutes);

app.use(express.static(path.join(__dirname, '..', 'public'), {
  index: 'index.html',
  setHeaders: (res) => res.setHeader('Cache-Control', 'no-cache'),
}));

app.use(notFound);
app.use(errorHandler);

const server = app.listen(config.port, () => {
  logger.info({ port: config.port, env: config.env }, `${config.appName} listening`);
});

function shutdown(sig) {
  logger.info({ sig }, 'shutting down');
  server.close(() => process.exit(0));
  setTimeout(() => process.exit(1), 10_000).unref();
}
process.on('SIGINT', () => shutdown('SIGINT'));
process.on('SIGTERM', () => shutdown('SIGTERM'));
