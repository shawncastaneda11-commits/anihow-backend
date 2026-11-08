import 'package:flutter/foundation.dart';

import '../config/app_version.dart';

/// Whether the server lets this app in: open, under maintenance, or asking
/// this version to update. Set from GET /app/config when the app opens or
/// resumes, and from any API answer of 503 (maintenance) or 426 (update).
class AppAvailability extends ChangeNotifier {
  AppAvailability._();

  static final AppAvailability instance = AppAvailability._();

  String? _maintenanceMessage;
  String? _requiredVersion;
  String? _downloadUrl;

  String? get maintenanceMessage => _maintenanceMessage;
  String? get requiredVersion => _requiredVersion;

  /// Where ICTD shares the new app (AniHow is an APK, not on the Play Store).
  /// Only http and https links are kept.
  String? get downloadUrl => _downloadUrl;
  bool get underMaintenance => _maintenanceMessage != null;
  bool get mustUpdate => _requiredVersion != null;

  void apply({
    String? maintenanceMessage,
    String? minVersion,
    String? downloadUrl,
  }) {
    final message = (maintenanceMessage ?? '').trim();
    final nextMaintenance = message.isEmpty ? null : message;
    final nextRequired =
        isOlder(kAppVersion, minVersion) ? minVersion!.trim() : null;
    final nextDownload = webLink(downloadUrl);
    if (nextMaintenance == _maintenanceMessage &&
        nextRequired == _requiredVersion &&
        nextDownload == _downloadUrl) {
      return;
    }
    _maintenanceMessage = nextMaintenance;
    _requiredVersion = nextRequired;
    _downloadUrl = nextDownload;
    notifyListeners();
  }

  /// [value] when it is an http or https link, otherwise null.
  static String? webLink(String? value) {
    final text = (value ?? '').trim();
    final uri = Uri.tryParse(text);
    if (uri == null ||
        !(uri.scheme == 'https' || uri.scheme == 'http') ||
        uri.host.isEmpty) {
      return null;
    }
    return text;
  }

  /// True when [version] is older than [minimum]. Unknown values never are.
  static bool isOlder(String version, String? minimum) {
    if (minimum == null || minimum.trim().isEmpty) {
      return false;
    }
    List<int>? parts(String v) {
      final core = v.trim().split('+').first;
      final pieces = core.split('.');
      final numbers = <int>[];
      for (final piece in pieces) {
        final n = int.tryParse(piece);
        if (n == null) {
          return null;
        }
        numbers.add(n);
      }
      return numbers;
    }

    final a = parts(version);
    final b = parts(minimum);
    if (a == null || b == null) {
      return false;
    }
    for (var i = 0; i < 4; i++) {
      final x = i < a.length ? a[i] : 0;
      final y = i < b.length ? b[i] : 0;
      if (x != y) {
        return x < y;
      }
    }
    return false;
  }
}
