import pino from 'pino';
import fs from 'node:fs';
import path from 'node:path';
import { config } from '../config.js';

const logsDir = '/app/logs';
try { fs.mkdirSync(logsDir, { recursive: true }); } catch {}

const streams = [
  { stream: process.stdout },
  { stream: fs.createWriteStream(path.join(logsDir, `${config.appName}.log`), { flags: 'a' }) },
];

export const logger = pino(
  {
    level: config.logLevel,
    base: { app: config.appName, env: config.env },
    timestamp: pino.stdTimeFunctions.isoTime,
    redact: {
      paths: [
        'req.headers.authorization',
        'req.headers.cookie',
        'req.body.password',
        'req.body.passwordConfirm',
        '*.password',
        '*.passwordHash',
        '*.tokenHash',
        '*.token',
      ],
      remove: true,
    },
  },
  pino.multistream(streams),
);

export function audit(event, fields = {}) {
  logger.info({ kind: 'audit', event, ...fields });
}
