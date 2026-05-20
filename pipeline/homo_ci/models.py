"""Modèles de données du pipeline HOMO-CI.

Les trois entités centrales sont :

* :class:`Evidence` — une *preuve d'audit* collectée par un watcher.
* :class:`Change` — un *delta* observé entre deux snapshots successifs.
* :class:`DossierSnapshot` — un *instantané* du dossier d'homologation
  produit par une exécution du pipeline.

Toutes les entités utilisent Pydantic v2 pour valider les invariants
au moment de la construction. Les modèles sont **immutables** :
toute modification produit une nouvelle entité.
"""

from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from enum import Enum
from typing import Any
from uuid import UUID, uuid4

from pydantic import BaseModel, ConfigDict, Field, field_validator


# ─────────────────────────────────────────────────────────────────────
# Énumérations
# ─────────────────────────────────────────────────────────────────────


class SourceType(str, Enum):
    """Sources possibles d'une preuve.

    Conformément à T-9.1 §V (Couche 1 — Collecte), huit watchers sont
    spécifiés. La v0.1 du pipeline implémente d'abord ``wc_nsg`` et
    ``wc_cve`` ; les autres restent en perspective.
    """

    NSG = "wc_nsg"
    CVE = "wc_cve"
    AD = "wc_ad"
    SIEM = "wc_siem"
    WAZUH = "wc_wazuh"
    DOCKER = "wc_docker"
    SAST = "wc_sast"
    DEPS = "wc_deps"
    ASVS = "wc_asvs"
    PACS = "wc_pacs"
    KRI = "wc_kri"
    COMPLIANCE = "wc_compliance"
    MANUAL = "manual"


class Category(str, Enum):
    """Catégories métier d'une preuve.

    Une catégorie regroupe des preuves de même nature, alimentant la
    même section du dossier d'homologation. Les catégories sont
    stables dans le temps (élargies par compatibilité ascendante
    uniquement).
    """

    CVE = "cve"
    NSG = "nsg"
    ACCOUNT = "account"
    RULE = "rule"
    SERVICE = "service"
    AGENT = "agent"
    ALERT = "alert"
    AD_OBJECT = "ad_object"
    PACS_ACTION = "pacs_action"
    KRI = "kri"
    COMPLIANCE = "compliance"
    SAST = "sast"
    DEP = "dep"
    ASVS = "asvs"
    OTHER = "other"


class Weight(float, Enum):
    """Poids d'une preuve dans la mesure de couverture M1.2.

    Conformément à T-1.2 §1.2, une preuve entièrement automatisable
    pèse 1,0 ; une preuve semi-automatisable pèse 0,5 ; une preuve
    purement manuelle n'est pas collectée par le pipeline.
    """

    AUTO = 1.0
    SEMI_AUTO = 0.5


class Materiality(str, Enum):
    """Matérialité d'un changement au sens du *Materiality Filter*.

    La matérialité conditionne le seuil de notification (T-9.1 §V.6).
    """

    LOW = "low"
    MEDIUM = "medium"
    HIGH = "high"


class ChangeType(str, Enum):
    """Types de changement détectés par le Diff Engine."""

    CREATED = "created"
    UPDATED = "updated"
    DELETED = "deleted"
    UNCHANGED = "unchanged"


# ─────────────────────────────────────────────────────────────────────
# Modèles
# ─────────────────────────────────────────────────────────────────────


class Scope(BaseModel):
    """Cartographie d'une preuve aux référentiels d'homologation.

    Cette structure matérialise la **contribution C-2** du PFE
    (cartographie d'automatisabilité). Une preuve est multi-mappée :
    elle peut alimenter plusieurs étapes ANSSI, plusieurs contrôles
    ISO et plusieurs contrôles ASVS.
    """

    model_config = ConfigDict(frozen=True, extra="forbid")

    anssi_steps: tuple[int, ...] = Field(
        default=(), description="Numéros des étapes ANSSI alimentées (1 à 9)."
    )
    iso_controls: tuple[str, ...] = Field(
        default=(), description="Identifiants des contrôles ISO/IEC 27002:2022."
    )
    asvs_controls: tuple[str, ...] = Field(
        default=(), description="Identifiants des contrôles OWASP ASVS v4.0.3."
    )

    @field_validator("anssi_steps")
    @classmethod
    def _valid_anssi(cls, v: tuple[int, ...]) -> tuple[int, ...]:
        if any(s < 1 or s > 9 for s in v):
            raise ValueError("Les étapes ANSSI doivent être comprises entre 1 et 9.")
        return v


class Evidence(BaseModel):
    """Une preuve d'audit collectée par un watcher.

    Une Evidence est **identifiée de manière unique** par son ``id``
    (UUID v4) et **datée** par ``ts`` (timestamp UTC). Le champ
    ``payload_hash`` permet la déduplication et la détection de
    changement par le Diff Engine.

    L'invariant fondamental est que deux Evidence sont sémantiquement
    identiques si et seulement si elles ont le même
    ``(source, ref, payload_hash)``.
    """

    model_config = ConfigDict(frozen=True, extra="forbid")

    id: UUID = Field(default_factory=uuid4, description="Identifiant unique (UUID v4).")
    ts: datetime = Field(
        default_factory=lambda: datetime.now(timezone.utc),
        description="Timestamp de collecte (UTC).",
    )
    source: SourceType = Field(..., description="Watcher d'origine.")
    category: Category = Field(..., description="Catégorie métier.")
    ref: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Identifiant métier de la preuve (CVE-XXXX, nom de règle NSG, etc.).",
    )
    scope: Scope = Field(default_factory=Scope, description="Mapping référentiels.")
    payload: dict[str, Any] = Field(
        ..., description="Contenu structuré spécifique à la catégorie."
    )
    payload_hash: str = Field(
        ...,
        pattern=r"^[a-f0-9]{64}$",
        description="SHA-256 du payload canonique (hex).",
    )
    weight: Weight = Field(default=Weight.AUTO, description="Poids dans M1.2.")
    materiality: Materiality = Field(
        default=Materiality.MEDIUM, description="Matérialité pour H3."
    )

    @classmethod
    def from_payload(
        cls,
        source: SourceType,
        category: Category,
        ref: str,
        payload: dict[str, Any],
        scope: Scope | None = None,
        weight: Weight = Weight.AUTO,
        materiality: Materiality = Materiality.MEDIUM,
    ) -> Evidence:
        """Construit une Evidence en calculant automatiquement le hash.

        Le hash est calculé sur la sérialisation JSON canonique du
        payload (clés triées, séparateurs compacts) afin d'être
        déterministe et stable au cours du temps.
        """
        canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"))
        payload_hash = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
        return cls(
            source=source,
            category=category,
            ref=ref,
            payload=payload,
            payload_hash=payload_hash,
            scope=scope or Scope(),
            weight=weight,
            materiality=materiality,
        )


class Change(BaseModel):
    """Un changement détecté entre deux snapshots successifs."""

    model_config = ConfigDict(frozen=True, extra="forbid")

    id: UUID = Field(default_factory=uuid4)
    detected_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))
    type: ChangeType
    ref: str
    category: Category
    before: Evidence | None = Field(
        default=None, description="État précédent (None si CREATED)."
    )
    after: Evidence | None = Field(
        default=None, description="État courant (None si DELETED)."
    )
    significance: Materiality = Materiality.MEDIUM


class ChangeSummary(BaseModel):
    """Résumé agrégé des changements d'un snapshot."""

    model_config = ConfigDict(frozen=True, extra="forbid")

    created: int = 0
    updated: int = 0
    deleted: int = 0
    unchanged: int = 0

    @property
    def total_changes(self) -> int:
        """Nombre total de changements non triviaux."""
        return self.created + self.updated + self.deleted


class DossierSnapshot(BaseModel):
    """Instantané du dossier d'homologation produit par le pipeline.

    Chaque snapshot référence son parent par ``parent_id`` formant
    ainsi une **chaîne de hash** auditable. Le hash du PDF est
    calculé après compilation et constitue la preuve d'intégrité.
    """

    model_config = ConfigDict(frozen=True, extra="forbid")

    id: UUID = Field(default_factory=uuid4)
    version: str = Field(..., description="Numéro de version (ex. 'V1.42').")
    generated_at: datetime = Field(default_factory=lambda: datetime.now(timezone.utc))
    evidence_count: int = Field(..., ge=0)
    pdf_path: str = Field(...)
    pdf_hash: str = Field(..., pattern=r"^[a-f0-9]{64}$")
    parent_id: UUID | None = Field(default=None, description="Hash chain vers parent.")
    parent_hash: str | None = Field(default=None, pattern=r"^[a-f0-9]{64}$|^$")
    change_summary: ChangeSummary = Field(default_factory=ChangeSummary)
    signature: str | None = Field(
        default=None,
        description="SHA-256 d'intégrité chaînée (pdf_hash || parent_hash || version).",
    )

    # ─── Horodatage RFC 3161 (TSA tierce) ────────────────────────────
    # Champs renseignés si une autorité d'horodatage a contresigné le
    # PDF. La présence de tsa_tsr_path implique l'existence du jeton de
    # réponse TSA sur disque (.tsr) ainsi que les certificats CA / TSA
    # utilisés pour la vérification.
    tsa_provider: str | None = Field(
        default=None,
        description="URL de la TSA ayant délivré l'horodatage (RFC 3161).",
    )
    tsa_tsr_path: str | None = Field(
        default=None,
        description="Chemin du jeton de réponse TSA (.tsr) sur disque.",
    )
    tsa_timestamp: datetime | None = Field(
        default=None,
        description="Horodatage UTC certifié par la TSA.",
    )
    tsa_serial: str | None = Field(
        default=None,
        description="Numéro de série du jeton délivré par la TSA.",
    )

    # ─── Signature CMS / CAdES (CA interne HOMO-CI) ──────────────────
    # Signature cryptographique détachée du PDF, émise par un
    # certificat X.509 lui-même issu de la CA HOMO-CI (cf. ca.py).
    cms_signature_path: str | None = Field(
        default=None,
        description="Chemin du fichier .p7s (signature CMS détachée).",
    )
    cms_signer_subject: str | None = Field(
        default=None,
        description="Sujet X.509 du certificat signataire.",
    )
    cms_signer_serial: str | None = Field(
        default=None,
        description="Numéro de série du certificat signataire.",
    )
    cms_signer_fingerprint: str | None = Field(
        default=None,
        description="Empreinte SHA-256 du certificat signataire.",
    )
