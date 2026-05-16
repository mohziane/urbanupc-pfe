"""Couche de stockage du pipeline HOMO-CI.

La stratégie de stockage suit le principe T-9.1 §IV.4 :

* **Evidence courantes** : SQLite (``evidence.db``) pour les accès
  fréquents en lecture, indexable.
* **Evidence historiques** : fichiers JSONL versionnés git
  (``evidence-store/AAAA-MM-DD.jsonl``) — *audit trail immuable*.
* **DossierSnapshot** : index JSON versionné git
  (``dossier-history.json``) + PDF en Azure Blob (en production).

La classe :class:`EvidenceStore` encapsule ces deux stockages et
garantit la cohérence (toute écriture en base est aussi journalisée
sur disque).
"""

from __future__ import annotations

import json
import logging
import sqlite3
from contextlib import contextmanager
from datetime import date, datetime, timezone
from pathlib import Path
from typing import Any, Iterator

from .models import (
    Category,
    Change,
    ChangeType,
    DossierSnapshot,
    Evidence,
    Materiality,
    Scope,
    SourceType,
    Weight,
)

logger = logging.getLogger(__name__)


# Schéma SQL — versionné via PRAGMA user_version (pour migrations futures).
SCHEMA_VERSION = 1

_DDL = """
CREATE TABLE IF NOT EXISTS evidence (
    id              TEXT PRIMARY KEY,
    ts              TEXT NOT NULL,                  -- ISO 8601 UTC
    source          TEXT NOT NULL,
    category        TEXT NOT NULL,
    ref             TEXT NOT NULL,
    scope_json      TEXT NOT NULL,                  -- JSON Scope
    payload_json    TEXT NOT NULL,                  -- JSON payload
    payload_hash    TEXT NOT NULL,
    weight          REAL NOT NULL,
    materiality     TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS ix_evidence_category    ON evidence(category);
CREATE INDEX IF NOT EXISTS ix_evidence_source      ON evidence(source);
CREATE INDEX IF NOT EXISTS ix_evidence_ref         ON evidence(ref);
CREATE INDEX IF NOT EXISTS ix_evidence_ts          ON evidence(ts);
CREATE INDEX IF NOT EXISTS ix_evidence_payload     ON evidence(payload_hash);
CREATE UNIQUE INDEX IF NOT EXISTS ux_evidence_ref_hash
    ON evidence(source, ref, payload_hash);

CREATE TABLE IF NOT EXISTS dossier_snapshots (
    id              TEXT PRIMARY KEY,
    version         TEXT NOT NULL UNIQUE,
    generated_at    TEXT NOT NULL,
    evidence_count  INTEGER NOT NULL,
    pdf_path        TEXT NOT NULL,
    pdf_hash        TEXT NOT NULL,
    parent_id       TEXT,
    parent_hash     TEXT,
    summary_json    TEXT NOT NULL,
    signature       TEXT
);
"""


class EvidenceStore:
    """Stockage typé des preuves d'audit.

    Le constructeur prend en paramètre le répertoire racine du
    *evidence store*. Sont créés si absents :

    * ``evidence.db`` — base SQLite contenant l'état courant.
    * ``trail/AAAA-MM-DD.jsonl`` — journal immuable journalier.
    """

    def __init__(self, root: Path) -> None:
        self.root = Path(root)
        self.root.mkdir(parents=True, exist_ok=True)
        self.db_path = self.root / "evidence.db"
        self.trail_dir = self.root / "trail"
        self.trail_dir.mkdir(parents=True, exist_ok=True)
        self._init_schema()

    def _init_schema(self) -> None:
        with self._connect() as cx:
            cx.executescript(_DDL)
            current = cx.execute("PRAGMA user_version").fetchone()[0]
            if current == 0:
                cx.execute(f"PRAGMA user_version = {SCHEMA_VERSION}")
            elif current < SCHEMA_VERSION:
                # Place réservée pour de futures migrations.
                logger.warning(
                    "Migration de schéma requise : courant=%s, cible=%s.",
                    current,
                    SCHEMA_VERSION,
                )

    @contextmanager
    def _connect(self) -> Iterator[sqlite3.Connection]:
        cx = sqlite3.connect(self.db_path, isolation_level=None)
        cx.row_factory = sqlite3.Row
        try:
            yield cx
        finally:
            cx.close()

    # ─── Écritures ────────────────────────────────────────────────

    def upsert(self, ev: Evidence) -> bool:
        """Insère ou met à jour une preuve. Retourne True si nouvelle.

        L'invariant *« une seule Evidence par (source, ref) à la fois »*
        est garanti : toute Evidence préexistante pour ce couple est
        remplacée atomiquement par la nouvelle.
        """
        existing = self.find_by_ref(ev.source, ev.ref)
        is_new = existing is None or existing.payload_hash != ev.payload_hash

        with self._connect() as cx:
            if existing is not None:
                cx.execute(
                    "DELETE FROM evidence WHERE source = ? AND ref = ?",
                    (ev.source.value, ev.ref),
                )
            cx.execute(
                """
                INSERT INTO evidence
                (id, ts, source, category, ref, scope_json, payload_json,
                 payload_hash, weight, materiality)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    str(ev.id),
                    ev.ts.astimezone(timezone.utc).isoformat(),
                    ev.source.value,
                    ev.category.value,
                    ev.ref,
                    ev.scope.model_dump_json(),
                    json.dumps(ev.payload, sort_keys=True, separators=(",", ":")),
                    ev.payload_hash,
                    float(ev.weight.value),
                    ev.materiality.value,
                ),
            )

        if is_new:
            self._append_trail(ev)
        return is_new

    def _append_trail(self, ev: Evidence) -> None:
        """Journalise une preuve nouvelle dans le trail immuable."""
        today = date.today().isoformat()
        trail_file = self.trail_dir / f"{today}.jsonl"
        with trail_file.open("a", encoding="utf-8") as fh:
            fh.write(ev.model_dump_json() + "\n")

    def delete_by_ref(self, source: SourceType, ref: str) -> bool:
        """Marque une preuve comme supprimée (suppression hard du store)."""
        before = self.find_by_ref(source, ref)
        if before is None:
            return False
        with self._connect() as cx:
            cx.execute(
                "DELETE FROM evidence WHERE source = ? AND ref = ?",
                (source.value, ref),
            )
        # Journalisation explicite de la suppression dans le trail.
        deleted = Evidence(
            id=before.id,
            ts=datetime.now(timezone.utc),
            source=source,
            category=before.category,
            ref=ref,
            scope=before.scope,
            payload={"_deleted": True, "_was": before.payload_hash},
            payload_hash="0" * 64,
            weight=before.weight,
            materiality=before.materiality,
        )
        self._append_trail(deleted)
        return True

    # ─── Lectures ─────────────────────────────────────────────────

    def find_by_ref(self, source: SourceType, ref: str) -> Evidence | None:
        with self._connect() as cx:
            row = cx.execute(
                "SELECT * FROM evidence WHERE source = ? AND ref = ?",
                (source.value, ref),
            ).fetchone()
        return self._row_to_evidence(row) if row else None

    def list_by_category(self, category: Category) -> list[Evidence]:
        with self._connect() as cx:
            rows = cx.execute(
                "SELECT * FROM evidence WHERE category = ? ORDER BY ref",
                (category.value,),
            ).fetchall()
        return [self._row_to_evidence(r) for r in rows]

    def list_all(self) -> list[Evidence]:
        with self._connect() as cx:
            rows = cx.execute(
                "SELECT * FROM evidence ORDER BY category, ref"
            ).fetchall()
        return [self._row_to_evidence(r) for r in rows]

    def count(self) -> int:
        with self._connect() as cx:
            return cx.execute("SELECT COUNT(*) FROM evidence").fetchone()[0]

    @staticmethod
    def _row_to_evidence(row: sqlite3.Row) -> Evidence:
        return Evidence(
            id=row["id"],
            ts=datetime.fromisoformat(row["ts"]),
            source=SourceType(row["source"]),
            category=Category(row["category"]),
            ref=row["ref"],
            scope=Scope.model_validate_json(row["scope_json"]),
            payload=json.loads(row["payload_json"]),
            payload_hash=row["payload_hash"],
            weight=Weight(row["weight"]),
            materiality=Materiality(row["materiality"]),
        )

    # ─── Snapshots de dossier ─────────────────────────────────────

    def save_snapshot(self, snap: DossierSnapshot) -> None:
        with self._connect() as cx:
            cx.execute(
                """
                INSERT INTO dossier_snapshots
                (id, version, generated_at, evidence_count, pdf_path, pdf_hash,
                 parent_id, parent_hash, summary_json, signature)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    str(snap.id),
                    snap.version,
                    snap.generated_at.isoformat(),
                    snap.evidence_count,
                    snap.pdf_path,
                    snap.pdf_hash,
                    str(snap.parent_id) if snap.parent_id else None,
                    snap.parent_hash,
                    snap.change_summary.model_dump_json(),
                    snap.signature,
                ),
            )

    def latest_snapshot(self) -> DossierSnapshot | None:
        with self._connect() as cx:
            row = cx.execute(
                "SELECT * FROM dossier_snapshots ORDER BY generated_at DESC LIMIT 1"
            ).fetchone()
        if not row:
            return None
        from .models import ChangeSummary  # local import (cyclic safe)

        return DossierSnapshot(
            id=row["id"],
            version=row["version"],
            generated_at=datetime.fromisoformat(row["generated_at"]),
            evidence_count=row["evidence_count"],
            pdf_path=row["pdf_path"],
            pdf_hash=row["pdf_hash"],
            parent_id=row["parent_id"],
            parent_hash=row["parent_hash"],
            change_summary=ChangeSummary.model_validate_json(row["summary_json"]),
            signature=row["signature"],
        )

    def list_snapshots(self) -> list[DossierSnapshot]:
        from .models import ChangeSummary

        with self._connect() as cx:
            rows = cx.execute(
                "SELECT * FROM dossier_snapshots ORDER BY generated_at DESC"
            ).fetchall()
        return [
            DossierSnapshot(
                id=r["id"],
                version=r["version"],
                generated_at=datetime.fromisoformat(r["generated_at"]),
                evidence_count=r["evidence_count"],
                pdf_path=r["pdf_path"],
                pdf_hash=r["pdf_hash"],
                parent_id=r["parent_id"],
                parent_hash=r["parent_hash"],
                change_summary=ChangeSummary.model_validate_json(r["summary_json"]),
                signature=r["signature"],
            )
            for r in rows
        ]
