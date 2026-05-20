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


### Déployer le lab

```bash
cd lab/infra
# Renseigner les variables Azure (subscription, RG, location) puis :
./azure-deploy.sh            # Crée RG, VNet, 3 subnets, 3 NSG, 3 VMs
# Les setup spécifiques par VM sont injectés au déploiement via cloud-init
# ou rejoués manuellement : vm-web01-setup.sh, vm-siem01-setup.sh, vm-dc01-setup.ps1
```

### Lancer un cycle HOMO-CI

```bash
cd pipeline
python3.11 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
make pipeline
# Sortie : build/dossier-AAAAMMJJ-HHMMSS.pdf (signé CMS + horodaté RFC 3161)
```

## Stack technique

- **Infra** : Azure (RG-PFE-SOC, swedencentral), 3 VMs Linux/Windows, NSG
- **SOC** : Wazuh 4.9.2 all-in-one (manager + indexer + dashboard)
- **AD** : Active Directory `corpnet.local`, 7 utilisateurs métier (6 OUs)
- **Applis** : PHP (CorpNet, vulnérable XSS/SQLi/IDOR), Node.js/Express (MaFormation, MaCandidature)
- **Pipeline** : Python 3.11, Pydantic v2, Click, SQLite, JSONL, Jinja2, WeasyPrint
- **Scellement** : SHA-256 chaînée + signature CMS (CA HOMO-CI) + horodatage RFC 3161 (FreeTSA)
- **CI** : GitHub Actions (cycle nightly, 22h00 UTC)

## Coût

Sous 100 € de crédit étudiant Azure pour les trois semaines de mesure.

## Auteur

Mohamed Yassine Ziane Berroudja — `mohziane02@gmail.com`
Encadrement : Université Paris Cité (Lyes Khoukhi, tuteur universitaire ;
Arnaud Lehnen, tuteur professionnel).

## Licence

Mémoire et documentation sous CC-BY-NC 4.0. Code sous MIT.
