<div class="atelier" id="atelier-4">

# Scénarios opérationnels

## 4.1 Démarche et conventions

L'atelier 4 consiste à **instancier** chaque scénario stratégique en
une ou plusieurs **chaînes d'attaque techniques** décrites au niveau
des actions élémentaires de l'attaquant. La structure adoptée pour
chaque scénario opérationnel (SO) est la suivante :

1. **Référence** au scénario stratégique parent et aux ER associés.
2. **Hypothèse adverse** (qui agit, dans quel contexte).
3. **Pré-conditions** techniques et organisationnelles.
4. **Chaîne d'attaque** étape par étape, avec **techniques MITRE
   ATT&CK** explicites.
5. **Évaluation de la vraisemblance** sur l'échelle V1–V4.
6. **Mesures du socle qui s'opposent** au scénario.
7. **Détection en place** (règles SIEM correspondantes).
8. **Niveau de risque initial** (Gravité × Vraisemblance).

L'échelle de vraisemblance retenue est rappelée en encadré
méthodologique.

::: box box-method
**Échelle de vraisemblance EBIOS RM (V1 → V4) :**

- **V1 — Minime.** Le scénario nécessite des moyens exceptionnels
  (capacités étatiques, vulnérabilité 0-day spécifique). Sa
  réalisation est jugée hautement improbable dans le contexte
  considéré.
- **V2 — Significative.** Le scénario est techniquement plausible
  mais requiert une combinaison de conditions favorables (cible
  spécifique, contournement de mesures *de facto*). Quelques cas
  documentés sur des environnements comparables.
- **V3 — Forte.** Le scénario s'appuie sur des techniques largement
  diffusées et observées. Sa réalisation ne demande pas de
  compétences exceptionnelles.
- **V4 — Maximale.** Le scénario s'appuie sur des outils prêts à
  l'emploi, des exploits publics, et exploite une vulnérabilité
  connue et présente. Sa réalisation est attendue plus tôt que tard
  en l'absence de mesure dédiée.
:::

## 4.2 Cartographie globale des scénarios opérationnels

L'analyse retient **neuf scénarios opérationnels** dérivés des six
scénarios stratégiques. La table ci-dessous fournit la cartographie
de référence.

| SO | Parent | Description courte | MITRE | Gravité | Vraisemblance |
|---|---|---|---|:---:|:---:|
| **SO-1** | SS-1 | *Credential stuffing* MaFormation | T1110.004 | G3 | V3 |
| **SO-2** | SS-2 | Phishing personnel + Kerberoasting | T1566 + T1558.003 | G4 | V2 |
| **SO-3** | SS-2 | AS-REP roasting de `p.bernard` | T1558.004 | G3 | V3 |
| **SO-4** | SS-3 | Exploit CVE-2021-41773 + defacement | T1190 | G2 | V4 |
| **SO-5** | SS-4 | Évasion de conteneur via Docker socket | T1611 + T1610 | G4 | V4 |
| **SO-6** | SS-5 | Ancien admin réutilise mot de passe partagé | T1078.003 | G4 | V3 |
| **SO-7** | SS-1 + SS-2 | Compromission AD complète par chaîne longue | T1110 → T1558 → T1078.002 | G3 | V2 |
| **SO-8** | SS-6 | DoS distribué sur portail public | T1498 | G3 | V3 |
| **SO-9** | SS-3 | Rebond opportuniste depuis CorpNet vers tiers | T1190 + T1090.003 | G2 | V3 |

Table 4.1 : Cartographie synthétique des scénarios opérationnels.

## 4.3 Scénarios opérationnels détaillés

### SO-1 — *Credential stuffing* sur MaFormation

**Référence.** SS-1, ER-5.

**Hypothèse adverse.** Cybercriminel motivé profit (SR-1) ayant
acquis sur le marché souterrain une liste de *credentials* fuités
incluant des adresses académiques. Il vise la compromission massive
de comptes étudiants pour exfiltrer la base.

**Pré-conditions.**

- Exposition Internet du portail public (vérifiée).
- Format des comptes prévisible (`prenom.nom` sur `corpnet.local`)
  — vérifié, conforme à la convention de nommage.
- Absence de MFA sur les comptes étudiants (vérifié, MFA non
  implémenté).

**Chaîne d'attaque.**

```
1. Reconnaissance               [T1595 Active Scanning,
                                 T1589 Gather Victim Identity Info]
   → Scraping LinkedIn + sites de promotion d'écoles
   → Construction d'une liste {prenom.nom@corpnet.local}
   → Croisement avec une base de credentials fuités (HIBP, Combolist)
   
2. Initial Access               [T1110.004 Credential Stuffing]
   → Bot effectue des séquences GET /api/csrf puis POST /api/auth/login
     en distribuant les tentatives sur un *proxy network* résidentiel
   
3. Persistence                  [T1078 Valid Accounts]
   → Pour chaque compte compromis, capture du cookie de session JWT
   
4. Collection                   [T1005 Data from Local System]
   → Téléchargement des documents personnels via /api/documents
   → Téléchargement des notes via /api/grades
   
5. Exfiltration                 [T1041 Exfiltration Over C2 Channel]
   → Agrégation côté C2 et revente sur marché souterrain
```

**Mesures du socle qui s'opposent.**

- `express-rate-limit` à 10 tentatives / 5 minutes par IP →
  *contournable* par rotation d'IPs résidentielles.
- Verrouillage AD après tentatives échouées → *partiel*, dépend de
  la politique du domaine (non durcie dans la version courante).

**Détection en place.**

- Règle SIEM **100301** (login failed) — niveau 5.
- Règle SIEM **100302** (brute force corrélé, 5 échecs en 120 s) —
  niveau 10.
- Règle SIEM **100340** (rate-limit déclenché) — niveau 7.

**Vraisemblance.** **V3 (Forte).** Technique largement diffusée,
outillage public (Burp Intruder, Hydra, scripts Python). Le seul
frein réel est la politique de mots de passe étudiants (mot de
passe par défaut `Welcome2025!` *intentionnellement* faible dans la
version de démonstration).

**Niveau de risque initial.** G3 × V3 = **9 / 16 — Élevé**.

---

### SO-2 — *Spear-phishing* personnel + Kerberoasting

**Référence.** SS-2, ER-4.

**Hypothèse adverse.** Cybercriminel motivé profit (SR-1) ciblant
l'établissement par un *phishing* personnalisé sur le personnel
administratif.

**Pré-conditions.**

- Cible identifiable (nom du DAF, du DPO, du DSI publics).
- Absence de MFA sur les comptes personnel (vérifié).
- Service Kerberoastable disponible dans le domaine (`soc.admin`
  avec SPN `HTTP/web01.corpnet.local` — vérifié).

**Chaîne d'attaque.**

```
1. Reconnaissance               [T1589.002 Gather Email Addresses]
2. Initial Access               [T1566.001 Spear-phishing Attachment]
   → Mail piégé contenant un document Office macro vers PP-9
3. Execution                    [T1059.005 Visual Basic]
   → Macro exécute un loader PowerShell encodé en base64
4. Defense Evasion              [T1027 Obfuscated Files or Information]
   → Charge utile chiffrée en mémoire (Cobalt Strike beacon)
5. Credential Access            [T1558.003 Kerberoasting]
   → Demande TGS pour HTTP/web01.corpnet.local depuis le poste
6. Brute force offline          [T1110.002 Password Cracking]
   → Hashcat contre le ticket TGS
7. Privilege Escalation         [T1078 Valid Accounts]
   → Connexion en tant que soc.admin (Domain Admin)
8. Persistence                  [T1136.002 Domain Account, T1098]
   → Création / abus de comptes supplémentaires
9. Impact                       [variable, ER-4 atteint]
```

**Mesures du socle qui s'opposent.**

- Détection des macros Office par anti-virus poste — hors périmètre.
- Sysmon + PSScriptBlockLogging sur DC → détection du loader si
  exécuté sur le DC (mais peu probable, exécution depuis le poste
  utilisateur).
- Audit Kerberos avancé → détection du TGS RC4 caractéristique.

**Détection en place.**

- Règles natives Wazuh sur Event ID 4769 (TGS request) avec ticket
  encryption type 0x17 (RC4).
- Règle native sur PowerShell base64.
- Détection des changements de membres de Domain Admins (Event ID
  4728, 4732).

**Vraisemblance.** **V2 (Significative).** La chaîne complète
nécessite :

- une cible cliquant sur la macro (taux observé : 3–8 % en
  *phishing* académique [VERIZON-DBIR-2024]) ;
- une absence de blocage anti-virus sur poste ;
- l'existence d'un SPN crackable (présent dans le périmètre courant).

La cotation est V2 plutôt que V3 du fait du nombre d'étapes
requises et de la dépendance à l'interaction utilisateur.

**Niveau de risque initial.** G4 × V2 = **8 / 16 — Élevé**.

---

### SO-3 — *AS-REP roasting* de `p.bernard`

**Référence.** SS-2, ER-4.

**Hypothèse adverse.** Identique à SO-2, mais l'attaquant emprunte
un chemin **plus court** : il exploite l'absence de
pré-authentification du compte `p.bernard` (compte
*intentionnellement* configuré ainsi pour la démonstration).

**Chaîne d'attaque.**

```
1. Reconnaissance               [T1087.002 Domain Account]
   → Enumeration des comptes via LDAP (lecture publique sur 389)
   → Filtre LDAP: (&(objectClass=user)(userAccountControl:1.2.840.113556.1.4.803:=4194304))
2. Credential Access            [T1558.004 AS-REP Roasting]
   → Demande AS-REP via Rubeus ou impacket-GetNPUsers
   → Pas de pré-auth → reçoit le blob chiffré
3. Brute force offline          [T1110.002 Password Cracking]
   → Hashcat -m 18200 (AS-REP)
4. Initial Access               [T1078]
   → Connexion en tant que p.bernard
5. Lateral Movement & Privilege Escalation
   → identique à SO-2 à partir de l'étape 5
```

**Détection en place.**

- Règles natives Wazuh sur Event ID 4768 (AS request) avec
  pré-authentification type 0.
- Corrélation avec Event ID 4625 (logon failure) sur le même compte
  à délai court (signature classique).

**Vraisemblance.** **V3 (Forte).** L'attaque ne requiert pas de
*foothold* préalable au domaine (énumération LDAP réussit depuis
n'importe quel compte authentifié, ou depuis l'extérieur avec un
compte étudiant valide). Outillage public éprouvé (Rubeus,
GetNPUsers).

**Niveau de risque initial.** G3 × V3 = **9 / 16 — Élevé**.

Note : la gravité est cotée G3 plutôt que G4 (cotation parente SS-2)
car la compromission de `p.bernard` seule ne porte pas atteinte
catastrophique : il faut une *seconde* étape d'escalade. Cependant
elle constitue un point d'entrée vers SO-2.

---

### SO-4 — Exploit CVE-2021-41773 + *defacement*

**Référence.** SS-3, ER-6.

**Hypothèse adverse.** Hacktiviste (SR-3) ou cybercriminel
opportuniste (SR-2) ayant scanné le portail public et identifié
Apache 2.4.49.

**Chaîne d'attaque.**

```
1. Reconnaissance               [T1595.002 Vulnerability Scanning]
   → Scan Shodan / Nuclei sur la bannière Apache
2. Initial Access               [T1190 Exploit Public-Facing Application]
   → curl "https://corpnet-pfe.../cgi-bin/.%2e/%2e%2e/%2e%2e/etc/passwd"
   → Vérification de l'exploitabilité (path traversal)
3. Execution                    [T1059.004 Unix Shell]
   → Si mod_cgi est actif, exécution de commande arbitraire
   → curl --data 'echo;id' "...cgi-bin/.%2e/%2e%2e/bin/sh"
4. Impact                       [T1491.001 Internal Defacement]
   → Modification de index.php / public/index.html
```

**Détection en place.**

- Règle Wazuh native **31101** (Web 400 error).
- Règle Wazuh native **31104** (Common web attack).
- Règle ciblée CVE-2021-41773.

**Vraisemblance.** **V4 (Maximale).** La CVE est publiquement
exploitée depuis 2021, des modules Metasploit et des scripts
prêts à l'emploi existent. La vulnérabilité est **présente** dans
le périmètre courant (Apache 2.4.49 *intentionnellement* conservé).

**Niveau de risque initial.** G2 × V4 = **8 / 16 — Élevé**.

---

### SO-5 — Évasion de conteneur via *Docker socket*

**Référence.** SS-4, ER-1, ER-5.

::: box box-crit
**Scénario emblématique du projet.** Ce scénario opérationnel
constitue, par construction, le **risque critique** du périmètre
UrbanUpC. Il mobilise la vulnérabilité de plantation pédagogique
documentée en conception (montage `/var/run/docker.sock` +
exécution en *root* + installation du CLI `docker` dans le
conteneur `macandidature_app`).
:::

**Hypothèse adverse.** Candidat malveillant (SR-4 dans sa variante
candidat) ou cybercriminel motivé profit (SR-1) ayant créé un
compte sur MaCandidature pour exploiter la vulnérabilité plantée.

**Chaîne d'attaque.**

```
1. Initial Access               [T1078 Valid Accounts]
   → Création de compte candidat via /candidat/api/auth/signup
   → Vérification email (outbox simulée — exposé en lab seul)
2. Resource Development         [T1588.005 Exploits]
   → Préparation d'un PDF avec payload exploitant un parser PDF
     (cas hypothétique — la chaîne d'exploit dans MaCandidature
     n'est pas implémentée, mais la vulnérabilité plantée
     n'exige qu'un point d'entrée RCE quelconque)
3. Execution                    [T1059 Command and Scripting Interpreter]
   → Obtention d'un shell dans macandidature_app
   → root (par construction, image plantée vulnérable)
4. Discovery                    [T1613 Container and Resource Discovery]
   → ls -la /var/run/docker.sock
   → docker ps  (liste TOUS les conteneurs de l'hôte)
   → docker network ls
5. Privilege Escalation         [T1611 Escape to Host]
   → Via docker.sock, capacité de contrôler le démon Docker
   → docker run --privileged ... possible
6. Lateral Movement             [T1610 Deploy Container,
                                 T1609 Container Administration Command]
   → docker exec maformation_app /bin/sh
   → Accès complet au conteneur étudiant
7. Collection                   [T1005 Data from Local System]
   → cat /app/uploads/* depuis maformation_app
   → SELECT * FROM "User" depuis maformation_db (port 5432)
8. Exfiltration                 [T1041]
   → Exfiltration via le démon Docker (docker cp) ou via le
     conteneur d'origine
```

**Mesures du socle qui s'opposent.**

- Isolation par réseaux Docker séparés : ❌ contournée par le
  *plan de contrôle* (démon Docker).
- Détection : ✅ règle 100351 (docker exec) déclenche.

**Détection en place.**

- Règle SIEM **100351** (HOST: docker exec on container ... POSSIBLE
  CONTAINER ESCAPE) — niveau 12, MITRE T1611.
- Règle SIEM **100352** (HOST: docker create/start) — niveau 10.

**Vraisemblance.** **V4 (Maximale).** La vulnérabilité est **présente
et documentée** dans le code. Toute exploitation aboutissant à un
RCE dans `macandidature_app` permet l'évasion sans difficulté
technique additionnelle.

**Niveau de risque initial.** G4 × V4 = **16 / 16 — Critique**.

---

### SO-6 — Ancien administrateur réutilisant un accès résiduel

**Référence.** SS-5, ER-2.

**Hypothèse adverse.** Ancien administrateur (SR-5) ayant quitté
l'établissement, conservant la connaissance du mot de passe d'un
compte de service partagé, ou d'une clé SSH résiduelle, ou de la
convention de mot de passe des comptes étudiants.

**Chaîne d'attaque.**

```
1. Initial Access               [T1078.003 Local Accounts]
   → Tentative de connexion via SSH avec clé SSH non révoquée
   → Ou via RDP sur vm-dc01 avec mot de passe partagé
2. Privilege Escalation         [T1078.002 Domain Accounts]
   → Si compte AD non révoqué, connexion directe
3. Lateral Movement / Collection
   → Modification directe dans la base MaFormation
     (UPDATE Grade SET value = 18 WHERE userId = ... )
```

**Détection en place.**

- Connexion RDP depuis IP externe → règle Wazuh 5760 (logon from
  unusual location) si géo-IP activé.
- Modification de la table Grade hors application → non détecté
  (manque de FIM/audit base de données).

**Vraisemblance.** **V3 (Forte).** Le processus de *off-boarding*
parfait est rare. Le scénario est documenté comme première cause
d'incident interne dans plusieurs études sectorielles
[VERIZON-DBIR-2024].

**Niveau de risque initial.** G4 × V3 = **12 / 16 — Critique**.

---

### SO-7 — Chaîne longue : brute force → Kerberoasting → Domain Admin

**Référence.** SS-1 + SS-2, ER-4.

**Hypothèse adverse.** Cybercriminel sophistiqué chaînant les
techniques pour atteindre Domain Admin à partir d'une exposition
externe.

**Chaîne d'attaque.**

```
1. SO-1 jusqu'à étape 2 → compte étudiant compromis
2. Authentification au domaine via compte étudiant valide
3. Énumération LDAP → identification de soc.admin (SPN)
4. SO-2 étape 5 (Kerberoasting) → ticket TGS de soc.admin
5. Cracking offline
6. Connexion en tant que Domain Admin
7. Compromission AD totale
```

**Vraisemblance.** **V2 (Significative).** Chaîne *à plusieurs sauts*,
chacun étant détectable individuellement. La probabilité de succès
sur l'ensemble dépend du non-déclenchement de la détection sur les
trois premières étapes.

**Niveau de risque initial.** G3 × V2 = **6 / 16 — Élevé**.

---

### SO-8 — DoS distribué sur portail public

**Référence.** SS-6, ER-3.

**Chaîne d'attaque.**

```
1. Resource Development         [T1583.005 Botnet]
2. Impact                       [T1498.001 Direct Network Flood,
                                 T1499.004 Application Exhaustion Flood]
   → Volumétrie SYN / HTTP GET sur https://corpnet-pfe.../
```

**Détection.** Surveillance des métriques NSG + alertes Azure DDoS
Standard (non activé dans le périmètre courant — *écart*).

**Vraisemblance.** **V3 (Forte).** Les services de DDoS-as-a-Service
sont disponibles à coût marginal (~50 €/heure de flood). La cible
est petite (portail à faible volumétrie) donc facile à saturer.

**Niveau de risque initial.** G3 × V3 = **9 / 16 — Élevé**.

---

### SO-9 — Rebond opportuniste depuis CorpNet vers un tiers

**Référence.** SS-3, ER-8.

**Hypothèse adverse.** Cybercriminel opportuniste (SR-2) compromettant
CorpNet via CVE-2021-41773, installant un *proxy* sortant pour
masquer une autre attaque ciblant un tiers (université partenaire,
service cloud).

**Chaîne d'attaque.**

```
1. SO-4 jusqu'à étape 3 (RCE sur CorpNet)
2. Persistence                  [T1543.002 Systemd Service]
3. Command and Control          [T1090.003 Multi-hop Proxy]
   → Installation d'un proxy SOCKS sortant
4. Impact                       → Attaque depuis l'IP d'UrbanUpC
```

**Vraisemblance.** **V3 (Forte).** Le rebond est un *case d'usage
historique* des compromissions de petites infrastructures Apache.

**Niveau de risque initial.** G2 × V3 = **6 / 16 — Élevé**.

## 4.4 Cartographie initiale du risque

| SO | Désignation | Gravité | Vrais. | Risque |
|---|---|:---:|:---:|:---:|
| SO-5 | Évasion conteneur (Docker socket) | G4 | V4 | **16 — Critique** |
| SO-6 | Ancien admin (réutilisation accès) | G4 | V3 | **12 — Critique** |
| SO-1 | *Credential stuffing* MaFormation | G3 | V3 | 9 — Élevé |
| SO-3 | AS-REP roasting `p.bernard` | G3 | V3 | 9 — Élevé |
| SO-8 | DoS portail | G3 | V3 | 9 — Élevé |
| SO-2 | Phishing + Kerberoasting | G4 | V2 | 8 — Élevé |
| SO-4 | CVE-2021-41773 + defacement | G2 | V4 | 8 — Élevé |
| SO-7 | Chaîne longue → Domain Admin | G3 | V2 | 6 — Élevé |
| SO-9 | Rebond opportuniste | G2 | V3 | 6 — Élevé |

Table 4.2 : Cartographie initiale du risque, scénarios opérationnels.

::: heatmap
                Vraisemblance →
                V1     V2     V3     V4
G4 Catastr.     .      SO-2   SO-6   SO-5  ◀ 1 critique (16)
G3 Grave        .      SO-7   SO-1   .         1 critique (12)
G3 Grave        .      .      SO-3   .
G3 Grave        .      .      SO-8   .
G2 Significat.  .      .      SO-9   SO-4
G1 Mineure      .      .      .      .
:::

## 4.5 Synthèse de l'atelier 4

L'atelier 4 confirme la concentration du risque sur **deux scénarios
critiques** :

- **SO-5 (Docker socket)** au niveau 16/16, *immédiatement
  remédiable* par l'action R2 du PACS ;
- **SO-6 (ancien personnel)** au niveau 12/16, traitement requérant
  une combinaison de mesures techniques et organisationnelles
  (revue régulière des comptes, rotation des secrets partagés, MFA
  obligatoire pour les administrateurs).

Sept autres scénarios sont cotés **élevés** (6–9/16). Aucun risque
**modéré** ou **faible** ne ressort de l'analyse initiale — ce qui
signifie que **chaque scénario** identifié nécessite une mesure de
traitement formalisée dans le PACS de l'atelier 5.

</div>
