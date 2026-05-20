"""Watcher Wazuh — état réel du SOC.

Collecte par SSH sur le manager Wazuh (vm-siem01) :

* Liste des agents enrôlés (statut Active/Disconnected/Never connected).
* Décompte des règles personnalisées sous ``/var/ossec/etc/rules/``.
* Configuration de la réponse active (blocs ``<active-response>``
  réellement déclarés, hors placeholder de commentaire).
* Synthèse des alertes par règle et par niveau lue dans
  ``alerts.json``.

Le mapping référentiel :

* **ANSSI étape 6** (mesures techniques — détection).
* **ANSSI étape 7** (vérification par audit — preuve d'efficacité).
* **ANSSI étape 9** (suivi — alimentation des KRI SOC).
* **ISO/IEC 27002:2022 §8.16** *Monitoring activities*.

Le watcher dégrade gracieusement : si la VM est éteinte ou si SSH
échoue, il journalise et retourne une liste vide (le template tombe
sur le bloc « aucune preuve collectée »).
"""

from __future__ import annotations

import json
import os
import shlex
import subprocess
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


# Commande Python exécutée à distance qui parse alerts.json et émet
# un JSON compact. On limite la lecture à 5 Mo pour borner le coût ;
# au-delà, l'échantillon reste représentatif des 24 dernières heures.
_REMOTE_PROBE = r"""
import json, sys, subprocess, re

out = {"agents": [], "rules_files": [], "ar_blocks": 0, "alerts": {"by_rule": {}, "by_level": {}, "total": 0}}

# --- Agents ---
try:
    raw = subprocess.check_output(
        ["sudo", "/var/ossec/bin/agent_control", "-l"],
        stderr=subprocess.DEVNULL, timeout=15,
    ).decode("utf-8", errors="replace")
    for line in raw.splitlines():
        m = re.match(r"\s*ID:\s*(\d+),\s*Name:\s*([^,]+),\s*IP:\s*(\S+),\s*([\w/ -]+)", line)
        if m:
            out["agents"].append({
                "id": m.group(1),
                "name": m.group(2).split(" (")[0],
                "ip": m.group(3),
                "status": m.group(4).strip(),
            })
except Exception as exc:  # noqa: BLE001
    out["agents_error"] = str(exc)

# --- Custom rules ---
rules_dir = "/var/ossec/etc/rules"
try:
    listing = subprocess.check_output(
        ["sudo", "ls", rules_dir], stderr=subprocess.DEVNULL, timeout=10,
    ).decode("utf-8", errors="replace")
    for fn in sorted(listing.splitlines()):
        fn = fn.strip()
        if not fn.endswith(".xml"):
            continue
        path = rules_dir + "/" + fn
        try:
            content = subprocess.check_output(
                ["sudo", "cat", path], stderr=subprocess.DEVNULL, timeout=5
            ).decode("utf-8", errors="replace")
            n = len(re.findall(r"<rule\s+id=", content))
            out["rules_files"].append({"file": fn, "rules": n})
        except Exception:
            continue
except Exception as exc:  # noqa: BLE001
    out["rules_error"] = str(exc)

# --- Active response (blocs effectivement déclarés) ---
try:
    ossec = subprocess.check_output(
        ["sudo", "cat", "/var/ossec/etc/ossec.conf"],
        stderr=subprocess.DEVNULL, timeout=5,
    ).decode("utf-8", errors="replace")
    # On ne compte que les blocs avec une <command> renseignée
    # (le bloc commenté du template par défaut n'en a pas).
    blocks = re.findall(
        r"<active-response>\s*<command>([^<]+)</command>.*?</active-response>",
        ossec, flags=re.DOTALL,
    )
    out["ar_blocks"] = len(blocks)
    out["ar_commands"] = blocks
except Exception as exc:  # noqa: BLE001
    out["ar_error"] = str(exc)

# --- Alerts (échantillon des 24 dernières heures) ---
try:
    raw = subprocess.check_output(
        ["sudo", "head", "-c", "5000000", "/var/ossec/logs/alerts/alerts.json"],
        stderr=subprocess.DEVNULL, timeout=15,
    ).decode("utf-8", errors="replace")
    for line in raw.splitlines():
        try:
            d = json.loads(line)
        except Exception:
            continue
        rid = str(d.get("rule", {}).get("id") or "?")
        lvl = str(d.get("rule", {}).get("level") or "?")
        out["alerts"]["by_rule"][rid] = out["alerts"]["by_rule"].get(rid, 0) + 1
        out["alerts"]["by_level"][lvl] = out["alerts"]["by_level"].get(lvl, 0) + 1
        out["alerts"]["total"] += 1
except Exception as exc:  # noqa: BLE001
    out["alerts_error"] = str(exc)

print(json.dumps(out))
"""


class WazuhWatcher(BaseWatcher):
    """Collecte l'état réel du SOC Wazuh via SSH."""

    source = SourceType.WAZUH

    SCOPE = Scope(
        anssi_steps=(6, 7, 9),
        iso_controls=("8.16",),
        asvs_controls=(),
    )

    def __init__(
        self,
        store: Any,
        host: str | None = None,
        user: str | None = None,
        key_path: str | None = None,
    ) -> None:
        super().__init__(store)
        self.host = host or os.environ.get("WAZUH_HOST", "20.91.206.70")
        self.user = user or os.environ.get("WAZUH_SSH_USER", "azureuser")
        self.key_path = key_path or os.environ.get(
            "WAZUH_SSH_KEY", os.path.expanduser("~/.ssh/id_rsa")
        )

    def collect(self) -> list[Evidence]:
        data = self._run_remote_probe()
        if data is None:
            return []

        evidences: list[Evidence] = []
        evidences.extend(self._emit_agents(data.get("agents", [])))
        evidences.extend(self._emit_rules(data.get("rules_files", [])))
        evidences.append(self._emit_ar(data.get("ar_blocks", 0), data.get("ar_commands", [])))
        evidences.extend(self._emit_alerts(data.get("alerts", {})))
        return evidences

    # ─── Probe ────────────────────────────────────────────────────────

    def _run_remote_probe(self) -> dict | None:
        """Exécute la sonde Python sur le manager via SSH."""
        cmd = [
            "ssh",
            "-o", "StrictHostKeyChecking=no",
            "-o", "BatchMode=yes",
            "-o", "ConnectTimeout=10",
            "-i", self.key_path,
            f"{self.user}@{self.host}",
            f"python3 -c {shlex.quote(_REMOTE_PROBE)}",
        ]
        try:
            proc = subprocess.run(
                cmd, capture_output=True, text=True, timeout=60, check=False
            )
        except (subprocess.TimeoutExpired, FileNotFoundError) as exc:
            self.log.warning("SSH vers %s indisponible : %s", self.host, exc)
            return None

        if proc.returncode != 0:
            self.log.warning(
                "Sonde Wazuh : SSH rc=%d, stderr=%s",
                proc.returncode, proc.stderr.strip()[:200],
            )
            return None

        try:
            return json.loads(proc.stdout.strip().splitlines()[-1])
        except (json.JSONDecodeError, IndexError) as exc:
            self.log.error("Sortie sonde non JSON : %s", exc)
            return None

    # ─── Emit ─────────────────────────────────────────────────────────

    def _emit_agents(self, agents: list[dict]) -> list[Evidence]:
        evidences: list[Evidence] = []
        for ag in agents:
            ref = f"agent::{ag.get('id', '?')}"
            status = (ag.get("status") or "").lower()
            mat = (
                Materiality.LOW
                if "active" in status
                else Materiality.HIGH  # déconnecté = preuve de dérive
            )
            evidences.append(
                Evidence.from_payload(
                    source=self.source,
                    category=Category.AGENT,
                    ref=ref,
                    payload={
                        "agent_id": ag.get("id"),
                        "name": ag.get("name"),
                        "ip": ag.get("ip"),
                        "status": ag.get("status"),
                    },
                    scope=self.SCOPE,
                    weight=Weight.AUTO,
                    materiality=mat,
                )
            )
        return evidences

    def _emit_rules(self, rules_files: list[dict]) -> list[Evidence]:
        evidences: list[Evidence] = []
        for rf in rules_files:
            ref = f"rules::{rf.get('file', '?')}"
            evidences.append(
                Evidence.from_payload(
                    source=self.source,
                    category=Category.RULE,
                    ref=ref,
                    payload={
                        "file": rf.get("file"),
                        "rules_count": rf.get("rules", 0),
                    },
                    scope=self.SCOPE,
                    weight=Weight.AUTO,
                    materiality=Materiality.LOW,
                )
            )
        return evidences

    def _emit_ar(self, blocks: int, commands: list[str]) -> Evidence:
        return Evidence.from_payload(
            source=self.source,
            category=Category.SERVICE,
            ref="active-response::ossec.conf",
            payload={
                "blocks_declared": blocks,
                "commands": commands,
                "enabled": blocks > 0,
            },
            scope=self.SCOPE,
            weight=Weight.AUTO,
            materiality=(Materiality.LOW if blocks > 0 else Materiality.MEDIUM),
        )

    def _emit_alerts(self, alerts: dict) -> list[Evidence]:
        """Résumés agrégés des alertes : un top-rules et un by-level."""
        evidences: list[Evidence] = []
        total = int(alerts.get("total", 0))
        by_rule = alerts.get("by_rule") or {}
        by_level = alerts.get("by_level") or {}

        # Top-10 règles déclenchantes (affichage) + map complète (KRI).
        # On garde les deux représentations dans la même preuve pour
        # que l'observateur voie un palmarès lisible tout en
        # permettant aux watchers dérivés (wc_kri) une requête précise
        # sur une règle qui n'apparaît pas dans le top.
        top = sorted(by_rule.items(), key=lambda kv: -int(kv[1]))[:10]
        full_by_rule = {str(r): int(c) for r, c in by_rule.items()}
        evidences.append(
            Evidence.from_payload(
                source=self.source,
                category=Category.ALERT,
                ref="alerts::by_rule",
                payload={
                    "window": "alerts.json head 5MB",
                    "total_sampled": total,
                    "rules_distinct": len(full_by_rule),
                    "top_rules": [{"rule_id": r, "count": int(c)} for r, c in top],
                    "by_rule": full_by_rule,
                },
                scope=self.SCOPE,
                weight=Weight.AUTO,
                materiality=Materiality.LOW,
            )
        )

        # Niveaux — alimente le KRI MTTR / saturation.
        levels = sorted(((int(l) if l.isdigit() else -1, int(c)) for l, c in by_level.items()))
        evidences.append(
            Evidence.from_payload(
                source=self.source,
                category=Category.ALERT,
                ref="alerts::by_level",
                payload={
                    "total_sampled": total,
                    "by_level": [{"level": l, "count": c} for l, c in levels],
                    "critical_count": sum(c for l, c in levels if l >= 12),
                    "high_count": sum(c for l, c in levels if 10 <= l < 12),
                },
                scope=self.SCOPE,
                weight=Weight.AUTO,
                materiality=Materiality.LOW,
            )
        )
        return evidences
