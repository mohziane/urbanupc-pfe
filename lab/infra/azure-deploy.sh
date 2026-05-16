#!/usr/bin/env bash
# =============================================================
#  UrbanUpC — Azure SOC Infrastructure Deployment
#  Recreates the full hybrid SOC lab from scratch
#  Source: commands extracted from zsh_history (11/04/2026)
# =============================================================
set -euo pipefail

# ── Variables (edit these) ───────────────────────────────────
LOCATION="swedencentral"
RG="rg-pfe-soc"
VNET="vnet-pfe"
VNET_PREFIX="10.0.0.0/16"

SNET_DMZ="snet-dmz"
SNET_DMZ_PREFIX="10.0.1.0/24"
SNET_LAN="snet-lan"
SNET_LAN_PREFIX="10.0.2.0/24"
SNET_MGMT="snet-mgmt"
SNET_MGMT_PREFIX="10.0.3.0/24"

NSG_DMZ="nsg-dmz"
NSG_LAN="nsg-lan"
NSG_MGMT="nsg-mgmt"

VM_SIZE="Standard_B2s_v2"
UBUNTU_IMAGE="Canonical:0001-com-ubuntu-server-jammy:22_04-lts-gen2:latest"
WIN_IMAGE="MicrosoftWindowsServer:WindowsServer:2022-datacenter-g2:latest"
ADMIN_USER="azureuser"

# IP publique de ton poste (pour les regles SSH/RDP)
ADMIN_IP=$(curl -s ifconfig.me)

# Mot de passe pour vm-dc01 (Windows) — min 12 chars, upper+lower+digit+special
WIN_PASSWORD="${WIN_PASSWORD:?Definis WIN_PASSWORD avant de lancer le script}"

echo "============================================="
echo "  UrbanUpC SOC — Azure Deployment"
echo "  Region:   $LOCATION"
echo "  Admin IP: $ADMIN_IP"
echo "============================================="

# ── Etape 1 : Resource Group ────────────────────────────────
echo ""
echo "[1/7] Creation du Resource Group..."
az group create \
    --name "$RG" \
    --location "$LOCATION" \
    --output none

# ── Etape 2 : VNet + Subnets ────────────────────────────────
echo "[2/7] Creation du VNet et des subnets..."
az network vnet create \
    --resource-group "$RG" \
    --name "$VNET" \
    --address-prefix "$VNET_PREFIX" \
    --subnet-name "$SNET_DMZ" \
    --subnet-prefix "$SNET_DMZ_PREFIX" \
    --output none

az network vnet subnet create \
    --resource-group "$RG" \
    --vnet-name "$VNET" \
    --name "$SNET_LAN" \
    --address-prefixes "$SNET_LAN_PREFIX" \
    --output none

az network vnet subnet create \
    --resource-group "$RG" \
    --vnet-name "$VNET" \
    --name "$SNET_MGMT" \
    --address-prefixes "$SNET_MGMT_PREFIX" \
    --output none

# ── Etape 3 : NSGs + Regles ─────────────────────────────────
echo "[3/7] Creation des NSGs et regles de securite..."

# --- NSG DMZ ---
az network nsg create -g "$RG" -n "$NSG_DMZ" --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_DMZ" \
    --name Allow-HTTP \
    --priority 100 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 80 443 \
    --source-address-prefixes Internet \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_DMZ" \
    --name Allow-SSH-Admin \
    --priority 110 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 22 \
    --source-address-prefixes "$ADMIN_IP" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_DMZ" \
    --name Allow-Wazuh-Agent \
    --priority 100 --direction Outbound --access Allow \
    --protocol Tcp --destination-port-ranges 1514 1515 \
    --destination-address-prefixes "$SNET_MGMT_PREFIX" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_DMZ" \
    --name Deny-DMZ-to-LAN \
    --priority 200 --direction Outbound --access Deny \
    --protocol "*" --destination-port-ranges "*" \
    --destination-address-prefixes "$SNET_LAN_PREFIX" \
    --output none

# --- NSG LAN ---
az network nsg create -g "$RG" -n "$NSG_LAN" --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_LAN" \
    --name Allow-RDP-Admin \
    --priority 100 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 3389 \
    --source-address-prefixes "$ADMIN_IP" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_LAN" \
    --name Allow-LAN-to-Web \
    --priority 100 --direction Outbound --access Allow \
    --protocol Tcp --destination-port-ranges 80 443 \
    --destination-address-prefixes "$SNET_DMZ_PREFIX" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_LAN" \
    --name Allow-Wazuh-Agent \
    --priority 110 --direction Outbound --access Allow \
    --protocol Tcp --destination-port-ranges 1514 1515 \
    --destination-address-prefixes "$SNET_MGMT_PREFIX" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_LAN" \
    --name Deny-LAN-to-Internet \
    --priority 200 --direction Outbound --access Deny \
    --protocol "*" --destination-port-ranges "*" \
    --destination-address-prefixes Internet \
    --output none

# --- NSG MGMT ---
az network nsg create -g "$RG" -n "$NSG_MGMT" --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_MGMT" \
    --name Allow-SSH-Admin \
    --priority 100 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 22 \
    --source-address-prefixes "$ADMIN_IP" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_MGMT" \
    --name Allow-Wazuh-Agents \
    --priority 110 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 1514 1515 \
    --source-address-prefixes "$VNET_PREFIX" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_MGMT" \
    --name Allow-Wazuh-Dashboard \
    --priority 120 --direction Inbound --access Allow \
    --protocol Tcp --destination-port-ranges 443 55000 \
    --source-address-prefixes "$ADMIN_IP" \
    --output none

az network nsg rule create -g "$RG" --nsg-name "$NSG_MGMT" \
    --name Allow-All-Outbound \
    --priority 100 --direction Outbound --access Allow \
    --protocol "*" --destination-port-ranges "*" \
    --destination-address-prefixes "*" \
    --output none

# ── Etape 4 : Association NSG → Subnet ──────────────────────
echo "[4/7] Association des NSGs aux subnets..."
az network vnet subnet update -g "$RG" --vnet-name "$VNET" \
    --name "$SNET_DMZ" --network-security-group "$NSG_DMZ" --output none
az network vnet subnet update -g "$RG" --vnet-name "$VNET" \
    --name "$SNET_LAN" --network-security-group "$NSG_LAN" --output none
az network vnet subnet update -g "$RG" --vnet-name "$VNET" \
    --name "$SNET_MGMT" --network-security-group "$NSG_MGMT" --output none

# ── Etape 5 : Creation des VMs ──────────────────────────────
echo "[5/7] Creation des VMs..."

echo "  -> vm-web01 (Ubuntu, DMZ)..."
az vm create \
    --resource-group "$RG" \
    --name vm-web01 \
    --image "$UBUNTU_IMAGE" \
    --size "$VM_SIZE" \
    --vnet-name "$VNET" \
    --subnet "$SNET_DMZ" \
    --private-ip-address 10.0.1.10 \
    --public-ip-address vm-web01-ip \
    --admin-username "$ADMIN_USER" \
    --generate-ssh-keys \
    --nsg "$NSG_DMZ" \
    --output none

echo "  -> vm-siem01 (Ubuntu, MGMT)..."
az vm create \
    --resource-group "$RG" \
    --name vm-siem01 \
    --image "$UBUNTU_IMAGE" \
    --size "$VM_SIZE" \
    --vnet-name "$VNET" \
    --subnet "$SNET_MGMT" \
    --private-ip-address 10.0.3.10 \
    --public-ip-address vm-siem01-ip \
    --admin-username "$ADMIN_USER" \
    --generate-ssh-keys \
    --nsg "$NSG_MGMT" \
    --output none

echo "  -> vm-dc01 (Windows Server, LAN)..."
az vm create \
    --resource-group "$RG" \
    --name vm-dc01 \
    --image "$WIN_IMAGE" \
    --size "$VM_SIZE" \
    --vnet-name "$VNET" \
    --subnet "$SNET_LAN" \
    --private-ip-address 10.0.2.10 \
    --public-ip-address vm-dc01-ip \
    --admin-username "$ADMIN_USER" \
    --admin-password "$WIN_PASSWORD" \
    --nsg "$NSG_LAN" \
    --output none

# ── Etape 6 : Auto-shutdown (22h00 UTC) ─────────────────────
echo "[6/7] Configuration auto-shutdown..."
az vm auto-shutdown -g "$RG" -n vm-web01 --time 2200 --output none
az vm auto-shutdown -g "$RG" -n vm-dc01  --time 2200 --output none

# ── Etape 7 : FQDN sur vm-web01 ─────────────────────────────
echo "[7/7] Configuration du FQDN..."
az network public-ip update \
    -g "$RG" \
    -n vm-web01-ip \
    --dns-name corpnet-pfe \
    --output none

# ── Recapitulatif ────────────────────────────────────────────
echo ""
echo "============================================="
echo "  Deploiement termine !"
echo "============================================="
echo ""
az vm list -g "$RG" --show-details \
    --query "[].{Nom:name, Etat:powerState, IP_Publique:publicIps, IP_Privee:privateIps, FQDN:fqdns}" \
    --output table
echo ""
echo "Prochaines etapes :"
echo "  1. ssh azureuser@\$(az vm show -g $RG -n vm-web01 --show-details -o tsv --query publicIps)"
echo "     Puis executer : infra/vm-web01-setup.sh"
echo "  2. ssh azureuser@\$(az vm show -g $RG -n vm-siem01 --show-details -o tsv --query publicIps)"
echo "     Puis executer : infra/vm-siem01-setup.sh"
echo "  3. RDP vers vm-dc01, puis executer : infra/vm-dc01-setup.ps1"
