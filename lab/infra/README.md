# Infrastructure Azure — SOC Hybride PFE

**Resource Group** : `RG-PFE-SOC`
**Region** : `swedencentral`
**Date de deploiement** : 11 avril 2026
**Credit** : Azure for Students (100 EUR)

---

## Architecture reseau

```
                      Internet
                         |
                    [IPs publiques]
                         |
    +============[vnet-pfe 10.0.0.0/16]============+
    |                                               |
    |  snet-dmz (10.0.1.0/24)         nsg-dmz      |
    |  +---------------------+                      |
    |  |  vm-web01           |                      |
    |  |  Ubuntu 22.04 LTS   |  Honeypot PHP        |
    |  |  10.0.1.10          |  Docker + CorpNet     |
    |  |  :80 :443 :8888     |  + Wazuh agent       |
    |  +---------+-----------+                      |
    |            | X (Deny-DMZ-to-LAN)              |
    |            |                                  |
    |  snet-lan (10.0.2.0/24)          nsg-lan      |
    |  +---------------------+                      |
    |  |  vm-dc01            |                      |
    |  |  Windows Srv 2022   |  Active Directory     |
    |  |  10.0.2.10          |  corpnet.local        |
    |  |  :3389 (RDP)        |  + Wazuh agent       |
    |  +---------+-----------+                      |
    |            |                                  |
    |  snet-mgmt (10.0.3.0/24)        nsg-mgmt     |
    |  +---------------------+                      |
    |  |  vm-siem01          |                      |
    |  |  Ubuntu 22.04 LTS   |  Wazuh all-in-one    |
    |  |  10.0.3.10          |  manager + indexer    |
    |  |  :443 :1514 :1515   |  + dashboard          |
    |  |  :55000             |                      |
    |  +---------------------+                      |
    +===============================================+

    MacBook M2 Max (local) = Poste attaquant Red Team
```

---

## VMs

| VM | OS | Taille | Subnet | IP privee | IP publique | FQDN | Auth |
|----|-----|--------|--------|-----------|-------------|------|------|
| vm-web01 | Ubuntu 22.04 LTS Gen2 | Standard_B2s_v2 | snet-dmz | 10.0.1.10 | 20.91.233.41 | corpnet-pfe.swedencentral.cloudapp.azure.com | SSH key |
| vm-dc01 | Windows Server 2022 Datacenter Gen2 | Standard_B2s_v2 | snet-lan | 10.0.2.10 | 20.91.233.214 | — | Password |
| vm-siem01 | Ubuntu 22.04 LTS Gen2 | Standard_B2s_v2 | snet-mgmt | 10.0.3.10 | 20.91.206.70 | — | SSH key |

**Auto-shutdown** : 22h00 UTC sur vm-web01 et vm-dc01

---

## Subnets

| Subnet | CIDR | NSG | Role |
|--------|------|-----|------|
| snet-dmz | 10.0.1.0/24 | nsg-dmz | Zone DMZ — serveur web expose sur Internet |
| snet-lan | 10.0.2.0/24 | nsg-lan | LAN interne — Active Directory, pas d'acces Internet |
| snet-mgmt | 10.0.3.0/24 | nsg-mgmt | Management — SIEM Wazuh, acces admin uniquement |

---

## Regles NSG

### nsg-dmz (zone web)

| Nom | Direction | Priorite | Action | Protocole | Ports | Source / Destination |
|-----|-----------|----------|--------|-----------|-------|----------------------|
| Allow-HTTP | Inbound | 100 | Allow | TCP | 80, 443 | Internet |
| Allow-SSH-Admin | Inbound | 110 | Allow | TCP | 22 | IP admin* |
| Allow-Wazuh-Agent | Outbound | 100 | Allow | TCP | 1514, 1515 | 10.0.3.0/24 |
| Deny-DMZ-to-LAN | Outbound | 200 | Deny | * | * | 10.0.2.0/24 |

### nsg-lan (Active Directory)

| Nom | Direction | Priorite | Action | Protocole | Ports | Source / Destination |
|-----|-----------|----------|--------|-----------|-------|----------------------|
| Allow-RDP-Admin | Inbound | 100 | Allow | TCP | 3389 | IP admin* |
| Allow-LAN-to-Web | Outbound | 100 | Allow | TCP | 80, 443 | 10.0.1.0/24 |
| Allow-Wazuh-Agent | Outbound | 110 | Allow | TCP | 1514, 1515 | 10.0.3.0/24 |
| Deny-LAN-to-Internet | Outbound | 200 | Deny | * | * | Internet |

### nsg-mgmt (SIEM)

| Nom | Direction | Priorite | Action | Protocole | Ports | Source / Destination |
|-----|-----------|----------|--------|-----------|-------|----------------------|
| Allow-SSH-Admin | Inbound | 100 | Allow | TCP | 22 | IP admin* |
| Allow-Wazuh-Agents | Inbound | 110 | Allow | TCP | 1514, 1515 | 10.0.0.0/16 |
| Allow-Wazuh-Dashboard | Inbound | 120 | Allow | TCP | 443, 55000 | IP admin* |
| Allow-All-Outbound | Outbound | 100 | Allow | * | * | * |

> *IP admin = IP publique de ton poste (mise a jour via `az network nsg rule update`)

---

## Acces aux VMs

```bash
# Demarrer les VMs
az vm start -g RG-PFE-SOC -n vm-web01
az vm start -g RG-PFE-SOC -n vm-siem01
az vm start -g RG-PFE-SOC -n vm-dc01

# SSH vers le serveur web
ssh azureuser@20.91.233.41

# SSH vers le SIEM
ssh azureuser@20.91.206.70

# RDP vers le Domain Controller
# Ouvrir Microsoft Remote Desktop → 20.91.233.214:3389
# User: azureuser / Password: (voir .env ou Azure portal)

# Arreter les VMs (economie de credit)
az vm deallocate -g RG-PFE-SOC -n vm-web01
az vm deallocate -g RG-PFE-SOC -n vm-siem01
az vm deallocate -g RG-PFE-SOC -n vm-dc01

# Mettre a jour l'IP admin dans les NSG (si ton IP change)
MY_IP=$(curl -s ifconfig.me)
az network nsg rule update -g RG-PFE-SOC --nsg-name nsg-dmz -n Allow-SSH-Admin --source-address-prefixes $MY_IP
az network nsg rule update -g RG-PFE-SOC --nsg-name nsg-lan -n Allow-RDP-Admin --source-address-prefixes $MY_IP
az network nsg rule update -g RG-PFE-SOC --nsg-name nsg-mgmt -n Allow-SSH-Admin --source-address-prefixes $MY_IP
az network nsg rule update -g RG-PFE-SOC --nsg-name nsg-mgmt -n Allow-Wazuh-Dashboard --source-address-prefixes $MY_IP
```

---

## Etat actuel (17 avril 2026)

| VM | Etat | Installe | Reste a faire |
|----|------|----------|---------------|
| vm-web01 | Deallocated | Docker + CorpNet (honeypot PHP) | Agent Wazuh |
| vm-siem01 | Deallocated | OS nu (Ubuntu 22.04) | Wazuh all-in-one |
| vm-dc01 | Deallocated | OS nu (Windows Server 2022) | AD DS + agent Wazuh |

---

## Scripts

| Script | Usage | Cible |
|--------|-------|-------|
| `azure-deploy.sh` | Recreer toute l'infra Azure from scratch | Azure CLI (local) |
| `vm-web01-setup.sh` | Provisioning Docker + corpnet + agent Wazuh | vm-web01 (SSH) |
| `vm-siem01-setup.sh` | Installation Wazuh all-in-one | vm-siem01 (SSH) |
| `vm-dc01-setup.ps1` | AD DS + agent Wazuh Windows | vm-dc01 (RDP/PowerShell) |

---

## Segmentation reseau — Justification

La segmentation en 3 subnets suit les bonnes pratiques SOC :

- **DMZ** : le honeypot est expose sur Internet pour attirer les attaques. Isolation stricte : la DMZ ne peut pas communiquer avec le LAN (`Deny-DMZ-to-LAN`), empechant un pivot lateral.
- **LAN** : le Domain Controller simule le reseau interne d'une entreprise (type RATP). Pas d'acces Internet direct (`Deny-LAN-to-Internet`), comme dans un vrai SI.
- **MGMT** : le SIEM est dans un reseau de management separe. Seuls les agents Wazuh (ports 1514/1515) et l'admin (SSH/dashboard) y accedent.

Cette architecture reproduit un scenario realiste ou un attaquant compromet le serveur web (DMZ), tente un pivot lateral (bloque), et le SOC detecte l'activite via Wazuh.
