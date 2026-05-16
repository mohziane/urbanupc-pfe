"""Couche 3 — Diff Engine.

Compare l'état courant du EvidenceStore avec un *snapshot précédent*
sérialisé en JSON Lines. Produit la liste des :class:`Change`.

L'algorithme est en O(N+M) où N = nb preuves passées, M = nb preuves
courantes : on construit un dict indexé par ``(source, ref)`` pour
les deux ensembles, on calcule la différence par opérations
ensemblistes.
"""

from __future__ import annotations

import json
import logging
from pathlib import Path

from .models import (
    Category,
    Change,
    ChangeSummary,
    ChangeType,
    Evidence,
    Materiality,
    Scope,
    SourceType,
    Weight,
)
from .storage import EvidenceStore

logger = logging.getLogger(__name__)


def snapshot_current(store: EvidenceStore, out_path: Path) -> int:
    """Sérialise l'état courant en JSON Lines.

    Returns:
        Nombre de preuves persistées.
    """
    out_path.parent.mkdir(parents=True, exist_ok=True)
    count = 0
    with out_path.open("w", encoding="utf-8") as fh:
        for ev in store.list_all():
            fh.write(ev.model_dump_json() + "\n")
            count += 1
    return count


def load_snapshot(path: Path) -> dict[tuple[str, str], Evidence]:
    """Charge un snapshot JSONL et l'indexe par (source, ref)."""
    if not path.exists():
        return {}
    out: dict[tuple[str, str], Evidence] = {}
    with path.open("r", encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line:
                continue
            ev = Evidence.model_validate_json(line)
            out[(ev.source.value, ev.ref)] = ev
    return out


def diff(
    previous: dict[tuple[str, str], Evidence],
    current: dict[tuple[str, str], Evidence],
) -> list[Change]:
    """Calcule les changements entre deux snapshots indexés."""
    changes: list[Change] = []

    keys_prev = set(previous.keys())
    keys_curr = set(current.keys())

    for key in keys_prev | keys_curr:
        before = previous.get(key)
        after = current.get(key)

        if before is None and after is not None:
            change_type = ChangeType.CREATED
        elif before is not None and after is None:
            change_type = ChangeType.DELETED
        elif (
            before is not None
            and after is not None
            and before.payload_hash != after.payload_hash
        ):
            change_type = ChangeType.UPDATED
        else:
            # Inchangé — ne génère pas de Change pour économiser de la mémoire.
            continue

        ref = (after or before).ref  # type: ignore[union-attr]
        category = (after or before).category  # type: ignore[union-attr]
        significance = _classify_significance(before, after, change_type)

        changes.append(
            Change(
                type=change_type,
                ref=ref,
                category=category,
                before=before,
                after=after,
                significance=significance,
            )
        )

    return changes


def summarize(changes: list[Change]) -> ChangeSummary:
    """Agrège une liste de changements en résumé."""
    return ChangeSummary(
        created=sum(1 for c in changes if c.type == ChangeType.CREATED),
        updated=sum(1 for c in changes if c.type == ChangeType.UPDATED),
        deleted=sum(1 for c in changes if c.type == ChangeType.DELETED),
        unchanged=0,
    )


def _classify_significance(
    before: Evidence | None,
    after: Evidence | None,
    change_type: ChangeType,
) -> Materiality:
    """Heuristique de significativité d'un changement (T-9.1 §V.3)."""
    ev = after or before
    if ev is None:
        return Materiality.LOW
    if ev.materiality == Materiality.HIGH:
        return Materiality.HIGH
    if change_type == ChangeType.DELETED:
        # Une suppression de règle de sécurité est généralement matérielle.
        return Materiality.HIGH if ev.category in (Category.NSG, Category.RULE) else Materiality.MEDIUM
    return ev.materiality
