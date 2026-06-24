# Deploy ANIMASTER to production (SSH + rsync/scp)
#
# Prerequisites:
#   - OpenSSH client (Windows 10+)
#   - SSH key loaded: ssh-add C:/Users/sergi/.ssh/id_ed25519
#
# Usage:
#   .\scripts\deploy.ps1              # sync entire project (respects excludes)
#   .\scripts\deploy.ps1 -DryRun      # show what would be sent
#   .\scripts\deploy.ps1 -Files @('public_html/client/js/trade.js')

param(
    [string]$RemoteHost = "5.250.188.110",
    [int]$RemotePort = 21709,
    [string]$RemoteUser = "dev",
    [string]$RemotePath = "/var/www/playanimaster",
    [string[]]$Files = @(),
    [switch]$DryRun
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$Key = "$env:USERPROFILE\.ssh\id_ed25519"
$SshTarget = "${RemoteUser}@${RemoteHost}"
$SshArgs = @("-p", $RemotePort, "-i", $Key, "-o", "BatchMode=yes")

function Test-Ssh
{
    & ssh @SshArgs $SshTarget "echo ok" 2>$null
    if ($LASTEXITCODE -ne 0)
    {
        Write-Host "SSH failed. Unlock your key:" -ForegroundColor Yellow
        Write-Host "  ssh-add $Key"
        exit 1
    }
}

Test-Ssh

if ($Files.Count -gt 0)
{
    foreach ($rel in $Files)
    {
        $local = Join-Path $Root ($rel -replace '^ANIMASTER/', '')
        if (-not (Test-Path $local))
        {
            Write-Host "Skip missing: $local" -ForegroundColor Yellow
            continue
        }

        $remoteFile = "$RemotePath/" + ($rel -replace '\\', '/' -replace '^ANIMASTER/', '')
        $remoteDir = ($remoteFile -replace '/[^/]+$', '')

        if ($DryRun)
        {
            Write-Host "[dry-run] $local -> $remoteFile"
            continue
        }

        & ssh @SshArgs $SshTarget "mkdir -p `"$remoteDir`""
        & scp -P $RemotePort -i $Key $local "${SshTarget}:${remoteFile}"
        Write-Host "Uploaded: $rel" -ForegroundColor Green
    }

    exit 0
}

# Full sync — requires rsync in PATH (Git for Windows / WSL)
$rsync = Get-Command rsync -ErrorAction SilentlyContinue
if (-not $rsync)
{
    Write-Host "rsync not found. Install Git for Windows or use -Files for single uploads." -ForegroundColor Yellow
    Write-Host "Example: .\scripts\deploy.ps1 -Files @('public_html/client/js/trade.js')"
    exit 1
}

$Dry = if ($DryRun) { "--dry-run" } else { "" }
$excludes = @(
    "--exclude", ".git",
    "--exclude", ".env",
    "--exclude", "LOG",
    "--exclude", "docker/data",
    "--exclude", "old_cs_files",
    "--exclude", "private_functions/i.php",
    "--exclude", "private_functions/c_variabili.php"
)

& rsync -avz --delete $Dry -e "ssh -p $RemotePort -i $Key" $excludes `
    "$Root/" "${SshTarget}:${RemotePath}/"

if ($LASTEXITCODE -eq 0)
{
    Write-Host "Deploy complete." -ForegroundColor Green
}
