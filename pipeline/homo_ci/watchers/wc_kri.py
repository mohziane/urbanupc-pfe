"""Watcher KRI — agrégation des valeurs mesurées.

Le wc_kri ne lit aucune source externe : il dérive les valeurs
courantes des KRI à partir des preuves émises par les *autres*
watchers (CVE, Wazuh, PACS). Il doit donc être exécuté **après** eux.

Le contrat est explicite : pour chaque indicateur défini dans la
nomenclature, on émet une Evidence avec
``{ kri_code, indicateur, valeur, mesurable_auto, source_watcher,
  seuil_franchi }``. Un KRI dont l'automatisation n'est pas encore
en place reçoit la valeur ``None`` et ``mesurable_auto=false`` ; le
template affiche alors « manuel ».

Cette transparence est exigible côté ANSSI : un tableau de bord
KRI sans valeurs mesurées n'a aucune valeur probante.
"""

from __future__ import annotations

from typing import Any

from ..models import Category, Evidence, Materiality, Scope, SourceType, Weight
from .base import BaseWatcher


class KRIWatcher(BaseWatcher):
    """Synthétise les valeurs courantes des KRI."""

    source = SourceType.KRI

    SCOPE = Scope(
        anssi_steps=(9,),
        iso_controls=("9.1",),
        asvs_controls=(),
    )

    def collect(self) -> list[Evidence]:
        # Index par (source, ref) pour lookup rapide.
        all_evs = self.store.list_all()
        by_source: dict[str, list[Any]] = {}
        for ev in all_evs:
            by_source.setdefault(ev.source.value, []).append(ev)

        derivations = [
            self._kri1_auth_failures(by_source),
            self._kri2_brute_force(by_source),
            self._kri3_container_escape(by_source),
            self._kri4_inactive_accounts(by_source),
            self._kri5_offboarding_delay(),
            self._kri6_mfa_admin_coverage(),
            self._kri7_critical_cves(by_source),
            self._kri8_mttr(),
            self._kri9_backup_coverage(),
            self._kri10_pacs_progress(by_source),
            self._kri11_fp_ratio(),
            self._kri12_detection_regressions(by_source),
        ]
        return [self._evidence(**d) for d in derivations]

    # ─── Helpers ──────────────────────────────────────────────────────

    def _evidence(
        self,
        code: str,
        indicateur: str,
        valeur: Any,
        unite: str,
        mesurable_auto: bool,
        source_watcher: str | None,
        seuil_franchi: bool | None,
        note: str = "",
    ) -> Evidence:
        # Matérialité : un seuil franchi est de matérialité haute.
        if seuil_franchi is True:
            mat = Materiality.HIGH
        elif mesurable_auto:
            mat = Materiality.LOW
        else:
            mat = Materiality.MEDIUM

        return Evidence.from_payload(
            source=self.source,
            category=Category.KRI,
            ref=f"kri::{code}",
            payload={
                "code": code,
                "indicateur": indicateur,
                "valeur": valeur,
                "unite": unite,
                "mesurable_auto": mesurable_auto,
                "source_watcher": source_watcher,
                "seuil_franchi": seuil_franchi,
                "note": note,
            },
            scope=self.SCOPE,
            weight=Weight.AUTO if mesurable_auto else Weight.SEMI_AUTO,
            materiality=mat,
        )

    @staticmethod
    def _alerts_by_rule(by_source: dict[str, list[Any]]) -> dict[str, int]:
        """Renvoie un dict rule_id → count depuis l'evidence wc_wazuh.

        Lit la map complète ``by_rule`` (et non plus seulement le
        top-10) afin que les KRI portant sur une règle absente du
        palmarès soient correctement valorisés.
        """
        result: dict[str, int] = {}
        for ev in by_source.get(SourceType.WAZUH.value, []):
            if ev.ref == "alerts::by_rule":
                full = ev.payload.get("by_rule") or {}
                if full:
                    return {str(k): int(v) for k, v in full.items()}
                # Compatibilité ascendante : fallback sur le top-10
                # si l'ancienne représentation est encore en base.
                for entry in ev.payload.get("top_rules", []):
                    result[str(entry["rule_id"])] = int(entry["count"])
                return result
        return result

    @staticmethod
    def _alerts_by_level(by_source: dict[str, list[Any]]) -> dict[int, int]:
        result: dict[int, int] = {}
        for ev in by_source.get(SourceType.WAZUH.value, []):
            if ev.ref == "alerts::by_level":
                for entry in ev.payload.get("by_level", []):
                    try:
                        result[int(entry["level"])] = int(entry["count"])
                    except (TypeError, ValueError):
                        continue
                return result
        return result

    # ─── Derivations ──────────────────────────────────────────────────

    def _kri1_auth_failures(self, by_source: dict[str, list[Any]]) -> dict:
        # Rules 5710 (sshd failed login) + 5712 (brute force) + 60204 (windows logon failure)
        by_rule = self._alerts_by_rule(by_source)
        val = (
            by_rule.get("5710", 0) + by_rule.get("5712", 0) + by_rule.get("60204", 0)
        )
        return dict(
            code="KRI-1",
            indicateur="Échecs d'authentification",
            valeur=val if by_rule else None,
            unite="événements / fenêtre échantillonnée",
            mesurable_auto=bool(by_rule),
            source_watcher="wc_wazuh",
            seuil_franchi=(val > 500) if by_rule else None,
            note="Échantillon : 5 Mo head de alerts.json (~24 h).",
        )

    def _kri2_brute_force(self, by_source: dict[str, list[Any]]) -> dict:
        by_rule = self._alerts_by_rule(by_source)
        val = by_rule.get("5712", 0) + by_rule.get("100302", 0)
        return dict(
            code="KRI-2",
            indicateur="Tentatives de brute force",
            valeur=val if by_rule else None,
            unite="événements / fenêtre",
            mesurable_auto=bool(by_rule),
            source_watcher="wc_wazuh",
            seuil_franchi=(val >= 1) if by_rule else None,
            note="Règles 5712 (SSH) + 100302 (apps internes).",
        )

    def _kri3_container_escape(self, by_source: dict[str, list[Any]]) -> dict:
        by_rule = self._alerts_by_rule(by_source)
        val = by_rule.get("100351", 0)
        return dict(
            code="KRI-3",
            indicateur="Container escape suspects",
            valeur=val if by_rule else None,
            unite="événements / fenêtre",
            mesurable_auto=bool(by_rule),
            source_watcher="wc_wazuh",
            seuil_franchi=(val >= 1) if by_rule else None,
            note="Règle 100351. Volume élevé attendu (CVE Docker plantée).",
        )

    def _kri4_inactive_accounts(self, by_source: dict[str, list[Any]]) -> dict:
        # Non automatisé : requêtage spécifique non implémenté dans wc_ad v1.
        return dict(
            code="KRI-4",
            indicateur="Comptes AD inactifs (>90 j)",
            valeur=None,
            unite="comptes",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=None,
            note="Mesure prévue dans wc_ad v2 (LastLogonDate filter).",
        )

    def _kri5_offboarding_delay(self) -> dict:
        return dict(
            code="KRI-5",
            indicateur="Délai moyen de off-boarding",
            valeur=None,
            unite="jours",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=None,
            note="Procédure off-boarding non encore formalisée (PACS R7).",
        )

    def _kri6_mfa_admin_coverage(self) -> dict:
        return dict(
            code="KRI-6",
            indicateur="Couverture MFA admin",
            valeur=0,
            unite="%",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=True,
            note="MFA non déployé (PACS R8 planifié). Seuil franchi par construction.",
        )

    def _kri7_critical_cves(self, by_source: dict[str, list[Any]]) -> dict:
        cves = by_source.get(SourceType.CVE.value, [])
        if not cves:
            return dict(
                code="KRI-7",
                indicateur="CVE critiques non patchées",
                valeur=None,
                unite="CVE",
                mesurable_auto=False,
                source_watcher="wc_cve",
                seuil_franchi=None,
                note="Aucune preuve CVE collectée (Trivy indisponible ?).",
            )
        high_critical = sum(
            1 for ev in cves
            if (ev.payload.get("severity") or "").upper() in ("HIGH", "CRITICAL")
        )
        return dict(
            code="KRI-7",
            indicateur="CVE critiques non patchées",
            valeur=high_critical,
            unite="CVE",
            mesurable_auto=True,
            source_watcher="wc_cve",
            seuil_franchi=(high_critical >= 1),
            note="Sévérité HIGH ou CRITICAL agrégée sur toutes les images scannées.",
        )

    def _kri8_mttr(self) -> dict:
        return dict(
            code="KRI-8",
            indicateur="MTTR alertes L≥10",
            valeur=None,
            unite="heures",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=None,
            note="Nécessite une intégration ticketing absente du lab.",
        )

    def _kri9_backup_coverage(self) -> dict:
        return dict(
            code="KRI-9",
            indicateur="Couverture sauvegardes avec test de restore",
            valeur=0,
            unite="%",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=True,
            note="Sauvegardes non en place (PACS R9 planifié).",
        )

    def _kri10_pacs_progress(self, by_source: dict[str, list[Any]]) -> dict:
        pacs = by_source.get(SourceType.PACS.value, [])
        if not pacs:
            return dict(
                code="KRI-10",
                indicateur="Avancement PACS",
                valeur=None,
                unite="%",
                mesurable_auto=False,
                source_watcher="wc_pacs",
                seuil_franchi=None,
                note="Aucune preuve PACS collectée.",
            )
        total = len(pacs)
        applied = sum(1 for ev in pacs if ev.payload.get("statut") == "applied")
        pct = round(100.0 * applied / total, 1) if total else 0.0
        return dict(
            code="KRI-10",
            indicateur="Avancement PACS (actions appliquées)",
            valeur=pct,
            unite="%",
            mesurable_auto=True,
            source_watcher="wc_pacs",
            seuil_franchi=(pct < 80.0),
            note=f"{applied}/{total} actions appliquées à ce jour.",
        )

    def _kri11_fp_ratio(self) -> dict:
        return dict(
            code="KRI-11",
            indicateur="Ratio faux positifs SIEM",
            valeur=None,
            unite="%",
            mesurable_auto=False,
            source_watcher=None,
            seuil_franchi=None,
            note="Pas de qualification de FP automatisée (revue manuelle SOC).",
        )

    def _kri12_detection_regressions(self, by_source: dict[str, list[Any]]) -> dict:
        # On considère qu'un rule_count=0 sur les fichiers custom est une régression.
        regressions = 0
        any_rules = False
        for ev in by_source.get(SourceType.WAZUH.value, []):
            if ev.category == Category.RULE:
                any_rules = True
                if int(ev.payload.get("rules_count", 0)) == 0:
                    regressions += 1
        return dict(
            code="KRI-12",
            indicateur="Régressions de règles custom",
            valeur=regressions if any_rules else None,
            unite="fichiers vides",
            mesurable_auto=any_rules,
            source_watcher="wc_wazuh",
            seuil_franchi=(regressions >= 1) if any_rules else None,
            note="Proxy : un fichier de règles custom vide = régression.",
        )
