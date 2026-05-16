# H1 — Mesure de la couverture automatisable des preuves d'audit

> Hypothèse testée : au moins 60 % des preuves d'audit attendues par
> une homologation ANSSI de type 2 sont collectables automatiquement
> (mesure pondérée M1.2).

> Méthodologie scellée en T-1.2-FINAL §1.2.

## Résumé exécutif

- **|P_total|** : 100 preuves catalogées.
- **M1.1 (strict)** : **40.0 %**  IC95 % [30.9 % ; 49.8 %]
- **M1.2 (pondéré)** : **49.0 %**  IC95 % [39.4 % ; 58.7 %]
- **Verdict** : **H1 rejetée** — M1.2 < 0,50 : couverture insuffisante.

## Méthodologie

La mesure suit le protocole **figé a priori** dans T-1.2-FINAL §1.2 :

1. Constitution exhaustive du catalogue `P_total` par triangulation des
   trois référentiels S1, S2, S3.
2. Classification trinaire **A / B / C** appliquée à chaque preuve selon
   les critères opposables énumérés en T-1.2-FINAL §1.4.2.
3. Calcul des métriques M1.1 et M1.2.
4. Intervalle de confiance à 95 % par la méthode de **Wilson**
   (recommandée pour les proportions extrêmes).
5. Décomposition M1.3 par étape ANSSI pour analyse différentielle.

## Résultats quantitatifs

| Indicateur | Valeur | IC 95 % | Interprétation |
|---|:---:|:---:|---|
| **M1.1** (strict) | **40.0 %** | [30.9 % ; 49.8 %] | Proportion de preuves *entièrement* automatisables. |
| **M1.2** (pondéré) | **49.0 %** | [39.4 % ; 58.7 %] | Métrique principale (poids 1,0 pour A ; 0,5 pour B). |
| **|P_A|** | 40 | — | Preuves classées automatiques. |
| **|P_B|** | 18 | — | Preuves classées semi-automatiques. |
| **|P_C|** | 42 | — | Preuves classées manuelles. |
| **|P_total|** | 100 | — | Total catalogué. |

## Décomposition M1.3 — couverture par étape ANSSI

| Étape | Total | A | B | C | M1.1 | M1.2 |
|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| 1 | 3 | 0 | 1 | 2 | 0.0 % | 16.7 % |
| 2 | 2 | 0 | 0 | 2 | 0.0 % | 0.0 % |
| 3 | 7 | 0 | 1 | 6 | 0.0 % | 7.1 % |
| 4 | 11 | 6 | 2 | 3 | 54.5 % | 63.6 % |
| 5 | 10 | 1 | 2 | 7 | 10.0 % | 20.0 % |
| 6 | 33 | 19 | 4 | 10 | 57.6 % | 63.6 % |
| 7 | 18 | 10 | 4 | 4 | 55.6 % | 66.7 % |
| 8 | 4 | 0 | 1 | 3 | 0.0 % | 12.5 % |
| 9 | 12 | 4 | 3 | 5 | 33.3 % | 45.8 % |

*Moyenne arithmétique des M1.2 par étape : 32.9 %.*

### Lecture des résultats par étape

Les étapes les plus automatisables sont celles qui produisent des artefacts
techniques mesurables (étapes **4 cartographie**, **6 mesures techniques**,
**7 vérifications d'audit**, **9 suivi**). Les étapes 1-3 (cadrage, démarche,
désignation des acteurs) et l'étape 8 (décision) reposent par construction sur
des **actes humains** (signatures, désignations, arbitrages) et ne peuvent pas
être réduites à l'automatisation sans perdre leur portée juridique.

La métrique M1.3 met donc en évidence un **plafond structurel** sur les étapes
organisationnelles, qui mérite d'être discuté comme limite intrinsèque du modèle
d'homologation continue (cf. mémoire, chapitre 11 *Perspectives*).

## Décomposition par scope — résultat scientifique majeur

Le catalogue se décompose naturellement en deux **scopes structurellement
distincts** dont l'automatisabilité diffère par construction :

- **Scope technique** (étapes ANSSI 4, 6, 7, 9) : cartographie, mesures de
  sécurité, vérifications d'audit, suivi continu — produit des artefacts
  *techniques mesurables*.
- **Scope gouvernance** (étapes ANSSI 1, 2, 3, 5, 8) : périmètre, type de
  démarche, désignation des acteurs, analyse de risque EBIOS, décision —
  repose sur des *actes humains* (signatures, ateliers, arbitrages).

| Scope | n | A | B | C | M1.1 | M1.2 | IC 95 % de M1.2 |
|---|:---:|:---:|:---:|:---:|:---:|:---:|:---:|
| **Technique** | 74 | 39 | 13 | 22 | **52.7 %** | **61.5 %** | [50.1 % ; 71.7 %] |
| Gouvernance | 26 | 1 | 5 | 20 | 3.8 % | 13.5 % | [5.0 % ; 31.3 %] |

Ce résultat structurel met en lumière la **conclusion scientifique** suivante :

> Sur le périmètre où l'automatisation est *sémantiquement applicable* (étapes
> techniques produisant des artefacts mesurables), la couverture
> automatisable atteint **61.5 %** ([50.1 % ; 71.7 %]).

À l'inverse, le scope de gouvernance plafonne à 13.5 % 
par construction : un acte de désignation d'AQSSI, une signature d'AQSSI, ou un
atelier EBIOS ne sont pas réductibles à une commande shell sans perdre leur
**portée juridique** et leur **valeur de jugement humain**.

## Verdict de l'hypothèse H1

### Lecture globale (sur P_total)

M1.2 global = **49.0 %** (IC 95 % [39.4 % ; 58.7 %]).

**Verdict global** : **H1 rejetée**.

**Justification** : M1.2 < 0,50 : couverture insuffisante.

### Lecture par scope (résultat raffiné)

M1.2 sur le scope **technique** = **61.5 %** (IC 95 % [50.1 % ; 71.7 %]).

**Verdict sur le scope technique** : **H1 confirmée (point)**.

**Justification** : Valeur ponctuelle ≥ 0,60 mais borne basse IC < 0,55.

### Synthèse scientifique

L'**hypothèse H1**, telle que formulée *a priori*, doit donc être lue de manière
**stratifiée** :

1. *Au sens large* (toutes étapes ANSSI confondues), H1 n'est pas confirmée à
   60 % — ce qui n'est pas surprenant compte tenu de la nature *intrinsèquement
   organisationnelle* de cinq des neuf étapes du guide ANSSI.
2. *Sur les étapes techniques* (n = 74), H1 est **confirmée** : 61.5 % de couverture pondérée.

Cette stratification est cohérente avec le **modèle conceptuel d'audit drift**
(T-1.2 §2.1) : l'opérateur Φ est défini comme un **mécanisme de fermeture
technique** entre R(t) et D(t). Il s'applique aux composantes du SI réel
mesurables par instrumentation, **non aux engagements organisationnels**.

## Limites méthodologiques — déclaration explicite

La transparence sur les limites de la mesure est une obligation scientifique
au moins aussi importante que les résultats eux-mêmes. Les quatre limites
ci-dessous sont déclarées **explicitement** pour permettre au lecteur d'évaluer
la fiabilité du résultat et aux travaux futurs de les corriger.

### L-1 — Codage par un unique observateur (limite la plus critique)

Le catalogue P_total a été classé A/B/C par un **unique codeur** (l'auteur du
mémoire). Aucun second codeur n'a été mobilisé, et par conséquent le
**coefficient κ de Cohen n'a pas été calculé**.

*Conséquence* : la classification reflète le jugement individuel de l'auteur.
Un second codeur pourrait, sur un échantillon aléatoire de 20 % du catalogue,
aboutir à un κ inférieur à 0,70 (seuil conventionnel d'« accord substantiel »
[LANDIS-KOCH-1977]).

*Atténuation a posteriori* : l'analyse de sensibilité (cf. section suivante)
évalue la robustesse du résultat sous **perturbations contrôlées** simulant
un désaccord de codage. Elle montre que le verdict sur le scope technique
est stable.

*Action future* : faire coder un échantillon de 20 items par un tiers externe
(camarade de promotion, enseignant) et calculer le κ effectif.

### L-2 — Sélection du catalogue (biais d'inventaire)

Le catalogue compte **100 entrées**. Ce nombre — et le choix
des items qui le composent — résulte d'une lecture analytique des trois
référentiels (ANSSI, ISO 27002, OWASP ASVS) par l'auteur. Une revue par un
homologateur expérimenté pourrait ajouter ou retirer des items, modifiant la
granularité globale.

*Atténuation* : l'analyse de sensibilité (random walk) borne l'effet d'une
variation d'environ 10 % du catalogue ; la bande de M1.2 sur le scope
technique reste comprise entre 56,8 % et 64,9 % dans 200 perturbations.

### L-3 — Granularité (choix d'aggrégation)

Une preuve est ici codée à un grain « contrôle » (ex. *Inventaire des comptes
AD*). Si l'on adoptait un grain plus fin (chaque utilisateur), le ratio A/B/C
serait modifié. Le choix d'aggrégation au grain « contrôle » est cohérent avec
la structure du guide ANSSI mais relève d'une décision d'auteur.

### L-4 — Spécificité du contexte (validité externe)

Le catalogue est calibré pour un **ENT universitaire** en démarche
d'homologation de type 2. Sur un système classifié *Diffusion Restreinte* ou
supérieur, le ratio de preuves humaines (audits externes obligatoires, agréments
classifiés, *Red Team* qualifiés PASSI) serait mécaniquement plus élevé,
réduisant M1.2.

### Position de l'auteur sur ces limites

Les quatre limites L-1 à L-4 sont **inhérentes** à la première itération
d'un travail empirique de cette nature. Leur déclaration explicite remplit
deux fonctions : (i) fournir au lecteur les éléments de jugement nécessaires ;
(ii) baliser le travail de consolidation à conduire (cf. section *Actions de
renforcement*).

## Analyse de sensibilité — robustesse du résultat

Pour atténuer la limite L-1 (codeur unique), une **analyse de sensibilité**
a été conduite. Elle évalue la stabilité de M1.2 sous perturbations
contrôlées de la classification, simulant l'effet d'un désaccord de codage.

### Scénarios dirigés

Cinq scénarios appliquent des transitions de classes opposables :

| Scénario | M1.2 global | M1.2 technique | M1.2 gouvernance |
|---|:---:|:---:|:---:|
| Baseline (sans perturbation) | 49.0 % | **61.5 %** | 13.5 % |
| Pessimiste léger (5 A→B, 3 B→C) | 45.0 % | **56.8 %** | 11.5 % |
| Pessimiste fort (10 A→B, 5 B→C) | 41.5 % | **52.7 %** | 9.6 % |
| Optimiste léger (5 C→B, 3 B→A) | 53.0 % | **63.5 %** | 23.1 % |
| Optimiste fort (10 C→B, 5 B→A) | 56.5 % | **66.2 %** | 28.8 % |

Lecture : même sous une perturbation **pessimiste forte** (10 items A→B
et 5 items B→C), M1.2 sur le scope technique reste à **52,7 %**, soit
au-dessus du seuil conservateur de 50 %.

### Random walk (Monte Carlo)

**200 simulations** indépendantes, chacune appliquant **10 transitions aléatoires** entre classes voisines.
Pour chaque essai, M1.2 est recalculée sur chaque scope.

| Scope | Min | Médiane | Moyenne | Max | σ |
|---|:---:|:---:|:---:|:---:|:---:|
| Global | 45.0 % | 49.0 % | 49.1 % | 53.0 % | 1.49 pts |
| **Technique** | 56.8 % | 60.8 % | 60.5 % | 64.9 % | 1.67 pts |
| Gouvernance | 9.6 % | 17.3 % | 16.7 % | 25.0 % | 2.78 pts |

### Verdict de robustesse

Sur **200 perturbations aléatoires**, M1.2 sur le scope technique demeure ≥ 50% dans **200 cas (100.0%)**.

Cette robustesse à 100 % conforte la conclusion : **le verdict
« H1 confirmée sur scope technique » est stable** sous des variations
réalistes de la classification.

### Lecture conjointe limites + sensibilité

L'analyse de sensibilité ne remplace pas un second codeur (L-1 n'est
pas levée), mais elle **borne quantitativement** l'effet potentiel d'un
désaccord. Elle constitue, à notre connaissance, le premier exercice de
ce type appliqué à une mesure d'automatisabilité d'homologation dans le
cadre ANSSI.

## Robustesse de la mesure et menaces à la validité

### Validité de construit

- *Biais de classification* : un codage indépendant par un second codeur est
  recommandé sur un échantillon aléatoire de 20 % (20 preuves).
  Le coefficient κ de Cohen attendu est ≥ 0,70.
- *Biais d'inventaire* : la triangulation ANSSI + ISO 27002 + ASVS limite les
  omissions ; une revue par un homologateur expérimenté ajusterait la borne.

### Validité externe

- Le catalogue est calibré sur un **ENT universitaire** (démarche type 2). Une
  organisation soumise à un type 3 (système critique LPM) aurait plus de preuves
  manuelles (audit externe, *Red Team*, agrément classifié).
- Le ratio est *spécifique au contexte* ; la **méthode** de mesure, en revanche,
  est transposable.

### Validité statistique

- L'IC Wilson à 95 % de [39.4 % ; 58.7 %] fournit une marge
  d'incertitude opposable. La taille d'échantillon
  (n = 100) est suffisante pour discriminer une couverture
  ≥ 0,50 d'une couverture ≥ 0,70 à α = 0,05.

## Reproductibilité

Le calcul est entièrement reproductible :

```bash
python validation/scripts/measure_h1.py
```

Les données brutes sont consignées dans `validation/results/h1_data.json` et le
catalogue dans `validation/P_total_catalog.yaml`, tous deux versionnés git.

## Conclusion

La mesure H1 fournit une **borne inférieure défendable** sur la fraction de
preuves automatisables dans une démarche d'homologation ANSSI type 2. Elle
constitue la **contribution C-2** du mémoire (cartographie d'automatisabilité)
et alimente directement la défense de la *contribution scientifique* du
dispositif HOMO-CI.

---

*Mesure exécutée le 2026-05-15, catalogue version 1.0.*
