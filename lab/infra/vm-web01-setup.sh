#!/usr/bin/env bash
# =============================================================
#  vm-web01 — Provisioning : Docker + CorpNet Honeypot + Wazuh Agent
#
#  Usage (depuis ton Mac) :
#    scp -r corpnet docker scripts ssl .env docker-compose.yml azureuser@<IP>:~/soc/
#    ssh azureuser@<IP> 'sudo bash ~/soc/infra/vm-web01-setup.sh'
#
#  Ou directement sur la VM :
#    sudo bash vm-web01-setup.sh
# =============================================================
set -euo pipefail

WAZUH_MANAGER_IP="10.0.3.10"
APP_DIR="/home/azureuser/soc"

echo "============================================="
echo "  vm-web01 — Provisioning Honeypot"
echo "============================================="

# ── 1. Mise a jour systeme ───────────────────────────────────
echo ""
echo "[1/5] Mise a jour du systeme..."
apt-get update -qq
apt-get upgrade -y -qq

# ── 2. Installation Docker ──────────────────────────────────
echo "[2/5] Installation de Docker..."
if ! command -v docker &>/dev/null; then
    apt-get install -y -qq \
        ca-certificates curl gnupg lsb-release

    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg \
        | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg

    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] \
https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
        > /etc/apt/sources.list.d/docker.list

    apt-get update -qq
    apt-get install -y -qq \
        docker-ce docker-ce-cli containerd.io docker-compose-plugin

    systemctl enable docker
    systemctl start docker
    usermod -aG docker azureuser
    echo "  Docker installe."
else
    echo "  Docker deja installe, skip."
fi

# ── 3. Deploiement CorpNet ───────────────────────────────────
echo "[3/5] Deploiement de l'application CorpNet..."
if [ ! -d "$APP_DIR" ]; then
    echo "  ERREUR: Le dossier $APP_DIR n'existe pas."
    echo "  Copie d'abord les fichiers depuis ton Mac :"
    echo "    scp -r corpnet docker scripts ssl .env docker-compose.yml azureuser@<IP>:~/soc/"
    exit 1
fi

cd "$APP_DIR"

# Build et demarrage
docker compose down 2>/dev/null || true
docker compose up -d --build

echo "  Attente du healthcheck MySQL (max 60s)..."
for i in $(seq 1 12); do
    if docker exec corpnet-db mysqladmin ping -h localhost \
        -u corpnet_app -p'C0rpN3t@2024!' --silent 2>/dev/null; then
        echo "  MySQL OK."
        break
    fi
    sleep 5
done

# Verification
echo ""
echo "  Services Docker :"
docker compose ps --format "table {{.Name}}\t{{.Status}}\t{{.Ports}}"

# ── 4. Installation agent Wazuh ──────────────────────────────
echo ""
echo "[4/5] Installation de l'agent Wazuh..."
if ! dpkg -l | grep -q wazuh-agent; then
    curl -s https://packages.wazuh.com/key/GPG-KEY-WAZUH | gpg --dearmor -o /usr/share/keyrings/wazuh.gpg
    echo "deb [signed-by=/usr/share/keyrings/wazuh.gpg] https://packages.wazuh.com/4.x/apt/ stable main" \
        > /etc/apt/sources.list.d/wazuh.list
    apt-get update -qq

    WAZUH_MANAGER="$WAZUH_MANAGER_IP" apt-get install -y -qq wazuh-agent

    # Config : pointer vers le manager
    sed -i "s|<address>MANAGER_IP</address>|<address>${WAZUH_MANAGER_IP}</address>|g" \
        /var/ossec/etc/ossec.conf 2>/dev/null || true

    systemctl daemon-reload
    systemctl enable wazuh-agent
    systemctl start wazuh-agent
    echo "  Agent Wazuh installe et demarre."
else
    echo "  Agent Wazuh deja installe, skip."
fi

# ── 5. Monitoring des logs Docker dans Wazuh ─────────────────
echo "[5/5] Configuration monitoring des logs applicatifs..."
WAZUH_CONF="/var/ossec/etc/ossec.conf"
if ! grep -q "corpnet" "$WAZUH_CONF" 2>/dev/null; then
    # Ajout des log sources avant la balise </ossec_config>
    sed -i '/<\/ossec_config>/i \
  <!-- CorpNet Honeypot logs -->\
  <localfile>\
    <log_format>json</log_format>\
    <location>/var/log/corpnet/audit.json</location>\
  </localfile>\
  <localfile>\
    <log_format>apache</log_format>\
    <location>/opt/apache2/logs/access_log</location>\
  </localfile>\
  <localfile>\
    <log_format>apache</log_format>\
    <location>/opt/apache2/logs/error_log</location>\
  </localfile>' "$WAZUH_CONF"

    systemctl restart wazuh-agent
    echo "  Logs CorpNet ajoutes a la config Wazuh."
else
    echo "  Logs CorpNet deja configures, skip."
fi

# ── Recapitulatif ────────────────────────────────────────────
echo ""
echo "============================================="
echo "  vm-web01 — Provisioning termine !"
echo "============================================="
echo ""
echo "  Honeypot :      http://$(hostname -I | awk '{print $1}')"
echo "  FQDN :          http://corpnet-pfe.swedencentral.cloudapp.azure.com"
echo "  Apache direct :  http://$(hostname -I | awk '{print $1}'):8888"
echo "  Adminer :        http://$(hostname -I | awk '{print $1}'):8080"
echo "  Wazuh agent :    $(systemctl is-active wazuh-agent 2>/dev/null || echo 'non installe')"
echo ""
