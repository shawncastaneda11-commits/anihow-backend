import 'package:flutter/foundation.dart';

import '../services/api_client.dart';

/// A push the app can show or open. Data values stay strings, matching FCM.
class PushPayload {
  const PushPayload({this.title, this.body, this.data = const {}});

  final String? title;
  final String? body;
  final Map<String, String> data;
}

/// The phone's push connection. Tests install a fake; the app installs Firebase.
abstract class DevicePush {
  bool get permissionDenied;

  String? get rememberedToken;

  Future<bool> requestPermission();

  Future<String?> currentToken();

  void remember(String? token);

  Future<PushPayload?> initialMessage();

  Future<void> showLocal(PushPayload message);

  Future<void> openSystemSettings();

  void listen({
    required void Function(String token) onToken,
    required void Function(PushPayload message) onForeground,
    required void Function(PushPayload message) onOpened,
  });
}

/// Shared push state. Firebase stays optional so a bad config cannot crash the app.
class PushRuntime {
  static bool ready = false;
  static DevicePush? device;
  static PushPayload? pending;
  static Future<void> Function(String token)? onToken;
  static final inbox = PushInbox();

  static Future<void> register(ApiClient api) async {
    final current = device;
    if (current == null) {
      return;
    }
    try {
      final token = await current.currentToken();
      if (token == null || token.isEmpty) {
        return;
      }
      current.remember(token);
      await api.registerDeviceToken(token);
    } catch (_) {
      // A failed registration leaves push off until the next sign-in or refresh.
    }
  }

  static Future<void> forgetDevice(ApiClient api) async {
    final token = device?.rememberedToken;
    device?.remember(null);
    if (token == null || token.isEmpty) {
      return;
    }
    try {
      await api.deleteDeviceToken(token);
    } catch (_) {}
  }

  static void deliverToken(String token) {
    device?.remember(token);
    final handler = onToken;
    if (handler != null) {
      handler(token);
    }
  }

  static void reset() {
    ready = false;
    device = null;
    pending = null;
    onToken = null;
  }
}

class PushInbox extends ChangeNotifier {
  void ping() => notifyListeners();
}

String pushChannelFor(Map<String, String> data) {
  final type = data['type'] ?? '';
  if (type == 'stall_message' || type == 'order_message') {
    return 'chats';
  }
  if (type.startsWith('payment_') || type.startsWith('refund_')) {
    return 'payments';
  }
  if (type == 'farm_announcement') {
    return 'farm_updates';
  }
  if (type.startsWith('order_') ||
      type.startsWith('reservation_') ||
      type == 'listing_low_stock' ||
      type == 'harvest_reminder' ||
      type == 'expired_stock_left') {
    return 'orders';
  }
  return 'general';
}
