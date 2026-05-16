<div class="atelier" id="atelier-2">

# Sources de risque et objectifs visés

## 2.1 Identification des sources de risque

Une **source de risque** désigne, au sens d'EBIOS RM, tout agent
(humain ou non) susceptible de provoquer un événement redouté. La
caractérisation des sources se fonde sur quatre critères :

<dl class="terms">
<dt>Motivation</dt><dd>finalité poursuivie : profit, idéologie, vengeance, curiosité, négligence.</dd>
<dt>Ressources</dt><dd>moyens humains, financiers et techniques mobilisables.</dd>
<dt>Expertise</dt><dd>niveau de compétence technique requis et effectivement disponible.</dd>
<dt>Exposition</dt><dd>position de la source par rapport au SI (interne, externe, semi-interne).</dd>
</dl>

Huit sources de risque candidates ont été examinées. À l'issue de
l'analyse, **six d'entre elles** ont été retenues comme pertinentes
pour le périmètre UrbanUpC.

### 2.1.1 Sources retenues

| Code | Désignation | Motivation | Ressources | Expertise | Exposition |
|---|---|---|---|---|---|
| **SR-1** | Cybercriminel motivé profit | Revente de données, rançongiciel | Élevée (kits) | Moyenne à élevée | Externe |
| **SR-2** | Cybercriminel opportuniste | Botnets, scan automatisé | Faible (kits gratuits) | Faible à moyenne | Externe |
| **SR-3** | Hacktiviste idéologique | Atteinte image, message politique | Moyenne | Moyenne | Externe |
| **SR-4** | Étudiant malveillant interne | Curiosité, vol de notes, vengeance | Faible | Faible à moyenne (très variable) | Interne (compte étudiant valide) |
| **SR-5** | Ancien personnel | Vengeance, vol de propriété intellectuelle | Faible à moyenne | Moyenne à élevée (connaissance du SI) | Externe (mais accès résiduel possible) |
| **SR-6** | Erreur humaine d'administration | Aucune (cause non-intentionnelle) | — | — | Interne (administrateur) |

Table 2.1 : Sources de risque retenues.

### 2.1.2 Sources écartées

| Code | Désignation | Justification d'écart |
|---|---|---|
| SR-X1 | Acteur étatique avancé (APT) | Vraisemblance jugée minime (V1) sur le périmètre universitaire ; toutefois conservé en veille |
| SR-X2 | Concurrent | Notion non applicable à un établissement public d'enseignement supérieur |

Table 2.2 : Sources écartées et motifs.

## 2.2 Caractérisation détaillée des sources retenues

### SR-1 — Cybercriminel motivé profit

**Profil.** Groupe organisé poursuivant un objectif financier. Cible
les systèmes dont les données ont une valeur sur les marchés
souterrains, ou dont l'indisponibilité peut être monétisée par
rançongiciel.

**Motivation.** Forte et persistante. Les données étudiantes
(identité, parcours, coordonnées) sont monnayables sur les marchés
de cybercrime [VERIZON-DBIR-2024].

**Ressources et expertise.** Accès à des kits offensifs (Cobalt
Strike, Sliver), à des *initial access brokers*, et à de
l'infrastructure de *command-and-control* louée. Expertise variable
selon le groupe, de moyenne (utilisation de kits) à élevée
(développement d'exploits).

**Pertinence pour UrbanUpC.** Élevée. Le périmètre expose en
permanence un portail public et héberge des dossiers de candidature
(données personnelles à valeur marchande, surtout en période de
campagne d'admission).

### SR-2 — Cybercriminel opportuniste

**Profil.** Acteur exploitant indifféremment toute vulnérabilité
exposée sur Internet, typiquement via des *botnets* de scan et des
exploits publics.

**Motivation.** Faible mais constante (volume).

**Ressources et expertise.** Faibles. Outillage public (Shodan,
nmap, Metasploit, Nuclei). Capacité limitée à des attaques à faible
sophistication.

**Pertinence pour UrbanUpC.** Très élevée. La présence d'Apache
2.4.49 (CVE-2021-41773) constitue une cible immédiate pour cette
catégorie.

### SR-3 — Hacktiviste idéologique

**Profil.** Individu ou groupe motivé par un message politique,
social ou environnemental. Vise des organisations symboliques pour
maximiser la visibilité de l'action.

**Motivation.** Moyenne, contextuelle (en fonction de l'actualité).

**Ressources et expertise.** Moyennes. Outillage similaire au
cybercriminel opportuniste, complété par une coordination via
réseaux sociaux.

**Pertinence pour UrbanUpC.** Moyenne. Une université est une cible
*symbolique* de second rang ; mais une polémique sur la mission
publique d'enseignement pourrait élever cette pertinence à élevée.

### SR-4 — Étudiant malveillant interne

**Profil.** Étudiant titulaire d'un compte AD valide, motivé par la
curiosité, le vol de notes, ou la vengeance contre un enseignant ou
un autre étudiant.

**Motivation.** Faible à moyenne. Souvent ponctuelle.

**Ressources et expertise.** Très variables. La population étudiante
d'un master en informatique inclut un sous-ensemble disposant des
compétences techniques d'un *junior pentester*.

**Pertinence pour UrbanUpC.** Élevée du fait de la position interne
et de la disponibilité d'un compte valide sur MaFormation. La
combinaison « accès interne + compétences variables » est
classiquement sous-estimée dans les analyses de risque universitaire.

### SR-5 — Ancien personnel

**Profil.** Personne ayant quitté l'établissement et conservant une
**connaissance fine du SI**, voire des accès résiduels (compte non
révoqué, mot de passe partagé non rotaté).

**Motivation.** Variable. Vengeance, vol de propriété intellectuelle
(sujets d'examen, recherches), curiosité.

**Ressources et expertise.** Moyennes à élevées. Connaissance des
architectures internes, des conventions de nommage, des chemins de
*pivot*.

**Pertinence pour UrbanUpC.** Élevée. La gestion fine du cycle de
vie des comptes AD (révocation immédiate au départ, rotation des
mots de passe partagés) est rarement parfaite dans une organisation
universitaire.

### SR-6 — Erreur humaine d'administration

**Profil.** Administrateur ou opérateur produisant, par négligence,
une faute de configuration ou un acte non malveillant ayant des
conséquences sur la sécurité (ex. : règle NSG trop ouverte,
publication d'un secret dans un dépôt public).

**Motivation.** Non applicable (cause involontaire).

**Ressources / Expertise.** Variable. L'erreur n'est *pas* corrélée
à la compétence — un expert peut commettre une erreur à plus haut
impact qu'un junior.

**Pertinence pour UrbanUpC.** Élevée. Toute analyse de risque
sérieuse en environnement *cloud* prend en compte la mauvaise
configuration comme première cause de fuite de données
[CSA-TT-2023].

## 2.3 Identification des objectifs visés

Un **objectif visé** désigne le but qu'une source de risque
poursuit, c'est-à-dire l'événement redouté qu'elle cherche à
provoquer. Huit objectifs visés ont été identifiés.

| Code | Désignation | ER associé(s) |
|---|---|---|
| **OV-1** | Exfiltrer les dossiers de candidature pour revente | ER-1 |
| **OV-2** | Modifier frauduleusement les notes d'un étudiant | ER-2 |
| **OV-3** | Compromettre un large ensemble de comptes étudiants pour usurpation | ER-5 |
| **OV-4** | Indisponibilité du portail public (déni de service) | ER-3 |
| **OV-5** | *Defacement* du portail public à des fins idéologiques | ER-6 |
| **OV-6** | Obtenir un *foothold* persistant dans le domaine AD | ER-4 |
| **OV-7** | Utiliser l'infrastructure UrbanUpC pour pivoter vers un tiers | ER-8 |
| **OV-8** | Désactiver la détection / supprimer les traces (anti-forensic) | ER-7 |

Table 2.3 : Objectifs visés identifiés.

### Note sur OV-8

L'objectif OV-8 n'est généralement pas une *finalité* en soi, mais
un **objectif intermédiaire** poursuivi pour préserver les autres
(OV-1 à OV-7). Il est néanmoins identifié séparément car il a un
impact spécifique sur VM-4 (capacité d'audit), et il appelle des
mesures de détection dédiées (intégrité des journaux Wazuh,
surveillance des modifications de règles).

## 2.4 Matrice de pertinence SR × OV

La matrice ci-dessous cote, pour chaque couple (Source × Objectif),
la **pertinence** sur l'échelle 1–4 définie en avertissement
méthodologique. Les couples cotés à 3 ou 4 sont retenus pour
l'atelier 3.

| SR \\ OV | OV-1 Exfil. cand. | OV-2 Mod. notes | OV-3 Compr. étud. | OV-4 DOS | OV-5 Defac. | OV-6 Footh. AD | OV-7 Rebond | OV-8 Anti-foren. |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **SR-1** Cybercrim. profit | **4** | 2 | **4** | 3 | 1 | **3** | 2 | **3** |
| **SR-2** Cybercrim. opport. | 2 | 1 | 3 | 3 | 2 | 2 | **4** | 1 |
| **SR-3** Hacktiviste | 2 | 2 | 2 | **3** | **4** | 1 | 1 | 1 |
| **SR-4** Étudiant intern. | 2 | **4** | 2 | 1 | 1 | 2 | 1 | 1 |
| **SR-5** Ancien personnel | 3 | **4** | 3 | 1 | 1 | **3** | 1 | 2 |
| **SR-6** Erreur humaine | 2 | 1 | 2 | 3 | 1 | 2 | 1 | 1 |

Table 2.4 : Matrice de pertinence SR × OV.

**Légende.** 1 = non pertinent ; 2 = peu pertinent ; 3 = pertinent (retenu) ;
4 = très pertinent (retenu, prioritaire).

### Discussion de quelques cotations

#### SR-1 × OV-1 (cybercriminel × exfiltration candidatures) : **4**

La conjonction est paradigmatique. Les données de candidature
contiennent identité civile complète, parcours scolaire détaillé,
parfois copie de pièce d'identité. La valeur unitaire sur le marché
souterrain (estimée à 5–15 €/dossier) multipliée par le volume
saisonnier de candidatures (~5 000 sur deux mois) donne un retour
estimé de **25 000 à 75 000 €** par campagne d'exfiltration —
incitatif significatif.

#### SR-3 × OV-5 (hacktiviste × defacement) : **4**

L'objectif principal du hacktiviste est la **visibilité**. Le
*defacement* du portail public d'une université figure parmi les
actions emblématiques recherchées par cette catégorie d'acteurs. La
combinaison est sur-représentée dans les bases de retours
d'expérience [ZONE-H-2024].

#### SR-4 × OV-2 (étudiant × modification notes) : **4**

C'est la combinaison **classique** dans l'expérience opérationnelle
des SOC universitaires : un étudiant techniquement compétent
cherchant à modifier sa propre note ou celle de ses pairs. La
motivation est *forte*, l'opportunité est *interne*, l'impact est
*catastrophique*. Combinaison à retenir prioritairement.

#### SR-5 × OV-2 (ancien personnel × modification notes) : **4**

Variante de la précédente, avec une **expertise supérieure** et un
**accès résiduel** potentiel. La gravité reste G4, et la
vraisemblance dépend de la qualité du processus de *off-boarding*
(révocation des comptes, rotation des mots de passe partagés).

#### SR-5 × OV-6 (ancien personnel × foothold AD) : **3**

Un ancien administrateur dispose, à son départ, d'une connaissance
fine du domaine `corpnet.local` et potentiellement de mots de passe
de comptes de service partagés. La pertinence est *pertinente* sans
être maximale (l'accès direct au LAN reste à conquérir).

#### SR-2 × OV-7 (cybercriminel opportuniste × rebond) : **4**

L'infrastructure UrbanUpC, vulnérable au CVE-2021-41773, constitue
une cible idéale pour un cybercriminel opportuniste cherchant à
constituer un *botnet* ou un *proxy network*. La pertinence est
maximale au sens où la conjonction est *historiquement avérée* sur
des installations comparables [CISA-2021].

## 2.5 Couples SR/OV retenus

À l'issue de la cotation, **dix couples** sont retenus pour
construction des scénarios stratégiques :

| # | SR | OV | Justification synthétique |
|:---:|---|---|---|
| 1 | SR-1 | OV-1 | Exfiltration dossiers candidature, profit, marché souterrain |
| 2 | SR-1 | OV-3 | Compromission massive de comptes étudiants |
| 3 | SR-1 | OV-6 | Foothold AD comme prérequis aux objectifs précédents |
| 4 | SR-1 | OV-8 | Anti-forensic pour assurer la persistance |
| 5 | SR-2 | OV-7 | Rebond opportuniste depuis l'infrastructure compromise |
| 6 | SR-3 | OV-4 | DOS du portail public, contexte de mobilisation |
| 7 | SR-3 | OV-5 | Defacement à visée idéologique |
| 8 | SR-4 | OV-2 | Étudiant compétent modifiant ses notes |
| 9 | SR-5 | OV-2 | Ancien personnel modifiant des notes (volume / cible précise) |
| 10 | SR-5 | OV-6 | Ancien administrateur réutilisant un accès résiduel |

Table 2.5 : Couples SR / OV retenus pour l'atelier 3.

## 2.6 Synthèse de l'atelier 2

L'atelier 2 confirme que le périmètre UrbanUpC est exposé à un
spectre représentatif des sources de risque connues, avec une
**concentration** sur :

- **trois sources externes** organisées (cybercriminels profit,
  cybercriminels opportunistes, hacktivistes) ;
- **deux sources internes ou semi-internes** (étudiant malveillant,
  ancien personnel) — la *deuxième catégorie* fréquemment sous-évaluée
  dans les analyses de risque ;
- **une source non-malveillante** (erreur humaine d'administration)
  systémiquement présente.

Les dix couples retenus alimentent l'atelier 3 pour la construction
des scénarios stratégiques.

</div>
