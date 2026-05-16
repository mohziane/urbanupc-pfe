<div class="atelier no-number" id="annexes">

# Annexes

## Annexe A — Glossaire EBIOS RM

<dl class="terms">
<dt>Valeur métier (VM)</dt>
<dd>Mission de l'organisation ou information sensible dont la
dégradation entraîne un préjudice. Élément central de l'analyse :
toute mesure de sécurité doit, *in fine*, viser la protection d'au
moins une valeur métier.</dd>

<dt>Bien support (BS)</dt>
<dd>Composant (matériel, logiciel, organisationnel, humain) sur
lequel reposent une ou plusieurs valeurs métier.</dd>

<dt>Événement redouté (ER)</dt>
<dd>Atteinte aux valeurs métier que l'organisation cherche à éviter.
Coté en gravité selon les critères DICT (Disponibilité, Intégrité,
Confidentialité, Traçabilité).</dd>

<dt>Source de risque (SR)</dt>
<dd>Tout acteur (humain, organisé, accidentel) susceptible de
provoquer un événement redouté.</dd>

<dt>Objectif visé (OV)</dt>
<dd>But poursuivi par une source de risque. Une SR peut viser
plusieurs OV, un OV peut être visé par plusieurs SR.</dd>

<dt>Scénario stratégique (SS)</dt>
<dd>Description au niveau écosystémique d'un chemin d'attaque :
SR → partie prenante (vecteur) → bien support → événement redouté.</dd>

<dt>Scénario opérationnel (SO)</dt>
<dd>Instanciation technique d'un scénario stratégique, décrite étape
par étape avec mobilisation explicite des techniques adverses
(typiquement MITRE ATT&CK).</dd>

<dt>Gravité (G)</dt>
<dd>Mesure de l'impact, échelle G1 (Mineure) à G4 (Catastrophique).</dd>

<dt>Vraisemblance (V)</dt>
<dd>Mesure de la plausibilité, échelle V1 (Minime) à V4 (Maximale).</dd>

<dt>Niveau de risque</dt>
<dd>Produit G × V sur l'échelle 1–16.</dd>

<dt>PACS</dt>
<dd>Plan d'Amélioration Continue de la Sécurité — formalisation des
mesures de traitement issues de l'atelier 5.</dd>

<dt>KRI</dt>
<dd>Key Risk Indicator — indicateur de risque permettant le suivi
continu de l'efficacité des mesures.</dd>

<dt>AQSSI</dt>
<dd>Autorité Qualifiée pour la Sécurité des Systèmes d'Information
— autorité prononçant l'homologation.</dd>

<dt>Homologation</dt>
<dd>Acte par lequel l'autorité responsable d'un SI atteste que les
risques résiduels ont été identifiés, mesurés et acceptés.</dd>
</dl>

## Annexe B — Échelles de cotation détaillées

### B.1 Échelle de gravité

| Niveau | Code | Définition | Exemple appliqué à UrbanUpC |
|---|---|---|---|
| G1 | Mineure | Impact limité, opérations marginalement perturbées | Indisponibilité courte d'Adminer |
| G2 | Significative | Impact tangible mais maîtrisable | *Defacement* du portail public 24 h |
| G3 | Grave | Impact lourd, atteinte importante à la mission | Vol de la base de candidatures, déclaration CNIL |
| G4 | Catastrophique | Impact existentiel, perte de mission, conséquences juridiques majeures | Compromission AD totale, falsification des diplômes |

### B.2 Échelle de vraisemblance

| Niveau | Code | Définition | Exemple appliqué à UrbanUpC |
|---|---|---|---|
| V1 | Minime | Moyens exceptionnels requis | APT étatique sur ENT |
| V2 | Significative | Plausible mais requérant combinaison favorable | Phishing personnel + Kerberoasting (SO-2) |
| V3 | Forte | Techniques largement diffusées | *Credential stuffing* (SO-1), AS-REP roasting (SO-3) |
| V4 | Maximale | Exploits prêts à l'emploi sur vulnérabilité présente | CVE-2021-41773 (SO-4), Docker socket (SO-5) |

### B.3 Échelle de pertinence SR × OV

| Niveau | Définition | Suite |
|---|---|---|
| 1 | Non pertinent | Écarté |
| 2 | Peu pertinent | Écarté |
| 3 | Pertinent | Retenu pour atelier 3 |
| 4 | Très pertinent — prioritaire | Retenu, traitement prioritaire |

## Annexe C — Référentiels et sources documentaires

| Référence | Description | URL / Citation |
|---|---|---|
| ANSSI — EBIOS Risk Manager (2018) | Guide méthodologique officiel | <https://cyber.gouv.fr/publications/la-methode-ebios-risk-manager-le-guide> |
| ANSSI — Guide de l'homologation (2020) | Conduite d'un dossier d'homologation | <https://cyber.gouv.fr/publications/lhomologation-de-securite-en-9-etapes-simples> |
| ANSSI — Recommandations AD (DAT-NT-17, 2017) | Sécurité Active Directory | <https://cyber.gouv.fr/publications/recommandations-de-securite-relatives-active-directory> |
| MITRE — ATT&CK Framework v14.1 | Techniques adverses | <https://attack.mitre.org/> |
| OWASP — ASVS v4.0.3 | Standard de vérification applicative | <https://owasp.org/www-project-application-security-verification-standard/> |
| CIS — Docker Benchmark v1.6 | Durcissement Docker | <https://www.cisecurity.org/benchmark/docker> |
| Verizon DBIR 2024 | Rapport annuel — retours d'expérience | Verizon Business |
| Cloud Security Alliance — Top Threats (2023) | Top des menaces cloud | <https://cloudsecurityalliance.org/> |

## Annexe D — Registre des risques résiduels

Le registre des risques résiduels est extrait du tableau 5.3 et
re-formaté pour intégration au dossier d'homologation.

| Risque résiduel | Désignation | Niveau | Échéance de revue | Justification |
|---|---|:---:|---|---|
| RR-1 | SO-4 — CVE-2021-41773 conservée pour démonstration | 6 (Élevé) | 12 mois | Posture pédagogique — patch hors scope projet |
| RR-2 | SO-6 — Compromission par ancien personnel | 6 (Élevé) | 3 mois | Risque structurel — mitigation par MFA + processus *off-boarding* |
| RR-3 | DSRM password récupérable via *secretsdump* sur sauvegarde non chiffrée | 4 (Modéré) | 6 mois | Mitigation par chiffrement des sauvegardes (action différée hors PACS courant) |
| RR-4 | Co-localisation pédagogique sur vm-web01 jusqu'à R1 | 3 (Modéré) | 14 jours | Borne temporelle ferme — R1 en cours |
| RR-5 | Limitation Docker Compose (secrets `mode 644`) | 3 (Modéré) | 21 jours | Traité par R4 (Azure Key Vault) |

Table D.1 : Registre des risques résiduels.

## Annexe E — Liste de contrôle d'audit du PACS

Cette liste de contrôle est destinée à l'auditeur (interne ou
externe) chargé de la vérification de la mise en œuvre du PACS au
terme du délai annoncé.

| ✓ | Item à vérifier | Réponse attendue |
|:-:|---|---|
| ☐ | R2 : `docker inspect macandidature_app` ne montre pas de bind sur `docker.sock` | OK / KO |
| ☐ | R2 : User `macandidature_app` non-root | UID ≥ 1000 |
| ☐ | R5.2 : `Get-ADGroupMember 'Domain Admins'` ne contient pas `svc.backup` | OK / KO |
| ☐ | R5.3 : `setspn -L soc.admin` retourne aucun SPN | OK / KO |
| ☐ | R5.4 : `Get-ADUser p.bernard -Properties DoesNotRequirePreAuth` retourne `$False` | OK / KO |
| ☐ | R1 : `vm-internal01` provisionnée, conteneurs migrés | OK / KO |
| ☐ | R3 : `maformation_app` redémarre en read-only | OK / KO |
| ☐ | R5.1 : `ldapsearch -H ldaps://...:636` aboutit | OK / KO |
| ☐ | R5.5 : Politique de verrouillage AD active (5 essais / 15 min) | OK / KO |
| ☐ | R6 : Azure DDoS Protection Standard activé | OK / KO |
| ☐ | R7 : Procédure formelle de off-boarding rédigée et signée | OK / KO |
| ☐ | R8 : MFA TOTP actif sur 100% des comptes administrateurs | OK / KO |
| ☐ | R9 : Sauvegarde automatique configurée, test de restauration réalisé | OK / KO |
| ☐ | R4 : Secrets `secrets/*.txt` retirés du système de fichiers | OK / KO |
| ☐ | KRI-1 à KRI-8 actifs dans le dashboard Wazuh | OK / KO |

Table E.1 : Liste de contrôle d'audit du PACS.

## Annexe F — Calendrier de revue de l'analyse

| Échéance | Activité | Document attendu |
|---|---|---|
| Mai 2026 | Émission de la présente analyse v1.0 | Document EBIOS-RM-URBANUPC-2026-V1.0 |
| Août 2026 | Revue à 3 mois (vérification PACS) | Note de revalidation v1.1 |
| Novembre 2026 | Revue à 6 mois | Note de revalidation v1.2 |
| Février 2027 | Bilan annuel + lancement v2.0 | Note de bilan |
| Mai 2027 | Émission v2.0 de l'analyse | Document EBIOS-RM-URBANUPC-2027-V2.0 |
| Sur événement | Revue exceptionnelle (incident, modification de périmètre) | Avenant |

Table F.1 : Calendrier de revue.

## Annexe G — Historique des versions

| Version | Date | Auteur | Changements |
|---|---|---|---|
| 1.0 | Mai 2026 | Risk Manager | Émission initiale du document, soumis pour homologation |

---

*Fin du document EBIOS-RM-URBANUPC-2026-V1.0*

</div>
