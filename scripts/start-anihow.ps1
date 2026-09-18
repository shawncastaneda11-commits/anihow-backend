param(
    [ValidateSet('web', 'mobile', 'both')]
    [string]$Mode = 'both'
)

$ErrorActionPreference = 'Continue'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
$MobileRoot = Join-Path $ProjectRoot 'mobile'
$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if ($phpCmd) {
    $Php = $phpCmd.Source
} else {
    $Php = Join-Path $env:LOCALAPPDATA 'Microsoft\WinGet\Packages\PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe'
}

$env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
$env:ANDROID_SDK_ROOT = $env:ANDROID_HOME
$env:JAVA_HOME = 'C:\Program Files\Android\Android Studio\jbr'
$env:Path = @(
    'C:\flutter\bin'
    (Join-Path $env:ANDROID_HOME 'platform-tools')
    (Join-Path $env:ANDROID_HOME 'emulator')
    (Join-Path $env:JAVA_HOME 'bin')
    $env:Path
) -join ';'

function Test-ApiListening {
    return [bool](Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue)
}

function Start-AniHowApi {
    if (Test-ApiListening) {
        Write-Host 'AniHow API already running on http://127.0.0.1:8000'
        return
    }

    if (-not (Test-Path $Php)) {
        throw "PHP was not found. Install PHP or add it to PATH."
    }

    Write-Host 'Starting AniHow API...'
    Start-Process -FilePath $Php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') -WorkingDirectory $ProjectRoot -WindowStyle Minimized

    $deadline = (Get-Date).AddSeconds(20)
    do {
        if (Test-ApiListening) {
            Write-Host 'API ready at http://127.0.0.1:8000'
            return
        }
        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    throw 'The API did not start on port 8000.'
}

function Open-AniHowAdmin {
    Start-Process 'http://127.0.0.1:8000/admin'
    Write-Host 'Opened AniHow admin: http://127.0.0.1:8000/admin'
    Write-Host 'Login: admin@anihow.local / password'
}

function Start-AniHowEmulator {
    $env:ANDROID_HOME = Join-Path $env:LOCALAPPDATA 'Android\Sdk'
    $env:Path = (Join-Path $env:ANDROID_HOME 'platform-tools') + ';' + (Join-Path $env:ANDROID_HOME 'emulator') + ';' + $env:Path
    $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
    $emulator = Join-Path $env:ANDROID_HOME 'emulator\emulator.exe'

    & $adb start-server | Out-Null
    $devices = & $adb devices
    if ($devices -match 'emulator-\d+\s+device') {
        Write-Host 'Android emulator already running.'
        return
    }

    Write-Host 'Starting Android emulator (AniHow Pixel)...'
    Start-Process -FilePath $emulator -ArgumentList @('-avd', 'AniHow_Pixel', '-netdelay', 'none', '-netspeed', 'full')
    & $adb wait-for-device

    $deadline = (Get-Date).AddMinutes(4)
    do {
        $boot = ((& $adb shell getprop sys.boot_completed 2>$null) | Out-String).Trim()
        if ($boot -eq '1') {
            Write-Host 'Emulator is ready.'
            return
        }
        Start-Sleep -Seconds 4
    } while ((Get-Date) -lt $deadline)

    throw 'The Android emulator did not finish booting.'
}

function Open-AniHowMobile {
    Start-AniHowEmulator

    $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
    $installed = & $adb shell pm path com.anihow.anihow 2>$null
    if ($installed) {
        Write-Host 'Opening AniHow on the emulator...'
        & $adb shell am start -n com.anihow.anihow/.MainActivity | Out-Null
        Write-Host 'Buyer: ana.buyer@anihow.local / password'
        Write-Host 'Farmer: juan@anihow.local / password'
        return
    }

    Write-Host 'App is not installed yet. Building with Flutter (first launch takes a few minutes)...'
    $flutter = 'C:\flutter\bin\flutter.bat'
    Set-Location $MobileRoot
    & $flutter run -d emulator-5554
}

Write-Host "AniHow launcher ($Mode)"
Start-AniHowApi

if ($Mode -eq 'web' -or $Mode -eq 'both') {
    Open-AniHowAdmin
}

if ($Mode -eq 'mobile' -or $Mode -eq 'both') {
    Open-AniHowMobile
}

if ($Mode -ne 'mobile') {
    Start-Sleep -Seconds 2
}
