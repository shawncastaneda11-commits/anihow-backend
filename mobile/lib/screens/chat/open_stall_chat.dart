import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../state/auth_controller.dart';
import 'order_chat_screen.dart';
import 'stall_chat_screen.dart';

/// Opens the stall thread. A product card is attached only when a listing
/// started the chat. Otherwise an open order still scrolls that thread to
/// the order.
Future<void> openChatWithStall(
  BuildContext context, {
  required int sellerId,
  int? listingId,
}) async {
  final api = context.read<AuthController>().api;
  if (listingId == null) {
    final orders = await api.buyerOrders();
    final open = orders
        .where(
          (order) =>
              order.sellerId == sellerId &&
              !order.isWalkIn &&
              !order.isCompleted &&
              !order.isCancelled,
        )
        .toList();
    if (!context.mounted) {
      return;
    }
    if (open.isNotEmpty) {
      open.sort((a, b) => (b.placedAt ?? '').compareTo(a.placedAt ?? ''));
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => OrderChatScreen(order: open.first),
        ),
      );
      return;
    }
  }

  final chat = await api.openStallChat(sellerId);
  if (!context.mounted) {
    return;
  }
  await Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => StallChatScreen(chat: chat, attachListingId: listingId),
    ),
  );
}
