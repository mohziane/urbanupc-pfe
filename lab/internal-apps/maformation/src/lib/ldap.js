import ldap from 'ldapjs';
import { config } from '../config.js';
import { logger } from './logger.js';

// LDAP injection defence: escape the sAMAccountName per RFC 4515.
function escapeFilter(input) {
  return String(input).replace(/[\\*()\0]/g, (c) => {
    return {
      '\\': '\\5c',
      '*':  '\\2a',
      '(':  '\\28',
      ')':  '\\29',
      '\0': '\\00',
    }[c];
  });
}

function createClient() {
  return ldap.createClient({
    url: config.ldap.url,
    timeout: 5000,
    connectTimeout: 5000,
    reconnect: false,
  });
}

// Two-step bind: bind as service account → search user → rebind as user.
// Never use raw user input in the bind DN.
export async function ldapAuthenticate(samAccountName, password) {
  if (!samAccountName || !password) return null;
  if (samAccountName.length > 64 || password.length > 128) return null;

  const filter = `(&(objectClass=user)(sAMAccountName=${escapeFilter(samAccountName)}))`;
  const client = createClient();

  try {
    await new Promise((res, rej) => {
      client.bind(config.ldap.bindDN, config.ldap.bindPassword, (e) => (e ? rej(e) : res()));
    });

    const entry = await new Promise((resolve, reject) => {
      client.search(
        config.ldap.searchBase,
        { filter, scope: 'sub', attributes: ['dn', 'sAMAccountName', 'displayName', 'mail', 'memberOf'] },
        (err, sres) => {
          if (err) return reject(err);
          let found = null;
          sres.on('searchEntry', (e) => { found = e.pojo || e.object; });
          sres.on('error', reject);
          sres.on('end', () => resolve(found));
        },
      );
    });

    if (!entry) return null;

    // Rebind as the user to validate the password.
    const userClient = createClient();
    try {
      await new Promise((res, rej) => {
        userClient.bind(entry.objectName || entry.dn, password, (e) => (e ? rej(e) : res()));
      });
    } catch (e) {
      logger.info({ samAccountName }, 'ldap user bind failed');
      return null;
    } finally {
      userClient.unbind(() => {});
    }

    // Map memberOf to a simple role.
    const memberOf = Array.isArray(entry.attributes)
      ? (entry.attributes.find((a) => a.type === 'memberOf')?.values || [])
      : (entry.memberOf || []);
    const groups = (Array.isArray(memberOf) ? memberOf : [memberOf]).map(String);

    let role = 'STUDENT';
    if (groups.some((g) => /Domain Admins|SOC-Admins/i.test(g))) role = 'ADMIN';
    else if (groups.some((g) => /Faculty|Profs?/i.test(g))) role = 'FACULTY';

    const attrs = (k) => {
      if (entry[k]) return Array.isArray(entry[k]) ? entry[k][0] : entry[k];
      const a = Array.isArray(entry.attributes) ? entry.attributes.find((x) => x.type === k) : null;
      return a ? (Array.isArray(a.values) ? a.values[0] : a.values) : undefined;
    };

    return {
      samAccount:  attrs('sAMAccountName') || samAccountName,
      displayName: attrs('displayName') || samAccountName,
      email:       attrs('mail') || null,
      role,
    };
  } catch (e) {
    logger.error({ err: e.message }, 'ldap authenticate error');
    return null;
  } finally {
    client.unbind(() => {});
  }
}
