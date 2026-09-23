import 'dart:async';

import 'package:dart_pusher_channels/dart_pusher_channels.dart';

import '../config/api_config.dart';
import '../models/models.dart';
import 'api_client.dart';

/// Live order-chat subscription over Laravel Reverb (Pusher protocol).
///
/// Failures are swallowed so the chat screen can keep polling as a fallback.
class OrderChatRealtime {
  OrderChatRealtime({required this.api, required this.orderId});

  final ApiClient api;
  final int orderId;

  PusherChannelsClient? _client;
  StreamSubscription<ChannelReadEvent>? _subscription;
  final _controller = StreamController<OrderMessage>.broadcast();

  Stream<OrderMessage> get messages => _controller.stream;

  Future<void> connect() async {
    await disconnect();

    try {
      final client = PusherChannelsClient.websocket(
        options: PusherChannelsOptions.fromHost(
          scheme: 'ws',
          host: ApiConfig.reverbHost,
          key: ApiConfig.reverbAppKey,
          port: ApiConfig.reverbPort,
        ),
        connectionErrorHandler: (_, __, ___) {},
      );
      _client = client;

      final channel = client.privateChannel(
        'private-orders.$orderId',
        authorizationDelegate: EndpointAuthorizableChannelTokenAuthorizationDelegate.forPrivateChannel(
          authorizationEndpoint: Uri.parse(ApiConfig.broadcastingAuthUrl),
          headers: await _authHeaders(),
        ),
      );

      _subscription = channel.bind('order.message.created').listen((event) {
        final data = event.data;
        if (data is! Map) {
          return;
        }
        final map = Map<String, dynamic>.from(data);
        final payload = map['data'];
        final messageMap = payload is Map
            ? Map<String, dynamic>.from(payload)
            : map;
        try {
          _controller.add(OrderMessage.fromJson(messageMap));
        } catch (_) {
          // Ignore malformed payloads; polling will catch up.
        }
      });

      client.onConnectionEstablished.listen((_) {
        channel.subscribeIfNotUnsubscribed();
      });

      unawaited(client.connect());
    } catch (_) {
      await disconnect();
    }
  }

  Future<Map<String, String>> _authHeaders() async {
    final token = await api.readToken();
    return {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
      if (token != null && token.isNotEmpty) 'Authorization': 'Bearer $token',
    };
  }

  Future<void> disconnect() async {
    await _subscription?.cancel();
    _subscription = null;
    _client?.dispose();
    _client = null;
  }

  Future<void> dispose() async {
    await disconnect();
    await _controller.close();
  }
}
