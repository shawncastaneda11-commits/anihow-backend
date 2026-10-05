function Initialize-AniHowTools {
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
}
