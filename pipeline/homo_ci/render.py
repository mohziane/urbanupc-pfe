"""Couche 4 — Renderer.

Produit le Markdown du dossier d'homologation par injection des
preuves stockées dans des templates Jinja2. Le Markdown peut ensuite
être compilé en PDF par pandoc + weasyprint (chaîne réutilisée de
l'itération 1).
"""

from __future__ import annotations

import logging
from datetime import datetime, timezone
from pathlib import Path

from jinja2 import Environment, FileSystemLoader, select_autoescape

from .models import Category
from .storage import EvidenceStore

logger = logging.getLogger(__name__)


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
        active_sources: set[str] = set()
        for ev in all_evidence:
            by_category.setdefault(ev.category.value, []).append(ev)
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

        dynamic_context = {
            "version": version,
            "generated_at": datetime.now(timezone.utc).isoformat(),
            "evidence_count": len(all_evidence),
            "nsg_rules": nsg_rules,
            "cve_findings": cve_findings,
            "categories_count": len(by_category),
            "active_sources": sorted(active_sources),
        }

        # Le contexte statique passe d'abord, puis est augmenté par le
        # contexte dynamique. En cas de conflit, le dynamique gagne (car
        # il reflète l'état réel courant).
        static_context = self._load_static_context()
        return {**static_context, **dynamic_context}
