param(
    [string]$NgrokPath = (Join-Path $env:LOCALAPPDATA 'Programs/ngrok/ngrok.exe')
)

$ErrorActionPreference = 'Stop'
if (!(Test-Path -LiteralPath $NgrokPath -PathType Leaf)) {
    $installed = Get-Command ngrok.exe -ErrorAction SilentlyContinue
    if (!$installed) { throw 'Install ngrok from https://ngrok.com/download/windows or pass -NgrokPath.' }
    $NgrokPath = $installed.Source
}

$connection = New-Object System.Net.Sockets.TcpClient
try {
    $connection.Connect('127.0.0.1', 443)
} catch {
    throw 'Start Apache in XAMPP first. This launcher requires local HTTPS on port 443.'
} finally {
    $connection.Dispose()
}

$policyPath = Join-Path $PSScriptRoot 'ngrok-policy.json'
Get-Content -Raw -LiteralPath $policyPath | ConvertFrom-Json | Out-Null

Write-Host 'Sharing PK DTS through the existing XAMPP HTTPS listener.'
Write-Host 'Open the HTTPS Forwarding URL shown by ngrok. Its root redirects to PK DTS.'
Write-Host 'Keep this window open; press Ctrl+C to stop sharing.'
# XAMPP uses a self-signed certificate on this loopback-only upstream connection.
# Preserve the public Host header so application links use the public HTTPS URL.
& $NgrokPath http 'https://127.0.0.1:443' '--upstream-tls-verify=false' '--inspect=false' '--traffic-policy-file' $policyPath
if ($LASTEXITCODE -ne 0) {
    throw 'ngrok could not start. Check the error above and connect your account with ngrok config add-authtoken.'
}
