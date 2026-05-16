"""Couche 1 — Watchers (collecteurs de preuves).

Chaque watcher est un module Python implémentant la classe abstraite
:class:`BaseWatcher`. Au démarrage, le watcher prend un *snapshot
courant* de la source qu'il surveille, le compare au snapshot
précédent (stocké dans l'EvidenceStore), et produit les Evidence
correspondantes.

Le contrat est strict :

* Un watcher est **idempotent** : exécuter deux fois sans
  changement externe ne produit aucune Evidence nouvelle.
* Un watcher est **sans état** : tout l'état persistant est dans
  l'EvidenceStore.
* Un watcher est **résilient** : une exception sur une preuve
  individuelle n'interrompt pas la collecte.
"""
from .base import BaseWatcher, WatcherResult

__all__ = ["BaseWatcher", "WatcherResult"]
