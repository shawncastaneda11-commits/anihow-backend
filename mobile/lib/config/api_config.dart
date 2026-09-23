import 'package:flutter/foundation.dart';

/// Single source for the AniHow API base URL.
///
/// Android emulator reaches this computer at 10.0.2.2, not 127.0.0.1.
/// Windows / Edge use 127.0.0.1 so the same app can run on the host.
class ApiConfig {
  static const String emulatorHost = 'http://10.0.2.2:8000';
  static const String localHost = 'http://127.0.0.1:8000';

  /// Must match REVERB_APP_KEY in the API .env.
  static const String reverbAppKey = 'anihow-reverb-key';
  static const int reverbPort = 8080;

  static String get host {
    if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
      return localHost;
    }
    return emulatorHost;
  }

  static String get baseUrl => '$host/api';

  static String get reverbHost {
    if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
      return '127.0.0.1';
    }
    return '10.0.2.2';
  }

  static String get broadcastingAuthUrl => '$host/broadcasting/auth';

  /// Storage URLs from Laravel use APP_URL (127.0.0.1). The emulator
  /// cannot reach that host, so rewrite them onto [host].
  static String? mediaUrl(String? url) {
    if (url == null || url.isEmpty) {
      return url;
    }
    return url
        .replaceAll('http://127.0.0.1:8000', host)
        .replaceAll('http://localhost:8000', host);
  }
}
