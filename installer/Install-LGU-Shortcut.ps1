#Requires -Version 5.1
param(
    [switch]$Quiet
)

<#
Creates a Desktop shortcut that opens LGU Inventory login.

Works two ways:
  1. This file is in installer\ beside the LGU folder that has bootstrap.php (normal project layout).
  2. Portable bundle next to lgue-shortcut-settings.ini (+ optional assets\logo.png).

Use -Quiet when the batch file hides the PowerShell window.
#>

function Write-Ui {
    param([string]$Msg, [string]$Color)
    if ($Quiet) {
        return
    }
    if ($Color -eq 'Err') {
        Write-Host $Msg -ForegroundColor Red
    }
    elseif ($Color) {
        Write-Host $Msg -ForegroundColor $Color
    }
    else {
        Write-Host $Msg
    }
}

function Stop-Installer {
    param([string]$Reason)
    Write-Ui "[ERROR] $Reason" Err
    if ($Quiet -and [Environment]::UserInteractive) {
        try {
            Add-Type -AssemblyName System.Windows.Forms | Out-Null
            [System.Windows.Forms.MessageBox]::Show($Reason, 'LGUE shortcut installer') | Out-Null
        }
        catch { }
    }
    exit 1
}

$ErrorActionPreference = 'Stop'
$installerDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$cfgFile = Join-Path $installerDir 'lgue-shortcut-settings.ini'

function Read-IniShortcuts {
    param([string]$Path)
    $out = @{
        LoginUrl     = 'http://localhost/lgu_invent/login'
        ShortcutName = 'LGU Inventory'
        IconPath     = ''
    }
    if (-not (Test-Path -LiteralPath $Path)) {
        return $out
    }
    Get-Content -LiteralPath $Path -Encoding UTF8 | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith(';')) {
            return
        }
        if ($line -match '^\s*LoginUrl\s*=\s*(.+)\s*$') {
            $out.LoginUrl = $Matches[1].Trim()
        }
        elseif ($line -match '^\s*ShortcutName\s*=\s*(.+)\s*$') {
            $out.ShortcutName = $Matches[1].Trim()
        }
        elseif ($line -match '^\s*IconPath\s*=\s*(.*)$') {
            $out.IconPath = $Matches[1].Trim()
        }
    }
    return $out
}

function Resolve-RepoRoot {
    param([string]$ScriptDir)

    # Flat bundle (EXE unpacked): bootstrap next to installer files
    $flatBootstrap = Join-Path $ScriptDir 'bootstrap.php'
    if (Test-Path -LiteralPath $flatBootstrap) {
        return $ScriptDir
    }

    # Repo layout: ...\lgu_invent\installer\this.ps1 → parent is repo
    $parent = Split-Path -Parent $ScriptDir
    if ($parent -and (Test-Path (Join-Path $parent 'bootstrap.php'))) {
        return $parent
    }

    # Fallback: bundled EXE-only (no repo on disk — icon from {app}\assets)
    return $ScriptDir
}

function Get-WebBrowserExe {
    $chrome = @(
        (Join-Path $env:ProgramFiles 'Google\Chrome\Application\chrome.exe'),
        (${env:ProgramFiles(x86)} + '\Google\Chrome\Application\chrome.exe'),
        (Join-Path $env:LOCALAPPDATA 'Google\Chrome\Application\chrome.exe')
    )
    foreach ($p in $chrome) {
        if ($p -and (Test-Path -LiteralPath $p)) {
            return $p
        }
    }

    $edge = @(
        (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe'),
        (${env:ProgramFiles(x86)} + '\Microsoft\Edge\Application\msedge.exe')
    )
    foreach ($p in $edge) {
        if ($p -and (Test-Path -LiteralPath $p)) {
            return $p
        }
    }

    return $null
}

function Normalize-ShortcutName {
    param([string]$Name)
    foreach ($bad in '*', '/', '\', ':', '?', '"', '<', '>', '|') {
        $Name = $Name.Replace([string]$bad, '')
    }
    if ([string]::IsNullOrWhiteSpace($Name)) {
        return 'LGU Inventory'
    }
    return $Name.Trim()
}

$repoRoot = Resolve-RepoRoot -ScriptDir $installerDir

$cfg = Read-IniShortcuts -Path $cfgFile
if (-not $cfg.LoginUrl.StartsWith('http://') -and -not $cfg.LoginUrl.StartsWith('https://')) {
    Stop-Installer -Reason 'LoginUrl in lgue-shortcut-settings.ini must start with http:// or https://'
}

$shortcutName = Normalize-ShortcutName -Name $cfg.ShortcutName
$browserExe = Get-WebBrowserExe

if (-not $browserExe) {
    Stop-Installer -Reason 'Could not find Google Chrome or Microsoft Edge. Install Chrome or Edge, then try again.'
}

$iconPath = $null
if ($cfg.IconPath -and (Test-Path -LiteralPath $cfg.IconPath)) {
    $iconPath = $cfg.IconPath
}
else {
    $iconCandidates = @(
        (Join-Path $installerDir 'assets\logo.png'),
        (Join-Path $installerDir 'assets\logo.ico'),
        (Join-Path $installerDir 'lgue.ico'),
        (Join-Path $repoRoot 'public\assets\logo.png'),
        (Join-Path $repoRoot 'public\assets\favicon.svg')
    )
    foreach ($p in $iconCandidates) {
        if ($p -and (Test-Path -LiteralPath $p)) {
            $iconPath = $p
            break
        }
    }
}

$desktopDir = [Environment]::GetFolderPath('Desktop')
$lnkPath = Join-Path $desktopDir "$shortcutName.lnk"

try {
    $ws = New-Object -ComObject WScript.Shell
    $lnk = $ws.CreateShortcut($lnkPath)
    $lnk.TargetPath = $browserExe
    $lnk.Arguments = '"' + ($cfg.LoginUrl -replace '"', '') + '"'
    $lnk.WindowStyle = 1
$lnk.Description = 'LGU Property Inventory — opens login page in browser'

    $lnk.WorkingDirectory = Split-Path -Parent $browserExe

    if ($iconPath) {
        try {
            $lnk.IconLocation = "$iconPath,0"
        }
        catch {
            $lnk.IconLocation = "$browserExe,0"
        }
    }
    else {
        $lnk.IconLocation = "$browserExe,0"
    }

    $lnk.Save()
}
catch {
    Stop-Installer -Reason ('Could not create shortcut: ' + $_.Exception.Message)
}

if (-not $Quiet) {
    Write-Ui ''
    Write-Ui 'Desktop shortcut created:' Green
    Write-Ui "  $lnkPath"
    Write-Ui ''
    Write-Ui 'Opens:'
    Write-Ui "  $($cfg.LoginUrl)"
    if ($iconPath) {
        Write-Ui ''
        Write-Ui "Icon: $iconPath"
    }
}

exit 0
