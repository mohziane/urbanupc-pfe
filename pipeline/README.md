# HOMO-CI — Pipeline d'homologation continue

> Implémentation de référence de l'architecture décrite dans
> `roadmap/T-9.1-pipeline-architecture.md`. Composant central du
> PFE UrbanUpC, instrumentant les hypothèses **H1** (couverture),
> **H2** (fraîcheur) et **H3** (densité informationnelle).

## Principes directeurs

1. **Single source of truth** : l'état attendu est en code (git) ;
   l'écart avec l'état réel est une dérive.
2. **Idempotence** : exécuter deux fois produit le même état.
3. **Traçabilité totale** : chaîne de hash SHA-256 entre dossiers,
   horodatage signé, journal append-only versionné git.
4. **Découplage par messages** : les watchers communiquent via une
   *evidence stream* (JSON Lines) et un *evidence store* (SQLite),
   sans appels directs.
5. **Sécurité du pipeline** : application des contrôles à l'outil
   lui-même (moindre privilège, secrets en vault, signature).
6. **Compatibilité ANSSI** : la sortie alimente la structure du
   dossier d'homologation (pas un format inventé).

## Architecture

```
┌────────────────────────────────────────────────────────────┐
│ Couche 6 — Gouvernance (KRI, alertes)                      │
├────────────────────────────────────────────────────────────┤
│ Couche 5 — Publication (PDF signé + hash chain + Blob)     │
├────────────────────────────────────────────────────────────┤
│ Couche 4 — Génération (Jinja2 + pandoc + weasyprint)       │
├────────────────────────────────────────────────────────────┤
│ Couche 3 — Détection de changement (Diff Engine)           │
├────────────────────────────────────────────────────────────┤
│ Couche 2 — Normalisation & stockage (SQLite + JSONL)       │
├────────────────────────────────────────────────────────────┤
│ Couche 1 — Collecte (watchers parallèles)                  │
└────────────────────────────────────────────────────────────┘
```

## Démarrage rapide

```bash
# Installation
python3.12 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt

# Configuration (variables d'env Azure)
export AZURE_SUBSCRIPTION_ID=...
export RESOURCE_GROUP=RG-PFE-SOC

# Exécution d'un cycle complet
make pipeline-run

# Résultat : build/dossier-AAAAMMJJ-HHMMSS.pdf
```

## Structure du dépôt

```
pipeline/
├── homo_ci/                  Package Python (cœur du pipeline)
│   ├── models.py             Modèles Evidence, Change, DossierSnapshot
│   ├── storage.py            SQLite Evidence Store + JSONL trail
│   ├── watchers/             Collecteurs de preuves
│   │   ├── base.py           Interface BaseWatcher
│   │   ├── wc_nsg.py         Azure Network Security Groups
│   │   ├── wc_cve.py         Trivy → CVE images Docker
│   │   ├── wc_ad.py          Active Directory inventory
│   │   └── wc_asvs.py        OWASP ASVS L2 conformité
│   ├── diff.py               Moteur de comparaison de snapshots
│   ├── render.py             Renderer Jinja2 → Markdown
│   ├── sign.py               Hash chain SHA-256 + signature
│   ├── cli.py                Point d'entrée CLI
│   └── tests/                Tests unitaires
├── templates/                Templates de dossier (Jinja2)
├── styles/                   CSS de mise en page
├── scripts/                  Scripts d'orchestration
├── docs/                     Documentation
├── .github/workflows/        CI/CD GitHub Actions
├── Makefile                  Orchestration locale
├── pyproject.toml            Configuration Python
└── requirements.txt          Dépendances
```

## Validation H1 / H2 / H3

Ce pipeline est **instrumenté** pour produire les mesures
expérimentales définies dans `roadmap/T-1.2-FINAL-hypotheses-protocole.md` :

- **H1 (couverture)** : la couverture est mesurée à chaque
  exécution par comparaison entre preuves collectées
  (classification A/B/C) et catalogue de référence (ANSSI + ISO
  27002 + ASVS).
- **H2 (fraîcheur)** : chaque Evidence porte un timestamp précis
  permettant de mesurer `Δt = t_dossier − t_source`.
- **H3 (densité informationnelle)** : l'indicateur `TA` (Taux
  d'Alignement) est calculé à chaque snapshot.

## Sécurité du pipeline lui-même

Cinq menaces sont adressées (cf. T-9.1 §IX) :

| Menace | Contrôle |
|---|---|
| Compromission clé GPG | Stockage en Key Vault, jamais exportée |
| Modification non autorisée des templates | Branche `main` protégée + review PR |
| Pipeline désactivé silencieusement | Heartbeat alert si pas de run ≥ 26h |
| Empoisonnement watcher | Hash chain Evidence ; vérification cohérence |
| Fuite RGPD | Redaction automatique sur champs sensibles |

## Références

- Architecture : `../roadmap/T-9.1-pipeline-architecture.md`
- Hypothèses : `../roadmap/T-1.2-FINAL-hypotheses-protocole.md`
- État de l'art : `../roadmap/PHASE-2-ETAT-DE-L-ART.md`
