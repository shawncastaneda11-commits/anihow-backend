param(
    [string]$Server = 'railway'
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'anihow-env.ps1')
Initialize-AniHowTools

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$mappedDrive = $null

function Get-UnusedDriveLetter {
    $taken = New-Object 'System.Collections.Generic.HashSet[string]' ([StringComparer]::OrdinalIgnoreCase)
    foreach ($drive in [System.IO.DriveInfo]::GetDrives()) {
        [void]$taken.Add($drive.Name.Substring(0, 1))
    }

    foreach ($code in [int][char]'Z'..[int][char]'A') {
        $letter = [string][char]$code
        if (-not $taken.Contains($letter)) {
            return $letter
        }
    }

    throw 'No free drive letter is available for subst.'
}

try {
    $buildRoot = $ProjectRoot
    if ($ProjectRoot -match '[ ()&!]') {
        $letter = Get-UnusedDriveLetter
        & subst.exe "${letter}:" $ProjectRoot
        if ($LASTEXITCODE -ne 0) {
            throw "subst ${letter}: failed with exit code $LASTEXITCODE"
        }
        $mappedDrive = $letter
        $buildRoot = "${letter}:\"
        Write-Host "Building from ${letter}:\ because the folder name has spaces or brackets"
        # Kotlin rejects plugin sources on C: when the project is on the subst
        # drive. Keep this build's pub cache on that same drive.
        $env:PUB_CACHE = Join-Path $buildRoot 'mobile\.dart_tool\apk-pub-cache'
    }

    $MobileRoot = Join-Path $buildRoot 'mobile'
    $Defines = Join-Path $MobileRoot "dart_defines\$Server.json"

    if (-not (Test-Path -LiteralPath $Defines)) {
        throw "No dart defines file for server '$Server' at $Defines"
    }

    Write-Host "Building AniHow release APK for $Server"
    Set-Location -LiteralPath $MobileRoot
    if ($null -ne $mappedDrive) {
        flutter pub get
        if ($LASTEXITCODE -ne 0) {
            throw "flutter pub get failed with exit code $LASTEXITCODE"
        }
    }
    flutter build apk --release --dart-define-from-file="dart_defines/$Server.json"
    if ($LASTEXITCODE -ne 0) {
        throw "flutter build apk failed with exit code $LASTEXITCODE"
    }

    $built = Join-Path $MobileRoot 'build\app\outputs\flutter-apk\app-release.apk'
    if (-not (Test-Path -LiteralPath $built)) {
        throw "Build finished without an APK at $built"
    }

    $dist = Join-Path $ProjectRoot 'dist'
    New-Item -ItemType Directory -Force -Path $dist | Out-Null
    $destination = Join-Path $dist "AniHow-$Server.apk"
    Copy-Item -LiteralPath $built -Destination $destination -Force

    $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
    if (Test-Path -LiteralPath $adb) {
        $phones = @(
            & $adb devices |
                ForEach-Object { $_.Trim() } |
                Where-Object { $_ -match '^(\S+)\s+device$' -and $Matches[1] -notlike 'emulator-*' } |
                ForEach-Object { $Matches[1] }
        )
        if ($phones.Count -gt 0) {
            $answer = Read-Host 'Install on the connected phone? (Y/N)'
            if ($answer -eq 'Y' -or $answer -eq 'y') {
                & $adb -s $phones[0] install -r $destination
                if ($LASTEXITCODE -ne 0) {
                    throw "adb install failed with exit code $LASTEXITCODE"
                }
            }
        }
    }

    Write-Host "APK: $destination"
    Start-Process explorer.exe -ArgumentList $dist
} finally {
    if ($null -ne $mappedDrive) {
        try {
            Set-Location -LiteralPath (Join-Path $ProjectRoot 'mobile')
            Remove-Item Env:\PUB_CACHE -ErrorAction SilentlyContinue
            flutter pub get
        } finally {
            Set-Location -LiteralPath $ProjectRoot
            & subst.exe "${mappedDrive}:" '/D'
        }
    }
}
