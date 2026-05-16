<div class="atelier" id="atelier-5">

# Traitement du risque

## 5.1 Stratégies de traitement

Pour chaque scénario opérationnel identifié à l'atelier 4, l'autorité
d'homologation dispose de quatre stratégies de traitement, conformément
à la norme ISO/IEC 27005 et au cadre EBIOS RM :

<dl class="terms">
<dt>Éviter</dt><dd>Renoncer à l'activité ou au composant à l'origine du risque (ex. : retirer une application exposée).</dd>
<dt>Réduire</dt><dd>Mettre en place des mesures techniques ou organisationnelles diminuant la gravité ou la vraisemblance.</dd>
<dt>Transférer</dt><dd>Transférer tout ou partie du risque à un tiers (assurance cyber, sous-traitance).</dd>
<dt>Accepter</dt><dd>Reconnaître le risque résiduel comme tolérable, sans action complémentaire.</dd>
</dl>

Le tableau ci-dessous récapitule la stratégie retenue pour chacun
des neuf scénarios opérationnels.

| SO | Stratégie | Justification |
|---|---|---|
| SO-1 | Réduire | Renforcement rate-limit + activation MFA étudiant à terme |
| SO-2 | Réduire | Détection AD + MFA personnel + suppression SPN |
| SO-3 | Réduire | Restauration de la pré-authentification sur `p.bernard` |
| SO-4 | Accepter (résiduel) | Patch Apache **hors scope** du projet pédagogique ; mitigations compensatoires |
| SO-5 | Réduire | **Action prioritaire R2** — suppression intégrale de la vulnérabilité plantée |
| SO-6 | Réduire | Procédure de *off-boarding* + revue trimestrielle des comptes |
| SO-7 | Réduire | Mesures combinées de SO-1 et SO-2 |
| SO-8 | Transférer | Activation d'Azure DDoS Protection Standard |
| SO-9 | Réduire (compensatoire) | Audit sortant + journalisation des flux Internet depuis CorpNet |

Table 5.1 : Stratégies de traitement par scénario.

## 5.2 Plan d'Amélioration Continue de la Sécurité (PACS)

Le PACS articule les actions concrètes à conduire, leur ordonnancement,
les responsabilités et les indicateurs de réalisation.

### 5.2.1 Actions structurantes

| Code | Action | SO traités | Priorité | Effort | Échéance |
|---|---|---|:---:|:---:|:---:|
| **R1** | Migration des applications internes sur `vm-internal01` dédiée en `snet-lan` | SO-5 (résiduel), SO-9 | 2 | 1 j-h (+ quota Azure) | D+14 |
| **R2** | Suppression de la vulnérabilité *Docker socket* dans `macandidature_app` | SO-5 | **1** | 2 j-h | D+3 |
| **R3** | Réintroduction du *read-only filesystem* sur `maformation_app` via conteneur migrator dédié | (durcissement général) | 3 | 1,5 j-h | D+7 |
| **R4** | Migration des secrets Docker vers Azure Key Vault | SO-1, SO-6 (résiduel) | 4 | 2,5 j-h | D+21 |
| **R5.1** | Activation LDAPS (port 636) entre MaFormation et DC01 | SO-7 (résiduel) | 2 | 1 j-h | D+7 |
| **R5.2** | Révocation du compte `svc.backup` (Domain Admin planté) | SO-2, SO-7 | **1** | 0,5 j-h | D+3 |
| **R5.3** | Retrait du SPN sur `soc.admin` ou rotation du mot de passe | SO-2 | **1** | 0,5 j-h | D+3 |
| **R5.4** | Activation de la pré-authentification sur `p.bernard` | SO-3 | **1** | 0,2 j-h | D+3 |
| **R5.5** | Configuration de la politique de verrouillage AD (5 essais / 15 min) | SO-1, SO-2, SO-3 | 2 | 0,5 j-h | D+7 |
| **R6** | Activation Azure DDoS Protection Standard sur l'IP publique | SO-8 | 3 | 1 j-h | D+14 |
| **R7** | Procédure formelle de *off-boarding* (révocation, rotation, audit) | SO-6 | 2 | 2 j-h (organisationnel) | D+21 |
| **R8** | MFA TOTP obligatoire pour comptes administrateurs et personnel | SO-2, SO-6 | 2 | 3 j-h | D+30 |
| **R9** | Mise en place de sauvegardes journalières de la base MaFormation | SO-2, SO-6 | 3 | 1 j-h | D+14 |
| **R10** | Intégration de la détection au pipeline d'homologation continue | (gouvernance) | 4 | 4 j-h | D+45 |

Table 5.2 : Plan d'Amélioration Continue de la Sécurité (PACS).

### 5.2.2 Ordonnancement des actions par priorité

#### Priorité 1 — Actions immédiates (D + 3 jours)

Quatre actions sont à conduire dans les **trois jours** suivant la
décision d'homologation, en raison de leur impact sur les risques
critiques :

- **R2** Suppression du *Docker socket* (SO-5, risque 16/16 → traité).
- **R5.2** Révocation `svc.backup` (élimination d'une porte dérobée).
- **R5.3** Retrait SPN `soc.admin` (élimination du Kerberoasting).
- **R5.4** Pré-authentification `p.bernard` (élimination de
  l'AS-REP roasting).

#### Priorité 2 — Court terme (D + 7 à D + 14 jours)

Actions structurantes de mise à niveau du socle :

- **R1** Migration vers `vm-internal01`.
- **R3** Read-only filesystem.
- **R5.1** Passage LDAPS.
- **R5.5** Politique de verrouillage AD.
- **R6** Azure DDoS Protection.
- **R9** Sauvegardes journalières.

#### Priorité 3 — Moyen terme (D + 21 à D + 30 jours)

- **R4** Azure Key Vault pour la gestion des secrets.
- **R7** Procédure formelle de *off-boarding*.
- **R8** MFA obligatoire pour les comptes à privilèges.

#### Priorité 4 — Long terme (D + 45 jours et au-delà)

- **R10** Intégration au pipeline d'homologation continue.

### 5.2.3 Effort total et budget

| Catégorie | Effort cumulé |
|---|---|
| Effort technique (actions R1 à R6, R8, R9) | 13,2 j-h |
| Effort organisationnel (R7, R10) | 6 j-h |
| **Effort total PACS** | **19,2 j-h** |

L'enveloppe budgétaire reste maîtrisable : à un coût moyen
journalier de 600 € (estimation pour un profil *Manager
Cybersécurité* en France métropolitaine), le PACS représente un
budget total de **~11 500 € HT**. Aucun investissement matériel ou
logiciel significatif n'est requis (Azure Key Vault et Azure DDoS
Standard sont des services facturés à l'usage).

## 5.3 Cotation des risques résiduels

À l'issue de l'application complète du PACS, les risques sont
ré-évalués sur la même grille G × V :

| SO | Avant | Stratégie | Mesures | Après | Évolution |
|---|:---:|---|---|:---:|:---:|
| SO-1 | 9 | Réduire | R5.5, R8 | 4 — Modéré | ↓ 5 |
| SO-2 | 8 | Réduire | R5.2 à R5.5, R8 | 3 — Modéré | ↓ 5 |
| SO-3 | 9 | Réduire | R5.4 | 2 — Faible | ↓ 7 |
| SO-4 | 8 | Accepter | (compensatoire R6) | 6 — Élevé | ↓ 2 |
| SO-5 | **16** | Réduire | R2 (+ R1, R3) | 2 — Faible | ↓ 14 |
| SO-6 | **12** | Réduire | R4, R7, R8 | 6 — Élevé | ↓ 6 |
| SO-7 | 6 | Réduire | R5.1 à R5.5, R8 | 2 — Faible | ↓ 4 |
| SO-8 | 9 | Transférer | R6 | 3 — Modéré | ↓ 6 |
| SO-9 | 6 | Réduire | R1 (+ audit sortant) | 3 — Modéré | ↓ 3 |

Table 5.3 : Cartographie des risques avant/après traitement.

::: heatmap
                         AVANT TRAITEMENT
                Vraisemblance →
                V1     V2     V3     V4
G4 Catastr.     .      SO-2   SO-6   SO-5  ← 2 critiques
G3 Grave        .      SO-7   SO-1   .
G3 Grave        .      .      SO-3   .
G3 Grave        .      .      SO-8   .
G2 Significat.  .      .      SO-9   SO-4
G1 Mineure      .      .      .      .

                         APRÈS TRAITEMENT
                Vraisemblance →
                V1     V2     V3     V4
G4 Catastr.     .      .      .      .     ← 0 critique
G3 Grave        SO-6   SO-4   .      .     ← 2 élevés résiduels
G3 Grave        .      .      .      .
G2 Significat.  .      SO-1   SO-8   .
G2 Significat.  .      SO-2   SO-9   .
G1 Mineure      SO-3,7 .      .      .
:::

### Discussion des risques résiduels notables

#### SO-4 résiduel à 6 (élevé)

La conservation d'Apache 2.4.49 dans le périmètre pédagogique
empêche le traitement complet de SO-4. La gravité reste cotée G2 ;
la vraisemblance est ramenée de V4 à V2 par mitigations
compensatoires :

- Azure DDoS Protection Standard (R6) absorbe les SO-8 mais aide
  également contre les scans massifs.
- Le SOC détecte le scan via règle 31104 (Common web attack).
- L'attaquant n'atteint pas les biens supports critiques (séparation
  réseau, application *legacy* sans données sensibles persistantes).

En contexte opérationnel réel, l'**acceptation** de ce risque devrait
être bornée dans le temps avec une *échéance ferme* de mise à jour.

#### SO-6 résiduel à 6 (élevé)

L'ancien personnel reste un vecteur intrinsèquement difficile à
neutraliser intégralement. La combinaison R4 (Key Vault) + R7
(off-boarding) + R8 (MFA) ramène la vraisemblance de V3 à V2 et la
gravité de G4 à G3 (le MFA empêche l'usage du compte hérité).
L'audit régulier (KRI-6 ci-après) constitue la mesure de surveillance
continue de ce risque résiduel.

## 5.4 Indicateurs de risque (KRI)

Le suivi continu de l'efficacité du PACS repose sur **huit
indicateurs de risque clés** (KRI). Pour chaque indicateur sont
définis le seuil d'alerte, la fréquence de mesure et le responsable.

| Code | Indicateur | Seuil d'alerte | Fréquence | Responsable |
|---|---|---|---|---|
| **KRI-1** | Nombre d'événements `auth.login_failed` par jour | > 500 / jour | Quotidienne | SOC |
| **KRI-2** | Nombre d'événements `100302 brute force` | ≥ 1 / 24 h | Quotidienne | SOC |
| **KRI-3** | Nombre d'événements `100351 docker exec` non whitelistés | ≥ 1 / mois | Mensuelle | SOC |
| **KRI-4** | Nombre de comptes AD non utilisés > 90 jours | ≥ 10 | Trimestrielle | Identité |
| **KRI-5** | Délai moyen de révocation post-départ (mesuré sur échantillon) | > 5 j | Trimestrielle | RH + Identité |
| **KRI-6** | Couverture MFA sur comptes administrateurs | < 100% | Mensuelle | Identité |
| **KRI-7** | CVE critiques (CVSS ≥ 7) non patchées sur les VMs | ≥ 1 / 30 j | Hebdomadaire | Infra |
| **KRI-8** | Délai de réponse aux alertes Wazuh L≥10 | > 4 h | Mensuelle | SOC |

Table 5.4 : Indicateurs de risque clés (KRI).

Ces indicateurs alimentent un **tableau de bord trimestriel**
présenté au comité de sécurité et conservé dans le dossier
d'homologation à des fins de traçabilité.

## 5.5 Cadre de suivi et révision

### 5.5.1 Cycle de revue

| Échéance | Activité | Livrable |
|---|---|---|
| Mensuel | Revue des KRI par le RSSI | Note de synthèse |
| Trimestriel | Revue des risques résiduels avec l'AQSSI | Note de revalidation |
| Annuel | Révision complète de l'analyse EBIOS RM | Nouvelle version (v2.0, v3.0...) |
| Sur événement | Revue immédiate en cas de modification du périmètre | Avenant à la décision d'homologation |

Table 5.5 : Cadre de suivi du PACS.

### 5.5.2 Conditions de remise en question de l'homologation

L'homologation devra être **remise en question** dans les cas suivants :

- déclenchement d'un KRI au seuil d'alerte sans résolution sous 30
  jours ;
- découverte d'une vulnérabilité critique non couverte par le PACS ;
- incident de sécurité significatif (perte de confidentialité,
  d'intégrité ou de disponibilité affectant une VM cotée G3 ou
  supérieure) ;
- modification de l'écosystème (ajout d'une partie prenante avec un
  niveau d'exposition supérieur à 3) ;
- évolution du référentiel ANSSI ou de la directive NIS 2.

### 5.5.3 Indicateurs de réalisation du PACS

Pour conclure le suivi, la table ci-dessous fournit la liste des
**indicateurs de réalisation** par action, permettant au comité
d'homologation de vérifier de manière objective la mise en œuvre.

| Action | Indicateur de réalisation |
|---|---|
| R1 | `vm-internal01` créée, conteneurs migrés, `vm-web01` ne porte plus que la pile *legacy* |
| R2 | `docker inspect macandidature_app --format '{{.HostConfig.Binds}}'` ne contient pas `docker.sock` ; `--format '{{.Config.User}}'` retourne un UID non nul |
| R3 | `docker inspect maformation_app --format '{{.HostConfig.ReadonlyRootfs}}'` retourne `true` |
| R4 | Le dossier `secrets/` est vidé sur l'hôte ; les conteneurs accèdent au Key Vault via *Managed Identity* |
| R5.1 | `ldapsearch -H ldaps://10.0.2.10:636 ...` aboutit avec validation de certificat |
| R5.2 | `Get-ADGroupMember "Domain Admins"` ne contient plus `svc.backup` |
| R5.3 | `setspn -L soc.admin` n'affiche aucun SPN, ou le mot de passe a été rotaté en > 24 caractères aléatoires |
| R5.4 | `Get-ADUser p.bernard -Properties DoesNotRequirePreAuth` retourne `False` |
| R5.5 | Politique de verrouillage active : `Get-ADDefaultDomainPasswordPolicy` montre `LockoutThreshold = 5` |
| R6 | Service Azure DDoS Protection Standard activé sur l'IP publique |
| R7 | Procédure documentée et signée ; revue effective sur trois départs récents |
| R8 | KRI-6 = 100% |
| R9 | Sauvegarde automatique configurée, *restore drill* trimestriel documenté |
| R10 | Pipeline CI émettant chaque nuit le rapport d'écart de couverture (cf. chap. 13 du mémoire principal) |

Table 5.6 : Indicateurs de réalisation par action.

## 5.6 Synthèse de l'atelier 5

Le PACS, articulé en **quatorze actions** (R1 à R10 et sous-actions
R5.x), permet de **ramener tous les risques sous le seuil critique**
et de **traiter sept des neuf scénarios** à un niveau modéré ou
faible. Deux risques résiduels demeurent au niveau *élevé* :

- **SO-4** (CVE-2021-41773) — par décision pédagogique de conservation,
  bornée dans le temps ;
- **SO-6** (ancien personnel) — par nature, mitigeable mais non
  totalement éliminable.

Ces deux risques résiduels font l'objet d'une **inscription
formelle** au registre des risques résiduels, présentée à l'autorité
d'homologation pour acceptation explicite.

</div>
