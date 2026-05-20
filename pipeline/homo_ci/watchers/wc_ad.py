"""Watcher Active Directory — état du domaine corpnet.local.

Collecte par ``az vm run-command invoke`` (le DC est Windows, pas
de SSH ouvert) sur la VM ``vm-dc01``. Émet une preuve agrégée par
catégorie d'objets (utilisateurs, ordinateurs, groupes, GPO,
contrôleurs de domaine).

Le mapping référentiel :

* **ANSSI étape 4** (cartographie — populations et comptes).
* **ANSSI étape 6** (mesures techniques — durcissement AD).
* **ISO/IEC 27002:2022 §5.16** *Identity management*.
* **ANSSI DAT-NT-17** (recommandations Active Directory).

Coûteux à exécuter (~30–60 s via run-command). Le watcher est désactivé
si la variable d'environnement ``HOMO_CI_AD_ENABLED`` n'est pas
définie à ``1`` ou si l'outil ``az`` est absent.
"""

from __future__ import annotations

import json
import os
import shutil
import subprocess
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


_AD_PROBE_PS = r"""
$ErrorActionPreference = "Stop"
try {
  $d = Get-ADDomain
  $r = @{
    dns_root        = $d.DNSRoot
    netbios         = $d.NetBIOSName
    domain_mode     = "$($d.DomainMode)"
    pdc             = $d.PDCEmulator
    users_total     = (Get-ADUser -Filter *).Count
    users_enabled   = (Get-ADUser -Filter {Enabled -eq $true}).Count
    users_disabled  = (Get-ADUser -Filter {Enabled -eq $false}).Count
    computers_total = @(Get-ADComputer -Filter *).Count
    groups_total    = (Get-ADGroup -Filter *).Count
    dcs             = (Get-ADDomainController -Filter *).Name -join ","
    admins_count    = (Get-ADGroupMember -Identity "Domain Admins").Count
    gpos_total      = (Get-GPO -All).Count
  }
  $r | ConvertTo-Json -Compress
} catch {
  Write-Output ('{"error": "' + ($_.Exception.Message -replace '"','\"') + '"}')
}
"""


class ADWatcher(BaseWatcher):
    """État synthétique d'un domaine Active Directory."""

    source = SourceType.AD

    SCOPE = Scope(
        anssi_steps=(4, 6),
        iso_controls=("5.16", "5.18"),
        asvs_controls=(),
    )

    def __init__(
        self,
        store: Any,
        resource_group: str | None = None,
        vm_name: str | None = None,
    ) -> None:
        super().__init__(store)
        self.resource_group = resource_group or os.environ.get(
            "RESOURCE_GROUP", "RG-PFE-SOC"
        )
        self.vm_name = vm_name or os.environ.get("HOMO_CI_AD_VM", "vm-dc01")

    def collect(self) -> list[Evidence]:
        if os.environ.get("HOMO_CI_AD_ENABLED", "0") != "1":
            self.log.info(
                "Watcher AD désactivé (HOMO_CI_AD_ENABLED != 1). Coûteux à exécuter."
            )
            return []
        if shutil.which("az") is None:
            self.log.warning("Azure CLI absent — watcher AD inactif.")
            return []

        data = self._run_remote_probe()
        if data is None or "error" in data:
            if data and data.get("error"):
                self.log.error("Sonde AD remontée en erreur : %s", data["error"])
            return []

        return [self._emit_summary(data)]

    def _run_remote_probe(self) -> dict | None:
        cmd = [
            "az", "vm", "run-command", "invoke",
            "-g", self.resource_group,
            "-n", self.vm_name,
            "--command-id", "RunPowerShellScript",
            "--scripts", _AD_PROBE_PS,
            "--query", "value[0].message",
            "-o", "tsv",
        ]
        try:
            proc = subprocess.run(
                cmd, capture_output=True, text=True, timeout=180, check=False
            )
        except (subprocess.TimeoutExpired, FileNotFoundError) as exc:
            self.log.warning("az run-command vers %s a échoué : %s", self.vm_name, exc)
            return None

        if proc.returncode != 0:
            self.log.warning(
                "Sonde AD : az rc=%d, stderr=%s",
                proc.returncode, proc.stderr.strip()[:200],
            )
            return None

        # La sortie peut contenir des warnings PowerShell avant le JSON.
        for line in reversed(proc.stdout.strip().splitlines()):
            line = line.strip()
            if line.startswith("{") and line.endswith("}"):
                try:
                    return json.loads(line)
                except json.JSONDecodeError:
                    continue
        self.log.error("Sortie AD non JSON : %s", proc.stdout[:200])
        return None

    def _emit_summary(self, data: dict) -> Evidence:
        # Normalisation : Count peut renvoyer [] dans PS quand vide.
        def _i(v: Any) -> int:
            try:
                return int(v) if not isinstance(v, list) else (v[0] if v else 0)
            except (TypeError, ValueError):
                return 0

        payload = {
            "domain": data.get("dns_root"),
            "netbios": data.get("netbios"),
            "domain_mode": data.get("domain_mode"),
            "pdc_emulator": data.get("pdc"),
            "domain_controllers": [
                dc for dc in (data.get("dcs", "") or "").split(",") if dc
            ],
            "users": {
                "total": _i(data.get("users_total")),
                "enabled": _i(data.get("users_enabled")),
                "disabled": _i(data.get("users_disabled")),
            },
            "computers_total": _i(data.get("computers_total")),
            "groups_total": _i(data.get("groups_total")),
            "domain_admins_count": _i(data.get("admins_count")),
            "gpos_total": _i(data.get("gpos_total")),
        }

        # Matérialité : un nombre d'admin élevé est un indicateur de risque.
        admins = payload["domain_admins_count"]
        if admins >= 5:
            mat = Materiality.HIGH
        elif admins >= 3:
            mat = Materiality.MEDIUM
        else:
            mat = Materiality.LOW

        return Evidence.from_payload(
            source=self.source,
            category=Category.AD_OBJECT,
            ref=f"domain::{payload['domain'] or 'unknown'}",
            payload=payload,
            scope=self.SCOPE,
            weight=Weight.AUTO,
            materiality=mat,
        )
