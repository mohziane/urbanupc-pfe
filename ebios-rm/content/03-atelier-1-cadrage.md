<div class="atelier" id="atelier-1">

# Cadrage et socle de sécurité

## 1.1 Cadrage de l'étude

### 1.1.1 Périmètre fonctionnel

Le périmètre de l'étude couvre **l'Espace Numérique de Travail
UrbanUpC** dans sa configuration de mai 2026, soit :

- le **portail public CorpNet**, exposant l'image institutionnelle de
  l'université, les annonces, la candidature aux formations, et un
  catalogue de services ;
- l'application **MaFormation**, portail authentifié dédié aux
  étudiants inscrits, offrant la consultation des cours, de l'emploi
  du temps, des notes et la gestion des documents personnels ;
- l'application **MaCandidature**, espace dédié aux candidats à
  l'inscription en M1/M2, permettant la création de compte, le dépôt
  de pièces et le suivi du dossier ;
- le **domaine Active Directory** `corpnet.local`, support
  d'authentification fédérée pour les utilisateurs internes ;
- le **SOC** assurant la détection des incidents : SIEM Wazuh 4.9.2,
  agents Wazuh sur les hôtes, règles de corrélation personnalisées.

### 1.1.2 Périmètre technique

Le périmètre technique englobe l'ensemble des ressources hébergées
sur la souscription Microsoft Azure de l'établissement, dans le
*Resource Group* `RG-PFE-SOC`, région *Sweden Central*. Sont
exclus :

- les postes utilisateurs (clients étudiants, candidats, personnel) —
  hors périmètre de cette analyse ;
- les infrastructures du fournisseur Microsoft Azure — gouvernées par
  l'accord de service Azure et hors champ d'audit direct ;
- les services tiers de fédération éventuels (RENATER, eduGAIN) — non
  intégrés dans la version courante.

### 1.1.3 Objectifs de l'analyse

L'analyse poursuit trois objectifs :

1. **Identifier** les biens supports, événements redoutés et chemins
   d'attaque pertinents pour le périmètre considéré.
2. **Coter** la gravité et la vraisemblance des scénarios, et en
   déduire le niveau de risque.
3. **Proposer** un plan d'amélioration continue de la sécurité (PACS)
   et évaluer le niveau de risque résiduel post-traitement, pour
   informer la décision d'homologation.

### 1.1.4 Participants et gouvernance

L'analyse est conduite par le Risk Manager de la Direction
Cybersécurité d'UrbanUpC. La validation interne fait intervenir :

| Acteur | Rôle |
|---|---|
| AQSSI | Autorité d'homologation, décision finale |
| RSSI | Validation technique du périmètre et des choix de cotation |
| DPO | Avis sur les valeurs métier liées aux données personnelles |
| Architecte SI | Validation de l'inventaire des biens supports |
| Responsable SOC | Validation des scénarios opérationnels et de la détection |

Table 1.1 : Acteurs intervenant dans l'analyse.

### 1.1.5 Calendrier

L'étude est conduite sur une fenêtre courte adaptée au contexte
projet :

| Phase | Durée | Livrable intermédiaire |
|---|---|---|
| Atelier 1 — Cadrage | 1 jour | Périmètre, VM, BS, ER, socle |
| Atelier 2 — Sources / Objectifs | 0,5 jour | Couples SR × OV |
| Atelier 3 — Stratégiques | 0,5 jour | Scénarios stratégiques |
| Atelier 4 — Opérationnels | 1 jour | Scénarios opérationnels |
| Atelier 5 — Traitement | 1 jour | PACS, risques résiduels |
| Revue interne et validation | 1 jour | Document final |

## 1.2 Identification des valeurs métier

Une **valeur métier** désigne, au sens d'EBIOS RM, une mission ou une
information sensible dont la dégradation entraînerait un préjudice
significatif pour l'organisation. Sept valeurs métier ont été
identifiées pour le périmètre UrbanUpC.

| Code | Désignation | Catégorie | Description |
|---|---|---|---|
| **VM-1** | Continuité d'accès à l'ENT | Mission | Disponibilité 24×7 du portail public et des services authentifiés |
| **VM-2** | Confidentialité des dossiers de candidature | Information | Données personnelles, CV, lettres, relevés (RGPD, art. 6 et 9) |
| **VM-3** | Confidentialité et intégrité du dossier scolaire | Information | Notes, validations, transcripts (RGPD + impact réputation institutionnelle) |
| **VM-4** | Capacité d'audit et de traçabilité | Mission | Conservation et intégrité des journaux SIEM pour réponse à incident et conformité |
| **VM-5** | Image et confiance institutionnelle | Mission | Réputation auprès des étudiants, candidats, partenaires académiques |
| **VM-6** | Conformité réglementaire | Mission | RGPD, RGS, NIS 2, recommandations ANSSI |
| **VM-7** | Sécurité du processus d'admission | Mission | Garantie d'équité et de non-falsification des décisions d'admission |

Table 1.2 : Valeurs métier identifiées (VM-1 à VM-7).

### Précision sur VM-3 « dossier scolaire »

La valeur métier VM-3 est particulièrement sensible : la
**modification frauduleuse d'une note** porte atteinte à la fois à
la confidentialité (révélation des notes d'autrui), à l'intégrité
(altération volontaire des résultats), et à la *confiance* dans la
fonction même de l'établissement. Le critère **intégrité** y est
prépondérant.

### Précision sur VM-4 « traçabilité »

La valeur métier VM-4 est un *enabler* : le SOC ne constitue pas une
*mission métier* au sens classique, mais sans lui, la détection et la
réponse aux atteintes des autres VM s'effondrent. La compromission
ciblée du SOC (suppression de journaux, désactivation de règles)
constitue donc un événement redouté de premier plan.

## 1.3 Identification des biens supports

Un **bien support** désigne, au sens d'EBIOS RM, un composant
technique, humain ou organisationnel qui *porte* tout ou partie d'une
valeur métier. Dix biens supports ont été identifiés.

| Code | Désignation | Type | VM associées |
|---|---|---|---|
| **BS-1** | Souscription Azure / *Resource Group* `RG-PFE-SOC` | Infrastructure | toutes |
| **BS-2** | Réseau virtuel `vnet-pfe` et NSG | Réseau | toutes |
| **BS-3** | `vm-web01` (Ubuntu) + stack CorpNet (Apache, PHP, MySQL) | Système | VM-1, VM-5 |
| **BS-4** | `vm-dc01` (Windows Server 2022) + AD `corpnet.local` | Identité | VM-1 à VM-7 |
| **BS-5** | Conteneurs `internal-apps` (MaFormation, MaCandidature, bases PG) | Application | VM-1, VM-2, VM-3, VM-7 |
| **BS-6** | `vm-siem01` (Wazuh manager + indexer + dashboard) | Sécurité | VM-4, VM-6 |
| **BS-7** | Comptes utilisateurs et secrets (AD, Docker secrets, certificats internes) | Identité | toutes |
| **BS-8** | Postes administrateurs (Mac M2, clés SSH, scripts) | Humain / poste | toutes |
| **BS-9** | Dépôt de code et pipelines de déploiement (Git, scripts Azure CLI) | Logiciel | toutes |
| **BS-10** | Documentation, runbooks, dossier d'homologation | Organisationnel | VM-4, VM-6 |

Table 1.3 : Biens supports identifiés (BS-1 à BS-10).

### Cartographie VM × BS

| BS \\ VM | VM-1 | VM-2 | VM-3 | VM-4 | VM-5 | VM-6 | VM-7 |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| BS-1 Azure | ● | ● | ● | ● | ● | ● | ● |
| BS-2 Réseau | ● | ● | ● | ● | ● | — | ● |
| BS-3 CorpNet *legacy* | ● | — | — | — | ● | — | — |
| BS-4 AD `corpnet.local` | ● | ● | ● | ● | ● | ● | ● |
| BS-5 internal-apps | ● | ● | ● | — | ● | ● | ● |
| BS-6 SIEM Wazuh | — | — | — | ● | — | ● | — |
| BS-7 Identités / secrets | ● | ● | ● | ● | ● | ● | ● |
| BS-8 Postes admin | — | ● | ● | ● | ● | ● | ● |
| BS-9 Code / pipelines | ● | ● | ● | ● | ● | ● | ● |
| BS-10 Doc. organisationnelle | — | — | — | ● | — | ● | — |

Table 1.4 : Cartographie de couverture VM × BS.

L'analyse de la cartographie révèle deux concentrations notables :

- **BS-4 (Active Directory)** porte la quasi-totalité des VM —
  ce qui le désigne comme **bien support critique** au sens du
  *Tier 0* dans la terminologie Microsoft ;
- **BS-7 (identités et secrets)** est également transverse à toutes
  les VM — toute compromission de ces éléments propage l'impact à
  l'ensemble du périmètre.

## 1.4 Identification des événements redoutés

Un **événement redouté** désigne, pour chaque VM, l'atteinte
qu'on souhaite éviter (au sens des critères DICT — *Disponibilité,
Intégrité, Confidentialité, Traçabilité*). Huit événements redoutés
ont été identifiés.

| Code | Désignation | VM impactée | Critère DICT | Gravité |
|---|---|---|---|---|
| **ER-1** | Vol massif des dossiers de candidature | VM-2 | C | **G3 Grave** |
| **ER-2** | Modification frauduleuse de notes étudiantes | VM-3 | I | **G4 Catastrophique** |
| **ER-3** | Indisponibilité du portail public pendant la période d'inscription | VM-1 | D | **G3 Grave** |
| **ER-4** | Compromission complète du domaine AD (escalade Domain Admin) | VM-1 à VM-7 | C, I, D, T | **G4 Catastrophique** |
| **ER-5** | Exfiltration de la base de données étudiants | VM-2, VM-3 | C | **G3 Grave** |
| **ER-6** | *Defacement* du portail public | VM-5 | I | **G2 Significative** |
| **ER-7** | Compromission du SIEM (perte de traçabilité, suppression d'alertes) | VM-4 | T, I | **G3 Grave** |
| **ER-8** | Utilisation de l'infrastructure pour rebond vers un tiers (image, responsabilité) | VM-5, VM-6 | — | **G3 Grave** |

Table 1.5 : Événements redoutés et cotation initiale de la gravité.

### Justification des cotations de gravité

#### ER-2 (G4 Catastrophique)

La modification frauduleuse de notes met en cause **le cœur même de
la mission de l'établissement** : la délivrance de diplômes
authentiques. L'impact dépasse le simple périmètre informatique :
contestation des diplômes émis, mise en cause de la qualité de la
formation, *de jure* incompatibilité avec la mission de service
public d'enseignement supérieur. La cotation G4 est justifiée.

#### ER-4 (G4 Catastrophique)

La compromission du domaine `corpnet.local` ouvre l'accès **à
l'ensemble** des biens supports identifiés (cf. cartographie VM × BS).
Un attaquant Domain Admin peut, par mouvement latéral, atteindre
n'importe quelle VM, falsifier les journaux, désactiver les agents
Wazuh, et reconstituer son anonymat. C'est l'événement redouté de
plus haut impact technique.

#### ER-1, ER-3, ER-5, ER-7, ER-8 (G3 Grave)

Ces cinq événements redoutés constituent une **deuxième strate** de
gravité. Ils portent des conséquences graves mais réversibles
(restauration des sauvegardes, notification CNIL, communication de
crise) sans atteinte définitive à la mission de l'établissement.

#### ER-6 (G2 Significative)

Le *defacement* du portail public produit un dommage réputationnel
court terme, mais ne porte pas atteinte aux données ni à l'intégrité
des fonctions critiques. La remédiation est rapide. La cotation G2
est en ligne avec les retours d'expérience sur des cas comparables
[ZIMMERMAN-2014, p. 217].

## 1.5 Socle de sécurité existant

Le **socle de sécurité** désigne, au sens d'EBIOS RM, l'ensemble des
mesures de sécurité **déjà en place** au moment de l'analyse. Il
constitue le point de départ pour évaluer la vraisemblance des
scénarios (atelier 4) et concevoir le plan de traitement (atelier 5).

### 1.5.1 Référentiels applicables

| Référentiel | Statut | Niveau de couverture |
|---|---|---|
| RGPD (UE 2016/679) | Obligatoire | Partiel — registre des traitements à formaliser |
| RGS v2.0 | Recommandé (administration) | Partiel — TLS, audit, segmentation |
| NIS 2 (UE 2022/2555) | Si OIV / OSE | Partiel — *reporting* incidents non automatisé |
| ANSSI — Guide AD (DAT-NT-17) | Bonne pratique | Bonne couverture sur AD |
| ANSSI — Guide Pare-feu (DAT-NT-006) | Bonne pratique | Bonne couverture sur NSG |
| OWASP ASVS v4.0.3 | Bonne pratique | Niveau L2 visé sur applications internes |
| CIS Docker Benchmark v1.6 | Bonne pratique | **Écart documenté** sur §5.31 (Docker socket) |

Table 1.6 : Référentiels et niveau de couverture.

### 1.5.2 Mesures de sécurité déjà en place

| Famille | Mesure | Statut |
|---|---|---|
| **Réseau** | Segmentation par sous-réseaux + NSG en liste blanche | ✅ |
| **Réseau** | Refus par défaut DMZ → LAN, LAN → Internet | ✅ |
| **Identité** | Authentification fédérée LDAP pour MaFormation | ✅ |
| **Identité** | Argon2id + verrouillage de compte pour MaCandidature | ✅ |
| **Identité** | Sysmon + PowerShell Script Block Logging sur AD | ✅ |
| **Identité** | Audit Kerberos avancé (4624, 4625, 4768, 4769, 4688 avec ligne de commande) | ✅ |
| **Réseau de transport** | TLS 1.2/1.3 sur tous les canaux | ✅ |
| **Réseau de transport** | LDAP en *clear* (389) — LDAPS non encore activé | ❌ Écart |
| **Applicatif** | OWASP ASVS L2 sur MaFormation et MaCandidature (CSRF, helmet, validations Zod) | ✅ |
| **Applicatif** | CorpNet *legacy* — non conforme ASVS L2 (vulnérabilités documentées) | ❌ Connu |
| **Conteneurs** | Non-root, *read-only*, *cap_drop ALL* pour services critiques | ✅ (sauf macandidature_app) |
| **Conteneurs** | Montage du *Docker socket* dans `macandidature_app` | ❌ Vuln. plantée |
| **Détection** | SIEM Wazuh + 11 règles personnalisées + corrélation brute force | ✅ |
| **Détection** | Watcher *Docker events* pour évasion conteneur | ✅ |
| **Sauvegardes** | Sauvegardes périodiques de l'AD et des bases de données | ❌ Non implémenté |
| **PCA / PRA** | Plan de continuité d'activité | ❌ Non documenté |
| **MFA** | Authentification multi-facteurs sur comptes administrateurs | ❌ Non implémenté |

Table 1.7 : Mesures de sécurité du socle existant.

### 1.5.3 Synthèse du socle

Le socle de sécurité existant présente une **couverture satisfaisante
sur la détection** (Wazuh, agents, règles, watcher Docker), une
**bonne hygiène applicative** pour les composants neufs (MaFormation,
MaCandidature, sauf vulnérabilité plantée), mais des **lacunes
identifiées et documentées** sur :

- la gestion de la continuité (sauvegardes, PCA/PRA) ;
- l'authentification forte des administrateurs (absence de MFA) ;
- la sécurité du transport sur LDAP (port 389 *clear* en lieu et
  place du port 636 LDAPS) ;
- la résilience du composant *legacy* CorpNet (CVE-2021-41773 non
  patchée, accepté dans le cadre du projet pédagogique).

Ces lacunes alimentent directement le plan d'amélioration continue de
la sécurité présenté en atelier 5.

</div>
