import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../push/push_runtime.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/async_view.dart';
import '../../widgets/notification_category.dart';
import '../buyer/listing_detail_screen.dart';
import '../buyer/marketplace_screen.dart';
import '../buyer/buyer_order_detail_screen.dart';
import '../buyer/pay_now_screen.dart';
import '../buyer/reservation_detail_screen.dart';
import '../buyer/order_history_screen.dart';
import '../farmer/farmer_orders_screen.dart';
import '../chat/order_chat_screen.dart';
import '../farmer/farm_announcements_screen.dart';
import '../farmer/stock_history_screen.dart';
import '../farmer/listing_form_screen.dart';
import '../farmer/listings_screen.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  List<AppNotification> _items = const [];
  bool _loading = true;
  bool _busy = false;
  Object? _error;
  NotificationCategory? _filter;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _items.isEmpty;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.notifications();
      if (!mounted) {
        return;
      }
      setState(() {
        _items = items;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  void _markLocallyRead(Iterable<int> ids) {
    final readAt = DateTime.now().toUtc().toIso8601String();
    setState(() {
      _items = [
        for (final item in _items)
          if (ids.contains(item.id)) item.copyWith(readAt: readAt) else item,
      ];
    });
  }

  Future<void> _markAllRead() async {
    final unreadIds = [
      for (final item in _items)
        if (item.isUnread) item.id,
    ];
    if (_busy || unreadIds.isEmpty) {
      return;
    }
    setState(() => _busy = true);
    _markLocallyRead(unreadIds);
    try {
      await context.read<AuthController>().api.markAllNotificationsRead();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
        await _reload();
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _drop(AppNotification item) {
    setState(() {
      _items = [
        for (final current in _items)
          if (current.id != item.id) current,
      ];
    });
  }

  void _restore(AppNotification item, int index) {
    setState(() {
      final next = [..._items];
      final at = index.clamp(0, next.length);
      next.insert(at, item);
      _items = next;
    });
  }

  Future<void> _remove(AppNotification item) async {
    final index = _items.indexWhere((current) => current.id == item.id);
    if (index < 0) {
      return;
    }
    _drop(item);
    final s = AppStrings.of(context);
    final messenger = ScaffoldMessenger.of(context);
    final closed = messenger
        .showSnackBar(
          SnackBar(
            content: Text(s.notificationRemoved),
            duration: const Duration(seconds: 4),
            behavior: SnackBarBehavior.floating,
            persist: false,
            action: SnackBarAction(label: s.undo, onPressed: () {}),
          ),
        )
        .closed;
    final reason = await closed;
    if (!mounted) {
      return;
    }
    if (reason == SnackBarClosedReason.action) {
      _restore(item, index);
      return;
    }
    try {
      await context.read<AuthController>().api.deleteNotification(item.id);
      PushRuntime.inbox.ping();
    } on ApiException {
      if (!mounted) {
        return;
      }
      _restore(item, index);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.of(context).somethingWentWrong)),
      );
    }
  }

  Future<void> _markOne(AppNotification item) async {
    if (!item.isUnread) {
      return;
    }
    _markLocallyRead([item.id]);
    try {
      await context.read<AuthController>().api.markNotificationRead(item.id);
      PushRuntime.inbox.ping();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
        await _reload();
      }
    }
  }

  Future<void> _clearEarlier() async {
    final s = AppStrings.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: Text(s.clearEarlierTitle),
          content: Text(s.clearEarlierBody),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(s.cancel),
            ),
            TextButton(
              key: const Key('confirm-clear-earlier'),
              onPressed: () => Navigator.of(context).pop(true),
              child: Text(s.clearEarlier),
            ),
          ],
        );
      },
    );
    if (confirmed != true || !mounted) {
      return;
    }
    final kept = [for (final item in _items) if (item.isUnread) item];
    setState(() => _items = kept);
    try {
      await context.read<AuthController>().api.clearReadNotifications();
      PushRuntime.inbox.ping();
    } on ApiException {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.of(context).somethingWentWrong)),
        );
        await _reload();
      }
    }
  }

  Future<void> _openMenu(AppNotification item) async {
    final s = AppStrings.of(context);
    await showModalBottomSheet<void>(
      context: context,
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (item.isUnread)
                ListTile(
                  title: Text(s.markAsRead),
                  onTap: () {
                    Navigator.of(context).pop();
                    _markOne(item);
                  },
                ),
              ListTile(
                title: Text(s.removeNotification),
                onTap: () {
                  Navigator.of(context).pop();
                  _remove(item);
                },
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _open(AppNotification item) async {
    if (item.isUnread) {
      _markLocallyRead([item.id]);
      try {
        await context.read<AuthController>().api.markNotificationRead(item.id);
      } on ApiException catch (error) {
        if (mounted) {
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(error.message)));
        }
      }
    }
    if (!mounted) {
      return;
    }
    await openNotificationTarget(context, item);
    if (mounted) {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasUnread = _items.any((item) => item.isUnread);
    return Scaffold(
      appBar: AppBar(
        title: Text(AppStrings.of(context).notifications),
        actions: [
          TextButton(
            style: TextButton.styleFrom(
              foregroundColor: Theme.of(context).colorScheme.onPrimary,
              disabledForegroundColor: Theme.of(context).colorScheme.onPrimary
                  .withValues(alpha: 0.7),
            ),
            onPressed: _busy || !hasUnread ? null : _markAllRead,
            child: Text(AppStrings.of(context).markAllRead),
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    final s = AppStrings.of(context);
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return AsyncViewError(onRetry: _reload);
    }
    final seller =
        context.watch<AuthController>().user?.isFarmerSeller == true;
    final filtered = [
      for (final item in _items)
        if (_filter == null || notificationCategory(item.type) == _filter)
          item,
    ];
    final unread = [for (final item in filtered) if (item.isUnread) item];
    final earlier = [for (final item in filtered) if (!item.isUnread) item];

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
          child: Row(
            children: [
              _filterChip(null, s.all),
              _filterChip(NotificationCategory.orders, s.orders),
              _filterChip(NotificationCategory.payments, s.payments),
              _filterChip(NotificationCategory.messages, s.messages),
              _filterChip(NotificationCategory.farmNews, s.farmNews),
              if (seller) _filterChip(NotificationCategory.listings, s.listings),
            ],
          ),
        ),
        Expanded(
          child: filtered.isEmpty
              ? Center(child: Text(s.nothingHere))
              : ListView(
                  padding: const EdgeInsets.fromLTRB(
                    AniHowSpace.screen,
                    AniHowSpace.cardGap,
                    AniHowSpace.screen,
                    AniHowSpace.screen,
                  ),
                  children: [
                    if (unread.isNotEmpty) ...[
                      _SectionLabel(s.newNotifications),
                      for (final item in unread) _row(item),
                    ],
                    if (earlier.isNotEmpty) ...[
                      _EarlierHeader(onClear: _clearEarlier),
                      for (final item in earlier) _row(item),
                    ],
                  ],
                ),
        ),
      ],
    );
  }

  Widget _filterChip(NotificationCategory? category, String label) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        label: Text(label),
        selected: _filter == category,
        onSelected: (_) => setState(() => _filter = category),
        materialTapTargetSize: MaterialTapTargetSize.padded,
      ),
    );
  }

  Widget _row(AppNotification item) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final unread = item.isUnread;
    final timeColor = unread
        ? readableAccent(context)
        : theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final card = theme.cardTheme.color ?? theme.colorScheme.surface;

    return Padding(
      padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
      child: Dismissible(
        key: ValueKey('notification-${item.id}'),
        direction: DismissDirection.endToStart,
        onDismissed: (_) => _remove(item),
        background: Container(
          alignment: Alignment.centerRight,
          padding: const EdgeInsets.symmetric(horizontal: 20),
          decoration: BoxDecoration(
            color: const Color(0xFFB3261E),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.delete_outline, color: Colors.white),
              const SizedBox(width: 8),
              Text(s.remove, style: const TextStyle(color: Colors.white)),
            ],
          ),
        ),
        child: Material(
          color: unread ? accentTint(context) : card,
          borderRadius: BorderRadius.circular(12),
          clipBehavior: Clip.antiAlias,
          child: InkWell(
            onTap: () => _open(item),
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 10, 0, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Padding(
                    padding: const EdgeInsets.only(top: 2),
                    child: NotificationCategoryBadge(item: item, size: 44),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          s.notificationTitle(item.type, item.title),
                          style: TextStyle(
                            fontSize: AniHowSpace.name,
                            fontWeight: unread
                                ? FontWeight.w800
                                : FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _notificationBody(item.body),
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: AniHowSpace.body),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          relativeTime(item.createdAt),
                          style: TextStyle(
                            fontSize: AniHowSpace.label,
                            color: timeColor,
                          ),
                        ),
                      ],
                    ),
                  ),
                  if (unread)
                    Padding(
                      padding: const EdgeInsets.only(top: 8, left: 4),
                      child: DecoratedBox(
                        decoration: BoxDecoration(
                          color: readableAccent(context),
                          shape: BoxShape.circle,
                        ),
                        child: const SizedBox(width: 8, height: 8),
                      ),
                    ),
                  IconButton(
                    tooltip: s.removeNotification,
                    onPressed: () => _openMenu(item),
                    constraints: const BoxConstraints.tightFor(
                      width: 48,
                      height: 48,
                    ),
                    icon: const Icon(Icons.more_vert),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Text(
        label,
        style: theme.textTheme.labelLarge?.copyWith(
          color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _EarlierHeader extends StatelessWidget {
  const _EarlierHeader({required this.onClear});

  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    return Row(
      children: [
        Expanded(
          child: Text(
            s.earlierNotifications,
            style: theme.textTheme.labelLarge?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        TextButton(
          onPressed: onClear,
          style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
          child: Text(s.clearEarlier),
        ),
      ],
    );
  }
}

Future<void> openNotificationTarget(
  BuildContext context,
  AppNotification item,
) async {
  if (item.isReportNotice) {
    return;
  }

  final user = context.read<AuthController>().user;
  final api = context.read<AuthController>().api;
  final isFarmer = user?.isFarmerSeller == true;

  if (isFarmer && item.pointsToAnnouncement) {
    if (!context.mounted) {
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => FarmAnnouncementsScreen(highlightId: item.relatedId),
      ),
    );
    return;
  }

  if (item.pointsToStockHistory && item.relatedId != null && isFarmer) {
    if (!context.mounted) {
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => StockHistoryScreen(listingId: item.relatedId!),
      ),
    );
    return;
  }

  if (item.pointsToListing && item.relatedId != null) {
    if (isFarmer) {
      try {
        final listing = await api.farmerListing(item.relatedId!);
        if (!context.mounted) {
          return;
        }
        await Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ListingFormScreen(listing: listing),
          ),
        );
        return;
      } on ApiException {
        if (!context.mounted) {
          return;
        }
        await _pushList(
          context,
          title: AppStrings.read(context).myListings,
          body: const FarmerListingsScreen(),
        );
        return;
      }
    }
    if (!context.mounted) {
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ListingDetailScreen(listingId: item.relatedId!),
      ),
    );
    return;
  }

  if (item.pointsToListing) {
    if (!context.mounted) {
      return;
    }
    if (isFarmer) {
      await _pushList(
        context,
        title: AppStrings.read(context).myListings,
        body: const FarmerListingsScreen(),
      );
    } else {
      await Navigator.of(context)
          .push(MaterialPageRoute(builder: (_) => const MarketplaceScreen()));
    }
    return;
  }

  if (item.type == 'order_message' && item.relatedId != null) {
    try {
      final order = isFarmer
          ? await api.farmerOrder(item.relatedId!)
          : await api.buyerOrder(item.relatedId!);
      if (!context.mounted) {
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => OrderChatScreen(order: order)),
      );
      return;
    } on ApiException {
      if (!context.mounted) {
        return;
      }
    }
  }

  if (item.pointsToReservation && item.relatedId != null) {
    try {
      final reservation = isFarmer
          ? await api.farmerReservation(item.relatedId!)
          : await _buyerReservation(api, item.relatedId!);
      if (!context.mounted) {
        return;
      }
      if (reservation != null) {
        final openPay = !isFarmer &&
            (reservation.isAwaitingPayment || reservation.latestProof?.isRejected == true);
        await Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => openPay
                ? PayNowScreen(reservation: reservation)
                : ReservationDetailScreen(
                    reservation: reservation,
                    forSeller: isFarmer,
                  ),
          ),
        );
        return;
      }
    } on ApiException {
      if (!context.mounted) {
        return;
      }
    }
  }

  if (!isFarmer && item.pointsToOrder) {
    if (!context.mounted) {
      return;
    }
    if (item.relatedId != null) {
      if (item.isPaymentNotice) {
        try {
          final order = await api.buyerOrder(item.relatedId!);
          if (!context.mounted) {
            return;
          }
          if (order.isAwaitingPayment) {
            await Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => PayNowScreen(order: order)),
            );
            return;
          }
        } on ApiException {
          if (!context.mounted) {
            return;
          }
        }
      }
      if (!context.mounted) {
        return;
      }
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => BuyerOrderDetailScreen(orderId: item.relatedId),
        ),
      );
      return;
    }
    await Navigator.of(context)
        .push(MaterialPageRoute(builder: (_) => const OrderHistoryScreen()));
    return;
  }

  if (isFarmer && item.pointsToOrder) {
    if (!context.mounted) {
      return;
    }
    if (item.relatedId != null) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => FarmerOrderDetailScreen(orderId: item.relatedId),
        ),
      );
      return;
    }
    await _pushList(
      context,
      title: AppStrings.read(context).incomingOrders,
      body: const FarmerOrdersScreen(),
    );
    return;
  }

  if (!context.mounted) {
    return;
  }
  if (isFarmer) {
    await _pushList(
      context,
      title: AppStrings.read(context).incomingOrders,
      body: const FarmerOrdersScreen(),
    );
    return;
  }

  await Navigator.of(context)
      .push(MaterialPageRoute(builder: (_) => const OrderHistoryScreen()));
}

Future<ReservationRecord?> _buyerReservation(ApiClient api, int id) async {
  final rows = await api.buyerReservations();
  for (final row in rows) {
    if (row.id == id) {
      return row;
    }
  }
  return null;
}

Future<void> _pushList(
  BuildContext context, {
  required String title,
  required Widget body,
}) {
  return Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => Scaffold(
        appBar: AppBar(title: Text(title)),
        body: body,
      ),
    ),
  );
}

/// Older notices stored the order code in the sentence. The order link is separate.
String _notificationBody(String body) {
  var text = body;
  text = text.replaceAll(
    RegExp(r'Order AH-\d{6}-[A-Z0-9]+ is now '),
    'This order is now ',
  );
  text = text.replaceAll(
    RegExp(r'Order AH-\d{6}-[A-Z0-9]+ is still waiting'),
    'An order is still waiting',
  );
  text = text.replaceAll(
    RegExp(r' placed order AH-\d{6}-[A-Z0-9]+ totaling '),
    ' placed an order totaling ',
  );
  text = text.replaceAll(RegExp(r' on order AH-\d{6}-[A-Z0-9]+: '), ': ');
  text = text.replaceAll(RegExp(r'\bAH-\d{6}-[A-Z0-9]+\b'), '');
  return text.replaceAll(RegExp(r' {2,}'), ' ').trim();
}
