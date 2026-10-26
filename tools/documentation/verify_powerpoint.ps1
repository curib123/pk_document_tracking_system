param(
    [string]$Presentation = "docs/system-documentation/PK_DTS_User_Guide.pptx",
    [string]$PreviewPdf = "docs/system-documentation/PK_DTS_User_Guide.pdf",
    [string]$PreviewDirectory = "storage/documentation-work/powerpoint-preview"
)

$ErrorActionPreference = 'Stop'
$presentationPath = (Resolve-Path -LiteralPath $Presentation).Path
$pdfPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $PreviewPdf))
$previewPath = [IO.Path]::GetFullPath((Join-Path (Get-Location) $PreviewDirectory))
New-Item -ItemType Directory -Force -Path $previewPath | Out-Null
$powerpoint = $null
$deck = $null

try {
    $powerpoint = New-Object -ComObject PowerPoint.Application
    $powerpoint.DisplayAlerts = 1
    # Open a read-only presentation without a visible application window.
    $deck = $powerpoint.Presentations.Open($presentationPath, -1, 0, 0)
    $overflows = @()
    $outOfBounds = @()
    foreach ($slide in $deck.Slides) {
        foreach ($shape in $slide.Shapes) {
            if ($shape.HasTextFrame -eq -1 -and $shape.TextFrame.HasText -eq -1) {
                if ($shape.TextFrame.TextRange.BoundHeight -gt ($shape.Height + 2)) {
                    $overflows += "Slide $($slide.SlideIndex): $($shape.Name)"
                }
            }
            if ($shape.Left -lt -2 -or $shape.Top -lt -2 -or
                ($shape.Left + $shape.Width) -gt ($deck.PageSetup.SlideWidth + 2) -or
                ($shape.Top + $shape.Height) -gt ($deck.PageSetup.SlideHeight + 2)) {
                $outOfBounds += "Slide $($slide.SlideIndex): $($shape.Name)"
            }
        }
    }
    # SaveAs with ppSaveAsPDF avoids late-bound ExportAsFixedFormat optional
    # argument conversion problems in Windows PowerShell's COM adapter.
    $deck.SaveAs($pdfPath, 32)
    foreach ($index in @(1, 4, 9, 12, 21, 29, 36, 42, $deck.Slides.Count) | Select-Object -Unique) {
        if ($index -le $deck.Slides.Count) {
            $deck.Slides.Item($index).Export((Join-Path $previewPath "slide-$index.png"), 'PNG', 1600, 900)
        }
    }
    $result = [ordered]@{
        native_powerpoint_open = $true
        slides = $deck.Slides.Count
        text_overflows = $overflows
        shapes_outside_slides = $outOfBounds
        pdf_export = [IO.File]::Exists($pdfPath)
    }
    $result | ConvertTo-Json -Depth 4
    if ($overflows.Count -or $outOfBounds.Count) {
        throw 'The PowerPoint layout needs review.'
    }
}
finally {
    if ($null -ne $deck) { $deck.Close() }
    if ($null -ne $powerpoint -and $powerpoint.Presentations.Count -eq 0) { $powerpoint.Quit() }
}
