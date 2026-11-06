<#
.SYNOPSIS
Open and render the documentation with Microsoft's installed Visio Viewer.
.DESCRIPTION
Requires Windows PowerShell in STA mode and a registered Microsoft Visio Viewer
ActiveX control. This tests the native Viewer, not the desktop Visio editor.
Every page is rendered in memory and its shape count is checked against the XML.
Drawings are opened read-only; no drawing or system settings are saved.
Reports and optional PNGs must stay under the repository's ignored storage folder.
.EXAMPLE
powershell.exe -NoProfile -STA -ExecutionPolicy Bypass -File tools/documentation/verify_visio.ps1
.EXAMPLE
& ./tools/documentation/verify_visio.ps1 -Paths @('docs/system-documentation/PK_DTS_DFD.vsdx') -RenderPages
#>
[CmdletBinding()]
param(
    [string[]]$Paths,
    [string]$OutputDirectory = 'storage/visio-verification',
    [switch]$RenderPages
)

$ErrorActionPreference = 'Stop'
$repositoryRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '../..'))
$viewerClassId = 'F8CF7A98-2C45-4c8d-9151-2D716989DDAB'

function Get-LocalPath([string]$Value) {
    if ([IO.Path]::IsPathRooted($Value)) { return [IO.Path]::GetFullPath($Value) }
    return [IO.Path]::GetFullPath((Join-Path $repositoryRoot $Value))
}

function Read-SafeXml([IO.Stream]$Stream) {
    $settings = New-Object System.Xml.XmlReaderSettings
    $settings.DtdProcessing = [Xml.DtdProcessing]::Prohibit
    $settings.XmlResolver = $null
    $reader = [Xml.XmlReader]::Create($Stream, $settings)
    try {
        $document = New-Object System.Xml.XmlDocument
        $document.XmlResolver = $null
        $document.Load($reader)
        return ,$document
    } finally { $reader.Dispose() }
}

function Read-PackageXml($Archive, [string]$Name) {
    $entry = $Archive.GetEntry($Name)
    if ($null -eq $entry) { throw "Missing Visio package part: $Name" }
    $stream = $entry.Open()
    try { return ,(Read-SafeXml $stream) } finally { $stream.Dispose() }
}

function Get-ExpectedPages([string]$Path) {
    if ([IO.Path]::GetExtension($Path) -eq '.vdx') {
        $stream = [IO.File]::OpenRead($Path)
        try { $document = Read-SafeXml $stream } finally { $stream.Dispose() }
        $ns = New-Object Xml.XmlNamespaceManager($document.NameTable)
        $ns.AddNamespace('v', 'http://schemas.microsoft.com/visio/2003/core')
        foreach ($page in $document.SelectNodes('/v:VisioDocument/v:Pages/v:Page', $ns)) {
            [pscustomobject]@{Name=$page.GetAttribute('Name'); Shapes=$page.SelectNodes('.//v:Shape', $ns).Count}
        }
        return
    }
    if ([IO.Path]::GetExtension($Path) -ne '.vsdx') { throw 'Only .vsdx and .vdx drawings are supported.' }
    $archive = [IO.Compression.ZipFile]::OpenRead($Path)
    try {
        $document = Read-PackageXml $archive 'visio/pages/pages.xml'
        $relationships = Read-PackageXml $archive 'visio/pages/_rels/pages.xml.rels'
        $ns = New-Object Xml.XmlNamespaceManager($document.NameTable)
        $ns.AddNamespace('v', 'http://schemas.microsoft.com/office/visio/2012/main')
        $ns.AddNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
        $targets = @{}
        foreach ($relationship in $relationships.DocumentElement.ChildNodes) {
            if ($relationship.TargetMode -eq 'External') { throw 'External Visio page relationships are unsupported.' }
            $target = New-Object Uri([Uri]'https://package.local/visio/pages/', [string]$relationship.Target)
            if ($target.Host -ne 'package.local') { throw 'Invalid Visio page relationship target.' }
            $targets[[string]$relationship.Id] = [Uri]::UnescapeDataString($target.AbsolutePath.TrimStart('/'))
        }
        foreach ($page in $document.SelectNodes('/v:Pages/v:Page', $ns)) {
            $relation = $page.SelectSingleNode('v:Rel', $ns)
            if ($null -eq $relation) { throw 'Visio page has no content relationship.' }
            $id = $relation.GetAttribute('id', $ns.LookupNamespace('r'))
            if (-not $targets.ContainsKey($id)) { throw "Missing Visio page relationship: $id" }
            $content = Read-PackageXml $archive $targets[$id]
            [pscustomobject]@{Name=$page.GetAttribute('Name'); Shapes=$content.SelectNodes('//v:Shape', $ns).Count}
        }
    } finally { $archive.Dispose() }
}

function Assert-ViewerState($Viewer, [string]$Stage) {
    $code = [int]$Viewer.LastErrorCode
    if ($code -ne 0) { throw "$Stage failed: $code - $($Viewer.GetErrorMessage($code))" }
}

try {
    if ([Threading.Thread]::CurrentThread.ApartmentState -ne 'STA') {
        throw 'Run this check with Windows PowerShell: powershell.exe -NoProfile -STA -File tools/documentation/verify_visio.ps1'
    }
    if (-not (Test-Path "Registry::HKEY_CLASSES_ROOT\CLSID\{$viewerClassId}\InprocServer32")) {
        throw 'Microsoft Visio Viewer is not registered for this PowerShell process. Install the matching Viewer or run a matching 32/64-bit Windows PowerShell. No native check was performed.'
    }
    Add-Type -AssemblyName System.Windows.Forms, System.Drawing, System.IO.Compression.FileSystem
    if (-not ('PkDtsVisioHost' -as [type])) {
        Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
using System.Windows.Forms;
public class PkDtsVisioHost : AxHost {
    public PkDtsVisioHost() : base("$viewerClassId") {}
    public object ViewerObject { get { return GetOcx(); } }
    public void RenderPage(string outputPath) {
        using (var bitmap = new System.Drawing.Bitmap(Width, Height)) {
            using (var graphic = System.Drawing.Graphics.FromImage(bitmap)) {
                graphic.Clear(System.Drawing.Color.White);
                IntPtr dc = graphic.GetHdc();
                try {
                    var bounds = new PkDtsViewerRect { left=0, top=0, right=Width, bottom=Height };
                    int result = ((PkDtsViewerDraw)GetOcx()).Draw(1, -1, IntPtr.Zero,
                        IntPtr.Zero, IntPtr.Zero, dc, ref bounds, IntPtr.Zero, IntPtr.Zero, IntPtr.Zero);
                    Marshal.ThrowExceptionForHR(result);
                } finally { graphic.ReleaseHdc(dc); }
            }
            if (!String.IsNullOrEmpty(outputPath)) {
                bitmap.Save(outputPath, System.Drawing.Imaging.ImageFormat.Png);
            }
        }
    }
}
[StructLayout(LayoutKind.Sequential)]
public struct PkDtsViewerRect { public int left, top, right, bottom; }
[ComImport, Guid("0000010D-0000-0000-C000-000000000046"), InterfaceType(ComInterfaceType.InterfaceIsIUnknown)]
public interface PkDtsViewerDraw {
    [PreserveSig] int Draw(uint aspect, int index, IntPtr aspectInfo, IntPtr targetDevice,
        IntPtr targetDc, IntPtr drawDc, ref PkDtsViewerRect bounds, IntPtr windowBounds,
        IntPtr continueFunction, IntPtr continueValue);
}
"@ -ReferencedAssemblies System.Windows.Forms, System.Drawing
    }
    $outputRoot = Get-LocalPath $OutputDirectory
    $storageRoot = (Join-Path $repositoryRoot 'storage') + [IO.Path]::DirectorySeparatorChar
    if (-not $outputRoot.StartsWith($storageRoot, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'OutputDirectory must be inside this repository''s ignored storage folder.'
    }
    [void][IO.Directory]::CreateDirectory($outputRoot)
    if (-not $Paths) {
        $Paths = foreach ($name in @('DFD', 'Flowchart', 'Process_Flow')) {
            foreach ($extension in @('vsdx', 'vdx')) { "docs/system-documentation/PK_DTS_$name.$extension" }
        }
    }
    $reports = New-Object 'Collections.Generic.List[object]'
    $failed = $false
    foreach ($item in $Paths) {
        $report = [ordered]@{Path=$item; Passed=$false; Consumer='Microsoft Visio Viewer (not the desktop editor)';
            ViewerVersion=$null; ExpectedPages=0; PageCount=0; Pages=@(); Failure=$null}
        $form = $null
        $viewer = $null
        try {
            $path = (Resolve-Path -LiteralPath (Get-LocalPath $item)).Path
            $report.Path = $path
            $originalHash = (Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash
            $expected = @(Get-ExpectedPages $path)
            if ($expected.Count -eq 0) { throw 'Drawing has no readable pages.' }
            $report.ExpectedPages = $expected.Count
            $form = New-Object Windows.Forms.Form
            $form.ShowInTaskbar = $false
            $form.Opacity = 0
            $hostControl = New-Object PkDtsVisioHost
            $hostControl.Size = New-Object Drawing.Size(1600, 1200)
            ([ComponentModel.ISupportInitialize]$hostControl).BeginInit()
            $form.Controls.Add($hostControl)
            ([ComponentModel.ISupportInitialize]$hostControl).EndInit()
            [void]$form.Handle
            [void]$hostControl.Handle
            $viewer = $hostControl.ViewerObject
            $viewer.AlertsEnabled = $false
            $viewer.ToolbarVisible = $false
            $viewer.ScrollbarsVisible = $false
            $viewer.PageTabsVisible = $false
            $viewer.GridVisible = $false
            $report.ViewerVersion = "$($viewer.MajorVersionNumber).$($viewer.MinorVersionNumber).$($viewer.BuildNumber)"
            if (-not $viewer.Load($path)) { throw "Viewer could not load drawing: $($viewer.GetErrorMessage($viewer.LastErrorCode))" }
            [Windows.Forms.Application]::DoEvents()
            Assert-ViewerState $viewer 'Loading'
            if (-not $viewer.DocumentLoaded) { throw 'Viewer did not retain a loaded drawing.' }
            $report.PageCount = [int]$viewer.PageCount
            if ($report.PageCount -ne $expected.Count) {
                throw "Page count mismatch: XML=$($expected.Count), Viewer=$($report.PageCount)."
            }
            for ($index = 1; $index -le $expected.Count; $index++) {
                $viewer.CurrentPageIndex = $index
                $viewer.SetPageView(8, 6, 0.9)
                [Windows.Forms.Application]::DoEvents()
                Assert-ViewerState $viewer "Opening page $index"
                $shapeCount = [int]$viewer.ShapeCount
                if ($shapeCount -ne $expected[$index-1].Shapes) {
                    throw "Page $index shape count mismatch: XML=$($expected[$index-1].Shapes), Viewer=$shapeCount."
                }
                $render = $null
                if ($RenderPages) {
                    $render = Join-Path $outputRoot (([IO.Path]::GetFileName($path)) + ('-page-{0:00}.png' -f $index))
                }
                $hostControl.RenderPage($render)
                Assert-ViewerState $viewer "Rendering page $index"
                $pageName = $viewer.PageName($index)
                Assert-ViewerState $viewer "Reading page $index"
                $report.Pages += [pscustomobject]@{Index=$index; Name=$pageName;
                    Shapes=$shapeCount; ExpectedShapes=$expected[$index-1].Shapes; Rendered=$true; Screenshot=$render}
            }
            if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ne $originalHash) {
                throw 'Drawing changed during the read-only check.'
            }
            $report.Passed = $true
        } catch {
            $failed = $true
            $report.Failure = $_.Exception.Message
        } finally {
            if ($null -ne $viewer) {
                try {
                    $viewer.Unload()
                    if ($report.Passed) { Assert-ViewerState $viewer 'Unloading' }
                } catch { $report.Passed=$false; $failed=$true; $report.Failure="Viewer cleanup failed: $($_.Exception.Message)" }
            }
            if ($null -ne $form) { $form.Dispose() }
        }
        $reports.Add([pscustomobject]$report)
        if ($report.Passed) { Write-Host "PASS $([IO.Path]::GetFileName($report.Path)): $($report.PageCount) pages opened and rendered." }
        else { Write-Host "FAIL $($report.Path): $($report.Failure)" }
    }
    $reportPath = Join-Path $outputRoot 'report.json'
    ConvertTo-Json -InputObject @($reports.ToArray()) -Depth 6 | Set-Content -LiteralPath $reportPath -Encoding UTF8
    Write-Host "Native Viewer report: $reportPath"
    if ($failed) { exit 1 }
    exit 0
} catch {
    Write-Error -Message $_.Exception.Message -ErrorAction Continue
    exit 1
}
