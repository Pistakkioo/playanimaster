# Upload ANIMASTER files to the production SFTP server and optionally verify.
# Usage:
#   .\upload-to-server.ps1 -Files 'public_html/funzioni/open_actions/get_npcs.php'
#   .\upload-to-server.ps1 -Files @('public_html/client/game.php','private_functions/character_profile.php') -VerifyOnly

param(
    [Parameter(Mandatory = $true)]
    [string[]]$Files,

    [switch]$VerifyOnly
)

$ErrorActionPreference = 'Stop'

$HostName = '5.250.188.110'
$Port = 21709
$User = 'dev'
$Key = 'C:/Users/sergi/.ssh/id_ed25519'
$RemoteBase = '/var/www/playanimaster'
$LocalBase = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

function Get-RemotePath([string]$LocalFilePath)
{
    $resolved = (Resolve-Path $LocalFilePath).Path
    if ($resolved -notlike "$LocalBase*")
    {
        throw "File is outside ANIMASTER root: $LocalFilePath"
    }

    $relative = $resolved.Substring($LocalBase.Length).TrimStart('\', '/')
    return "$RemoteBase/$($relative -replace '\\', '/')"
}

function Invoke-Remote([string]$Command)
{
    & ssh -i $Key -p $Port -o BatchMode=yes -o StrictHostKeyChecking=accept-new "${User}@${HostName}" $Command
    if ($LASTEXITCODE -ne 0)
    {
        throw "Remote command failed (exit $LASTEXITCODE): $Command"
    }
}

foreach ($file in $Files)
{
    $localPath = Join-Path $LocalBase $file
    if (-not (Test-Path $localPath))
    {
        throw "Local file not found: $localPath"
    }

    $remotePath = Get-RemotePath $localPath
    Write-Host "Remote: $remotePath"

    if ($VerifyOnly)
    {
        Invoke-Remote "test -f '$remotePath' && head -n 25 '$remotePath'"
        continue
    }

    $remoteDir = ($remotePath -replace '/[^/]+$', '')
    Invoke-Remote "mkdir -p '$remoteDir'"
    & scp -i $Key -P $Port -o BatchMode=yes -o StrictHostKeyChecking=accept-new $localPath "${User}@${HostName}:${remotePath}"
    if ($LASTEXITCODE -ne 0)
    {
        throw "SCP failed for $file (exit $LASTEXITCODE). Run: ssh-add $Key"
    }

    Write-Host "Uploaded: $file"
    Invoke-Remote "head -n 25 '$remotePath'"
}

Write-Host 'Done.'
