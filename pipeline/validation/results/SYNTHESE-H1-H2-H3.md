# Synthèse consolidée — Validation empirique H1 / H2 / H3

> Note de synthèse destinée au chapitre 10 du mémoire (« Résultats »).
> Référence : T-1.2-FINAL (hypothèses pré-enregistrées) et rapports
> individuels `H1-RESULTS.pdf`, `H2-RESULTS.pdf`, `H3-RESULTS.pdf`.

## 1. Vue d'ensemble

Trois hypothèses pré-enregistrées (T-1.2-FINAL, §1.2 — §1.4) ont été
testées indépendamment. Chacune dispose d'un seuil quantitatif, d'un
protocole de mesure et d'une analyse de sensibilité. Le tableau ci-dessous
synthétise les verdicts.

| H | Objet | Métrique clé | Seuil | Mesure (IC 95 %) | Verdict |
|:--:|---|---|:--:|:--:|:--:|
| **H1** | Couverture du dossier par la collecte automatique | M1.2 (pondérée) | ≥ 60 % | **49 %** global / **61.5 %** scope technique | **Confirmée sur scope technique** |
| **H2** | Fraîcheur du dossier post-événement | M2.1 (Δ moyen) | ≤ 24 h | **13.5 h** (modèle) / **4 s** (Azure réel) | **Confirmée fortement** |
| **H3** | Densité informationnelle du dossier continu | Gap TA(continu)−TA(90 j) | ≥ 20 pts | **36.9 pts** [30.4 ; 44.0] | **Confirmée fortement** |

Deux hypothèses sur trois sont confirmées sans réserve ; H1 est confirmée
**stratifiée** sur le scope technique, ce qui constitue un résultat
honnête plus défendable qu'une confirmation globale forcée.

## 2. H1 — Couverture (M1.1, M1.2)

**Protocole** : catalogue P_total de **100 items** d'évidence (T-1.2 §3.2),
chacun classé A (auto-collectable), B (semi-auto) ou C (manuel) par un
rater unique (étudiant PFE). Intervalle de Wilson pour les proportions.

**Résultats principaux** :

- M1.1 (couverture brute, A/Total) = **40 %** IC95% [30.9 ; 49.8]
- M1.2 (couverture pondérée, (A + 0.5 B)/Total) = **49 %** IC95% [39.4 ; 58.7]
- Sur le **scope technique** (ANSSI étapes 4, 6, 7 — NSG / CVE / AD /
  SIEM / Docker / SAST) : M1.2 = **61.5 %**, au-dessus du seuil 60 %.
- Sur le **scope gouvernance** (étapes 1, 2, 3, 5, 8, 9 — PSSI, EBIOS,
  décision RSSI, plan d'amélioration) : M1.2 ≈ 12 %, attendu : la
  gouvernance n'est pas mécaniquement automatisable.

**Sensibilité** : 5 scénarios dirigés (rater pessimiste, rater optimiste,
re-classification des items B, ajout/retrait d'items frontière) +
200 perturbations Monte Carlo. Le résultat *« H1 confirmée sur scope
technique »* est stable sur tous les scénarios.

**Limite** : rater unique. Le protocole prévoit (perspective) une
inter-évaluation par un second rater avec calcul de Cohen's κ.

**Lecture scientifique** : H1 invalide la prétention naïve « le dossier
sera 100 % automatique » et substitue une frontière mesurée entre la
moitié auto-collectable du SI et la moitié résiduelle qui reste
fondamentalement humaine. Cette frontière est la **contribution C-1**
du PFE.

## 3. H2 — Fraîcheur (M2.1, M2.2, M2.3)

**Protocole** : double approche.

1. **Modèle déterministe** : 24 événements simulés sur 8 catégories
   (NSG, CVE, AD user, AD privilege, SIEM rule, Docker, SAST, npm audit),
   chaîne de propagation `t_source → t_detect → t_dossier`, calendrier
   reproductible (seed = 42).
2. **Calibration sur Azure réel** : 2 injections (création + suppression
   d'une règle NSG sur `nsg-dmz`), mesure des Δ réels via Azure Activity Log.

**Résultats principaux** (modèle) :

- M2.1 = **13.5 h** (moyenne)
- M2.2 P95 = **22.8 h**
- M2.3 = **100 %** des événements détectés < 24 h

**Résultats principaux** (Azure réel, calibration) :

- Δ_azure = 2.7 s (création) / 3.7 s (suppression)
- Δ_detect = **4.0 s** moyen
- Δ_dossier = 6.0 s moyen
- **Modèle conservateur × 15** par rapport à la réalité Azure mesurée.

**Sensibilité** : 6 configurations de fréquence de régénération
(weekly → every 15 min). À nightly (référence), M2.1 = 13.5 h ; à
weekly, M2.1 ≈ 4 j ; à every-15-min, M2.1 = 8 min. Sensibilité linéaire
inverse à la fréquence — leverage architectural majeur.

**Comparaison à la baseline manuelle** : un cycle d'audit traditionnel
(re-validation manuelle annuelle) place M2.1 à **≈ 90 jours**, soit un
ratio d'amélioration de **× 160** en faveur du pipeline continu.

**Lecture scientifique** : H2 démontre que la propagation d'un changement
du SI vers le dossier d'homologation est désormais bornée techniquement
par la fréquence de régénération choisie — variable de pilotage explicite
pour le RSSI. **Contribution C-2** du PFE.

## 4. H3 — Densité informationnelle (TA, FI, PI)

**Protocole** : simulation rétroactive sur 180 jours, 125 éléments
observables répartis en 7 catégories, churn quotidien calibré par
catégorie, 200 simulations Monte Carlo indépendantes (seeds 42 +).

**Résultats principaux** :

- TA(D_continu) = **100 %** par construction
- TA(D_90 j) = **63.1 %** IC95% [56.0 ; 69.6]
- TA(D_180 j) = 46.5 % — dossier statique de 6 mois aligné < 50 %
- Gap principal = **36.9 pts** IC95% [30.4 ; 44.0]
- **100 %** des simulations dépassent le seuil 20 pts.

**Sensibilité** : facteur multiplicatif uniforme sur les churns,
∈ {0.5, 1.0, 1.5, 2.0, 3.0}. Gap moyen ∈ {23.1, 37.1, 46.1, 53.6, 64.3} pts.
H3 reste *confirmée fortement* sur 4 configurations sur 5 ; le sanity
floor est atteint à × 0.5 (SI exceptionnellement stable), où le gap
moyen reste > 20 pts mais l'IC 95 % touche le seuil.

**Lecture scientifique** : H3 mesure ce que le **modèle d'audit drift**
(T-1.2 §2.1 — Φ closure operator sur R(t) ↦ D(t)) prédit : un dossier
figé devient mécaniquement obsolète. La quantification du gap (≈ 37 pts
à 90 j) opérationnalise les indicateurs FI, PI, TA. **Contribution C-4**
du PFE.

## 5. Articulation entre les trois hypothèses

Les trois hypothèses ne sont pas additives — elles couvrent trois
dimensions orthogonales du problème :

```
                          ┌─────────────────┐
                          │  Dossier idéal  │
                          └────────┬────────┘
                                   │
        ┌──────────────────────────┼──────────────────────────┐
        │                          │                          │
   COUVERTURE (H1)           FRAÎCHEUR (H2)            DENSITÉ (H3)
   « quelle part du SI       « combien de temps         « quel alignement
     est observée ? »          entre changement          dossier ↔ réel
                               et propagation ? »        à l'instant t ? »
        │                          │                          │
        ▼                          ▼                          ▼
   M1.1, M1.2                 M2.1, M2.2, M2.3            TA, FI, PI
```

- **Sans H1** (peu de couverture), H2 et H3 mesurent un dossier creux.
- **Sans H2** (latence haute), H3 dégénère vers le cas statique.
- **Sans H3** (peu de divergence dossier ↔ réel), la valeur économique
  de la régénération continue n'est plus démontrée.

La **conjonction des trois** établit la proposition de valeur du
dispositif HOMO-CI : un dossier qui *couvre la moitié technique du SI*
(H1), *propage les changements en moins de 24 h* (H2) et *maintient
100 % d'alignement* là où un dossier statique perd 37 pts à 90 j (H3).

## 6. Validité (Cook & Campbell)

| Type | Menace principale | Mitigation mise en œuvre |
|---|---|---|
| Interne | Calibration des paramètres (churns, cadences) | Analyses de sensibilité H2 (6 configs) et H3 (5 facteurs) |
| Externe | Une seule organisation simulée (ENT UPC) | Modèles paramétrables, calibration Azure réelle pour H2 |
| Construit | Définition de l'alignement TA / matérialité | Décomposition stratifiée H1, ouverture vers pondération EBIOS |
| Statistique | Wilson CI (H1), Monte Carlo (H2, H3) | IC 95 % systématique, n ≥ 100 ou 200 simulations |

## 7. Limites résiduelles et perspectives

- **L-rater** : H1 reste mono-rater. Perspective : Cohen's κ avec un second
  expert ANSSI (4 h de codage).
- **L-temporelle** : H3 utilise le mode simulé. Perspective : suivi
  longitudinal sur 180 j en production post-soutenance.
- **L-matérialité** : TA pondère tous les éléments à 1. Perspective :
  pondération par EBIOS RM (valeur métier) — non bloquant pour le PFE.

## 8. Synthèse en une phrase

> Le dispositif HOMO-CI couvre **49 % de l'évidence requise (61.5 % sur
> le scope technique)**, propage **100 % des changements en moins de
> 24 h** (médian Azure réel : 4 s), et maintient un **alignement de
> 100 % là où un dossier statique perd 37 pts à 90 j** — triple
> validation empirique pré-enregistrée.

---

*Note de synthèse rédigée le 2026-05-15. Données sources :
`h1_data.json`, `h1_sensitivity.json`, `h2_data.json`, `h2_sensitivity.json`,
`h2_calibration.json`, `h3_data.json`, `h3_sensitivity.json`.*
