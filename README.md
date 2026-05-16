# UrbanUpC SOC + HOMO-CI — PFE

Projet de fin d'études (M2 Cybersécurité, Université Paris Cité, promotion 2026).
Lab SOC complet déployé sur Azure et outil d'automatisation d'homologation ANSSI.

## Vue d'ensemble

Le PFE livre deux choses concrètes :

1. **Un lab SOC complet** (UrbanUpC) : 3 VMs Azure, Active Directory, SIEM Wazuh,
   applications volontairement vulnérables, attaques rejouées et détectées.
2. **Un outil d'automatisation** (HOMO-CI) : pipeline Python en six couches qui
   régénère chaque nuit un dossier d'homologation aligné sur la réalité du SI.

## Arborescence

```
urbanupc-pfe/
├── lab/          Infrastructure UrbanUpC (Azure, Wazuh, AD, applications)
├── pipeline/     Outil HOMO-CI (Python, watchers, diff, rendu, publication)
├── ebios-rm/     Analyse de risques EBIOS RM du SI UrbanUpC
└── memoire/      Mémoire LaTeX du PFE (sources + PDF compilé)
```

Chaque dossier contient son propre `README.md` détaillé.

## Démarrage rapide

### Compiler le mémoire

```bash
cd memoire
make
open memoire.pdf
```

### Déployer le lab

```bash
cd lab/infra
# Renseigner les variables Azure (subscription, RG, location)
./deploy-vm-web01.sh
./deploy-vm-dc01.sh
./deploy-vm-siem01.sh
```

### Lancer un cycle HOMO-CI

```bash
cd pipeline
python3.11 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
make pipeline-run
# Sortie : build/dossier-AAAAMMJJ-HHMMSS.pdf
```

## Stack technique

- **Infra** : Azure (RG-PFE-SOC, swedencentral), 3 VMs Linux/Windows, NSG
- **SOC** : Wazuh 4.9.2 all-in-one (manager + indexer + dashboard)
- **AD** : Active Directory `corpnet.local`, ~20 utilisateurs
- **Applis** : PHP (CorpNet, vulnérable), Node.js/Express (MaFormation, MaCandidature)
- **Pipeline** : Python 3.11, Pydantic v2, Click, SQLite, JSONL, Jinja2, WeasyPrint
- **CI** : GitHub Actions (cycle nightly, 03h00 UTC)

## Coût

Sous 275 € de crédit étudiant Azure pour les trois semaines de mesure.

## Auteur

Mohamed Ziane Bouziane — `mohziane02@gmail.com`
Encadrement : Université Paris Cité.

## Licence

Mémoire et documentation sous CC-BY-NC 4.0. Code sous MIT.
