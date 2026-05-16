"""Tests unitaires du Diff Engine."""

from __future__ import annotations

from pathlib import Path

import pytest

from homo_ci.diff import diff, load_snapshot, snapshot_current, summarize
from homo_ci.models import Category, ChangeType, Evidence, SourceType
from homo_ci.storage import EvidenceStore


@pytest.fixture()
def store(tmp_path: Path) -> EvidenceStore:
    return EvidenceStore(tmp_path / "s")


def _ev(ref: str, payload: dict) -> Evidence:
    return Evidence.from_payload(SourceType.NSG, Category.NSG, ref, payload)


class TestDiff:
    def test_no_changes(self) -> None:
        prev = {(SourceType.NSG.value, "r"): _ev("r", {"v": 1})}
        curr = {(SourceType.NSG.value, "r"): _ev("r", {"v": 1})}
        assert diff(prev, curr) == []

    def test_created(self) -> None:
        prev: dict = {}
        curr = {(SourceType.NSG.value, "r"): _ev("r", {})}
        result = diff(prev, curr)
        assert len(result) == 1
        assert result[0].type == ChangeType.CREATED

    def test_deleted(self) -> None:
        prev = {(SourceType.NSG.value, "r"): _ev("r", {})}
        curr: dict = {}
        result = diff(prev, curr)
        assert len(result) == 1
        assert result[0].type == ChangeType.DELETED

    def test_updated(self) -> None:
        prev = {(SourceType.NSG.value, "r"): _ev("r", {"v": 1})}
        curr = {(SourceType.NSG.value, "r"): _ev("r", {"v": 2})}
        result = diff(prev, curr)
        assert len(result) == 1
        assert result[0].type == ChangeType.UPDATED

    def test_summary(self) -> None:
        prev = {
            (SourceType.NSG.value, "a"): _ev("a", {}),
            (SourceType.NSG.value, "b"): _ev("b", {}),
        }
        curr = {
            (SourceType.NSG.value, "a"): _ev("a", {"updated": True}),
            (SourceType.NSG.value, "c"): _ev("c", {}),
        }
        summary = summarize(diff(prev, curr))
        assert summary.created == 1  # c
        assert summary.updated == 1  # a
        assert summary.deleted == 1  # b


class TestSnapshot:
    def test_roundtrip(self, store: EvidenceStore, tmp_path: Path) -> None:
        store.upsert(_ev("a", {"x": 1}))
        store.upsert(_ev("b", {"x": 2}))
        snap_file = tmp_path / "snap.jsonl"
        n = snapshot_current(store, snap_file)
        assert n == 2

        loaded = load_snapshot(snap_file)
        assert len(loaded) == 2
        assert (SourceType.NSG.value, "a") in loaded
        assert loaded[(SourceType.NSG.value, "a")].payload == {"x": 1}
