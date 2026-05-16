#!/usr/bin/env bash
set -euo pipefail

# Generate self-signed CA + per-vhost certs for internal apps.
# CA pinned in CorpNet backend trust store for outbound HTTPS to internal apps.

CERTS_DIR="$(dirname "$0")/../nginx/certs"
mkdir -p "$CERTS_DIR"
cd "$CERTS_DIR"

if [[ -f ca.key ]]; then
  echo "[gen-certs] CA already exists, skipping CA generation."
else
  echo "[gen-certs] Creating internal CA..."
  openssl genrsa -out ca.key 4096
  openssl req -x509 -new -nodes -key ca.key -sha256 -days 3650 \
    -subj "/C=FR/ST=IDF/O=UPC-CorpNet/CN=CorpNet Internal CA" \
    -out ca.crt
fi

gen_cert() {
  local name="$1"
  local cn="$2"
  echo "[gen-certs] Generating $name ($cn)..."
  openssl genrsa -out "$name.key" 2048
  openssl req -new -key "$name.key" \
    -subj "/C=FR/ST=IDF/O=UPC-CorpNet/CN=$cn" \
    -out "$name.csr"
  cat > "$name.ext" <<EOF
authorityKeyIdentifier=keyid,issuer
basicConstraints=CA:FALSE
keyUsage = digitalSignature, keyEncipherment
extendedKeyUsage = serverAuth
subjectAltName = @alt_names
[alt_names]
DNS.1 = $cn
DNS.2 = $name
DNS.3 = ${name}_app
IP.1 = 10.0.2.20
EOF
  openssl x509 -req -in "$name.csr" \
    -CA ca.crt -CAkey ca.key -CAcreateserial \
    -out "$name.crt" -days 825 -sha256 -extfile "$name.ext"
  rm "$name.csr" "$name.ext"
}

gen_cert "maformation"   "maformation.corpnet.local"
gen_cert "macandidature" "macandidature.corpnet.local"

chmod 600 *.key
echo "[gen-certs] Done. Certs in $CERTS_DIR"
ls -la "$CERTS_DIR"
