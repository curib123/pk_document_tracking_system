param(
    [string]$TargetPath = 'C:\xampp\htdocs\pk_document_tracking_system',
    [string]$LiveBaseUrl = 'http://localhost/pk_document_tracking_system/public/'
)

$ErrorActionPreference = 'Stop'
$sourcePath = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$targetRoot = (Resolve-Path -LiteralPath $TargetPath).Path
if ($sourcePath -eq $targetRoot) { throw 'Run this command from the development checkout, not the served checkout.' }

function Invoke-GitChecked {
    param([string]$Directory, [string[]]$Arguments)
    $result = & git -C $Directory @Arguments
    if ($LASTEXITCODE -ne 0) { throw "Git failed in ${Directory}: $($Arguments -join ' ')" }
    return $result
}

foreach ($checkout in @($sourcePath, $targetRoot)) {
    $changes = Invoke-GitChecked $checkout @('status', '--porcelain')
    if ($changes) { throw "Uncommitted changes in ${checkout}. Commit or preserve them before syncing." }
}
$branch = Invoke-GitChecked $sourcePath @('branch', '--show-current')
if (!$branch) { throw 'The source checkout must be on a branch.' }

# A fast-forward preserves the running checkout's history and private storage.
Invoke-GitChecked $targetRoot @('fetch', '--no-tags', $sourcePath, $branch)
& git -C $targetRoot merge-base --is-ancestor HEAD FETCH_HEAD
if ($LASTEXITCODE -ne 0) { throw 'Checkout histories have diverged. Review their differences before syncing.' }
Invoke-GitChecked $targetRoot @('merge', '--ff-only', 'FETCH_HEAD')

# Verify the actual HTTP response, not only files on disk.
foreach ($asset in @('assets/js/live-table.js', 'assets/js/app.js', 'assets/css/backup.css')) {
    $url = $LiveBaseUrl.TrimEnd('/') + '/' + $asset
    $response = Invoke-WebRequest -Uri $url -UseBasicParsing -Headers @{ 'Cache-Control' = 'no-cache' }
    $algorithm = [System.Security.Cryptography.SHA256]::Create()
    try {
        $response.RawContentStream.Position = 0
        $actual = [BitConverter]::ToString($algorithm.ComputeHash($response.RawContentStream)).Replace('-', '')
    } finally { $algorithm.Dispose() }
    $expected = (Get-FileHash -LiteralPath (Join-Path $sourcePath ('public/' + $asset)) -Algorithm SHA256).Hash
    if ($actual -ne $expected) { throw "The running server returned different bytes for ${url}. Check its document root." }
    Write-Host "Verified $url"
}
Write-Host 'The local XAMPP site is up to date. Reload its browser tab to load the updated page.'
