# Deploy PasteGuard plugins to the local Moodle instance and purge caches.
# Usage: powershell -ExecutionPolicy Bypass -File deploy.ps1

$ErrorActionPreference = 'Stop'

$repo = $PSScriptRoot
$moodle = 'D:\server\moodle\public'
$php = 'D:\server\php\php.exe'

$targets = @{
    'tiny_pasteguard' = Join-Path $moodle 'lib\editor\tiny\plugins\pasteguard'
    'local_pasteguard' = Join-Path $moodle 'local\pasteguard'
}

foreach ($plugin in $targets.Keys) {
    $src = Join-Path $repo $plugin
    $dst = $targets[$plugin]
    Write-Host "Deploying $plugin -> $dst"
    robocopy $src $dst /MIR /NFL /NDL /NJH /NJS | Out-Null
    if ($LASTEXITCODE -ge 8) {
        throw "robocopy failed for $plugin (exit $LASTEXITCODE)"
    }
}

Write-Host 'Purging caches...'
& $php 'D:\server\moodle\admin\cli\purge_caches.php'

Write-Host 'Done. If version.php or db/ changed, also run:'
Write-Host "  $php D:\server\moodle\admin\cli\upgrade.php --non-interactive"
