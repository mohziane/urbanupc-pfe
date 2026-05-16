# internal-apps — MaFormation & MaCandidature

Deux applications internes UrbanUpC, conçues *secure by design* (avec une vuln conteneur plantée volontairement dans MaCandidature pour la phase d'homologation).

## Architecture

```
vm-web01 (snet-dmz)  ─── Docker engine ───┐
                                          ├── stack "internal-apps"
                                          │   ┌── nginx (TLS, loopback only)
                                          │   │   ├ :8443 → maformation_app
                                          │   │   └ :8444 → macandidature_app
                                          │   ├── maformation_app + maformation_db
                                          │   └── macandidature_app  ⚠️ vuln docker.sock
                                          │       + macandidature_db
                                          └── stack "corpnet" (existante)
```

- 3 networks Docker isolés (`maformation_proxy_net`, `macandidature_proxy_net`, +2 DB internes).
- nginx termine TLS, applique HSTS/CSP/X-Frame-Options, fait rate-limit.
- Apps en non-root + read-only FS + cap_drop ALL (sauf MaCandidature, intentionnellement vulnérable).
- Postgres dans des networks `internal: true` (zéro accès Internet).
- Secrets via fichiers Docker (jamais en env clair).

## Vuln intentionnelle (à corriger en phase homologation)

`docker-compose.yml` monte `/var/run/docker.sock` dans `macandidature_app` et le container tourne en root avec le CLI `docker` installé. Un attaquant qui obtient RCE dans MaCandidature peut `docker exec` dans `maformation_app` et exfiltrer les données étudiants — malgré l'isolation réseau Docker.

Référence : CIS Docker Benchmark 5.31. MITRE ATT&CK T1611 (Escape to Host).

## Déploiement

### Pré-requis

1. **DC01** : créer un user de bind LDAP (`svc_ldap`) :
   ```powershell
   # via az vm run-command:
   az vm run-command invoke -g RG-PFE-SOC -n vm-dc01 \
     --command-id RunPowerShellScript \
     --scripts @scripts/setup-ldap-bind-user.ps1
   ```
   Récupérer le mot de passe affiché et l'écrire localement :
   ```bash
   printf '%s' 'le_password' > secrets/ldap_bind_pw.txt
   ```

2. **WEB01** : s'assurer que Docker engine + compose sont installés (déjà le cas pour CorpNet).

### Lancement

```bash
./scripts/deploy-web01.sh
```

Le script :
1. génère le CA interne + certs vhost (`nginx/certs/`)
2. génère les secrets aléatoires (`secrets/`)
3. rsync le code vers `vm-web01:/srv/internal-apps`
4. lance `docker compose build` + `up`
5. exécute les migrations Prisma + seed MaFormation
6. smoke test sur les `/healthz` loopback

## Accès depuis l'extérieur (via CorpNet)

Les apps sont **uniquement** sur `127.0.0.1:8443/8444` de vm-web01. Pour les exposer aux navigateurs des utilisateurs, CorpNet doit ajouter des routes proxy :

- `https://corpnet.fr/maformation/*` → `https://127.0.0.1:8443/*` (avec le CA interne dans le trust store)
- `https://corpnet.fr/candidat/*` → `https://127.0.0.1:8444/*`

Voir prochaine étape (Phase 5 — intégration CorpNet).

## Logs

- `/var/log/corpnet/internal/maformation/maformation.log` (JSON)
- `/var/log/corpnet/internal/macandidature/macandidature.log` (JSON)
- `/var/log/corpnet/internal/nginx/access.log` (JSON)

Le Wazuh agent de web01 doit ajouter ces chemins dans `ossec.conf` (Phase 6).

## Comptes de test

### MaFormation
- Logins existants dans corpnet.local : `m.martin`, `p.bernard`, `j.dupont`, `s.david`, etc. (mot de passe par défaut `Welcome2025!`)
- À la 1ère connexion, l'utilisateur est créé localement avec le rôle déduit du `memberOf`.

### MaCandidature
- À créer via le formulaire `Créer un compte`.
- Récupérer le token de vérification dans `/var/log/corpnet/internal/macandidature/outbox.log`.

## Démonstration de la vuln (pour le rapport PFE)

```bash
# 1) Connecté en candidat → upload un PDF dont le payload final exécute du code (RCE sim)
# 2) Depuis le shell obtenu dans macandidature_app:
docker ps                              # liste les containers, on voit maformation_app
docker exec maformation_app cat /app/uploads/*  # exfiltration
```

Détection attendue côté Wazuh : exec inhabituel `docker` depuis un container web (à intégrer en Phase 6).
