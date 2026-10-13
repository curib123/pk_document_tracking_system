#Requires -RunAsAdministrator
<#
 Register a daily encrypted PK DTS backup on an XAMPP Windows host.
 Run once in elevated PowerShell. Jobs are scheduled for 17:00 LOCAL machine
 time. Philippines time is UTC+8, with no daylight-saving time.
 Environment variables must be available to the SYSTEM service account.
#>
param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path,
    [string]$PhpExe = 'C:\xampp\php\php.exe',
    [string]$TaskName = 'PKDTS-Daily-Backup-5PM'
)
$ErrorActionPreference = 'Stop'
if (-not (Test-Path -LiteralPath $PhpExe -PathType Leaf)) {
    throw "PHP executable not found: $PhpExe"
}
$ScriptPath = Join-Path $ProjectRoot 'tools\backup.php'
if (-not (Test-Path -LiteralPath $ScriptPath -PathType Leaf)) {
    throw "Backup CLI not found: $ScriptPath"
}
$timezone = [TimeZoneInfo]::Local.BaseUtcOffset.TotalHours
if ($timezone -ne 8) {
    throw 'Host timezone must be UTC+08:00 for 5:00 PM Philippines scheduling.'
}
$action = New-ScheduledTaskAction -Execute $PhpExe -Argument ('"' + $ScriptPath + '" auto') -WorkingDirectory $ProjectRoot
$trigger = New-ScheduledTaskTrigger -Daily -At '5:00PM'
$settings = New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Hours 6)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
Register-ScheduledTask -TaskName $TaskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Description 'Full signed AES-256 PK Document Tracking System snapshot every day at 5:00 PM Philippine time' -Force | Out-Null
Write-Host ("Registered {0} for 5:00 PM Asia/Manila host time." -f $TaskName)
Write-Host "Verify Machine-scope DB_* and PK_BACKUP_* environment variables, then run the task manually once."
Write-Host ("Test with: Start-ScheduledTask -TaskName '{0}'" -f $TaskName)
