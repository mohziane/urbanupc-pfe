"""Watcher CVE — scan Trivy d'une ou plusieurs images Docker.

Chaque vulnérabilité (CVE) trouvée par Trivy donne lieu à une
Evidence. Le mapping référentiel :

* **ANSSI étape 6** (mesures de sécurité techniques) ;
* **ANSSI étape 7** (vérification de l'efficacité par audit) ;
* **ISO/IEC 27002:2022 §8.8** *Management of technical vulnerabilities*.

L'invocation de Trivy est faite par :command:`trivy image --format json`.
Une exécution sans Trivy disponible est gracieusement détectée et
signalée.
"""

from __future__ import annotations

import json
import shutil
import subprocess
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher

# Sévérité Trivy → Materiality interne.
_SEV_MAP = {
    "CRITICAL": Materiality.HIGH,
    "HIGH": Materiality.HIGH,
    "MEDIUM": Materiality.MEDIUM,
    "LOW": Materiality.LOW,
    "UNKNOWN": Materiality.LOW,
}


class CVEWatcher(BaseWatcher):
    """Scan d'images Docker avec Trivy."""

    source = SourceType.CVE

    SCOPE = Scope(
        anssi_steps=(6, 7),
        iso_controls=("8.8", "8.9"),
        asvs_controls=("V14.2.1",),
    )

    def __init__(self, store: Any, images: list[str] | None = None) -> None:
        super().__init__(store)
        self.images = images or [
            "internal-apps/maformation:latest",
            "internal-apps/macandidature:latest",
        ]

    def collect(self) -> list[Evidence]:
        if shutil.which("trivy") is None:
            self.log.warning("Trivy non disponible — watcher CVE inactif.")
            return []

        evidences: list[Evidence] = []
        for image in self.images:
            evidences.extend(self._scan_image(image))
        return evidences

    def _scan_image(self, image: str) -> list[Evidence]:
        try:
            proc = subprocess.run(
                [
                    "trivy",
                    "image",
                    "--quiet",
                    "--format",
                    "json",
                    "--severity",
                    "CRITICAL,HIGH,MEDIUM,LOW",
                    image,
                ],
                capture_output=True,
                text=True,
                timeout=300,
                check=False,
            )
        except (subprocess.TimeoutExpired, FileNotFoundError) as exc:
            self.log.error("Trivy a échoué sur %s : %s", image, exc)
            return []

        if proc.returncode != 0:
            self.log.error("Trivy a renvoyé un code %d sur %s.", proc.returncode, image)
            return []

        try:
            data = json.loads(proc.stdout)
        except json.JSONDecodeError:
            self.log.error("Sortie Trivy non-JSON sur %s.", image)
            return []

        return self._parse_trivy_output(image, data)

    def _parse_trivy_output(self, image: str, data: dict[str, Any]) -> list[Evidence]:
        evidences: list[Evidence] = []
        for result in data.get("Results", []):
            for vuln in result.get("Vulnerabilities", []) or []:
                ref = f"{image}::{vuln['VulnerabilityID']}::{vuln.get('PkgName','')}"
                payload = {
                    "image": image,
                    "vulnerability_id": vuln.get("VulnerabilityID"),
                    "package": vuln.get("PkgName"),
                    "installed_version": vuln.get("InstalledVersion"),
                    "fixed_version": vuln.get("FixedVersion"),
                    "severity": vuln.get("Severity", "UNKNOWN"),
                    "cvss_score": (vuln.get("CVSS") or {})
                    .get("nvd", {})
                    .get("V3Score"),
                    "title": vuln.get("Title", ""),
                    "published": vuln.get("PublishedDate"),
                }
                evidences.append(
                    Evidence.from_payload(
                        source=self.source,
                        category=Category.CVE,
                        ref=ref,
                        payload=payload,
                        scope=self.SCOPE,
                        weight=Weight.AUTO,
                        materiality=_SEV_MAP.get(
                            payload["severity"].upper(), Materiality.MEDIUM
                        ),
                    )
                )
        return evidences
