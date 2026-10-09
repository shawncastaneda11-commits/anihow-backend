import 'dart:async';
import 'dart:convert';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';

import 'push_open.dart';
import 'push_runtime.dart';

final FlutterLocalNotificationsPlugin _local = FlutterLocalNotificationsPlugin();

/// Background isolate. A missing Firebase config must not crash the process.
@pragma('vm:entry-point')
Future<void> pushBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

/// Starts Firebase. Failure leaves push off and the rest of the app running.
Future<void> startPush() async {
  try {
    await Firebase.initializeApp();
  } catch (_) {
    PushRuntime.ready = false;
    PushRuntime.device = null;
    return;
  }

  try {
    FirebaseMessaging.onBackgroundMessage(pushBackgroundHandler);
    final device = FirebaseDevicePush();
    await device.prepare();
    PushRuntime.device = device;
    PushRuntime.ready = true;
    device.listen(
      onToken: PushRuntime.deliverToken,
      onForeground: (message) {
        unawaited(device.showLocal(message));
        PushRuntime.inbox.ping();
      },
      onOpened: (message) {
        PushRuntime.pending = message;
        PushRuntime.inbox.ping();
      },
    );
    PushRuntime.pending = await device.initialMessage();
  } catch (_) {
    PushRuntime.ready = false;
    PushRuntime.device = null;
  }
}

class FirebaseDevicePush implements DevicePush {
  bool _permissionDenied = false;
  String? _token;

  @override
  bool get permissionDenied => _permissionDenied;

  @override
  String? get rememberedToken => _token;

  Future<void> prepare() async {
    await _local.initialize(
      settings: const InitializationSettings(
        android: AndroidInitializationSettings('ic_stat_anihow'),
      ),
      onDidReceiveNotificationResponse: (response) {
        final data = payloadData(response.payload);
        if (data.isEmpty) {
          return;
        }
        PushRuntime.pending = PushPayload(data: data);
        PushRuntime.inbox.ping();
      },
    );
    final android = _local.resolvePlatformSpecificImplementation<
        AndroidFlutterLocalNotificationsPlugin>();
    for (final channel in _channels) {
      await android?.createNotificationChannel(channel);
    }
    await _readPermission();
  }

  @override
  Future<bool> requestPermission() async {
    final settings = await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    await _readPermission();
    return settings.authorizationStatus == AuthorizationStatus.authorized ||
        settings.authorizationStatus == AuthorizationStatus.provisional;
  }

  @override
  Future<String?> currentToken() => FirebaseMessaging.instance.getToken();

  @override
  void remember(String? token) => _token = token;

  @override
  Future<PushPayload?> initialMessage() async {
    final message = await FirebaseMessaging.instance.getInitialMessage();
    if (message == null) {
      return null;
    }
    return _payload(message);
  }

  @override
  Future<void> showLocal(PushPayload message) async {
    final data = message.data;
    final channel = pushChannelFor(data);
    final id = int.tryParse(data['notification_id'] ?? '') ?? message.hashCode;
    await _local.show(
      id: id.abs() % 100000,
      title: message.title ?? 'AniHow',
      body: message.body ?? 'Open AniHow to see it.',
      notificationDetails: NotificationDetails(
        android: AndroidNotificationDetails(
          channel,
          _channelName(channel),
          importance: Importance.high,
          priority: Priority.high,
          visibility: NotificationVisibility.private,
          icon: 'ic_stat_anihow',
        ),
      ),
      payload: jsonEncode(data),
    );
  }

  @override
  Future<void> openSystemSettings() async {
    await _local.openAppNotificationSettings();
  }

  @override
  void listen({
    required void Function(String token) onToken,
    required void Function(PushPayload message) onForeground,
    required void Function(PushPayload message) onOpened,
  }) {
    FirebaseMessaging.instance.onTokenRefresh.listen(onToken);
    FirebaseMessaging.onMessage.listen((message) => onForeground(_payload(message)));
    FirebaseMessaging.onMessageOpenedApp.listen((message) => onOpened(_payload(message)));
  }

  Future<void> _readPermission() async {
    final settings = await FirebaseMessaging.instance.getNotificationSettings();
    _permissionDenied = settings.authorizationStatus == AuthorizationStatus.denied;
  }

  PushPayload _payload(RemoteMessage message) {
    return PushPayload(
      title: message.notification?.title,
      body: message.notification?.body,
      data: stringData(message.data),
    );
  }
}

const _channels = [
  AndroidNotificationChannel('orders', 'Orders / Mga order', importance: Importance.high),
  AndroidNotificationChannel('payments', 'Payments / Mga bayad', importance: Importance.high),
  AndroidNotificationChannel('chats', 'Chats / Mga chat', importance: Importance.high),
  AndroidNotificationChannel(
    'farm_updates',
    'Farm updates / Mga update sa bukid',
    importance: Importance.high,
  ),
  AndroidNotificationChannel('general', 'AniHow / Pangkalahatan', importance: Importance.high),
];

String _channelName(String id) {
  for (final channel in _channels) {
    if (channel.id == id) {
      return channel.name;
    }
  }
  return 'AniHow / Pangkalahatan';
}
