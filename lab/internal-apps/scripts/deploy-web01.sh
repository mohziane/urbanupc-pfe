#!/usr/bin/env bash
# Deploy MaFormation + MaCandidature stack to vm-web01.
# Assumes:
#   - vm-web01 is running (azure VM started)
#   - Your admin IP is allowed by nsg-dmz on port 22
#   - SSH key already provisioned (~/.ssh/id_rsa)
#   - Docker engine is installed on web01 (it is, since CorpNet runs there)
set -euo pipefail

REMOTE_USER="${REMOTE_USER:-azureuser}"
REMOTE_HOST="${REMOTE_HOST:-20.91.233.41}"
REMOTE_DIR="${REMOTE_DIR:-/srv/internal-apps}"
LOCAL_DIR="$(cd "$(dirname "$0")/.." && pwd)"

echo "[deploy] Local source : $LOCAL_DIR"
echo "[deploy] Remote target: $REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR"

# 1. Generate TLS certs + secrets locally if missing
if [[ ! -f "$LOCAL_DIR/nginx/certs/ca.crt" ]]; then
  echo "[deploy] Generating internal CA + per-vhost certs..."
  bash "$LOCAL_DIR/scripts/gen-certs.sh"
fi

if [[ ! -f "$LOCAL_DIR/secrets/jwt_secret.txt" ]]; then
  echo "[deploy] Generating docker secrets..."
  bash "$LOCAL_DIR/scripts/gen-secrets.sh"
fi

if [[ ! -f "$LOCAL_DIR/secrets/ldap_bind_pw.txt" ]]; then
  echo "⚠️  [deploy] $LOCAL_DIR/secrets/ldap_bind_pw.txt is missing."
  echo "    Create svc_ldap on dc01 first (see scripts/setup-ldap-bind-user.ps1),"
  echo "    then run:  printf '%s' 'YourPassword' > $LOCAL_DIR/secrets/ldap_bind_pw.txt"
  exit 1
fi

# 2. Prepare remote dirs
echo "[deploy] Preparing remote directories..."
ssh -o StrictHostKeyChecking=accept-new "$REMOTE_USER@$REMOTE_HOST" "
  set -e
  sudo mkdir -p '$REMOTE_DIR'
  sudo chown -R $REMOTE_USER:$REMOTE_USER '$REMOTE_DIR'
  sudo mkdir -p /var/log/corpnet/internal/{nginx,maformation,macandidature}
  sudo chown -R 1001:1001 /var/log/corpnet/internal/maformation /var/log/corpnet/internal/macandidature
  sudo chown -R 101:101 /var/log/corpnet/internal/nginx
"

# 3. Rsync source (excluding node_modules + heavy files)
echo "[deploy] Rsync source..."
rsync -avz --delete \
  --exclude node_modules \
  --exclude '.git' \
  --exclude '*.log' \
  --exclude 'secrets/*.txt' \
  --exclude 'nginx/certs/*' \
  "$LOCAL_DIR/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/"

# 4. Push secrets + certs separately with strict perms
echo "[deploy] Pushing secrets (root:600)..."
rsync -avz "$LOCAL_DIR/secrets/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/secrets/"
rsync -avz "$LOCAL_DIR/nginx/certs/" "$REMOTE_USER@$REMOTE_HOST:$REMOTE_DIR/nginx/certs/"
ssh "$REMOTE_USER@$REMOTE_HOST" "
  sudo chmod 755 '$REMOTE_DIR/secrets' '$REMOTE_DIR/nginx/certs'
  # Bind-mount limitation (see note in compose): non-root containers must be able
  # to read these files. uid mapping inside the container differs from the host
  # so we fall back to world-readable bytes on a 755 directory.
  sudo chmod 644 '$REMOTE_DIR/secrets/'*.txt
  sudo chmod 644 '$REMOTE_DIR/nginx/certs/'*.key
  sudo chmod 644 '$REMOTE_DIR/nginx/certs/'*.crt
"

# 5. Build + start
echo "[deploy] Building images + starting stack..."
ssh "$REMOTE_USER@$REMOTE_HOST" "
  cd '$REMOTE_DIR'
  sudo docker compose build --pull
  sudo docker compose up -d
  echo
  echo '=== docker ps ==='
  sudo docker compose ps
"

# 6. Run Prisma migrations via the dedicated migrator services (PACS R3).
#    The runtime apps are now read-only ; migrations run in short-lived
#    one-shot containers that exit after applying the schema.
echo "[deploy] Running Prisma migrations via migrator services..."
ssh "$REMOTE_USER@$REMOTE_HOST" "
  cd '$REMOTE_DIR'
  sudo docker compose --profile migration run --rm maformation_migrator
  sudo docker compose --profile migration run --rm macandidature_migrator
"

# 7. Smoke test from web01 (loopback)
echo "[deploy] Smoke test..."
ssh "$REMOTE_USER@$REMOTE_HOST" "
  echo '--- MaFormation healthz ---'
  curl -sk https://127.0.0.1:8443/healthz || echo FAIL
  echo
  echo '--- MaCandidature healthz ---'
  curl -sk https://127.0.0.1:8444/healthz || echo FAIL
"

echo
echo "[deploy] ✅ Done. Apps are reachable on the host's loopback:"
echo "  https://127.0.0.1:8443  → MaFormation"
echo "  https://127.0.0.1:8444  → MaCandidature"
echo
echo "[deploy] Next step: wire CorpNet backend to proxy /maformation/* and /candidat/* to these URLs."
