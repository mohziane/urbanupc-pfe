# =============================================================
#  vm-dc01 — Provisioning : Active Directory + Wazuh Agent Windows
#
#  Usage :
#    1. RDP vers vm-dc01 (20.91.233.214:3389)
#    2. Ouvrir PowerShell en Administrateur
#    3. Copier-coller ce script ou l'executer :
#       Set-ExecutionPolicy Bypass -Scope Process -Force
#       .\vm-dc01-setup.ps1
#
#  La VM redemarrera automatiquement apres la promotion AD DS.
#  Relancer le script apres le reboot pour les etapes restantes.
# =============================================================

$WazuhManagerIP = "10.0.3.10"
$DomainName     = "corpnet.local"
$NetBIOSName    = "CORPNET"
$SafeModePass   = ConvertTo-SecureString "PfE-S0c@2025!" -AsPlainText -Force

Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  vm-dc01 - Provisioning Active Directory"     -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan

# ── Etape 1 : Installation du role AD DS ─────────────────────
Write-Host ""
Write-Host "[1/4] Installation du role AD DS + DNS..." -ForegroundColor Yellow

$adFeature = Get-WindowsFeature AD-Domain-Services
if (-not $adFeature.Installed) {
    Install-WindowsFeature -Name AD-Domain-Services -IncludeManagementTools
    Write-Host "  Role AD DS installe." -ForegroundColor Green
} else {
    Write-Host "  Role AD DS deja installe, skip." -ForegroundColor Gray
}

# ── Etape 2 : Promotion en Domain Controller ────────────────
Write-Host ""
Write-Host "[2/4] Promotion en Domain Controller..." -ForegroundColor Yellow

try {
    $domain = Get-ADDomain -ErrorAction Stop
    Write-Host "  Domaine '$($domain.DNSRoot)' deja configure, skip." -ForegroundColor Gray
} catch {
    Write-Host "  Creation de la foret $DomainName..."
    Write-Host "  La VM va REDEMARRER automatiquement." -ForegroundColor Red
    Write-Host "  Apres le reboot, relancer ce script pour les etapes 3 et 4." -ForegroundColor Red
    Write-Host ""

    Install-ADDSForest `
        -DomainName $DomainName `
        -DomainNetbiosName $NetBIOSName `
        -SafeModeAdministratorPassword $SafeModePass `
        -InstallDNS:$true `
        -NoRebootOnCompletion:$false `
        -Force:$true

    # Le script s'arrete ici — la VM reboot
    exit
}

# ── Etape 3 : Creation des OUs et utilisateurs ──────────────
Write-Host ""
Write-Host "[3/4] Creation de la structure AD..." -ForegroundColor Yellow

# OUs
$OUs = @(
    @{Name="SOC-Users";    Path="DC=corpnet,DC=local"},
    @{Name="SOC-Admins";   Path="DC=corpnet,DC=local"},
    @{Name="SOC-Servers";  Path="DC=corpnet,DC=local"},
    @{Name="IT";           Path="OU=SOC-Users,DC=corpnet,DC=local"},
    @{Name="RH";           Path="OU=SOC-Users,DC=corpnet,DC=local"},
    @{Name="Finance";      Path="OU=SOC-Users,DC=corpnet,DC=local"}
)

foreach ($ou in $OUs) {
    $ouDN = "OU=$($ou.Name),$($ou.Path)"
    if (-not (Get-ADOrganizationalUnit -Filter "DistinguishedName -eq '$ouDN'" -ErrorAction SilentlyContinue)) {
        New-ADOrganizationalUnit -Name $ou.Name -Path $ou.Path -ProtectedFromAccidentalDeletion $false
        Write-Host "  OU cree : $($ou.Name)" -ForegroundColor Green
    }
}

# Utilisateurs de test (simulent les employes du honeypot)
$DefaultPass = ConvertTo-SecureString "Welcome2025!" -AsPlainText -Force
$Users = @(
    @{Sam="m.martin";   First="Marie";    Last="Martin";    Dept="IT";      OU="OU=IT,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="p.bernard";  First="Pierre";   Last="Bernard";   Dept="RH";      OU="OU=RH,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="a.lefebvre"; First="Anne";     Last="Lefebvre";  Dept="Finance"; OU="OU=Finance,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="j.dupont";   First="Jean";     Last="Dupont";    Dept="IT";      OU="OU=IT,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="s.david";    First="Sophie";   Last="David";     Dept="RH";      OU="OU=RH,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="c.simon";    First="Claire";   Last="Simon";     Dept="Finance"; OU="OU=Finance,OU=SOC-Users,DC=corpnet,DC=local"},
    @{Sam="soc.admin";  First="SOC";      Last="Admin";     Dept="IT";      OU="OU=SOC-Admins,DC=corpnet,DC=local"}
)

foreach ($u in $Users) {
    if (-not (Get-ADUser -Filter "SamAccountName -eq '$($u.Sam)'" -ErrorAction SilentlyContinue)) {
        New-ADUser `
            -SamAccountName $u.Sam `
            -UserPrincipalName "$($u.Sam)@$DomainName" `
            -GivenName $u.First `
            -Surname $u.Last `
            -Name "$($u.First) $($u.Last)" `
            -Department $u.Dept `
            -Path $u.OU `
            -AccountPassword $DefaultPass `
            -Enabled $true `
            -ChangePasswordAtLogon $false
        Write-Host "  Utilisateur cree : $($u.Sam)" -ForegroundColor Green
    }
}

# Ajouter soc.admin au groupe Domain Admins
Add-ADGroupMember -Identity "Domain Admins" -Members "soc.admin" -ErrorAction SilentlyContinue

Write-Host "  Structure AD creee." -ForegroundColor Green

# ── Etape 4 : Installation agent Wazuh Windows ──────────────
Write-Host ""
Write-Host "[4/4] Installation de l'agent Wazuh Windows..." -ForegroundColor Yellow

$wazuhSvc = Get-Service -Name WazuhSvc -ErrorAction SilentlyContinue
if (-not $wazuhSvc) {
    $wazuhMSI = "$env:TEMP\wazuh-agent.msi"

    # Pin explicite : aligner avec le manager all-in-one (4.9.2).
    # Sans pin, on récupère le dernier MSI publié sur le repo 4.x
    # (ex. 4.10.x) et l'enrôlement échoue silencieusement.
    $WazuhAgentVersion = "4.9.2-1"
    $WazuhAgentMsi = "https://packages.wazuh.com/4.x/windows/wazuh-agent-$WazuhAgentVersion.msi"

    Write-Host "  Telechargement de l'agent Wazuh ($WazuhAgentVersion)..."
    Invoke-WebRequest `
        -Uri $WazuhAgentMsi `
        -OutFile $wazuhMSI

    Write-Host "  Installation..."
    Start-Process msiexec.exe -ArgumentList @(
        "/i", $wazuhMSI,
        "/q",
        "WAZUH_MANAGER=$WazuhManagerIP",
        "WAZUH_AGENT_NAME=vm-dc01"
    ) -Wait -NoNewWindow

    # Demarrer le service
    Start-Service WazuhSvc -ErrorAction SilentlyContinue
    Write-Host "  Agent Wazuh installe et demarre." -ForegroundColor Green

    # Firewall : autoriser le trafic sortant vers le manager
    New-NetFirewallRule `
        -DisplayName "Wazuh Agent Outbound" `
        -Direction Outbound `
        -Protocol TCP `
        -RemotePort 1514,1515 `
        -RemoteAddress $WazuhManagerIP `
        -Action Allow `
        -ErrorAction SilentlyContinue

    Write-Host "  Regle firewall ajoutee." -ForegroundColor Green
} else {
    Write-Host "  Agent Wazuh deja installe ($($wazuhSvc.Status))." -ForegroundColor Gray
}

# ── Recapitulatif ────────────────────────────────────────────
Write-Host ""
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host "  vm-dc01 - Provisioning termine !"            -ForegroundColor Cyan
Write-Host "=============================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "  Domaine :       $DomainName"
Write-Host "  Utilisateurs :  $($Users.Count) crees"
Write-Host "  Wazuh agent :   $((Get-Service WazuhSvc -ErrorAction SilentlyContinue).Status)"
Write-Host ""
Write-Host "  Pour verifier AD :"
Write-Host "    Get-ADUser -Filter * | Select Name, SamAccountName"
Write-Host "    Get-ADOrganizationalUnit -Filter * | Select Name"
Write-Host ""
