"""Interface abstraite des watchers."""

from __future__ import annotations

import logging
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from time import perf_counter

from ..models import Evidence, SourceType
from ..storage import EvidenceStore

logger = logging.getLogger(__name__)


@dataclass
class WatcherResult:
    """Résumé d'une exécution de watcher.

    Cette structure est *retournée* (non émise) afin que l'orchestrateur
    puisse mesurer la durée, le nombre de preuves nouvelles, et
    journaliser le résultat.
    """

    source: SourceType
    duration_s: float
    evidence_collected: int = 0
    evidence_new: int = 0
    evidence_unchanged: int = 0
    errors: list[str] = field(default_factory=list)

    @property
    def succeeded(self) -> bool:
        return not self.errors


class BaseWatcher(ABC):
    """Classe de base de tout watcher.

    Sous-classes :

    * surchargent :meth:`collect` pour produire les Evidence ;
    * sont identifiées par :attr:`source` (énum :class:`SourceType`).

    L'orchestrateur instancie le watcher avec un :class:`EvidenceStore`
    et appelle :meth:`run`. Aucune autre interface n'est publique.
    """

    source: SourceType  # à définir par la sous-classe

    def __init__(self, store: EvidenceStore) -> None:
        self.store = store
        self.log = logging.getLogger(f"homo_ci.watchers.{self.source.value}")

    @abstractmethod
    def collect(self) -> list[Evidence]:
        """Collecte les preuves courantes depuis la source.

        Cette méthode doit être *idempotente* et *sans effet de bord*
        autre que la lecture de la source externe.
        """

    def run(self) -> WatcherResult:
        """Exécute le watcher : collecte, stockage, comparaison, métriques."""
        t0 = perf_counter()
        result = WatcherResult(source=self.source, duration_s=0.0)

        try:
            current = self.collect()
        except Exception as exc:  # noqa: BLE001
            self.log.exception("Watcher failure during collect()")
            result.errors.append(str(exc))
            result.duration_s = perf_counter() - t0
            return result

        result.evidence_collected = len(current)

        # Détection : ce qui était présent et ne l'est plus → suppression.
        previous_refs = {e.ref for e in self.store.list_all() if e.source == self.source}
        current_refs = {e.ref for e in current}
        deleted_refs = previous_refs - current_refs

        for ref in deleted_refs:
            if self.store.delete_by_ref(self.source, ref):
                self.log.info("Deleted evidence: source=%s ref=%s", self.source.value, ref)

        for ev in current:
            try:
                is_new = self.store.upsert(ev)
                if is_new:
                    result.evidence_new += 1
                else:
                    result.evidence_unchanged += 1
            except Exception as exc:  # noqa: BLE001
                self.log.error("Failed to store evidence ref=%s: %s", ev.ref, exc)
                result.errors.append(f"{ev.ref}: {exc}")

        result.duration_s = perf_counter() - t0
        self.log.info(
            "Watcher %s done in %.2fs : %d collected (%d new, %d unchanged, %d deleted)",
            self.source.value,
            result.duration_s,
            result.evidence_collected,
            result.evidence_new,
            result.evidence_unchanged,
            len(deleted_refs),
        )
        return result
