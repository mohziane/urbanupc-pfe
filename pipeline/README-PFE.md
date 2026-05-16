# HOMO-CI — Pipeline d'homologation continue

Outil Python qui régénère chaque nuit un dossier d'homologation aligné
sur la réalité du SI.

## Architecture en six couches

```
Collect → Store → Diff → Render → Publish → Govern
```

| Couche       | Rôle                                                          |
|--------------|---------------------------------------------------------------|
| **Collect**  | Watchers (NSG, CVE, AD, Wazuh) qui produisent des `Evidence`. |
| **Store**    | SQLite (état) + JSONL (snapshots) + canonicalisation SHA-256. |
| **Diff**     | Compare deux snapshots, produit des `Change` (created/updated/deleted). |
| **Render**   | Templates Jinja2 → Markdown → PDF (WeasyPrint).               |
| **Publish**  | Signe (SHA-256) et stocke le dossier daté.                    |
| **Govern**   | Cron GitHub Actions, CLI Click, logs Rich.                    |

## Démarrage

```bash
cd pipeline
python3.11 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt

export AZURE_SUBSCRIPTION_ID=...
export RESOURCE_GROUP=RG-PFE-SOC

homo-ci collect    # Inventaire Azure + CVE + AD + SIEM
homo-ci diff       # Compare avec le snapshot précédent
homo-ci render     # Génère le dossier Markdown
homo-ci publish    # Signe et exporte en PDF
```

Sortie : `build/dossier-AAAAMMJJ-HHMMSS.pdf`.

## Performances mesurées sur le lab

- Cycle complet : **~90 s** (dont 70 % en appels API Azure).
- Latence détection d'un changement NSG : **4 à 6 s** en mode déclenché,
  **13,5 h** en moyenne en mode nightly.
- Couverture des preuves attendues : **61,5 %** sur le périmètre technique.

Voir `memoire/chapters/08-resultats.tex` pour les chiffres détaillés.

## Stack

`pydantic >= 2.5`, `azure-identity`, `azure-mgmt-network`, `azure-mgmt-resource`,
`jinja2`, `pypandoc`, `weasyprint`, `click`, `rich`.
