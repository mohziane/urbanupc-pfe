import fs from 'node:fs';

// Read secret from file (Docker secrets) or env var, in that priority.
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
  appName: process.env.APP_NAME || 'maformation',
  logLevel: process.env.LOG_LEVEL || 'info',

  jwtSecret: readSecret('JWT_SECRET_FILE', 'JWT_SECRET'),
  sessionSecret: readSecret('SESSION_SECRET_FILE', 'SESSION_SECRET'),

  ldap: {
    url: process.env.LDAP_URL || 'ldap://10.0.2.10:389',
    bindDN: process.env.LDAP_BIND_DN || 'CN=svc_ldap,CN=Users,DC=corpnet,DC=local',
    bindPassword: readSecret('LDAP_BIND_PW_FILE', 'LDAP_BIND_PW'),
    searchBase: process.env.LDAP_SEARCH_BASE || 'OU=SOC-Users,DC=corpnet,DC=local',
  },

  uploads: {
    dir: '/app/uploads',
    maxBytes: 5 * 1024 * 1024, // 5 MB
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
};
