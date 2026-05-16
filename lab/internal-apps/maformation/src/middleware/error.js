import { logger } from '../lib/logger.js';

// Last-resort handler — never leak stack traces to clients.
export function errorHandler(err, req, res, _next) {
  const status = err.status || 500;
  logger.error({ err: { message: err.message, stack: err.stack }, url: req.originalUrl }, 'request error');
  res.status(status).json({
    error: status >= 500 ? 'internal_error' : (err.code || 'error'),
    requestId: req.id,
  });
}

export function notFound(_req, res) {
  res.status(404).json({ error: 'not_found' });
}
