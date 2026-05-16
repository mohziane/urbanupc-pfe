#!/usr/bin/env bash
set -euo pipefail

# Generate cryptographically strong secrets for docker-compose.
# Each secret is a 64-byte base64-encoded random value.

SECRETS_DIR="$(dirname "$0")/../secrets"
mkdir -p "$SECRETS_DIR"
cd "$SECRETS_DIR"

gen_secret() {
  local name="$1"
  local fmt="${2:-base64}"
  if [[ -f "$name.txt" ]]; then
    echo "[gen-secrets] $name already exists, skipping."
    return
  fi
  case "$fmt" in
    hex)    openssl rand -hex 32    | tr -d '\n' > "$name.txt" ;;
    base64) openssl rand -base64 48 | tr -d '\n' > "$name.txt" ;;
  esac
  chmod 644 "$name.txt"  # readable by non-root app user inside container (compose v2 limitation)
  echo "[gen-secrets] Generated $name ($fmt)"
}

gen_secret "jwt_secret"          base64
gen_secret "session_secret"      base64
# DB passwords MUST be hex to avoid URL-encoding edge cases in Prisma's connection string.
gen_secret "maformation_db_pw"   hex
gen_secret "macandidature_db_pw" hex

# LDAP bind password — must match what was set on dc01 for svc_ldap
# If you haven't created svc_ldap yet, run scripts/setup-ldap-bind-user.ps1 first.
if [[ ! -f "ldap_bind_pw.txt" ]]; then
  echo "[gen-secrets] ⚠️  ldap_bind_pw.txt missing — create svc_ldap user on dc01 and write password here:"
  echo "[gen-secrets]    echo -n 'YourLDAPBindPassword' > $SECRETS_DIR/ldap_bind_pw.txt"
fi

echo "[gen-secrets] Done. Secrets in $SECRETS_DIR"
ls -la "$SECRETS_DIR"
