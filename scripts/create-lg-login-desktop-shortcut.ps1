# Creates Desktop shortcut LGU.lnk with LGU icon; opens Chrome to inventory login URL.
# Run: powershell -ExecutionPolicy Bypass -File "C:\xampp\htdocs\lgu_invent\scripts\create-lg-login-desktop-shortcut.ps1"

$ErrorActionPreference = 'Stop'
$url = 'http://localhost/lgu_invent/login'
$shortcutName = 'LGU.lnk'

$scriptDir = $PSScriptRoot
if (-not $scriptDir) {
    $scriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
}

$pngPath = Join-Path $scriptDir 'lgu-desktop-icon.png'
$iconStoreDir = Join-Path $env:LOCALAPPDATA 'LGUInventory'
$iconFullPath = Join-Path $iconStoreDir 'lgu-shortcut.ico'

function Save-IconToIco {
    param([System.Drawing.Icon]$Icon, [string]$DestIco)
    $parent = Split-Path $DestIco -Parent
    if (-not (Test-Path $parent)) {
        New-Item -ItemType Directory -Path $parent -Force | Out-Null
    }
    $ms = New-Object System.IO.MemoryStream
    try {
        $Icon.Save($ms)
        [System.IO.File]::WriteAllBytes($DestIco, $ms.ToArray())
    } finally {
        $ms.Dispose()
    }
    if ((Get-Item $DestIco).Length -lt 64) {
        throw "ICO file looks invalid."
    }
}

function Convert-PngFileToIco {
    param([string]$SourcePng, [string]$DestIco)
    Add-Type -AssemblyName System.Drawing
    $bmp = [System.Drawing.Bitmap]::FromFile((Resolve-Path $SourcePng))
    try {
        $hIcon = $bmp.GetHicon()
        try {
            $tmp = [System.Drawing.Icon]::FromHandle($hIcon)
            try {
                $icon = $tmp.Clone()
            } finally {
                $tmp.Dispose()
            }
        } finally {
            try {
                [void][System.Runtime.InteropServices.Marshal]::DestroyIcon($hIcon)
            } catch {
                # DestroyIcon missing on some PowerShell/.NET builds; cloned icon is independent.
            }
        }
        try {
            Save-IconToIco -Icon $icon -DestIco $DestIco
        } finally {
            $icon.Dispose()
        }
    } finally {
        $bmp.Dispose()
    }
}

function Write-LguBuiltinIco {
    param([string]$DestIco)
    # Builds LGU logo without needing PNG on disk (Explorer ignores ICO from Bitmap unless cloned).
    Add-Type -AssemblyName System.Drawing
    $size = 256
    $bmp = New-Object System.Drawing.Bitmap ($size), ($size)
    try {
        $g = [System.Drawing.Graphics]::FromImage($bmp)
        try {
            $g.SmoothingMode = [System.Drawing.Drawing2D.SmoothingMode]::AntiAlias
            $g.TextRenderingHint = [System.Drawing.Text.TextRenderingHint]::AntiAliasGridFit
            $g.Clear([System.Drawing.Color]::FromArgb(139, 35, 35))

            $fontPx = [Math]::Floor($size * 0.26)
            if ($fontPx -lt 40) {
                $fontPx = 40
            }
            $font = New-Object System.Drawing.Font(
                'Segoe UI',
                [float]$fontPx,
                [System.Drawing.FontStyle]::Bold,
                [System.Drawing.GraphicsUnit]::Pixel
            )
            try {
                $brush = New-Object System.Drawing.SolidBrush ([System.Drawing.Color]::White)
                try {
                    $sf = New-Object System.Drawing.StringFormat
                    try {
                        $sf.Alignment = [System.Drawing.StringAlignment]::Center
                        $sf.LineAlignment = [System.Drawing.StringAlignment]::Center
                        $rect = New-Object System.Drawing.RectangleF(0, 0, $size, $size)
                        $g.DrawString('LGU', $font, $brush, $rect, $sf)
                    } finally {
                        $sf.Dispose()
                    }
                } finally {
                    $brush.Dispose()
                }
            } finally {
                $font.Dispose()
            }
        } finally {
            $g.Dispose()
        }

        $hIcon = $bmp.GetHicon()
        try {
            $tmp = [System.Drawing.Icon]::FromHandle($hIcon)
            try {
                $icon = $tmp.Clone()
            } finally {
                $tmp.Dispose()
            }
        } finally {
            try {
                [void][System.Runtime.InteropServices.Marshal]::DestroyIcon($hIcon)
            } catch {
                # DestroyIcon missing on some PowerShell/.NET builds; cloned icon is independent.
            }
        }

        try {
            Save-IconToIco -Icon $icon -DestIco $DestIco
        } finally {
            $icon.Dispose()
        }
    } finally {
        $bmp.Dispose()
    }
}

$iconResolved = $null
try {
    Add-Type -AssemblyName System.Drawing -ErrorAction Stop
    if (Test-Path $pngPath) {
        Convert-PngFileToIco -SourcePng $pngPath -DestIco $iconFullPath
    } else {
        Write-Host 'No PNG beside script - building built-in LGU icon.'
        Write-LguBuiltinIco -DestIco $iconFullPath
    }
    $iconResolved = (Resolve-Path $iconFullPath).Path
    $sz = (Get-Item $iconResolved).Length
    Write-Host ('Icon file: {0} ({1} bytes)' -f $iconResolved, $sz)
} catch {
    Write-Warning ('Could not create ICO: ' + $_.Exception.Message)
}

$chromeCandidates = @(
    "${env:ProgramFiles}\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "${env:LocalAppData}\Google\Chrome\Application\chrome.exe"
)

$chrome = $chromeCandidates | Where-Object { Test-Path $_ } | Select-Object -First 1

$desktop = [Environment]::GetFolderPath('Desktop')
$lnkPath = Join-Path $desktop $shortcutName

$oldNames = @('LGU Inventory Login.lnk', 'LGU Inventory.lnk')
foreach ($on in $oldNames) {
    $oldPath = Join-Path $desktop $on
    if ((Test-Path $oldPath) -and ($oldPath -ne $lnkPath)) {
        Remove-Item $oldPath -Force -ErrorAction SilentlyContinue
    }
}

if (Test-Path $lnkPath) {
    Remove-Item $lnkPath -Force
}

$shell = New-Object -ComObject WScript.Shell
$shortcut = $shell.CreateShortcut($lnkPath)

if ($chrome) {
    $shortcut.TargetPath = $chrome
    $shortcut.Arguments = $url
} else {
    Write-Warning 'Chrome not found in usual folders. Shortcut opens the URL in your default browser.'
    $shortcut.TargetPath = 'rundll32.exe'
    $shortcut.Arguments = "url.dll,FileProtocolHandler $url"
}

if ($iconResolved -and (Test-Path $iconResolved)) {
    $shortcut.IconLocation = $iconResolved + ',0'
} elseif ($chrome) {
    $shortcut.IconLocation = "$chrome,0"
} else {
    $shortcut.IconLocation = '%SystemRoot%\System32\imageres.dll,104'
}

$shortcut.Description = 'LGU Inventory (localhost)'
$shortcut.Save()

Write-Host ('Created shortcut: {0}' -f (Resolve-Path $lnkPath))
