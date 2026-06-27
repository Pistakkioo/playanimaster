# Deploy ANIMASTER to production (SSH + rsync / tar+scp / scp)
#
# Prerequisites:
#   - OpenSSH client (Windows 10+)
#   - SSH key loaded: ssh-add C:/Users/sergi/.ssh/id_ed25519
#
# Full sync uses rsync if available (scoop: cwrsync), else tar+scp (built into Windows).
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
$ScpArgs = @("-P", $RemotePort, "-i", $Key, "-o", "BatchMode=yes")

$DeployExcludes = @(
    ".git",
    ".env",
    "LOG",
    "docker/data",
    "old_cs_files",
    "private_functions/d.php"
)

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

function Find-RsyncExe
{
    $candidates = @(
        (Get-Command rsync -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source),
        "$env:USERPROFILE\scoop\shims\rsync.exe",
        "$env:USERPROFILE\scoop\apps\cwrsync\current\bin\rsync.exe"
    )

    foreach ($path in $candidates)
    {
        if ($path -and (Test-Path $path))
        {
            return $path
        }
    }

    return $null
}

function Get-RsyncExcludeArgs
{
    $excludeArgs = @()
    foreach ($pattern in $DeployExcludes)
    {
        $excludeArgs += "--exclude"
        $excludeArgs += $pattern
    }
    return $excludeArgs
}

function Get-TarExcludeArgs
{
    $excludeArgs = @()
    foreach ($pattern in $DeployExcludes)
    {
        $excludeArgs += "--exclude=$pattern"
    }
    return $excludeArgs
}

function Invoke-RsyncDeploy
{
    param(
        [string]$RsyncExe
    )

    $excludeArgs = Get-RsyncExcludeArgs
    $dryArgs = if ($DryRun) { @("--dry-run") } else { @() }
    $sshCmd = "ssh -p $RemotePort -i `"$Key`""
    $localPath = ($Root.TrimEnd('\', '/') + '/')
    $remoteDest = ($SshTarget + ':' + $RemotePath.TrimEnd('/') + '/')

    Write-Host "Full sync via rsync..." -ForegroundColor Cyan
    Write-Host "  $localPath -> $remoteDest" -ForegroundColor DarkGray

    & $RsyncExe -avz --delete @dryArgs @excludeArgs -e $sshCmd $localPath $remoteDest

    return ($LASTEXITCODE -eq 0)
}

function Invoke-TarDeploy
{
    if ($DryRun)
    {
        Write-Host "[dry-run] tar+scp full sync -> ${RemotePath}/" -ForegroundColor Cyan
        Write-Host "  Excludes: $($DeployExcludes -join ', ')" -ForegroundColor DarkGray
        Write-Host "  Note: tar mode updates/adds files but does not delete removed files on server." -ForegroundColor DarkGray
        return $true
    }

    $tar = Get-Command tar -ErrorAction SilentlyContinue
    if (-not $tar)
    {
        Write-Host "tar not found. Use -Files for partial upload." -ForegroundColor Yellow
        return $false
    }

    $tempTar = Join-Path $env:TEMP ("animaster-deploy-" + [guid]::NewGuid().ToString("n") + ".tar.gz")
    $remoteTar = "/tmp/animaster-deploy.tar.gz"

    Write-Host "Full sync via tar+scp (no rsync)..." -ForegroundColor Cyan
    Write-Host "  Building archive..." -ForegroundColor DarkGray

    try
    {
        Push-Location $Root
        $tarExcludeArgs = Get-TarExcludeArgs
        & tar -czf $tempTar @tarExcludeArgs .
        if ($LASTEXITCODE -ne 0)
        {
            throw "tar create failed (exit $LASTEXITCODE)"
        }
        Pop-Location

        Write-Host "  Uploading..." -ForegroundColor DarkGray
        $remoteTarTarget = ($SshTarget + ':' + $remoteTar)
        & scp @ScpArgs $tempTar $remoteTarTarget
        if ($LASTEXITCODE -ne 0)
        {
            throw "scp failed (exit $LASTEXITCODE)"
        }

        Write-Host "  Extracting on server..." -ForegroundColor DarkGray
        & ssh @SshArgs $SshTarget "mkdir -p '$RemotePath' && tar -xzf '$remoteTar' -C '$RemotePath' && rm -f '$remoteTar'"
        if ($LASTEXITCODE -ne 0)
        {
            throw "remote extract failed (exit $LASTEXITCODE)"
        }

        return $true
    }
    finally
    {
        if ((Get-Location).Path -ne $Root)
        {
            Pop-Location -ErrorAction SilentlyContinue
        }

        Remove-Item $tempTar -Force -ErrorAction SilentlyContinue
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
        $remoteFileTarget = ($SshTarget + ':' + $remoteFile)
        & scp @ScpArgs $local $remoteFileTarget
        Write-Host "Uploaded: $rel" -ForegroundColor Green
    }

    exit 0
}

$rsyncExe = Find-RsyncExe
$ok = $false

if ($rsyncExe)
{
    $ok = Invoke-RsyncDeploy -RsyncExe $rsyncExe
}
else
{
    Write-Host "rsync not found - using tar+scp fallback." -ForegroundColor Yellow
    Write-Host "  Optional: scoop install cwrsync   (not 'rsync')" -ForegroundColor DarkGray
    $ok = Invoke-TarDeploy
}

if ($ok)
{
    Write-Host "Deploy complete." -ForegroundColor Green
}
else
{
    Write-Host "Deploy failed." -ForegroundColor Red
    exit 1
}
