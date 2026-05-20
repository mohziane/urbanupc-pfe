"""Watcher Conformité — détail contrôle par contrôle.

Lit ``config/compliance_status.yaml`` et émet une Evidence par
contrôle. Trois référentiels techniques sont aujourd'hui suivis :

* **OWASP ASVS v4.0.3** (sélection L2 applicable aux apps internes) ;
* **CIS Docker Benchmark v1.6** (sélection sur l'hôte web01) ;
* **ANSSI DAT-NT-17** (recommandations Active Directory).

Le mapping référentiel :

* **ANSSI étape 6** (mesures de sécurité) — chaque contrôle est une
  mesure technique mesurable.
* **ANSSI étape 7** (vérification par audit) — la colonne
  ``evidence_ref`` cite la trace (fichier, alerte, commit).
* **ISO/IEC 27002:2022** — alignement transversal.

L'usage typique est de mettre à jour
``compliance_status.yaml`` lors d'une revue trimestrielle.
Toute évolution de statut produit un événement UPDATED dans le
journal du pipeline.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


_STATUT_MATERIALITY = {
    "conformant": Materiality.LOW,
    "partial": Materiality.MEDIUM,
    "wip": Materiality.MEDIUM,
    "non_conformant": Materiality.HIGH,
    "non_applicable": Materiality.LOW,
}


class ComplianceWatcher(BaseWatcher):
    """Lit la conformité contrôle par contrôle depuis YAML versionné."""

    source = SourceType.COMPLIANCE

    SCOPE = Scope(
        anssi_steps=(6, 7),
        iso_controls=("5.36",),
        asvs_controls=(),
    )

    def __init__(self, store: Any, status_file: Path | str | None = None) -> None:
        super().__init__(store)
        default = Path(__file__).resolve().parents[2] / "config" / "compliance_status.yaml"
        self.status_file = Path(
            status_file or os.environ.get("HOMO_CI_COMPLIANCE_FILE", default)
        )

    def collect(self) -> list[Evidence]:
        if not self.status_file.exists():
            self.log.warning("Compliance status file absent : %s", self.status_file)
            return []

        import yaml  # import paresseux

        with self.status_file.open("r", encoding="utf-8") as fh:
            data = yaml.safe_load(fh) or {}

        evidences: list[Evidence] = []
        for ref_key, ref_block in data.items():
            if not isinstance(ref_block, dict):
                continue
            scope = (ref_block.get("scope") or "").strip()
            for ctrl in ref_block.get("controles") or []:
                ctrl_id = str(ctrl.get("id") or "").strip()
                if not ctrl_id:
                    continue
                statut = (ctrl.get("statut") or "wip").lower()
                payload = {
                    "referentiel": ref_key,
                    "control_id": ctrl_id,
                    "categorie": ctrl.get("categorie", ""),
                    "exigence": ctrl.get("exigence", ""),
                    "statut": statut,
                    "evidence_ref": ctrl.get("evidence_ref", ""),
                    "proprietaire": ctrl.get("proprietaire", ""),
                    "derniere_revue": str(ctrl.get("derniere_revue", "")),
                    "scope": scope,
                }
                evidences.append(
                    Evidence.from_payload(
                        source=self.source,
                        category=Category.COMPLIANCE,
                        ref=f"{ref_key}::{ctrl_id}",
                        payload=payload,
                        scope=self.SCOPE,
                        weight=Weight.AUTO,
                        materiality=_STATUT_MATERIALITY.get(statut, Materiality.MEDIUM),
                    )
                )

        self.log.info(
            "Compliance : %d contrôles lus depuis %s.",
            len(evidences), self.status_file,
        )
        return evidences
