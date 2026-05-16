import './bootstrap.js';
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
import profileRoutes from './routes/profile.js';
import applicationsRoutes from './routes/applications.js';
import documentsRoutes from './routes/documents.js';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

const app = express();
app.disable('x-powered-by');
app.set('trust proxy', 1);

app.use((req, _res, next) => { req.id = req.headers['x-request-id'] || randomUUID(); next(); });
app.use(pinoHttp({ logger, customProps: (req) => ({ requestId: req.id }) }));

app.use(helmetMw);
app.use(express.json({ limit: '256kb' }));
app.use(express.urlencoded({ extended: false, limit: '256kb' }));
app.use(cookieParser(config.sessionSecret));

app.get('/api/csrf', (req, res) => {
  const { generateToken } = csrfProtection;
  res.json({ csrfToken: generateToken(req, res) });
});

app.use((req, res, next) => {
  if (req.method === 'GET' || req.method === 'HEAD') return next();
  // Public POSTs: signup, login, verify — auth state established by the request itself.
  if (['/api/auth/signup', '/api/auth/login', '/api/auth/verify'].includes(req.path)) return next();
  return csrfProtection.doubleCsrfProtection(req, res, next);
});

app.get('/healthz', (_req, res) => res.json({ ok: true, app: config.appName }));

app.use('/api/auth',         authRoutes);
app.use('/api/profile',      profileRoutes);
app.use('/api/applications', applicationsRoutes);
app.use('/api/documents',    documentsRoutes);

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
