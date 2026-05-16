#!/usr/bin/env bash
# Wires the new internal-apps logs into Wazuh (agent on web01 + manager on siem01).
# Also installs an auditd watch on the docker socket for container-escape detection.
set -euo pipefail

WEB01="${WEB01:-azureuser@20.91.233.41}"
SIEM01="${SIEM01:-azureuser@20.91.206.70}"
HERE="$(cd "$(dirname "$0")/.." && pwd)"

echo "[wazuh] Pushing files..."
rsync -avz "$HERE/wazuh/" "$WEB01:/tmp/wazuh-internal/"   2>&1 | tail -3
rsync -avz "$HERE/wazuh/" "$SIEM01:/tmp/wazuh-internal/"  2>&1 | tail -3

echo
echo "[wazuh] Installing decoder + rules on siem01..."
ssh "$SIEM01" '
  set -e
  sudo cp /tmp/wazuh-internal/decoders/local_decoder_internal_apps.xml /var/ossec/etc/decoders/
  sudo cp /tmp/wazuh-internal/rules/local_rules_internal_apps.xml      /var/ossec/etc/rules/
  sudo chown wazuh:wazuh /var/ossec/etc/decoders/local_decoder_internal_apps.xml /var/ossec/etc/rules/local_rules_internal_apps.xml
  sudo systemctl restart wazuh-manager
  echo "[siem01] wazuh-manager restarted."
'

echo
echo "[wazuh] Installing localfile entries + auditd rule on web01..."
ssh "$WEB01" '
  set -e

  # 1) Append localfile blocks into ossec.conf (idempotent: check marker first)
  if ! sudo grep -q "maformation.log" /var/ossec/etc/ossec.conf; then
    echo "[web01] appending localfile entries to ossec.conf..."
    sudo cp /var/ossec/etc/ossec.conf /var/ossec/etc/ossec.conf.bak.$(date +%s)
    # Strip the wrapping <ossec_config></ossec_config> and inject just the localfile blocks
    sudo sed -i "s|</ossec_config>|$(sed -n "/<localfile>/,/<\/localfile>/p" /tmp/wazuh-internal/agent-localfile-internal-apps.xml | sed "s|/|\\\/|g" | tr "\n" " ")\n</ossec_config>|" /var/ossec/etc/ossec.conf || true
    # Simpler approach: just inject before the closing tag using awk
    sudo awk -v insert="$(sed -n "/<localfile>/,/<\/localfile>/p" /tmp/wazuh-internal/agent-localfile-internal-apps.xml)" "
      /<\/ossec_config>/ && !done { print insert; done=1 }
      { print }
    " /var/ossec/etc/ossec.conf > /tmp/ossec.conf.new
    sudo mv /tmp/ossec.conf.new /var/ossec/etc/ossec.conf
    sudo chown wazuh:wazuh /var/ossec/etc/ossec.conf
  else
    echo "[web01] ossec.conf already wired for internal-apps."
  fi

  # 2) Auditd rule: watch /var/run/docker.sock for write/exec
  if ! sudo grep -q "docker-sock" /etc/audit/rules.d/audit.rules 2>/dev/null; then
    echo "[web01] adding auditd watch on /var/run/docker.sock..."
    echo "-w /var/run/docker.sock -p rwa -k docker-sock" | sudo tee -a /etc/audit/rules.d/audit.rules >/dev/null
    sudo augenrules --load 2>/dev/null || sudo service auditd reload 2>/dev/null || true
  fi

  # 3) Make sure the log files exist + are readable by the wazuh agent (runs as root usually)
  sudo mkdir -p /var/log/corpnet/internal/{nginx,maformation,macandidature}
  sudo touch /var/log/corpnet/internal/maformation/maformation.log
  sudo touch /var/log/corpnet/internal/macandidature/macandidature.log
  sudo touch /var/log/corpnet/internal/macandidature/outbox.log

  echo "[web01] restarting wazuh-agent..."
  sudo systemctl restart wazuh-agent
  sleep 2
  sudo systemctl is-active wazuh-agent || true
'

echo
echo "[wazuh] ✅ Done. Sanity checks:"
echo "  - On siem01:  tail -f /var/ossec/logs/alerts/alerts.json | grep internal"
echo "  - On web01:   sudo journalctl -u wazuh-agent -n 30"
