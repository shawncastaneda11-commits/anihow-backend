import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../screens/chat/open_stall_chat.dart';
import '../screens/chat/order_chats_screen.dart';
import '../screens/notifications/notifications_screen.dart';
import '../state/app_availability.dart';
import '../state/auth_controller.dart';
import 'push_runtime.dart';

/// Opens a tapped push once the app knows who is signed in. A tap that starts
/// the app arrives before the sign-in is restored, so it waits for that, and
/// for any gate in front of the app (password change, terms, email check,
/// maintenance, update). If nobody is signed in, the tap is dropped: the
/// notice may belong to whoever used the phone before.
class PushOpenBinder extends StatefulWidget {
  const PushOpenBinder({super.key, required this.child});

  final Widget child;

  @override
  State<PushOpenBinder> createState() => _PushOpenBinderState();
}

class _PushOpenBinderState extends State<PushOpenBinder> {
  AuthController? _auth;

  @override
  void initState() {
    super.initState();
    PushRuntime.inbox.addListener(_flush);
    AppAvailability.instance.addListener(_flush);
    _auth = context.read<AuthController>()..addListener(_flush);
    WidgetsBinding.instance.addPostFrameCallback((_) => _flush());
  }

  @override
  void dispose() {
    PushRuntime.inbox.removeListener(_flush);
    AppAvailability.instance.removeListener(_flush);
    _auth?.removeListener(_flush);
    super.dispose();
  }

  Future<void> _flush() async {
    final payload = PushRuntime.pending;
    final auth = _auth;
    if (payload == null || auth == null || !mounted) {
      return;
    }
    switch (pushOpenState(auth, AppAvailability.instance)) {
      case PushOpenState.wait:
        return;
      case PushOpenState.drop:
        PushRuntime.pending = null;
        return;
      case PushOpenState.open:
        PushRuntime.pending = null;
        await openPushData(context, payload.data);
    }
  }

  @override
  Widget build(BuildContext context) => widget.child;
}

enum PushOpenState { wait, drop, open }

/// Whether a tapped push can open its screen now.
PushOpenState pushOpenState(AuthController auth, AppAvailability availability) {
  if (auth.restoring) {
    return PushOpenState.wait;
  }
  final user = auth.user;
  if (user == null) {
    return PushOpenState.drop;
  }
  if (user.mustChangePassword ||
      user.needsTerms ||
      auth.pendingEmailVerification ||
      availability.underMaintenance ||
      availability.mustUpdate) {
    return PushOpenState.wait;
  }
  return PushOpenState.open;
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
