# Lab UrbanUpC

Infrastructure du SI fictif utilisé comme banc de test pour le PFE.

## Contenu

```
lab/
├── infra/             Scripts de déploiement Azure (Bash + PowerShell)
├── corpnet/           Application PHP CorpNet (volontairement vulnérable)
├── internal-apps/     Apps Node.js (MaFormation, MaCandidature) + Docker
│   ├── maformation/   App "secure by design" (Express/Prisma/PostgreSQL)
│   ├── macandidature/ App avec faille intentionnelle (montage docker.sock)
│   ├── nginx/         Reverse-proxy TLS
│   ├── wazuh/         Config Wazuh (règles custom, ossec.conf)
│   └── docker-compose.yml
├── scripts/           Utilitaires (provisioning, exercices d'attaque)
└── docker-compose.yml Orchestration globale
```

## Architecture

Trois VMs Azure dans `RG-PFE-SOC` (région `swedencentral`) :

| Hôte         | Rôle                    | Sous-réseau | IP privée   |
|--------------|-------------------------|-------------|-------------|
| `vm-web01`   | Frontaux web + Docker   | DMZ         | 10.0.1.10   |
| `vm-dc01`    | Domain Controller AD    | LAN         | 10.0.2.10   |
| `vm-siem01`  | SIEM Wazuh all-in-one   | MGMT        | 10.0.3.10   |

NSG : segmentation `Deny-DMZ-to-LAN`, `Deny-LAN-to-Internet`,
`Allow-Wazuh-Agents` (1514/1515 TCP MGMT → DMZ/LAN).

## Déploiement

Voir `infra/README.md` pour la séquence détaillée (RG, VNet, NSG, VMs, Wazuh).
Coût observé : sous 275 € de crédit étudiant pour 3 semaines de mesures.

## Avertissement

`corpnet/` et `macandidature/` contiennent **volontairement** des failles
(XSS, IDOR, SQLi, mots de passe en clair, montage `docker.sock`).
**Ne jamais déployer en dehors d'un environnement de lab isolé.**
