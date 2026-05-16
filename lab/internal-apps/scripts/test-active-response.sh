#!/usr/bin/env bash
# ============================================================
# Test Active Response — SSH brute force scenario
# ============================================================
#
# Scénario : depuis une machine d'attaque (mac local ou un cloud
# externe), lancer 10 tentatives SSH avec un mauvais mot de passe
# sur vm-web01, puis vérifier :
#   1. Wazuh manager détecte la brute force (rule 5712)
#   2. Active Response déclenche firewall-drop sur vm-web01
#   3. Les connexions suivantes vers SSH sont droppées
#
# Mesure : t0 = première tentative
#          t1 = 8e tentative (seuil de rule 5712)
#          t2 = première connexion droppée (timeout TCP)
#          détection = t1 - t0
#          blocage   = t2 - t1
#
# Pré-requis :
#   - SSH ouvert sur vm-web01 (port 22 dans NSG nsg-dmz)
#   - Wazuh manager démarré sur vm-siem01 avec active-response.xml chargé
#   - Pas d'utilisateur valide nommé 'invalid_user_test' sur web01
#
# Usage :
#   ./test-active-response.sh <web01-public-ip>

set -euo pipefail

WEB01_IP="${1:?Usage: $0 <web01-public-ip>}"
ATTEMPTS=10
TIMEOUT_SECS=5

echo "[+] Cible : $WEB01_IP"
echo "[+] $ATTEMPTS tentatives SSH avec credentials invalides"
echo

t0=$(date +%s.%N)

for i in $(seq 1 $ATTEMPTS); do
  ts=$(date +"%H:%M:%S.%3N")
  # sshpass évite le prompt de mot de passe ; -o BatchMode pour échouer vite
  if timeout $TIMEOUT_SECS sshpass -p 'wrongpassword123' \
       ssh -o StrictHostKeyChecking=no \
           -o UserKnownHostsFile=/dev/null \
           -o ConnectTimeout=3 \
           -o PreferredAuthentications=password \
           -o PubkeyAuthentication=no \
           "invalid_user_test@$WEB01_IP" \
           "exit" 2>&1 | tail -1; then
    echo "  [$i] $ts - inattendu : SSH a réussi"
  else
    rc=$?
    if [ $rc -eq 124 ]; then
      echo "  [$i] $ts - TIMEOUT (probablement bloqué par firewall-drop)"
      t2=$(date +%s.%N)
      echo
      echo "[!] Blocage actif détecté à la tentative $i"
      echo "[!] t_total = $(echo "$t2 - $t0" | bc)s"
      exit 0
    else
      echo "  [$i] $ts - échec attendu (rc=$rc)"
    fi
  fi

  if [ $i -eq 8 ]; then
    t1=$(date +%s.%N)
    echo "  ----- seuil rule 5712 atteint à $(echo "$t1 - $t0" | bc)s -----"
  fi

  sleep 0.5
done

echo
echo "[!] Aucun blocage observé après $ATTEMPTS tentatives."
echo "[!] Vérifier sur vm-web01 : sudo iptables -L INPUT -n | grep DROP"
echo "[!] Vérifier sur vm-siem01 : sudo tail /var/ossec/logs/active-responses.log"
