import fs from 'node:fs';
import { config } from '../config.js';
import { logger, audit } from './logger.js';

// Outbox-only mailer for the lab — writes JSON envelopes to a file the
// test harness can tail. In production, swap for an SMTP transport.
export async function sendMail({ to, subject, body }) {
  const envelope = {
    ts: new Date().toISOString(),
    to,
    subject,
    body,
  };
  try {
    fs.appendFileSync(config.outboxFile, JSON.stringify(envelope) + '\n');
  } catch (e) {
    logger.error({ err: e.message }, 'outbox write failed');
  }
  audit('mail.sent', { to, subject });
}
