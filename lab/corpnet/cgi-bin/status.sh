#!/bin/bash
# Script CGI de statut serveur
# Présent pour activer mod_cgi (nécessaire pour CVE-2021-41773)
echo "Content-Type: text/html"
echo ""
echo "<html><body>"
echo "<h3>CorpNet Server Status</h3>"
echo "<p>Uptime: $(uptime)</p>"
echo "<p>Date: $(date)</p>"
echo "</body></html>"
