$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$Icon = Join-Path $PSScriptRoot 'anihow.ico'
$Desktop = [Environment]::GetFolderPath('Desktop')
$Shell = New-Object -ComObject WScript.Shell

function Set-AniHowCommandShortcut {
    param(
        [string]$Name,
        [string]$CommandPath
    )

    $path = Join-Path $Desktop "$Name.lnk"
    $shortcut = $Shell.CreateShortcut($path)
    $shortcut.TargetPath = $CommandPath
    $shortcut.WorkingDirectory = $ProjectRoot
    $shortcut.IconLocation = "$Icon,0"
    $shortcut.Save()
    Write-Host "Shortcut: $path"
}

$adminUrl = Join-Path $Desktop 'AniHow Admin (Online).url'
@"
[InternetShortcut]
URL=https://web-production-8a441d.up.railway.app/admin
IconFile=$Icon
IconIndex=0
"@ | Set-Content -LiteralPath $adminUrl -Encoding ASCII
Write-Host "Shortcut: $adminUrl"

Set-AniHowCommandShortcut -Name 'AniHow (This Computer)' -CommandPath (Join-Path $PSScriptRoot 'start-anihow-all.cmd')
Set-AniHowCommandShortcut -Name 'AniHow - Build Phone App' -CommandPath (Join-Path $PSScriptRoot 'build-anihow-apk.cmd')
