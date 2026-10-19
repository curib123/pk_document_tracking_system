param(
    [Parameter(Mandatory = $true)][string]$Uri,
    [string]$ConfigPath = (Join-Path $PSScriptRoot 'config.json'),
    [switch]$ValidateOnly
)
$ErrorActionPreference = 'Stop'
try {
    if ($Uri.Length -gt 4096 -or $Uri -notmatch '^pk-dts-office://wps/([A-Za-z0-9_-]+)$') { throw 'Invalid PK DTS app link.' }
    $encoded = $Matches[1].Replace('-', '+').Replace('_', '/')
    $encoded = $encoded.PadRight($encoded.Length + ((4 - $encoded.Length % 4) % 4), '=')
    $documentUri = [Uri][Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($encoded))
    $siteUri = [Uri](Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json).BaseUrl
    $allowedPrefix = $siteUri.AbsolutePath.TrimEnd('/') + '/files/review-content/'
    if (!$documentUri.IsAbsoluteUri -or $documentUri.Scheme -notin @('http', 'https') -or
        $documentUri.GetLeftPart([UriPartial]::Authority) -ne $siteUri.GetLeftPart([UriPartial]::Authority) -or
        $documentUri.UserInfo -or $documentUri.Query -or $documentUri.Fragment -or
        !$documentUri.AbsolutePath.StartsWith($allowedPrefix, [StringComparison]::Ordinal) -or
        $documentUri.AbsolutePath.Substring($allowedPrefix.Length) -notmatch '^[1-9][0-9]*/[A-Za-z0-9_-]+\.[a-f0-9]{64}/review\.(docx|xlsx)$') {
        throw 'This link is outside the configured PK DTS review service.'
    }
    $extension = $Matches[1]
    if ($ValidateOnly) { Write-Output ('Validated PK DTS review link for .' + $extension); exit }
    $exeName = if ($extension -eq 'xlsx') { 'et.exe' } else { 'wps.exe' }
    $executable = $null
    foreach ($hive in @('HKEY_CURRENT_USER', 'HKEY_LOCAL_MACHINE')) {
        foreach ($candidate in @($exeName, 'wps.exe')) {
            $value = [Microsoft.Win32.Registry]::GetValue(($hive + '\SOFTWARE\Microsoft\Windows\CurrentVersion\App Paths\' + $candidate), '', $null)
            if ($value -and (Test-Path -LiteralPath $value -PathType Leaf)) { $executable = $value; break }
        }
        if ($executable) { break }
    }
    if (!$executable) {
        foreach ($root in @($env:LOCALAPPDATA, $env:ProgramFiles, ${env:ProgramFiles(x86)})) {
            if (!$root) { continue }
            $wpsRoot = Join-Path $root 'Kingsoft\WPS Office'
            if (!(Test-Path -LiteralPath $wpsRoot)) { continue }
            foreach ($directory in @((Get-Item -LiteralPath $wpsRoot)) + @(Get-ChildItem -LiteralPath $wpsRoot -Directory)) {
                foreach ($candidate in @($exeName, 'wps.exe')) {
                    $path = Join-Path $directory.FullName ('office6\' + $candidate)
                    if (Test-Path -LiteralPath $path -PathType Leaf) { $executable = $path; break }
                }
                if ($executable) { break }
            }
            if ($executable) { break }
        }
    }
    if (!$executable) { throw 'WPS Office is not installed. Install WPS Office, or use Download on the review page.' }
    Add-Type -AssemblyName System.Net.Http
    $handler = New-Object System.Net.Http.HttpClientHandler
    $handler.AllowAutoRedirect = $false
    $client = New-Object System.Net.Http.HttpClient($handler)
    $client.MaxResponseContentBufferSize = 20971520
    $client.Timeout = [TimeSpan]::FromSeconds(60)
    try {
        $response = $client.GetAsync($documentUri).GetAwaiter().GetResult()
        if ([int]$response.StatusCode -ne 200) { throw 'Review access expired. Reopen Review File in PK DTS and try again.' }
        $bytes = $response.Content.ReadAsByteArrayAsync().GetAwaiter().GetResult()
        if ($bytes.Length -lt 4 -or $bytes[0] -ne 80 -or $bytes[1] -ne 75) { throw 'The server did not return an Office review file.' }
    } finally { $client.Dispose(); $handler.Dispose() }
    $reviewDirectory = Join-Path $env:LOCALAPPDATA ('PK DTS\ReviewCopies\' + [Guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $reviewDirectory -Force | Out-Null
    $reviewFile = Join-Path $reviewDirectory ('review.' + $extension)
    [IO.File]::WriteAllBytes($reviewFile, $bytes)
    (Get-Item -LiteralPath $reviewFile).IsReadOnly = $true
    Start-Process -FilePath $executable -ArgumentList ('"' + $reviewFile + '"') -WindowStyle Hidden
} catch {
    if ($ValidateOnly) { throw }
    Add-Type -AssemblyName System.Windows.Forms
    [System.Windows.Forms.MessageBox]::Show($_.Exception.Message, 'PK DTS - Open in WPS') | Out-Null
    exit 1
}
