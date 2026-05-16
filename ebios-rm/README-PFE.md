# Analyse EBIOS RM — UrbanUpC

Analyse de risques selon la méthode **EBIOS Risk Manager** (ANSSI, 2018) du SI
fictif UrbanUpC.

## Contenu

```
ebios-rm/
├── content/        Sources Markdown des cinq ateliers
├── assets/         Images, schémas, tableaux
├── styles/         CSS / templates pour rendu PDF
├── metadata.yaml   Métadonnées (titre, auteur, version)
└── Makefile        Compilation Markdown → PDF (pandoc + weasyprint)
```

## Compilation

```bash
cd ebios-rm
make
open build/ebios-rm-urbanupc.pdf
```

## Les cinq ateliers

1. **Atelier 1** — Cadrage et socle de sécurité.
2. **Atelier 2** — Sources de risque et objectifs visés.
3. **Atelier 3** — Scénarios stratégiques.
4. **Atelier 4** — Scénarios opérationnels.
5. **Atelier 5** — Traitement du risque (plan d'actions).

## Lien avec HOMO-CI

Les valeurs métier identifiées en Atelier 1 et les événements redoutés en Atelier 2
servent de base pour cataloguer les **100 preuves attendues** que HOMO-CI tente
de collecter automatiquement.
