$ErrorActionPreference = 'Stop'
$taskRoot = Split-Path -Parent $PSScriptRoot
$configFile = Join-Path ([IO.Path]::GetTempPath()) ('pk-office-config-' + [Guid]::NewGuid().ToString('N') + '.json')
try {
    '{"BaseUrl":"http://localhost/pk_document_tracking_system/"}' | Set-Content -LiteralPath $configFile -Encoding UTF8
    $valid = 'http://localhost/pk_document_tracking_system/files/review-content/42/eyJhIjoxfQ.' + ('a' * 64) + '/review.docx'
    $cases = @(
        @($valid, $true),
        @($valid.Replace('.docx','.xlsx'), $true),
        @($valid.Replace('localhost','external.example'), $false),
        @($valid.Replace('http:','file:'), $false),
        @($valid.Replace('/files/review-content/','/files/download/'), $false),
        @($valid.Replace('.docx','.exe'), $false),
        @(($valid + '?redirect=1'), $false)
    )
    $caseIndex = 0
    foreach ($case in $cases) {
        $caseIndex++
        $encoded = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($case[0])).TrimEnd('=').Replace('+','-').Replace('/','_')
        $ErrorActionPreference = 'Continue'
        $result = & powershell.exe -NoProfile -ExecutionPolicy Bypass -File (Join-Path $taskRoot 'tools/office-opener/open-office.ps1') -Uri ('pk-dts-office://wps/' + $encoded) -ConfigPath $configFile -ValidateOnly 2>&1
        $ErrorActionPreference = 'Stop'
        if (($LASTEXITCODE -eq 0) -ne $case[1]) { throw ('Office opener case ' + $caseIndex + ' expected success=' + $case[1] + ', exit=' + $LASTEXITCODE + ': ' + $result) }
    }
    Write-Output 'Windows opener accepts scoped Word/Excel links and rejects other origins, protocols, paths, extensions and queries.'
} finally { Remove-Item -LiteralPath $configFile -Force }
