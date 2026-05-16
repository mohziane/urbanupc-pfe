#!/usr/bin/env bash
# =============================================================
#  vm-siem01 — Provisioning : Wazuh All-in-One (Manager + Indexer + Dashboard)
#
#  Usage :
#    ssh azureuser@<IP>
#    sudo bash vm-siem01-setup.sh
#
#  Requis : Ubuntu 22.04, min 4GB RAM (B2s_v2 = 4GB OK)
#  Ports : 443 (dashboard), 1514/1515 (agent), 55000 (API)
# =============================================================
set -euo pipefail

echo "============================================="
echo "  vm-siem01 — Provisioning Wazuh SIEM"
echo "============================================="

# ── 1. Mise a jour systeme ───────────────────────────────────
echo ""
echo "[1/4] Mise a jour du systeme..."
apt-get update -qq
apt-get upgrade -y -qq

# ── 2. Installation Wazuh all-in-one ─────────────────────────
echo "[2/4] Installation de Wazuh (all-in-one)..."
if [ ! -f /usr/share/wazuh-dashboard/bin/opensearch-dashboards ]; then
    cd /tmp

    # Telecharger le script d'installation officiel
    curl -sO https://packages.wazuh.com/4.9/wazuh-install.sh
    curl -sO https://packages.wazuh.com/4.9/config.yml

    # Generer la config minimale pour un deploiement single-node
    cat > /tmp/config.yml <<'CONF'
nodes:
  indexer:
    - name: node-1
      ip: "127.0.0.1"
  server:
    - name: wazuh-1
      ip: "127.0.0.1"
  dashboard:
    - name: dashboard
      ip: "0.0.0.0"
CONF

    # Lancer l'installation all-in-one
    # -a = all-in-one (indexer + manager + dashboard sur une seule machine)
    bash wazuh-install.sh -a

    echo ""
    echo "  Wazuh installe avec succes."
else
    echo "  Wazuh deja installe, skip."
fi

# ── 3. Extraction des credentials ────────────────────────────
echo ""
echo "[3/4] Extraction des credentials du dashboard..."
CREDS_FILE="/tmp/wazuh-install-files/wazuh-passwords.txt"
if [ -f "$CREDS_FILE" ]; then
    echo ""
    echo "  ┌─────────────────────────────────────────┐"
    echo "  │  CREDENTIALS WAZUH DASHBOARD             │"
    echo "  ├─────────────────────────────────────────┤"
    grep -E "username|password" "$CREDS_FILE" | head -4 | while read -r line; do
        echo "  │  $line"
    done
    echo "  └─────────────────────────────────────────┘"
    echo ""
    echo "  IMPORTANT : Note ces credentials ! Le fichier sera supprime."
else
    # Alternative : extraire via l'outil Wazuh
    echo "  Fichier de credentials non trouve."
    echo "  Essaie : sudo tar -O -xvf /tmp/wazuh-install-files.tar wazuh-install-files/wazuh-passwords.txt"
fi

# ── 4. Verification des services ─────────────────────────────
echo ""
echo "[4/4] Verification des services Wazuh..."

echo ""
echo "  Services :"
for svc in wazuh-manager wazuh-indexer wazuh-dashboard; do
    status=$(systemctl is-active "$svc" 2>/dev/null || echo "non installe")
    printf "    %-20s %s\n" "$svc" "$status"
done

echo ""
echo "  Ports en ecoute :"
ss -tlnp | grep -E ':(443|1514|1515|55000|9200)\s' | awk '{printf "    %s\n", $4}'

# Test du dashboard
echo ""
if curl -sk https://127.0.0.1:443 -o /dev/null -w "%{http_code}" 2>/dev/null | grep -q "200\|302"; then
    echo "  Dashboard Wazuh : OK (https://127.0.0.1:443)"
else
    echo "  Dashboard Wazuh : en demarrage (attendre 1-2 min)..."
fi

# ── Recapitulatif ────────────────────────────────────────────
LOCAL_IP=$(hostname -I | awk '{print $1}')
echo ""
echo "============================================="
echo "  vm-siem01 — Provisioning termine !"
echo "============================================="
echo ""
echo "  Dashboard :  https://${LOCAL_IP}:443"
echo "  API :        https://${LOCAL_IP}:55000"
echo "  Agent port : ${LOCAL_IP}:1514 (enrollment: 1515)"
echo ""
echo "  Prochaines etapes :"
echo "    1. Connecte-toi au dashboard depuis ton Mac :"
echo "       https://20.91.206.70:443"
echo "    2. Deploie les agents sur vm-web01 et vm-dc01"
echo "    3. Verifie que les agents remontent dans le dashboard"
echo ""
