param(
    [ValidateSet('web', 'mobile', 'both')]
    [string]$Mode = 'both'
)

$ErrorActionPreference = 'Stop'
$ProjectRoot = Split-Path -Parent $PSScriptRoot
if (-not (Test-Path (Join-Path $ProjectRoot 'artisan'))) {
    $ProjectRoot = 'C:\dev\anihow'
}
$MobileRoot = Join-Path $ProjectRoot 'mobile'
Set-Location $ProjectRoot

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

function Test-PortListening([int]$Port) {
    try {
        $client = [System.Net.Sockets.TcpClient]::new()
        $async = $client.BeginConnect('127.0.0.1', $Port, $null, $null)
        $ok = $async.AsyncWaitHandle.WaitOne(400)
        $connected = $ok -and $client.Connected
        $client.Close()
        return $connected
    } catch {
        return $false
    }
}

function Test-ApiListening {
    return Test-PortListening 8000
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
    Start-Process -FilePath $Php -ArgumentList @('artisan', 'serve', '--host=127.0.0.1', '--port=8000') -WorkingDirectory $ProjectRoot

    $deadline = (Get-Date).AddSeconds(25)
    do {
        if (Test-ApiListening) {
            Write-Host 'API ready at http://127.0.0.1:8000'
            return
        }
        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    Write-Host 'API is still starting. Opening the website anyway...'
}

function Start-AniHowReverb {
    if (Test-PortListening 8080) {
        Write-Host 'AniHow Reverb already running on ws://127.0.0.1:8080'
        return
    }

    if (-not (Test-Path $Php)) {
        throw "PHP was not found. Install PHP or add it to PATH."
    }

    Write-Host 'Starting AniHow Reverb (order chat)...'
    Start-Process -FilePath $Php -ArgumentList @('artisan', 'reverb:start') -WorkingDirectory $ProjectRoot

    $deadline = (Get-Date).AddSeconds(20)
    do {
        if (Test-PortListening 8080) {
            Write-Host 'Reverb ready at ws://127.0.0.1:8080 (emulator uses 10.0.2.2:8080)'
            return
        }
        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    Write-Host 'Reverb is still starting. Chat will fall back to polling until it is up.'
}

function Open-AniHowAdmin {
    $url = 'http://127.0.0.1:8000/admin'
    Start-Process $url
    Write-Host "Opened AniHow admin: $url"
    Write-Host 'Login: admin@anihow.local / password'
}

function Get-AdbEmulatorStatus {
    $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
    $raw = (& $adb devices 2>$null | Out-String)
    if ($raw -match 'emulator-\d+\s+device') {
        return 'online'
    }
    if ($raw -match 'emulator-\d+\s+offline') {
        return 'offline'
    }
    return 'none'
}

function Get-PreferredAvdName {
    $emulator = Join-Path $env:ANDROID_HOME 'emulator\emulator.exe'
    $available = @(& $emulator -list-avds 2>$null | ForEach-Object { "$_".Trim() } | Where-Object { $_ })
    if ($available -contains 'AniHow_Pixel') {
        return 'AniHow_Pixel'
    }
    if ($available.Count -gt 0) {
        return $available[0]
    }
    return $null
}

function Start-AniHowEmulator {
    $previousNativeErrors = $PSNativeCommandUseErrorActionPreference
    $PSNativeCommandUseErrorActionPreference = $false
    try {
        $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
        $emulator = Join-Path $env:ANDROID_HOME 'emulator\emulator.exe'
        if (-not (Test-Path $adb)) {
            throw "Android SDK adb was not found at $adb"
        }
        if (-not (Test-Path $emulator)) {
            throw "Android emulator.exe was not found at $emulator"
        }

        & $adb start-server 2>$null | Out-Null

        $status = Get-AdbEmulatorStatus
        if ($status -eq 'online') {
            $boot = ((& $adb shell getprop sys.boot_completed 2>$null) | Out-String).Trim()
            if ($boot -eq '1') {
                Write-Host 'Android emulator already running.'
                return
            }
            Write-Host 'Android emulator is online but still booting...'
        } else {
            # Stale qemu with no adb device blocks the old "already starting" branch.
            $leftover = @(Get-Process qemu-system*, emulator -ErrorAction SilentlyContinue)
            if ($status -eq 'none' -and $leftover.Count -gt 0) {
                Write-Host 'Clearing leftover emulator process that is not visible to adb...'
                $leftover | Stop-Process -Force -ErrorAction SilentlyContinue
                Start-Sleep -Seconds 2
                & $adb kill-server 2>$null | Out-Null
                & $adb start-server 2>$null | Out-Null
                $status = 'none'
            }

            if ($status -ne 'online') {
                $avd = Get-PreferredAvdName
                if (-not $avd) {
                    throw 'No Android Virtual Device is installed. Create AniHow_Pixel in Android Studio Device Manager.'
                }

                Write-Host "Starting Android emulator ($avd)..."
                $proc = Start-Process -FilePath $emulator -ArgumentList @(
                    '-avd', $avd,
                    '-netdelay', 'none',
                    '-netspeed', 'full'
                ) -PassThru -WindowStyle Normal

                if (-not $proc -or $proc.HasExited) {
                    throw "Emulator process exited immediately. Open Android Studio Device Manager and start '$avd' once to confirm it works."
                }
                Write-Host "Emulator process started (PID $($proc.Id)). Waiting for boot..."
            } else {
                Write-Host 'Emulator is starting. Waiting until it is ready...'
            }
        }

        $deadline = (Get-Date).AddMinutes(5)
        do {
            $status = Get-AdbEmulatorStatus
            if ($status -eq 'online') {
                $boot = ((& $adb shell getprop sys.boot_completed 2>$null) | Out-String).Trim()
                if ($boot -eq '1') {
                    Write-Host 'Emulator is ready.'
                    return
                }
            }
            Write-Host "  waiting... (adb=$status)"
            Start-Sleep -Seconds 4
        } while ((Get-Date) -lt $deadline)

        throw 'Emulator did not finish booting in time. Wait for the phone home screen, then run AniHow Mobile again.'
    } finally {
        $PSNativeCommandUseErrorActionPreference = $previousNativeErrors
    }
}

function Open-AniHowMobile {
    $previousNativeErrors = $PSNativeCommandUseErrorActionPreference
    $PSNativeCommandUseErrorActionPreference = $false
    try {
        Start-AniHowEmulator

        if ((Get-AdbEmulatorStatus) -ne 'online') {
            throw 'The Android emulator is not ready yet (adb device offline). Wait for the phone home screen, then run AniHow Mobile again.'
        }

        $adb = Join-Path $env:ANDROID_HOME 'platform-tools\adb.exe'
        $installed = (& $adb shell pm path com.anihow.anihow 2>$null | Out-String)
        if ($installed -match 'package:') {
            Write-Host 'Opening AniHow on the emulator...'
            $started = $false
            for ($i = 1; $i -le 8; $i++) {
                $out = (& $adb shell am start -n com.anihow.anihow/.MainActivity 2>&1 | Out-String)
                if ($out -match 'Error type 3|does not exist|Unable to resolve') {
                    Write-Host "  app not ready yet, retry $i/8..."
                    Start-Sleep -Seconds 3
                    continue
                }
                $started = $true
                break
            }
            if (-not $started) {
                throw 'Could not start the AniHow app on the emulator after several tries.'
            }
            Write-Host 'Demo buyer: carla.santos@demo.anihow.local / password'
            Write-Host 'Demo farmer: nena.villanueva@demo.anihow.local / password'
            Write-Host 'Admin CMS: admin@anihow.local / password'
            return
        }

        $flutter = 'C:\flutter\bin\flutter.bat'
        if (-not (Test-Path $flutter)) {
            throw 'The AniHow app is not installed and Flutter was not found at C:\flutter\bin\flutter.bat'
        }

        Write-Host 'App is not installed yet. Building with Flutter (first launch takes a few minutes)...'
        Set-Location $MobileRoot
        & $flutter run -d emulator-5554
    } finally {
        $PSNativeCommandUseErrorActionPreference = $previousNativeErrors
    }
}

try {
    Write-Host "AniHow launcher ($Mode)"
    Write-Host "Project: $ProjectRoot"
    Start-AniHowApi
    Start-AniHowReverb

    if ($Mode -eq 'web' -or $Mode -eq 'both') {
        Open-AniHowAdmin
    }

    if ($Mode -eq 'mobile' -or $Mode -eq 'both') {
        Open-AniHowMobile
    }

    Write-Host ''
    Write-Host 'AniHow is starting. You can close this window.'
    Start-Sleep -Seconds 6
} catch {
    Write-Host ''
    Write-Host "AniHow failed to start: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host 'Press any key to close...'
    $null = $Host.UI.RawUI.ReadKey('NoEcho,IncludeKeyDown')
    exit 1
}
