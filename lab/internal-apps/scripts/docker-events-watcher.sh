#!/usr/bin/env bash
# Docker events watcher — writes one JSON line per Docker daemon event to
# /var/log/corpnet/internal/docker-events.log. Wazuh tails this file and
# rule 100351 fires on `exec_start` events (container escape signature).
set -euo pipefail
LOG=/var/log/corpnet/internal/docker-events.log
mkdir -p "$(dirname "$LOG")"
exec docker events --format '{"time":"{{.Time}}","type":"{{.Type}}","evt_action":"{{.Action}}","actor":"{{.Actor.Attributes.name}}","image":"{{.Actor.Attributes.image}}"}' >> "$LOG"
