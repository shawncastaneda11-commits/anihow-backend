import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../screens/chat/open_stall_chat.dart';
import '../screens/chat/order_chats_screen.dart';
import '../screens/notifications/notifications_screen.dart';
import '../state/auth_controller.dart';
import 'push_runtime.dart';

/// Flushes a tap that arrived before the navigator existed, and later taps.
class PushOpenBinder extends StatefulWidget {
  const PushOpenBinder({super.key, required this.child});

  final Widget child;

  @override
  State<PushOpenBinder> createState() => _PushOpenBinderState();
}

class _PushOpenBinderState extends State<PushOpenBinder> {
  @override
  void initState() {
    super.initState();
    PushRuntime.inbox.addListener(_flush);
    WidgetsBinding.instance.addPostFrameCallback((_) => _flush());
  }

  @override
  void dispose() {
    PushRuntime.inbox.removeListener(_flush);
    super.dispose();
  }

  Future<void> _flush() async {
    final payload = PushRuntime.pending;
    if (payload == null || !mounted) {
      return;
    }
    PushRuntime.pending = null;
    await openPushData(context, payload.data);
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

/// Opens the screen a push points at. A test push with no notification id
/// only brings the app forward.
Future<void> openPushData(BuildContext context, Map<String, String> data) async {
  final type = data['type'] ?? '';
  if (type == 'stall_message') {
    final user = context.read<AuthController>().user;
    if (user?.isFarmerSeller == true) {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => const OrderChatsScreen(forSeller: true),
        ),
      );
      return;
    }
    final sellerId = int.tryParse(data['seller_id'] ?? '') ?? 0;
    if (sellerId > 0) {
      await openChatWithStall(context, sellerId: sellerId);
    }
    return;
  }

  final id = int.tryParse(data['notification_id'] ?? '') ?? 0;
  if (id <= 0) {
    return;
  }
  final relatedRaw = data['related_id'] ?? '';
  final item = AppNotification(
    id: id,
    title: '',
    body: '',
    type: type.isEmpty ? null : type,
    relatedId: int.tryParse(relatedRaw),
    relatedType: (data['related_type'] ?? '').isEmpty ? null : data['related_type'],
  );
  await openNotificationTarget(context, item);
  if (!context.mounted) {
    return;
  }
  try {
    await context.read<AuthController>().api.markNotificationRead(id);
  } catch (_) {}
  PushRuntime.inbox.ping();
}

Map<String, String> stringData(Map<String, dynamic>? raw) {
  if (raw == null) {
    return const {};
  }
  return {
    for (final entry in raw.entries) entry.key: '${entry.value}',
  };
}

Map<String, String> payloadData(String? payload) {
  if (payload == null || payload.isEmpty) {
    return const {};
  }
  try {
    final decoded = jsonDecode(payload);
    if (decoded is Map) {
      return stringData(Map<String, dynamic>.from(decoded));
    }
  } catch (_) {}
  return const {};
}
