import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/order_chat_head.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../buyer/order_history_screen.dart';
import '../farmer/farmer_orders_screen.dart';
import 'order_chat_screen.dart';
import 'stall_chat_screen.dart';

/// Chats for app orders, including finished ones. Walk-ins have no buyer chat.
class OrderChatsScreen extends StatefulWidget {
  const OrderChatsScreen({
    super.key,
    this.forSeller = false,
    this.embedded = false,
    this.active = true,
  });

  final bool forSeller;

  /// The seller shell already shows the title, so this page skips its app bar.
  final bool embedded;

  /// Reloads when a kept-alive tab becomes visible.
  final bool active;

  @override
  State<OrderChatsScreen> createState() => _OrderChatsScreenState();
}

class _ChatInbox {
  const _ChatInbox({required this.orders, required this.stalls});

  final List<OrderRecord> orders;
  final List<StallChat> stalls;

  bool get isEmpty => orders.isEmpty && stalls.isEmpty;
}

class _OrderChatsScreenState extends State<OrderChatsScreen> {
  late Future<_ChatInbox> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_ChatInbox> _load() async {
    final api = context.read<AuthController>().api;
    final ordersRaw = widget.forSeller
        ? await api.farmerOrders()
        : await api.buyerOrders();
    final stalls = await api.stallChats();
    final orders = ordersRaw.where((order) => !order.isWalkIn).toList();
    return _ChatInbox(orders: orders, stalls: stalls);
  }

  @override
  void didUpdateWidget(OrderChatsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) {
      _future = _load();
    }
  }

  Future<void> _reload() async {
    final future = _load();
    setState(() {
      _future = future;
    });
    await future;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final inbox = AsyncView<_ChatInbox>(
      future: _future,
      onRetry: _reload,
      isEmpty: (inbox) => inbox.isEmpty,
      emptyBuilder: (context) => _EmptyChats(forSeller: widget.forSeller),
      builder: (context, inbox) {
        final entries = inbox.orders.length + inbox.stalls.length;
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: entries,
            separatorBuilder: (_, _) =>
                const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              if (index < inbox.orders.length) {
                final order = inbox.orders[index];
                return _ChatCard(
                  order: order,
                  title: order.chatPeerTitle(viewingAsSeller: widget.forSeller),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => OrderChatScreen(order: order),
                      ),
                    );
                  },
                );
              }
              final chat = inbox.stalls[index - inbox.orders.length];
              return _StallChatCard(
                chat: chat,
                title: chat.title(viewingAsSeller: widget.forSeller),
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => StallChatScreen(
                        chat: chat,
                        viewingAsSeller: widget.forSeller,
                      ),
                    ),
                  );
                },
              );
            },
          ),
        );
      },
    );

    if (widget.embedded) {
      return inbox;
    }

    return Scaffold(
      appBar: AppBar(title: Text(s.chats)),
      body: inbox,
    );
  }
}

class _EmptyChats extends StatelessWidget {
  const _EmptyChats({required this.forSeller});

  final bool forSeller;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(AniHowSpace.section),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DecoratedBox(
              decoration: const BoxDecoration(
                color: AniHowColors.brand,
                shape: BoxShape.circle,
              ),
              child: const Padding(
                padding: EdgeInsets.all(22),
                child: ChatBubbleMark(size: 36, color: Colors.white),
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
            Text(
              s.noOpenChats,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: AniHowSpace.headline,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 280),
              child: Text(
                s.noChatsYet,
                textAlign: TextAlign.center,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: muted,
                  height: 1.4,
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
            FilledButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => forSeller
                        ? Scaffold(
                            appBar: AppBar(title: Text(s.orders)),
                            body: const FarmerOrdersScreen(),
                          )
                        : const OrderHistoryScreen(),
                  ),
                );
              },
              child: Text(s.openOrders),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatCard extends StatelessWidget {
  const _ChatCard({
    required this.order,
    required this.title,
    required this.onTap,
  });

  final OrderRecord order;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final when = relativeTime(order.placedAt);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AniHowAvatar(name: title, radius: 24),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: AniHowSpace.title,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        if (when.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Text(
                            when,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: muted,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      order.itemSummary,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium,
                    ),
                    const SizedBox(height: 8),
                    Flexible(
                      child: StatusPill.order(
                        order.status,
                        strings: s,
                        fulfillmentPreference: order.fulfillmentPreference,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}

class _StallChatCard extends StatelessWidget {
  const _StallChatCard({
    required this.chat,
    required this.title,
    required this.onTap,
  });

  final StallChat chat;
  final String title;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final when = relativeTime(chat.latestAt ?? chat.updatedAt);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              AniHowAvatar(name: title, radius: 24),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: AniHowSpace.title,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        if (when.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Text(
                            when,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: muted,
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      chat.latestBody ?? AppStrings.of(context).chatWithStall,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium,
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}
