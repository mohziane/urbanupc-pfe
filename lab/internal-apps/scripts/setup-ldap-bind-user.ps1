# Run on vm-dc01 (RDP or `az vm run-command`) BEFORE deploying internal-apps.
# Creates a low-privilege LDAP bind user that MaFormation uses to search the directory.
#
# After running this script, copy the password to your local machine:
#   echo -n 'TheGeneratedPassword' > internal-apps/secrets/ldap_bind_pw.txt

param(
    [string]$BindUser = "svc_ldap",
    [string]$BindOU   = "CN=Users,DC=corpnet,DC=local"
)

Import-Module ActiveDirectory -ErrorAction Stop

# Strong random password (24 chars, mixed)
function New-StrongPassword {
    $bytes = New-Object byte[] 18
    [System.Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    return [Convert]::ToBase64String($bytes) -replace '/','_' -replace '\+','-'
}

$existing = Get-ADUser -Filter "SamAccountName -eq '$BindUser'" -ErrorAction SilentlyContinue
if ($existing) {
    Write-Host "[ldap] User $BindUser already exists. Resetting password..."
    $pw = New-StrongPassword
    Set-ADAccountPassword -Identity $existing -NewPassword (ConvertTo-SecureString $pw -AsPlainText -Force) -Reset
    Enable-ADAccount -Identity $existing
} else {
    Write-Host "[ldap] Creating user $BindUser ..."
    $pw = New-StrongPassword
    New-ADUser `
        -SamAccountName $BindUser `
        -Name "Service LDAP Bind" `
        -DisplayName "LDAP Bind (internal apps)" `
        -Description "Read-only bind user for MaFormation LDAP authentication" `
        -Path "CN=Users,DC=corpnet,DC=local" `
        -AccountPassword (ConvertTo-SecureString $pw -AsPlainText -Force) `
        -Enabled $true `
        -PasswordNeverExpires $true `
        -CannotChangePassword $true
}

Write-Host ""
Write-Host "==============================================================="
Write-Host " LDAP bind user provisioned"
Write-Host "   DN:       CN=Service LDAP Bind,$BindOU"
Write-Host "   sAMAccountName: $BindUser"
Write-Host "   password: $pw"
Write-Host ""
Write-Host " Copy the password ONCE to your laptop:"
Write-Host "   printf '%s' '$pw' > internal-apps/secrets/ldap_bind_pw.txt"
Write-Host "==============================================================="
