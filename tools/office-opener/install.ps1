param(
    [string]$BaseUrl,
    [switch]$ValidateOnly,
    [switch]$Uninstall
)
$ErrorActionPreference = 'Stop'
$installDirectory = Join-Path $env:LOCALAPPDATA 'PK DTS\OfficeOpener'
$protocolKey = 'HKCU:\Software\Classes\pk-dts-office'
if ($Uninstall) {
    if (Test-Path -LiteralPath $protocolKey) {
        $registered = (Get-Item -LiteralPath ($protocolKey + '\shell\open\command')).GetValue('')
        if ($registered -notlike ('*' + $installDirectory + '*')) { throw 'This protocol belongs to another installation.' }
        Remove-Item -LiteralPath $protocolKey -Recurse
    }
    Write-Output 'PK DTS WPS opener unregistered. Download remains available.'
    exit
}
if (!$BaseUrl) {
    $configFile = Join-Path $PSScriptRoot 'config.json'
    if (!(Test-Path -LiteralPath $configFile)) { throw 'Supply -BaseUrl with the PK DTS site URL.' }
    $BaseUrl = (Get-Content -LiteralPath $configFile -Raw | ConvertFrom-Json).BaseUrl
}
$siteUri = [Uri]$BaseUrl
if (!$siteUri.IsAbsoluteUri -or $siteUri.Scheme -notin @('http', 'https') -or $siteUri.UserInfo -or $siteUri.Query -or $siteUri.Fragment) {
    throw 'Use an HTTP or HTTPS PK DTS site URL without a query or credentials.'
}
if ($ValidateOnly) { Write-Output 'PK DTS opener site configuration validated.'; exit }
New-Item -ItemType Directory -Path $installDirectory -Force | Out-Null
Copy-Item -LiteralPath (Join-Path $PSScriptRoot 'open-office.ps1') -Destination (Join-Path $installDirectory 'open-office.ps1') -Force
@{ BaseUrl = $siteUri.AbsoluteUri.TrimEnd('/') + '/' } | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $installDirectory 'config.json') -Encoding UTF8
New-Item -Path ($protocolKey + '\shell\open\command') -Force | Out-Null
Set-Item -LiteralPath $protocolKey -Value 'URL:PK DTS Office Review'
New-ItemProperty -LiteralPath $protocolKey -Name 'URL Protocol' -Value '' -PropertyType String -Force | Out-Null
$powerShellExe = Join-Path $PSHOME 'powershell.exe'
$openerScript = Join-Path $installDirectory 'open-office.ps1'
Set-Item -LiteralPath ($protocolKey + '\shell\open\command') -Value ('"' + $powerShellExe + '" -NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File "' + $openerScript + '" -Uri "%1"')
Write-Output 'PK DTS WPS opener installed for this Windows user. Return to Review File and choose Open in WPS.'
