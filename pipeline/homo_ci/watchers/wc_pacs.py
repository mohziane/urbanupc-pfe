"""Watcher PACS — état d'avancement des actions du Plan d'Amélioration.

Lit ``config/pacs_status.yaml`` (source de vérité versionnée dans le
dépôt) et émet une Evidence par action. Chaque mise à jour de statut
ou de date génère un événement ``UPDATED`` capturé par le Diff Engine,
qui devient une trace auditable du suivi PACS.

Le mapping référentiel :

* **ANSSI étape 6** (mesures de sécurité — plan d'action).
* **ANSSI étape 9** (suivi — revue périodique du PACS).
* **ISO/IEC 27001:2022 §6.2** *Information security objectives*.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


_STATUT_MATERIALITY = {
    "applied": Materiality.LOW,
    "in_progress": Materiality.MEDIUM,
    "planned": Materiality.MEDIUM,
    "blocked": Materiality.HIGH,
    "dropped": Materiality.HIGH,
}


class PACSWatcher(BaseWatcher):
    """Lit le statut des actions PACS depuis un YAML versionné."""

    source = SourceType.PACS

    SCOPE = Scope(
        anssi_steps=(6, 9),
        iso_controls=("6.2",),
        asvs_controls=(),
    )

    def __init__(self, store: Any, status_file: Path | str | None = None) -> None:
        super().__init__(store)
        default = Path(__file__).resolve().parents[2] / "config" / "pacs_status.yaml"
        self.status_file = Path(
            status_file or os.environ.get("HOMO_CI_PACS_FILE", default)
        )

    def collect(self) -> list[Evidence]:
        if not self.status_file.exists():
            self.log.warning("PACS status file absent : %s", self.status_file)
            return []

        import yaml  # import paresseux

        with self.status_file.open("r", encoding="utf-8") as fh:
            data = yaml.safe_load(fh) or {}

        actions = data.get("actions") or []
        evidences: list[Evidence] = []
        for action in actions:
            code = str(action.get("code") or "").strip()
            if not code:
                continue
            statut = (action.get("statut") or "planned").lower()
            payload = {
                "code": code,
                "action": action.get("action"),
                "priorite": int(action.get("priorite", 3)),
                "effort_jh": float(action.get("effort_jh", 0.0)),
                "echeance": str(action.get("echeance") or ""),
                "statut": statut,
                "statut_label": action.get("statut_label") or statut,
                "last_update": str(action.get("last_update") or ""),
                "owner": action.get("owner") or "",
            }
            evidences.append(
                Evidence.from_payload(
                    source=self.source,
                    category=Category.PACS_ACTION,
                    ref=f"pacs::{code}",
                    payload=payload,
                    scope=self.SCOPE,
                    weight=Weight.AUTO,
                    materiality=_STATUT_MATERIALITY.get(statut, Materiality.MEDIUM),
                )
            )
        self.log.info("PACS : %d actions lues depuis %s.", len(evidences), self.status_file)
        return evidences
