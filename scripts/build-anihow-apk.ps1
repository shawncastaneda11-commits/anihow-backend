param(
    [string]$Server = 'railway'
)

$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'anihow-env.ps1')
Initialize-AniHowTools

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$MobileRoot = Join-Path $ProjectRoot 'mobile'
$Defines = Join-Path $MobileRoot "dart_defines\$Server.json"

if (-not (Test-Path -LiteralPath $Defines)) {
    throw "No dart defines file for server '$Server' at $Defines"
}

Write-Host "Building AniHow release APK for $Server"
Set-Location $MobileRoot
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
