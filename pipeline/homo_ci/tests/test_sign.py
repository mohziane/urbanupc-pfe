"""Tests du Signer (hash chain)."""

from __future__ import annotations

import hashlib
from pathlib import Path

import pytest

from homo_ci.models import ChangeSummary
from homo_ci.sign import create_snapshot, hash_file, verify_chain
from homo_ci.storage import EvidenceStore


@pytest.fixture()
def store(tmp_path: Path) -> EvidenceStore:
    return EvidenceStore(tmp_path / "s")


def _mk_pdf(path: Path, content: bytes) -> Path:
    path.write_bytes(content)
    return path


class TestHashFile:
    def test_hash_known_content(self, tmp_path: Path) -> None:
        p = _mk_pdf(tmp_path / "f.pdf", b"hello world")
        expected = hashlib.sha256(b"hello world").hexdigest()
        assert hash_file(p) == expected

    def test_hash_large_file_streaming(self, tmp_path: Path) -> None:
        p = _mk_pdf(tmp_path / "big.pdf", b"x" * (200 * 1024))
        # Doit gérer sans tout charger en mémoire.
        h = hash_file(p)
        assert len(h) == 64


class TestSnapshotChain:
    def test_first_snapshot_has_no_parent(self, store: EvidenceStore, tmp_path: Path) -> None:
        pdf = _mk_pdf(tmp_path / "d1.pdf", b"v1")
        snap = create_snapshot(store, pdf, "V1", ChangeSummary(created=1))
        assert snap.parent_hash is None
        assert snap.signature is not None

    def test_second_snapshot_references_first(self, store: EvidenceStore, tmp_path: Path) -> None:
        pdf1 = _mk_pdf(tmp_path / "d1.pdf", b"v1")
        snap1 = create_snapshot(store, pdf1, "V1", ChangeSummary(created=1))

        pdf2 = _mk_pdf(tmp_path / "d2.pdf", b"v2")
        snap2 = create_snapshot(store, pdf2, "V2", ChangeSummary(updated=1))

        assert snap2.parent_hash == snap1.pdf_hash
        assert snap2.parent_id == snap1.id

    def test_verify_chain_ok(self, store: EvidenceStore, tmp_path: Path) -> None:
        for i in range(3):
            pdf = _mk_pdf(tmp_path / f"d{i}.pdf", f"v{i}".encode())
            create_snapshot(store, pdf, f"V{i}", ChangeSummary())
        assert verify_chain(store) is True
