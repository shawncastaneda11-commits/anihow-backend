import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../support/relative_time.dart';
import '../buyer/listing_detail_screen.dart';
import '../buyer/marketplace_screen.dart';
import '../buyer/reservation_detail_screen.dart';
import '../buyer/reservations_screen.dart';
import '../farmer/listing_form_screen.dart';
import '../farmer/listings_screen.dart';
import '../farmer/reservations_screen.dart';

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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
        await _reload();
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _open(AppNotification item) async {
    if (item.isUnread) {
      _markLocallyRead([item.id]);
      try {
        await context.read<AuthController>().api.markNotificationRead(item.id);
      } on ApiException catch (error) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
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
        title: const Text('Notifications'),
        actions: [
          TextButton(
            style: TextButton.styleFrom(
              foregroundColor: Colors.white,
              disabledForegroundColor: Colors.white70,
            ),
            onPressed: _busy || !hasUnread ? null : _markAllRead,
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    if (_items.isEmpty) {
      return const _CaughtUpEmpty();
    }
    return ListView.separated(
      padding: AniHowSpace.screenPadding,
      itemCount: _items.length,
      separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
      itemBuilder: (context, index) {
        final item = _items[index];
        return Card(
          child: ListTile(
            contentPadding: const EdgeInsets.symmetric(
              horizontal: AniHowSpace.cardPad,
              vertical: 8,
            ),
            leading: _UnreadDot(visible: item.isUnread),
            title: Text(
              item.title,
              style: TextStyle(
                fontSize: AniHowSpace.name,
                fontWeight: item.isUnread ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
            subtitle: Padding(
              padding: const EdgeInsets.only(top: 4),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    item.body,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: AniHowSpace.body),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    relativeTime(item.createdAt),
                    style: TextStyle(
                      fontSize: AniHowSpace.label,
                      color: Theme.of(context).textTheme.bodySmall?.color,
                    ),
                  ),
                ],
              ),
            ),
            onTap: () => _open(item),
          ),
        );
      },
    );
  }
}

class _CaughtUpEmpty extends StatelessWidget {
  const _CaughtUpEmpty();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: AniHowSpace.screenPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.notifications_none, size: 56, color: AniHowColors.brand),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              "You're all caught up",
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class _UnreadDot extends StatelessWidget {
  const _UnreadDot({required this.visible});

  final bool visible;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 12,
      height: 12,
      child: visible
          ? const DecoratedBox(
              decoration: BoxDecoration(
                color: AniHowColors.brand,
                shape: BoxShape.circle,
              ),
            )
          : null,
    );
  }
}

Future<void> openNotificationTarget(BuildContext context, AppNotification item) async {
  final user = context.read<AuthController>().user;
  final api = context.read<AuthController>().api;
  final isFarmer = user?.isFarmerSeller == true;

  if (item.pointsToListing && item.relatedId != null) {
    if (isFarmer) {
      try {
        final listing = await api.farmerListing(item.relatedId!);
        if (!context.mounted) {
          return;
        }
        await Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => ListingFormScreen(listing: listing)),
        );
        return;
      } on ApiException {
        if (!context.mounted) {
          return;
        }
        await _pushList(context, title: 'My listings', body: const FarmerListingsScreen());
        return;
      }
    }
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ListingDetailScreen(listingId: item.relatedId!)),
    );
    return;
  }

  if (item.pointsToListing) {
    if (isFarmer) {
      await _pushList(context, title: 'My listings', body: const FarmerListingsScreen());
    } else {
      await Navigator.of(context).push(
        MaterialPageRoute(builder: (_) => const MarketplaceScreen()),
      );
    }
    return;
  }

  if (!isFarmer && item.pointsToReservation && item.relatedId != null) {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ReservationDetailScreen(reservationId: item.relatedId!)),
    );
    return;
  }

  await _pushList(
    context,
    title: isFarmer ? 'Incoming orders' : 'Reservations',
    body: isFarmer ? const FarmerReservationsScreen() : const BuyerReservationsScreen(),
  );
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
