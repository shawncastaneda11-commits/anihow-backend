import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/order_chat_head.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../buyer/order_history_screen.dart';
import '../farmer/farmer_orders_screen.dart';
import 'order_chat_screen.dart';
import 'remove_chat_dialog.dart';
import 'stall_chat_screen.dart';

/// Chats for app orders, including finished ones. Walk-ins have no buyer chat.
class OrderChatsScreen extends StatefulWidget {
  const OrderChatsScreen({
    super.key,
    this.forSeller = false,
    this.embedded = false,
    this.active = true,
    this.showAccountMenu = false,
  });

  final bool forSeller;

  /// The seller shell already shows the title, so this page skips its app bar
  /// unless [showAccountMenu] asks for the shared tab header.
  final bool embedded;

  /// Set by [FarmerShell] only.
  final bool showAccountMenu;

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
  final _hiddenLatestAt = <int, String?>{};
  Timer? _refresh;

  @override
  void initState() {
    super.initState();
    _future = _load();
    _startRefresh();
  }

  void _startRefresh() {
    _refresh?.cancel();
    if (!widget.active) {
      return;
    }
    _refresh = Timer.periodic(const Duration(seconds: 10), (_) {
      if (mounted) {
        unawaited(_reload());
      }
    });
  }

  Future<_ChatInbox> _load() async {
    final api = context.read<AuthController>().api;
    final ordersRaw = widget.forSeller
        ? await api.farmerOrders()
        : await api.buyerOrders();
    final stalls = await api.stallChats();
    final orders = ordersRaw.where((order) => !order.isWalkIn).toList();
    if (mounted) {
      setState(() => _releaseChatsWithNewMessages(stalls));
    }
    return _ChatInbox(orders: orders, stalls: stalls);
  }

  void _releaseChatsWithNewMessages(List<StallChat> stalls) {
    for (final chat in stalls) {
      if (_hiddenLatestAt.containsKey(chat.id) && !_isHidden(chat)) {
        _hiddenLatestAt.remove(chat.id);
      }
    }
  }

  bool _isHidden(StallChat chat) {
    if (!_hiddenLatestAt.containsKey(chat.id)) {
      return false;
    }
    final removedAt = _hiddenLatestAt[chat.id];
    final current = chat.latestAt;
    if (current == null || current.isEmpty) {
      return true;
    }
    if (removedAt == null || removedAt.isEmpty) {
      return false;
    }
    return current.compareTo(removedAt) <= 0;
  }

  @override
  void didUpdateWidget(OrderChatsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active != oldWidget.active) {
      _startRefresh();
    }
    if (widget.active && !oldWidget.active) {
      _future = _load();
    }
  }

  @override
  void dispose() {
    _refresh?.cancel();
    super.dispose();
  }

  Future<void> _hideAndDelete(StallChat chat) async {
    setState(() => _hiddenLatestAt[chat.id] = chat.latestAt);
    try {
      await context.read<AuthController>().api.removeStallChat(chat.id);
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.of(context).chatRemoved)),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _hiddenLatestAt.remove(chat.id));
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
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
        final stalls = inbox.stalls.where((chat) => !_isHidden(chat)).toList();
        final entries = inbox.orders.length + stalls.length;
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              widget.showAccountMenu ? 0 : AniHowSpace.screen,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            itemCount: entries,
            separatorBuilder: (_, _) =>
                const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              if (index < inbox.orders.length) {
                final order = inbox.orders[index];
                return _ChatCard(
                  order: order,
                  title: order.chatPeerTitle(viewingAsSeller: widget.forSeller),
                  onTap: () async {
                    await Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => OrderChatScreen(order: order),
                      ),
                    );
                    if (mounted) {
                      await _reload();
                    }
                  },
                );
              }
              final chat = stalls[index - inbox.orders.length];
              final title = chat.title(viewingAsSeller: widget.forSeller);
              return _StallChatCard(
                chat: chat,
                title: title,
                onTap: () async {
                  final removed = await Navigator.of(context).push<bool>(
                    MaterialPageRoute(
                      builder: (_) => StallChatScreen(
                        chat: chat,
                        viewingAsSeller: widget.forSeller,
                      ),
                    ),
                  );
                  if (!mounted) {
                    return;
                  }
                  if (removed == true) {
                    setState(() => _hiddenLatestAt[chat.id] = chat.latestAt);
                  }
                  await _reload();
                },
                onAskRemove: () => confirmRemoveStallChat(context, title),
                onRemove: () => _hideAndDelete(chat),
              );
            },
          ),
        );
      },
    );

    if (widget.embedded) {
      if (!widget.showAccountMenu) {
        return inbox;
      }
      return Scaffold(
        appBar: mainTabAppBar(title: s.chats, showAccountMenu: true),
        body: Column(
          children: [
            mainTabBodyGap,
            Expanded(child: inbox),
          ],
        ),
      );
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
    required this.onAskRemove,
    required this.onRemove,
  });

  final StallChat chat;
  final String title;
  final VoidCallback onTap;
  final Future<bool> Function() onAskRemove;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final when = relativeTime(chat.latestAt ?? chat.updatedAt);
    final strings = AppStrings.of(context);

    return Dismissible(
      key: ValueKey('stall-chat-${chat.id}'),
      direction: DismissDirection.endToStart,
      background: ColoredBox(
        key: const Key('remove-chat-swipe'),
        color: theme.colorScheme.error,
        child: const Align(
          alignment: Alignment.centerRight,
          child: Padding(
            padding: EdgeInsets.only(right: 20),
            child: Icon(Icons.delete, color: Colors.white),
          ),
        ),
      ),
      confirmDismiss: (_) async {
        if (await onAskRemove()) {
          onRemove();
        }
        return false;
      },
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          onLongPress: () => _openMenu(context),
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
                        chat.latestBody ?? strings.chatWithStall,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
                PopupMenuButton<String>(
                  key: ValueKey('stall-chat-menu-${chat.id}'),
                  tooltip: strings.removeChat,
                  icon: const Icon(Icons.more_vert),
                  style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
                  itemBuilder: (context) => [
                    PopupMenuItem(
                      value: 'remove',
                      height: 48,
                      child: Text(strings.removeChat),
                    ),
                  ],
                  onSelected: (value) async {
                    if (value != 'remove') {
                      return;
                    }
                    if (await onAskRemove()) {
                      onRemove();
                    }
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _openMenu(BuildContext context) async {
    final box = context.findRenderObject() as RenderBox?;
    final overlay =
        Overlay.of(context).context.findRenderObject() as RenderBox?;
    if (box == null || overlay == null || !box.hasSize) {
      return;
    }
    final origin = box.localToGlobal(Offset.zero, ancestor: overlay);
    final selected = await showMenu<String>(
      context: context,
      position: RelativeRect.fromLTRB(
        origin.dx,
        origin.dy,
        overlay.size.width - origin.dx,
        overlay.size.height - origin.dy,
      ),
      items: [
        PopupMenuItem(
          value: 'remove',
          height: 48,
          child: Text(AppStrings.of(context).removeChat),
        ),
      ],
    );
    if (selected == 'remove' && await onAskRemove() && context.mounted) {
      onRemove();
    }
  }
}
