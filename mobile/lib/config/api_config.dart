import 'package:flutter/foundation.dart';

/// Single source for the AniHow API and Reverb endpoints.
///
/// Android emulator reaches this computer at 10.0.2.2, not 127.0.0.1.
/// Windows / Edge use 127.0.0.1 so the same app can run on the host.
///
/// Release builds pass `--dart-define` values. When those are omitted,
/// behaviour matches local emulator development.
class ApiConfig {
  static const String emulatorHost = 'http://10.0.2.2:8000';
  static const String localHost = 'http://127.0.0.1:8000';

  static const String _apiBaseUrl = String.fromEnvironment('API_BASE_URL');
  static const String _reverbHost = String.fromEnvironment('REVERB_HOST');
  static const int _reverbPort = int.fromEnvironment('REVERB_PORT', defaultValue: 8080);
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

  static int get reverbPort => _reverbPort;

  static String get reverbScheme => _reverbScheme.isEmpty ? 'ws' : _reverbScheme;

  static String get reverbAppKey =>
      _reverbAppKey.isEmpty ? 'anihow-reverb-key' : _reverbAppKey;

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
