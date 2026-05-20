"""Couche 4 — Renderer.

Produit le Markdown du dossier d'homologation par injection des
preuves stockées dans des templates Jinja2. Le Markdown peut ensuite
être compilé en PDF par pandoc + weasyprint (chaîne réutilisée de
l'itération 1).
"""

from __future__ import annotations

import logging
import os
from datetime import datetime, timezone
from pathlib import Path

from jinja2 import Environment, FileSystemLoader, select_autoescape

from .models import Category, SourceType
from .storage import EvidenceStore

logger = logging.getLogger(__name__)


# Statuts possibles d'un dossier généré. Pilote l'affichage du
# bandeau "VERSION DE TRAVAIL" en pièce de garde et le marquage de
# chaque page. La valeur par défaut est 'draft' : un dossier sans
# choix explicite reste prudent (« non homologatoire »).
_STATUSES = {
    "draft": {
        "code": "draft",
        "label": "Version de travail",
        "banner": "VERSION DE TRAVAIL — NON HOMOLOGATOIRE",
        "disclaimer": (
            "Ce document est produit dans le cadre d'un projet de fin "
            "d'études. La décision d'homologation pages 9.3 et 9.4 ne "
            "peut être signée que par un AQSSI désigné par "
            "l'établissement homologuant, après examen indépendant."
        ),
    },
    "reviewed": {
        "code": "reviewed",
        "label": "Soumis à revue",
        "banner": "SOUMIS À REVUE — EN ATTENTE DE DÉCISION AQSSI",
        "disclaimer": (
            "Ce document a été revu par le RSSI et est prêt à être "
            "examiné par le comité d'homologation. Il n'engage pas "
            "encore l'établissement."
        ),
    },
    "final": {
        "code": "final",
        "label": "Version homologuée",
        "banner": "",
        "disclaimer": "",
    },
}


class DossierRenderer:
    """Renderer du dossier d'homologation HOMO-CI.

    Le renderer combine deux sources :

    * Le **contexte statique** (chargé depuis un YAML versionné) — contient
      les éléments institutionnels du dossier (PSSI, populations, KRI,
      acteurs, etc.) qui ne sont pas auto-collectés du SI.
    * Les **preuves dynamiques** (lues depuis le EvidenceStore) — règles
      NSG, vulnérabilités CVE, etc., auto-collectées par les watchers.

    Cette séparation garantit que :
      - le dossier régénéré reste **conforme au cadre ANSSI** dans sa
        structure (pieces obligatoires) ;
      - les éléments dynamiques **reflètent toujours l'état réel** du SI.
    """

    def __init__(
        self,
        store: EvidenceStore,
        templates_dir: Path,
        context_file: Path | None = None,
    ) -> None:
        self.store = store
        self.templates_dir = templates_dir
        self.context_file = context_file
        self.env = Environment(
            loader=FileSystemLoader(str(templates_dir)),
            autoescape=select_autoescape(disabled_extensions=("md", "j2")),
            keep_trailing_newline=True,
            trim_blocks=False,
            lstrip_blocks=False,
        )

    def render(self, version: str) -> str:
        """Rend le dossier complet en Markdown."""
        template = self.env.get_template("dossier.md.j2")
        return template.render(**self._context(version))

    def _load_static_context(self) -> dict:
        """Charge le contexte statique depuis YAML.

        Retourne un dict vide si aucun fichier n'est configuré ou si le
        fichier est absent. Une erreur de parsing remonte (échec rapide
        plutôt que silencieux).
        """
        if not self.context_file or not Path(self.context_file).exists():
            logger.warning(
                "Aucun contexte statique chargé (context_file=%s).", self.context_file
            )
            return {}
        import yaml  # import paresseux (dépendance optionnelle)

        with Path(self.context_file).open("r", encoding="utf-8") as fh:
            data = yaml.safe_load(fh) or {}
        logger.info(
            "Contexte statique chargé : %d clés racine.", len(data) if isinstance(data, dict) else 0
        )
        return data

    def _context(self, version: str) -> dict:
        """Construit le contexte d'injection pour les templates."""
        all_evidence = self.store.list_all()
        by_category: dict[str, list] = {}
        by_source: dict[str, list] = {}
        active_sources: set[str] = set()
        for ev in all_evidence:
            by_category.setdefault(ev.category.value, []).append(ev)
            by_source.setdefault(ev.source.value, []).append(ev)
            active_sources.add(ev.source.value)

        nsg_rules = sorted(
            by_category.get(Category.NSG.value, []),
            key=lambda e: (e.payload.get("nsg", ""), e.payload.get("priority", 0)),
        )

        cve_findings = sorted(
            by_category.get(Category.CVE.value, []),
            key=lambda e: (
                {"HIGH": 0, "CRITICAL": -1, "MEDIUM": 1, "LOW": 2}.get(
                    e.payload.get("severity", "MEDIUM").upper(), 3
                ),
                e.ref,
            ),
        )

        # ── Sorties dérivées des nouveaux watchers ──────────────────
        wazuh_agents = sorted(
            (ev.payload for ev in by_source.get(SourceType.WAZUH.value, [])
             if ev.category == Category.AGENT),
            key=lambda p: str(p.get("agent_id", "")),
        )
        wazuh_rules_files = sorted(
            (ev.payload for ev in by_source.get(SourceType.WAZUH.value, [])
             if ev.category == Category.RULE),
            key=lambda p: str(p.get("file", "")),
        )
        wazuh_custom_rules_total = sum(
            int(p.get("rules_count", 0)) for p in wazuh_rules_files
        )
        wazuh_ar = next(
            (ev.payload for ev in by_source.get(SourceType.WAZUH.value, [])
             if ev.ref == "active-response::ossec.conf"),
            None,
        )
        wazuh_top_rules = next(
            (ev.payload for ev in by_source.get(SourceType.WAZUH.value, [])
             if ev.ref in ("alerts::by_rule", "alerts::top_rules")),
            None,
        )
        wazuh_by_level = next(
            (ev.payload for ev in by_source.get(SourceType.WAZUH.value, [])
             if ev.ref == "alerts::by_level"),
            None,
        )

        ad_summary = next(
            (ev.payload for ev in by_source.get(SourceType.AD.value, [])
             if ev.category == Category.AD_OBJECT),
            None,
        )

        pacs_actions = sorted(
            (ev.payload for ev in by_source.get(SourceType.PACS.value, [])),
            key=lambda p: (int(p.get("priorite", 9)), str(p.get("code", ""))),
        )

        kri_measures = {
            ev.payload.get("code"): ev.payload
            for ev in by_source.get(SourceType.KRI.value, [])
        }

        # ── Conformité regroupée par référentiel ────────────────────
        compliance_controls = sorted(
            (ev.payload for ev in by_source.get(SourceType.COMPLIANCE.value, [])),
            key=lambda p: (p.get("referentiel", ""), str(p.get("control_id", ""))),
        )
        compliance_by_ref: dict[str, list] = {}
        for c in compliance_controls:
            compliance_by_ref.setdefault(c.get("referentiel", "?"), []).append(c)
        compliance_stats: dict[str, dict[str, int]] = {}
        for ref, ctrls in compliance_by_ref.items():
            stats = {"conformant": 0, "partial": 0, "wip": 0,
                     "non_conformant": 0, "non_applicable": 0, "total": len(ctrls)}
            for c in ctrls:
                stats[c.get("statut", "wip")] = stats.get(c.get("statut", "wip"), 0) + 1
            compliance_stats[ref] = stats

        status_code = os.environ.get("HOMO_CI_DOCUMENT_STATUS", "draft").lower()
        document_status = _STATUSES.get(status_code, _STATUSES["draft"])

        dynamic_context = {
            "version": version,
            "document_status": document_status,
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "evidence_count": len(all_evidence),
            "nsg_rules": nsg_rules,
            "cve_findings": cve_findings,
            "wazuh_agents": wazuh_agents,
            "wazuh_rules_files": wazuh_rules_files,
            "wazuh_custom_rules_total": wazuh_custom_rules_total,
            "wazuh_ar": wazuh_ar,
            "wazuh_top_rules": wazuh_top_rules,
            "wazuh_by_level": wazuh_by_level,
            "ad_summary": ad_summary,
            "pacs_actions": pacs_actions,
            "kri_measures": kri_measures,
            "compliance_controls": compliance_controls,
            "compliance_by_ref": compliance_by_ref,
            "compliance_stats": compliance_stats,
            "categories_count": len(by_category),
            "active_sources": sorted(active_sources),
        }

        # Le contexte statique passe d'abord, puis est augmenté par le
        # contexte dynamique. En cas de conflit, le dynamique gagne (car
        # il reflète l'état réel courant).
        static_context = self._load_static_context()
        return {**static_context, **dynamic_context}
