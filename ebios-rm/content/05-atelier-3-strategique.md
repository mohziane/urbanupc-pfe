<div class="atelier" id="atelier-3">

# Scénarios stratégiques

## 3.1 Identification de l'écosystème

Avant de construire les scénarios stratégiques, EBIOS RM impose
l'identification de **l'écosystème** dans lequel s'inscrit l'objet
d'étude. L'écosystème désigne l'ensemble des **parties prenantes**
externes (partenaires, fournisseurs, clients, régulateurs) qui
interagissent avec le SI et **peuvent constituer un vecteur d'attaque**.

| Code | Partie prenante | Nature de la relation | Niveau d'accès au SI |
|---|---|---|---|
| **PP-1** | Microsoft (Azure) | Fournisseur d'infrastructure | Hébergement complet — relation contractuelle |
| **PP-2** | Wazuh Inc. | Fournisseur logiciel (SIEM) | Code source, support, licence |
| **PP-3** | RENATER | Réseau d'enseignement supérieur | Connectivité, services fédérés (optionnel) |
| **PP-4** | Lycées d'origine des candidats | Sources de pièces de candidature | Aucun accès direct |
| **PP-5** | Partenaires académiques (universités) | Échanges Erasmus, mobilité | Échanges ponctuels, pas d'accès direct |
| **PP-6** | Prestataires de paiement | Frais d'inscription (potentiel) | Hors périmètre actuel |
| **PP-7** | Étudiants (utilisateurs finaux) | Population principale | Compte AD limité |
| **PP-8** | Candidats (utilisateurs externes) | Population transitoire | Compte local MaCandidature |
| **PP-9** | Personnel administratif et enseignant | Population interne | Comptes AD avec rôles spécifiques |
| **PP-10** | CNIL | Régulateur (RGPD) | Aucun, mais autorité de contrôle |
| **PP-11** | ANSSI | Référent national cybersécurité | Aucun, mais référence méthodologique |

Table 3.1 : Parties prenantes de l'écosystème UrbanUpC.

### Évaluation de la *menace par partie prenante*

Pour chaque partie prenante, deux indicateurs sont évalués :

<dl class="terms">
<dt>Fiabilité</dt><dd>Niveau de confiance en la PP (qualité de sa gouvernance, robustesse de ses propres mesures de sécurité). Échelle 1 (faible) à 4 (élevée).</dd>
<dt>Exposition</dt><dd>Surface d'attaque qu'elle expose à UrbanUpC. Échelle 1 (minime) à 4 (maximale).</dd>
</dl>

La menace résultante (M = (5 – Fiabilité) × Exposition) cote la
**vulnérabilité écosystémique** sur 1–16.

| Code | Partie prenante | Fiabilité | Exposition | Menace |
|---|---|:---:|:---:|:---:|
| PP-1 | Microsoft Azure | 4 | 4 | 4 |
| PP-2 | Wazuh Inc. | 3 | 2 | 4 |
| PP-3 | RENATER | 4 | 1 | 1 |
| PP-4 | Lycées d'origine | 2 | 2 | 6 |
| PP-5 | Partenaires académiques | 3 | 1 | 2 |
| PP-6 | Prestataires de paiement | 3 | 1 | 2 |
| PP-7 | Étudiants | 2 | 4 | 12 |
| PP-8 | Candidats | 1 | 3 | 12 |
| PP-9 | Personnel | 3 | 4 | 8 |
| PP-10 | CNIL | — | — | — |
| PP-11 | ANSSI | — | — | — |

Table 3.2 : Menace écosystémique par partie prenante.

L'analyse fait ressortir **trois parties prenantes** porteuses de la
menace écosystémique la plus élevée :

- **PP-7 Étudiants** (M = 12) — population à fiabilité moyenne
  (compétences techniques variables, hygiène cyber rarement
  exemplaire) et à exposition maximale (compte AD valide, accès
  permanent à MaFormation).
- **PP-8 Candidats** (M = 12) — population de fiabilité faible (pas
  d'engagement contractuel, identité non vérifiée à l'inscription)
  et exposition forte (auto-inscription, dépôt de fichiers).
- **PP-9 Personnel** (M = 8) — population de fiabilité moyenne mais
  exposition maximale (privilèges élevés, accès à des données
  sensibles).

Ces trois populations *internes ou semi-internes* constituent ainsi
les **vecteurs préférentiels** des scénarios stratégiques retenus en
section 3.3.

## 3.2 Cartographie de la menace écosystémique

::: heatmap
                       Exposition →
                       1      2      3      4
   F  4 (très fiab.)   PP-3   PP-2   .      PP-1
   i  3 (fiable)       PP-5,6 .      .      PP-9
   a  2 (moy. fiab.)   .      PP-4   .      PP-7
   b  1 (peu fiab.)    .      .      PP-8   .
       ↑
       Fiabilité
:::

L'analyse cartographique met en évidence un **quadrant haut-droit
critique** où se concentrent les parties prenantes à la fois peu
fiables et fortement exposées. Pour UrbanUpC, PP-7 (étudiants) et
PP-8 (candidats) sont situées dans ce quadrant. La conséquence est
que **toute mesure de sécurité doit pouvoir résister à une compromission
interne** de ces deux populations.

## 3.3 Construction des scénarios stratégiques

Un **scénario stratégique** décrit, au niveau écosystémique, un
chemin d'attaque articulant :

```
Source de risque → Partie prenante (vecteur) → Bien support → Événement redouté
```

Six scénarios stratégiques sont retenus à partir des couples SR × OV
de l'atelier 2 et de la cartographie écosystémique précédente.

### SS-1 — *Credential stuffing* sur MaFormation depuis Internet

**Description.** Un cybercriminel motivé profit (SR-1) ou opportuniste
(SR-2) exploite la liste publique de noms et adresses académiques
(directement accessible via LinkedIn, sites de promotion d'écoles)
pour conduire une attaque par *credential stuffing* sur le portail
public, en s'appuyant sur les *credentials* fuités de bases tierces.

**Chaîne.** SR-1/SR-2 → PP-7 (étudiants ayant réutilisé un mot de
passe) → BS-3 (CorpNet) ↔ BS-5 (MaFormation) → ER-5 (exfiltration de
la base étudiante) ou ER-2 (modification de notes).

**Gravité stratégique.** G3 (Grave) — atteinte large à la
confidentialité des comptes ; impact réglementaire RGPD ; perte de
confiance.

### SS-2 — Phishing ciblé sur le personnel pour escalade AD

**Description.** Un cybercriminel motivé profit (SR-1) envoie un
*spear-phishing* personnalisé à un membre du personnel administratif
(PP-9). La compromission de son poste fournit un *foothold* dans le
LAN. L'attaquant exploite alors les **fragilités AD plantées**
(`p.bernard` AS-REP roastable, `soc.admin` Kerberoastable) pour
escalader vers Domain Admin.

**Chaîne.** SR-1 → PP-9 (personnel administratif) → BS-4 (AD) → ER-4
(compromission AD totale) → ER-2, ER-5, ER-7 (par effet de domino).

**Gravité stratégique.** G4 (Catastrophique) — compromission AD
porteuse de l'ensemble des VM.

### SS-3 — Exploitation directe de CVE-2021-41773 sur CorpNet

**Description.** Un cybercriminel opportuniste (SR-2) ou un
hacktiviste (SR-3) détecte Apache 2.4.49 par scan automatisé
(Shodan, recherche par bannière), exploite la CVE-2021-41773 pour
obtenir une exécution de code distante, puis *defacement* (cas
hacktiviste) ou rebond (cas cybercriminel).

**Chaîne.** SR-2/SR-3 → Internet (vecteur direct) → BS-3 (CorpNet) →
ER-6 (defacement) ou ER-3 (indisponibilité) ou ER-8 (rebond).

**Gravité stratégique.** G3 (Grave) si rebond et persistance ; G2
(Significative) si defacement isolé rapidement remédié.

### SS-4 — Évasion de conteneur via *Docker socket* depuis MaCandidature

**Description.** Un candidat malveillant (PP-8, depuis la position
externe) crée un compte sur MaCandidature, exploite une vulnérabilité
applicative pour obtenir une exécution de code dans le conteneur
`macandidature_app`, découvre le *Docker socket* monté, exécute
`docker exec` sur le conteneur `maformation_app` et exfiltre les
documents étudiants.

**Chaîne.** SR-1 (ou SR-4 candidat technique) → PP-8 (compte
candidat) → BS-5 (`macandidature_app` → daemon Docker → `maformation_app`)
→ ER-1 (exfiltration candidatures) + ER-5 (exfiltration base étudiante).

**Gravité stratégique.** G4 (Catastrophique) — exfiltration croisée
entre deux populations de données, traversée d'une barrière de
sécurité par construction.

::: box box-crit
**Scénario emblématique du projet.** SS-4 mobilise une vulnérabilité
**volontairement plantée** dans la configuration de démonstration
(montage du *Docker socket*). Sa vraisemblance, dans le périmètre
courant, est *maximale* — ce qui justifie le caractère prioritaire
de l'action de remédiation R2 dans le PACS (atelier 5).
:::

### SS-5 — Compromission par un ancien personnel

**Description.** Un ancien membre du personnel administratif (SR-5)
ayant quitté l'établissement conserve la connaissance des conventions
de mot de passe, d'un compte de service partagé non rotaté, ou d'une
clé SSH résiduelle. Il en exploite l'accès depuis Internet pour
modifier les notes d'un étudiant (vengeance) ou pour exfiltrer des
dossiers (vol de propriété intellectuelle).

**Chaîne.** SR-5 → Accès résiduel (mot de passe partagé, clé SSH non
révoquée) → BS-4 (AD) ou BS-5 (apps internes) → ER-2 ou ER-1.

**Gravité stratégique.** G3 à G4 selon le périmètre d'action.

### SS-6 — DOS du portail en période d'inscription

**Description.** Un hacktiviste (SR-3) lance, à l'ouverture de la
campagne d'admission, une attaque par déni de service distribué
contre le portail public, rendant impossible le dépôt de candidature
pendant la durée critique de la campagne.

**Chaîne.** SR-3 → Internet → BS-2 (réseau) + BS-3 (CorpNet) → ER-3
(indisponibilité en période critique).

**Gravité stratégique.** G3 (Grave) — la *temporalité* de
l'indisponibilité (période d'inscription) en aggrave l'impact.

## 3.4 Cotation des scénarios stratégiques

| Scénario | Description synthétique | Source(s) | ER | Gravité |
|---|---|---|---|:---:|
| SS-1 | *Credential stuffing* sur MaFormation | SR-1, SR-2 | ER-5 | **G3** |
| SS-2 | *Spear-phishing* personnel + escalade AD | SR-1 | ER-4 | **G4** |
| SS-3 | Exploitation CVE-2021-41773 CorpNet | SR-2, SR-3 | ER-6, ER-3, ER-8 | **G3** |
| SS-4 | Évasion de conteneur (Docker socket) | SR-1, SR-4 | ER-1, ER-5 | **G4** |
| SS-5 | Compromission par ancien personnel | SR-5 | ER-2, ER-1 | **G4** |
| SS-6 | DOS du portail en campagne d'inscription | SR-3 | ER-3 | **G3** |

Table 3.3 : Cotation des scénarios stratégiques.

## 3.5 Discussion

L'analyse stratégique fait ressortir **trois axes de défense
prioritaires** :

1. **Durcir le périmètre identité AD** (SS-2 et SS-5) — actions
   R5.x du PACS : LDAPS, révocation `svc.backup`, retrait SPN
   `soc.admin`, activation pré-authentification.
2. **Éliminer la vulnérabilité d'évasion de conteneur** (SS-4) —
   action R2 du PACS, priorité maximale.
3. **Renforcer le composant *legacy* CorpNet** (SS-3 et SS-6) —
   limité par la posture pédagogique de conservation de la
   vulnérabilité ; mitigations détaillées en atelier 5.

L'atelier 4 décline maintenant ces six scénarios stratégiques en
scénarios opérationnels techniques, avec cotation de la
vraisemblance.

</div>
