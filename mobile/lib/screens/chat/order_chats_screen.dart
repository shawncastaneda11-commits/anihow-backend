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
import '../../theme/readable_accent.dart';
import '../../widgets/order_card_parts.dart';
import '../../widgets/order_chat_head.dart';
import '../../widgets/profile_avatar_button.dart';
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

enum _ChatFilter { all, needsReply, orders, questions }

class _OrderChatsScreenState extends State<OrderChatsScreen> {
  late Future<_ChatInbox> _future;
  final _hiddenLatestAt = <int, String?>{};
  final _search = TextEditingController();
  _ChatFilter _filter = _ChatFilter.all;
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
    _search.dispose();
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

  bool _showOrder(OrderRecord order) {
    if (order.latestMessageAt != null || order.latestMessageBody != null) {
      return true;
    }
    return order.isPlaced || order.isConfirmed || order.isReady;
  }

  /// Stall chats that only hold order messages are covered by their order
  /// rows, so they are left out instead of showing twice.
  bool _showStall(StallChat chat) => !_isHidden(chat) && chat.hasQuestions;

  bool _hasThreads(_ChatInbox inbox) {
    return inbox.orders.any(_showOrder) ||
        inbox.stalls.any(_showStall);
  }

  bool _needsReply(int? authorId, int? myId) {
    return authorId != null && authorId != myId;
  }

  bool _passesFilter({required bool needsReply, required bool isOrder}) {
    return switch (_filter) {
      _ChatFilter.all => true,
      _ChatFilter.needsReply => needsReply,
      _ChatFilter.orders => isOrder,
      _ChatFilter.questions => !isOrder,
    };
  }

  bool _orderMatches(OrderRecord order, String query) {
    if (query.isEmpty) {
      return true;
    }
    final name = order
        .chatPeerTitle(viewingAsSeller: widget.forSeller)
        .toLowerCase();
    final items = order.items.map((item) => item.listingName).join(' ');
    final haystack = '$name $items #${order.id}'.toLowerCase();
    return haystack.contains(query);
  }

  bool _stallMatches(StallChat chat, String query) {
    if (query.isEmpty) {
      return true;
    }
    return chat
        .title(viewingAsSeller: widget.forSeller)
        .toLowerCase()
        .contains(query);
  }

  DateTime _stamp(String? primary, String? fallback) {
    return DateTime.tryParse(primary ?? '') ??
        DateTime.tryParse(fallback ?? '') ??
        DateTime.fromMillisecondsSinceEpoch(0);
  }

  List<_ChatEntry> _entries(_ChatInbox inbox, int? myId) {
    final query = _search.text.trim().toLowerCase();
    final entries = <_ChatEntry>[];
    for (final order in inbox.orders.where(_showOrder)) {
      if (!_orderMatches(order, query)) {
        continue;
      }
      final needsReply = _needsReply(order.latestMessageUserId, myId);
      if (!_passesFilter(needsReply: needsReply, isOrder: true)) {
        continue;
      }
      entries.add(
        _ChatEntry.order(
          order: order,
          needsReply: needsReply,
          sortAt: _stamp(order.latestMessageAt, order.placedAt),
        ),
      );
    }
    for (final chat in inbox.stalls.where(_showStall)) {
      if (!_stallMatches(chat, query)) {
        continue;
      }
      final needsReply = _needsReply(chat.previewUserId, myId);
      if (!_passesFilter(needsReply: needsReply, isOrder: false)) {
        continue;
      }
      entries.add(
        _ChatEntry.stall(
          chat: chat,
          needsReply: needsReply,
          sortAt: _stamp(chat.previewAt, chat.updatedAt),
        ),
      );
    }
    entries.sort((a, b) => b.sortAt.compareTo(a.sortAt));
    return entries;
  }

  int _needsReplyCount(_ChatInbox inbox, int? myId) {
    final query = _search.text.trim().toLowerCase();
    var count = 0;
    for (final order in inbox.orders.where(_showOrder)) {
      if (_orderMatches(order, query) &&
          _needsReply(order.latestMessageUserId, myId)) {
        count++;
      }
    }
    for (final chat in inbox.stalls.where(_showStall)) {
      if (_stallMatches(chat, query) && _needsReply(chat.previewUserId, myId)) {
        count++;
      }
    }
    return count;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final inbox = AsyncView<_ChatInbox>(
      future: _future,
      onRetry: _reload,
      isEmpty: (inbox) => !_hasThreads(inbox),
      emptyBuilder: (context) => _EmptyChats(forSeller: widget.forSeller),
      builder: (context, inbox) {
        final myId = context.read<AuthController>().user?.id;
        final entries = _entries(inbox, myId);
        return Column(
          children: [
            Padding(
              padding: EdgeInsets.fromLTRB(
                AniHowSpace.screen,
                widget.showAccountMenu ? 0 : AniHowSpace.cardGap,
                AniHowSpace.screen,
                8,
              ),
              child: SizedBox(
                height: 48,
                child: TextField(
                  controller: _search,
                  textInputAction: TextInputAction.search,
                  onChanged: (_) => setState(() {}),
                  decoration: InputDecoration(
                    hintText: widget.forSeller
                        ? s.searchBuyersOrProduce
                        : s.searchStoresOrProduce,
                    prefixIcon: const Icon(Icons.search),
                    isDense: true,
                    contentPadding: const EdgeInsets.symmetric(horizontal: 16),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(999),
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(999),
                      borderSide: BorderSide(
                        color: Theme.of(context).dividerColor,
                      ),
                    ),
                  ),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
              child: _ChatFilters(
                selected: _filter,
                needsReply: _needsReplyCount(inbox, myId),
                onSelected: (filter) => setState(() => _filter = filter),
              ),
            ),
            const SizedBox(height: 12),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _reload,
                child: entries.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: [
                          const SizedBox(height: 48),
                          Center(child: Text(s.nothingHere)),
                        ],
                      )
                    : ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(
                          AniHowSpace.screen,
                          0,
                          AniHowSpace.screen,
                          AniHowSpace.screen,
                        ),
                        children: [
                          Card(
                            clipBehavior: Clip.antiAlias,
                            child: Column(
                              children: [
                                for (var index = 0; index < entries.length; index++) ...[
                                  if (index > 0) const Divider(height: 1),
                                  _ChatRow(
                                    entry: entries[index],
                                    forSeller: widget.forSeller,
                                    myId: myId,
                                    onOpenOrder: (order) async {
                                      await Navigator.of(context).push(
                                        MaterialPageRoute<void>(
                                          builder: (_) => OrderChatScreen(order: order),
                                        ),
                                      );
                                      if (mounted) {
                                        await _reload();
                                      }
                                    },
                                    onOpenStall: (chat) async {
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
                                        setState(
                                          () => _hiddenLatestAt[chat.id] = chat.latestAt,
                                        );
                                      }
                                      await _reload();
                                    },
                                    onAskRemove: (title) =>
                                        confirmRemoveStallChat(context, title),
                                    onRemove: _hideAndDelete,
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ],
                      ),
              ),
            ),
          ],
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

class _ChatEntry {
  const _ChatEntry.order({
    required this.order,
    required this.needsReply,
    required this.sortAt,
  }) : chat = null;

  const _ChatEntry.stall({
    required this.chat,
    required this.needsReply,
    required this.sortAt,
  }) : order = null;

  final OrderRecord? order;
  final StallChat? chat;
  final bool needsReply;
  final DateTime sortAt;
}

class _ChatFilters extends StatelessWidget {
  const _ChatFilters({
    required this.selected,
    required this.needsReply,
    required this.onSelected,
  });

  final _ChatFilter selected;
  final int needsReply;
  final ValueChanged<_ChatFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final chips = <(_ChatFilter, String)>[
      (_ChatFilter.all, s.filterAll),
      (_ChatFilter.needsReply, s.needsReply),
      (_ChatFilter.orders, s.orders),
      (_ChatFilter.questions, s.questions),
    ];
    return Row(
      children: [
        for (final (index, chip) in chips.indexed) ...[
          if (index > 0) const SizedBox(width: 8),
          Expanded(
            child: _ChatFilterChip(
              label: chip.$2,
              count: chip.$1 == _ChatFilter.needsReply ? needsReply : null,
              selected: selected == chip.$1,
              onTap: () => onSelected(chip.$1),
            ),
          ),
        ],
      ],
    );
  }
}

class _ChatFilterChip extends StatelessWidget {
  const _ChatFilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
    this.count,
  });

  final String label;
  final int? count;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final fill = dark ? const Color(0xFF2A3330) : const Color(0xFFF1EFE8);
    final background = selected
        ? (dark ? const Color(0xFFA8D5BA) : AniHowColors.brand)
        : fill;
    final foreground = selected
        ? (dark ? const Color(0xFF10251A) : Colors.white)
        : theme.colorScheme.onSurface;
    return Semantics(
      button: true,
      selected: selected,
      child: Material(
        color: background,
        shape: const StadiumBorder(),
        child: InkWell(
          onTap: onTap,
          customBorder: const StadiumBorder(),
          child: SizedBox(
            height: 40,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 6),
              child: Center(
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        label,
                        maxLines: 1,
                        style: theme.textTheme.labelLarge?.copyWith(
                          color: foreground,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      if (count != null && count! > 0) ...[
                        const SizedBox(width: 4),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            color: selected ? Colors.white : AniHowColors.brand,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 5,
                              vertical: 1,
                            ),
                            child: Text(
                              '$count',
                              style: TextStyle(
                                color: selected
                                    ? const Color(0xFF145C38)
                                    : Colors.white,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ChatRow extends StatelessWidget {
  const _ChatRow({
    required this.entry,
    required this.forSeller,
    required this.myId,
    required this.onOpenOrder,
    required this.onOpenStall,
    required this.onAskRemove,
    required this.onRemove,
  });

  final _ChatEntry entry;
  final bool forSeller;
  final int? myId;
  final Future<void> Function(OrderRecord order) onOpenOrder;
  final Future<void> Function(StallChat chat) onOpenStall;
  final Future<bool> Function(String title) onAskRemove;
  final Future<void> Function(StallChat chat) onRemove;

  @override
  Widget build(BuildContext context) {
    final chat = entry.chat;
    if (chat == null) {
      return _ThreadBody(
        entry: entry,
        forSeller: forSeller,
        myId: myId,
        onTap: () => onOpenOrder(entry.order!),
      );
    }
    final title = chat.title(viewingAsSeller: forSeller);
    return Dismissible(
      key: ValueKey('stall-chat-${chat.id}'),
      direction: DismissDirection.endToStart,
      background: ColoredBox(
        key: const Key('remove-chat-swipe'),
        color: Theme.of(context).colorScheme.error,
        child: const Align(
          alignment: Alignment.centerRight,
          child: Padding(
            padding: EdgeInsets.only(right: 20),
            child: Icon(Icons.delete, color: Colors.white),
          ),
        ),
      ),
      confirmDismiss: (_) async {
        if (await onAskRemove(title)) {
          await onRemove(chat);
        }
        return false;
      },
      child: _ThreadBody(
        entry: entry,
        forSeller: forSeller,
        myId: myId,
        onTap: () => onOpenStall(chat),
        menu: PopupMenuButton<String>(
          key: ValueKey('stall-chat-menu-${chat.id}'),
          tooltip: AppStrings.of(context).removeChat,
          icon: const Icon(Icons.more_vert),
          style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
          itemBuilder: (context) => [
            PopupMenuItem(
              value: 'remove',
              height: 48,
              child: Text(AppStrings.of(context).removeChat),
            ),
          ],
          onSelected: (value) async {
            if (value != 'remove') {
              return;
            }
            if (await onAskRemove(title)) {
              await onRemove(chat);
            }
          },
        ),
      ),
    );
  }
}

class _ThreadBody extends StatelessWidget {
  const _ThreadBody({
    required this.entry,
    required this.forSeller,
    required this.myId,
    required this.onTap,
    this.menu,
  });

  final _ChatEntry entry;
  final bool forSeller;
  final int? myId;
  final VoidCallback onTap;
  final Widget? menu;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final order = entry.order;
    final chat = entry.chat;
    final title = order != null
        ? order.chatPeerTitle(viewingAsSeller: forSeller)
        : chat!.title(viewingAsSeller: forSeller);
    final avatarUrl = order != null
        ? order.chatPeerAvatar(viewingAsSeller: forSeller)
        : chat!.avatarUrl(viewingAsSeller: forSeller);
    final when = relativeTime(
      order != null
          ? (order.latestMessageAt ?? order.placedAt)
          : (chat!.previewAt ?? chat.updatedAt),
    );
    final preview = _preview(s);
    final quiet = preview == s.noMessagesYet;
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final accent = readableAccent(context);

    return InkWell(
      onTap: onTap,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 72),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _ThreadAvatar(
                name: title,
                imageUrl: avatarUrl,
                order: order != null,
                needsReply: entry.needsReply,
              ),
              const SizedBox(width: 12),
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
                            style: TextStyle(
                              fontSize: AniHowSpace.title,
                              fontWeight: entry.needsReply
                                  ? FontWeight.w800
                                  : FontWeight.w600,
                            ),
                          ),
                        ),
                        if (when.isNotEmpty)
                          Text(
                            when,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: entry.needsReply ? accent : muted,
                              fontWeight: entry.needsReply
                                  ? FontWeight.w800
                                  : FontWeight.w500,
                            ),
                          ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Text(
                      preview,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: quiet ? muted : null,
                      ),
                    ),
                    const SizedBox(height: 6),
                    _ContextChip(entry: entry, forSeller: forSeller),
                  ],
                ),
              ),
              ?menu,
            ],
          ),
        ),
      ),
    );
  }

  String _preview(AppStrings s) {
    final order = entry.order;
    if (order != null) {
      final body = order.latestMessageBody;
      if (body == null || body.isEmpty) {
        return s.noMessagesYet;
      }
      if (order.latestMessageUserId != null &&
          order.latestMessageUserId == myId) {
        return s.youSaid(body);
      }
      return body;
    }
    final body = entry.chat?.previewBody;
    if (body == null || body.isEmpty) {
      return s.noMessagesYet;
    }
    if (entry.chat?.previewUserId != null &&
        entry.chat?.previewUserId == myId) {
      return s.youSaid(body);
    }
    return body;
  }
}

class _ThreadAvatar extends StatelessWidget {
  const _ThreadAvatar({
    required this.name,
    required this.order,
    required this.needsReply,
    this.imageUrl,
  });

  final String name;
  final String? imageUrl;
  final bool order;
  final bool needsReply;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final fill = order
        ? accentTint(context)
        : (dark ? const Color(0xFF3C2A4A) : const Color(0xFFF3E8FA));
    final ink = order
        ? readableAccent(context)
        : (dark ? const Color(0xFFE7CFF5) : const Color(0xFF5B2C83));
    return SizedBox(
      width: 48,
      height: 48,
      child: Stack(
        children: [
          AniHowAvatar(
            name: name,
            imageUrl: imageUrl,
            radius: 24,
            backgroundColor: fill,
            foregroundColor: ink,
          ),
          if (needsReply)
            Positioned(
              right: 0,
              bottom: 0,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: dark ? AniHowColors.switchOn : AniHowColors.brand,
                  shape: BoxShape.circle,
                  border: Border.all(color: theme.cardColor, width: 2),
                ),
                child: const SizedBox(width: 12, height: 12),
              ),
            ),
        ],
      ),
    );
  }
}

class _ContextChip extends StatelessWidget {
  const _ContextChip({required this.entry, required this.forSeller});

  final _ChatEntry entry;
  final bool forSeller;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final order = entry.order;
    late final String text;
    late final Color background;
    late final Color foreground;
    if (order == null) {
      text = forSeller ? s.questionAboutStall : s.chatWithTheStall;
      background = dark ? const Color(0xFF3C2A4A) : const Color(0xFFF3E8FA);
      foreground = dark ? const Color(0xFFE7CFF5) : const Color(0xFF5B2C83);
    } else {
      final item = orderItemLine(s, order);
      final status = _statusWord(s, order);
      text = [
        '#${order.id}',
        if (item.isNotEmpty) item,
        status,
      ].join(' · ');
      (background, foreground) = _statusColors(dark, order);
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Text(
          text,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: foreground,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }

  String _statusWord(AppStrings s, OrderRecord order) {
    if (order.isPlaced) {
      return s.newOrders;
    }
    if (order.isConfirmed) {
      return s.confirmed;
    }
    if (order.isReady) {
      return s.ready;
    }
    if (order.isCompleted) {
      return s.completed;
    }
    if (order.isCancelled) {
      return s.cancelled;
    }
    return order.statusLabel ?? order.status;
  }

  (Color, Color) _statusColors(bool dark, OrderRecord order) {
    if (order.isPlaced) {
      return dark
          ? (const Color(0xFF5A431C), const Color(0xFFF6D48A))
          : (const Color(0xFFFBF3DC), const Color(0xFF7A4E0C));
    }
    if (order.isConfirmed) {
      return dark
          ? (const Color(0xFF1E3A5C), const Color(0xFFC5DDF5))
          : (const Color(0xFFE7F0FA), const Color(0xFF1A4E86));
    }
    if (order.isReady) {
      return dark
          ? (const Color(0xFF245C42), const Color(0xFFB7E4C7))
          : (const Color(0xFFE5F4EB), const Color(0xFF145C38));
    }
    return dark
        ? (const Color(0xFF2E3834), const Color(0xFFD5D1C8))
        : (const Color(0xFFEEEDE8), const Color(0xFF3E4642));
  }
}
