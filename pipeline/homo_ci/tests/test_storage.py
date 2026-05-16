"""Tests unitaires de l'EvidenceStore."""

from __future__ import annotations

from pathlib import Path

import pytest

from homo_ci.models import Category, Evidence, SourceType
from homo_ci.storage import EvidenceStore


@pytest.fixture()
def store(tmp_path: Path) -> EvidenceStore:
    return EvidenceStore(tmp_path / "store")


def _mk_evidence(ref: str, payload: dict | None = None) -> Evidence:
    return Evidence.from_payload(
        source=SourceType.NSG,
        category=Category.NSG,
        ref=ref,
        payload=payload or {"name": ref, "version": 1},
    )


class TestUpsert:
    def test_first_insert_is_new(self, store: EvidenceStore) -> None:
        ev = _mk_evidence("ng-a/rule-1")
        is_new = store.upsert(ev)
        assert is_new is True
        assert store.count() == 1

    def test_identical_re_insert_is_not_new(self, store: EvidenceStore) -> None:
        payload = {"name": "rule-1", "version": 1}
        ev1 = _mk_evidence("ng-a/rule-1", payload=payload)
        ev2 = _mk_evidence("ng-a/rule-1", payload=payload)
        store.upsert(ev1)
        is_new = store.upsert(ev2)
        assert is_new is False
        assert store.count() == 1

    def test_different_payload_is_new_update(self, store: EvidenceStore) -> None:
        ev1 = _mk_evidence("ng-a/rule-1", payload={"v": 1})
        ev2 = _mk_evidence("ng-a/rule-1", payload={"v": 2})
        store.upsert(ev1)
        is_new = store.upsert(ev2)
        assert is_new is True
        assert store.count() == 1
        found = store.find_by_ref(SourceType.NSG, "ng-a/rule-1")
        assert found is not None
        assert found.payload["v"] == 2


class TestDelete:
    def test_delete_existing(self, store: EvidenceStore) -> None:
        store.upsert(_mk_evidence("r"))
        assert store.delete_by_ref(SourceType.NSG, "r") is True
        assert store.find_by_ref(SourceType.NSG, "r") is None

    def test_delete_unknown(self, store: EvidenceStore) -> None:
        assert store.delete_by_ref(SourceType.NSG, "unknown") is False


class TestQueries:
    def test_list_by_category(self, store: EvidenceStore) -> None:
        store.upsert(_mk_evidence("rule-1"))
        store.upsert(_mk_evidence("rule-2"))
        items = store.list_by_category(Category.NSG)
        assert {e.ref for e in items} == {"rule-1", "rule-2"}

    def test_list_all_sorted(self, store: EvidenceStore) -> None:
        store.upsert(_mk_evidence("b"))
        store.upsert(_mk_evidence("a"))
        items = store.list_all()
        assert [e.ref for e in items] == ["a", "b"]


class TestTrail:
    def test_trail_file_created(self, store: EvidenceStore, tmp_path: Path) -> None:
        store.upsert(_mk_evidence("r"))
        files = list(store.trail_dir.glob("*.jsonl"))
        assert len(files) == 1
        content = files[0].read_text().strip().splitlines()
        assert len(content) == 1

    def test_trail_append_only_on_new_or_updated(self, store: EvidenceStore) -> None:
        ev_a = _mk_evidence("r", payload={"v": 1})
        ev_b = _mk_evidence("r", payload={"v": 1})  # identique
        ev_c = _mk_evidence("r", payload={"v": 2})  # updated
        store.upsert(ev_a)
        store.upsert(ev_b)
        store.upsert(ev_c)
        files = list(store.trail_dir.glob("*.jsonl"))
        content = files[0].read_text().strip().splitlines()
        # 1 ligne pour création, 0 pour identique, 1 pour update = 2 lignes
        assert len(content) == 2
