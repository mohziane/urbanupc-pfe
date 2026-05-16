// Build DATABASE_URL from the secret file BEFORE Prisma reads the env.
// We never want the DB password to live in env directly.
import fs from 'node:fs';

const pwFile = process.env.MAFORMATION_DB_PW_FILE;
const tpl = process.env.DATABASE_URL || '';
if (pwFile && fs.existsSync(pwFile) && tpl.includes('__PW__')) {
  const pw = fs.readFileSync(pwFile, 'utf8').trim();
  process.env.DATABASE_URL = tpl.replace('__PW__', encodeURIComponent(pw));
}
