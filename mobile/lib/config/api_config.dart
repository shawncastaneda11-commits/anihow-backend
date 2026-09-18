import 'package:flutter/foundation.dart';

/// Single source for the AniHow API base URL.
///
/// Android emulator reaches this computer at 10.0.2.2, not 127.0.0.1.
/// Windows / Edge use 127.0.0.1 so the same app can run on the host.
class ApiConfig {
  static const String emulatorHost = 'http://10.0.2.2:8000';
  static const String localHost = 'http://127.0.0.1:8000';

  static String get host {
    if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
      return localHost;
    }
    return emulatorHost;
  }

  static String get baseUrl => '$host/api';
}
