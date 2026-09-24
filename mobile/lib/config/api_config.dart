import 'package:flutter/foundation.dart';

/// Single source for the AniHow API and Reverb endpoints.
///
/// Android emulator reaches this computer at 10.0.2.2, not 127.0.0.1.
/// Windows / Edge use 127.0.0.1 so the same app can run on the host.
///
/// Release builds pass `--dart-define` values. When those are omitted,
/// debug/profile keep emulator defaults; a release APK shows a
/// misconfigured-build screen instead of pointing at 10.0.2.2.
class ApiConfig {
  static const String emulatorHost = 'http://10.0.2.2:8000';
  static const String localHost = 'http://127.0.0.1:8000';

  static const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static const String _reverbHost = String.fromEnvironment('REVERB_HOST');
  static const String _reverbPortRaw = String.fromEnvironment('REVERB_PORT');
  static const String _reverbScheme = String.fromEnvironment('REVERB_SCHEME');
  static const String _reverbAppKey = String.fromEnvironment('REVERB_APP_KEY');

  static bool get hasReleaseApi => _apiBaseUrl.isNotEmpty;

  static String get host {
    if (hasReleaseApi) {
      return _stripTrailingSlash(_apiBaseUrl);
    }
    if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
      return localHost;
    }
    return emulatorHost;
  }

  static String get baseUrl => '$host/api';

  static String get broadcastingAuthUrl => '$host/broadcasting/auth';

  static String get reverbHost {
    if (_reverbHost.isNotEmpty) {
      return _reverbHost;
    }
    if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
      return '127.0.0.1';
    }
    return '10.0.2.2';
  }

  static int get reverbPort => resolveReverbPort(
        portRaw: _reverbPortRaw,
        scheme: reverbScheme,
      );

  static String get reverbScheme => _reverbScheme.isEmpty ? 'ws' : _reverbScheme;

  static String get reverbAppKey =>
      _reverbAppKey.isEmpty ? 'anihow-reverb-key' : _reverbAppKey;

  /// Empty port → 443 for wss, 8080 for ws. An explicit value wins.
  static int resolveReverbPort({required String portRaw, required String scheme}) {
    final parsed = int.tryParse(portRaw.trim());
    if (parsed != null && parsed > 0) {
      return parsed;
    }
    return scheme == 'wss' ? 443 : 8080;
  }

  /// Null when the build can talk to a server. Release-only; debug/profile
  /// always return null so the emulator defaults stay usable.
  static String? configurationProblem({
    bool isRelease = kReleaseMode,
    String apiBaseUrl = _apiBaseUrl,
    String reverbHost = _reverbHost,
    String reverbScheme = _reverbScheme,
  }) {
    if (!isRelease) {
      return null;
    }
    if (apiBaseUrl.isEmpty) {
      return 'API_BASE_URL is empty';
    }
    if (!apiBaseUrl.startsWith('https://')) {
      return 'API_BASE_URL must start with https://';
    }
    if (reverbHost.isEmpty) {
      return 'REVERB_HOST is empty';
    }
    if (reverbScheme != 'wss') {
      return 'REVERB_SCHEME must be wss';
    }
    return null;
  }

  /// Storage URLs from Laravel use APP_URL (127.0.0.1). The emulator
  /// cannot reach that host, so rewrite them onto [host] — but only
  /// when no release [API_BASE_URL] was given.
  static String? mediaUrl(String? url) {
    if (url == null || url.isEmpty) {
      return url;
    }
    if (hasReleaseApi) {
      return url;
    }
    return url
        .replaceAll('http://127.0.0.1:8000', host)
        .replaceAll('http://localhost:8000', host);
  }

  static String _stripTrailingSlash(String value) {
    return value.endsWith('/') ? value.substring(0, value.length - 1) : value;
  }
}
