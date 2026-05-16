"""Couche 5 — Signer.

Implémentation de la *hash chain* qui garantit l'intégrité de la
suite de dossiers générés. Chaque DossierSnapshot porte le SHA-256
du dossier précédent (``parent_hash``) ; la suite forme une chaîne
auditablement immuable.

L'option GPG est prévue (variable ``HOMO_CI_GPG_KEY``) mais
volontairement défausée : pour le PFE, la hash chain SHA-256 est
suffisante et plus simple à reproduire en environnement
hétérogène.
"""

from __future__ import annotations

import hashlib
import logging
from pathlib import Path
from uuid import uuid4

from .models import ChangeSummary, DossierSnapshot
from .storage import EvidenceStore

logger = logging.getLogger(__name__)


def hash_file(path: Path) -> str:
    """SHA-256 d'un fichier en streaming (mémoire bornée)."""
    h = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(64 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def create_snapshot(
    store: EvidenceStore,
    pdf_path: Path,
    version: str,
    change_summary: ChangeSummary,
) -> DossierSnapshot:
    """Crée un :class:`DossierSnapshot` signé par hash chain.

    Args:
        store: EvidenceStore pour résoudre le snapshot parent.
        pdf_path: Chemin du PDF généré.
        version: Numéro de version (ex. ``V1.42``).
        change_summary: Résumé des changements vs parent.

    Returns:
        Snapshot persisté dans le store et prêt à publication.
    """
    pdf_hash = hash_file(pdf_path)
    parent = store.latest_snapshot()
    parent_hash = parent.pdf_hash if parent else None
    parent_id = parent.id if parent else None

    snap = DossierSnapshot(
        id=uuid4(),
        version=version,
        evidence_count=store.count(),
        pdf_path=str(pdf_path),
        pdf_hash=pdf_hash,
        parent_id=parent_id,
        parent_hash=parent_hash,
        change_summary=change_summary,
        # Signature = SHA-256 de (pdf_hash || parent_hash || version) — preuve d'intégrité chaînée.
        signature=hashlib.sha256(
            f"{pdf_hash}|{parent_hash or ''}|{version}".encode("utf-8")
        ).hexdigest(),
    )
    store.save_snapshot(snap)
    logger.info(
        "Snapshot %s créé : pdf_hash=%s parent=%s",
        version,
        pdf_hash[:12],
        (parent_hash or "<root>")[:12],
    )
    return snap


def verify_chain(store: EvidenceStore) -> bool:
    """Vérifie l'intégrité de la chaîne de snapshots.

    Parcourt les snapshots du plus récent au plus ancien et vérifie
    que ``parent_hash`` de N+1 correspond à ``pdf_hash`` de N.

    Returns:
        ``True`` si la chaîne est cohérente, ``False`` sinon.
    """
    snapshots = list(reversed(store.list_snapshots()))  # ancien → récent
    if not snapshots:
        return True
    for i, snap in enumerate(snapshots):
        if i == 0:
            if snap.parent_hash:
                logger.error("Premier snapshot %s a un parent_hash non-nul.", snap.version)
                return False
            continue
        prev = snapshots[i - 1]
        if snap.parent_hash != prev.pdf_hash:
            logger.error(
                "Hash chain rompue : %s.parent_hash=%s ≠ %s.pdf_hash=%s",
                snap.version,
                (snap.parent_hash or "")[:12],
                prev.version,
                prev.pdf_hash[:12],
            )
            return False
    return True
