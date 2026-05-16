"""Tests unitaires des modèles de données."""

from __future__ import annotations

import hashlib
import json

import pytest

from homo_ci.models import (
    Category,
    Evidence,
    Materiality,
    Scope,
    SourceType,
    Weight,
)


class TestScope:
    def test_valid_anssi_steps(self) -> None:
        s = Scope(anssi_steps=(1, 5, 9), iso_controls=("8.20",))
        assert s.anssi_steps == (1, 5, 9)
        assert s.iso_controls == ("8.20",)

    def test_invalid_anssi_step_rejected(self) -> None:
        with pytest.raises(ValueError):
            Scope(anssi_steps=(0, 5))
        with pytest.raises(ValueError):
            Scope(anssi_steps=(10,))

    def test_scope_is_frozen(self) -> None:
        s = Scope(anssi_steps=(1,))
        with pytest.raises(Exception):
            s.anssi_steps = (2,)  # type: ignore[misc]


class TestEvidence:
    def test_from_payload_calculates_hash(self) -> None:
        payload = {"key": "value", "n": 42}
        ev = Evidence.from_payload(
            source=SourceType.NSG,
            category=Category.NSG,
            ref="ng-test/rule-1",
            payload=payload,
        )
        canonical = json.dumps(payload, sort_keys=True, separators=(",", ":"))
        expected = hashlib.sha256(canonical.encode("utf-8")).hexdigest()
        assert ev.payload_hash == expected

    def test_hash_is_deterministic(self) -> None:
        """Deux Evidence construites avec le même payload ont le même hash."""
        p = {"a": 1, "b": [2, 3], "c": {"x": True}}
        e1 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", p)
        e2 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", p)
        assert e1.payload_hash == e2.payload_hash

    def test_hash_changes_with_payload(self) -> None:
        e1 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {"a": 1})
        e2 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {"a": 2})
        assert e1.payload_hash != e2.payload_hash

    def test_hash_invariant_to_key_order(self) -> None:
        """Le hash doit être indépendant de l'ordre des clés."""
        e1 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {"a": 1, "b": 2})
        e2 = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {"b": 2, "a": 1})
        assert e1.payload_hash == e2.payload_hash

    def test_weight_defaults_to_auto(self) -> None:
        ev = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {})
        assert ev.weight == Weight.AUTO

    def test_evidence_is_immutable(self) -> None:
        ev = Evidence.from_payload(SourceType.NSG, Category.NSG, "r", {})
        with pytest.raises(Exception):
            ev.ref = "other"  # type: ignore[misc]
