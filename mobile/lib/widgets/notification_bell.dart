import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../navigation/route_observer.dart';
import '../push/push_runtime.dart';
import '../screens/notifications/notifications_screen.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../support/relative_time.dart';
import '../theme/readable_accent.dart';
import 'notification_category.dart';

class NotificationBellButton extends StatefulWidget {
  const NotificationBellButton({super.key});

  @override
  State<NotificationBellButton> createState() => _NotificationBellButtonState();
}

class _NotificationBellButtonState extends State<NotificationBellButton>
    with WidgetsBindingObserver, RouteAware {
  final MenuController _menu = MenuController();
  int _unread = 0;
  List<AppNotification> _preview = const [];
  bool _loading = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    PushRuntime.inbox.addListener(_onInbox);
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
  }

  void _onInbox() {
    _refresh();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    anihowRouteObserver.unsubscribe(this);
    final route = ModalRoute.of(context);
    if (route is PageRoute) {
      anihowRouteObserver.subscribe(this, route);
    }
  }

  @override
  void dispose() {
    anihowRouteObserver.unsubscribe(this);
    PushRuntime.inbox.removeListener(_onInbox);
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refresh();
    }
  }

  @override
  void didPopNext() => _refresh();

  @override
  void didPush() => _refresh();

  Future<void> _refresh() async {
    if (!mounted) {
      return;
    }
    final auth = context.read<AuthController>();
    if (auth.user == null) {
      setState(() => _unread = 0);
      return;
    }
    try {
      final count = await auth.api.unreadNotificationCount();
      if (mounted) {
        setState(() => _unread = count);
      }
    } catch (_) {
      // Keep the last known count if the poll fails.
    }
  }

  Future<void> _loadPreview() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.notifications();
      if (!mounted) {
        return;
      }
      final sorted = [...items]..sort(_newestFirst);
      setState(() {
        _preview = sorted.take(5).toList();
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  Future<void> _markAllRead() async {
    final hasUnread = _unread > 0 || _preview.any((item) => item.isUnread);
    if (!hasUnread) {
      return;
    }
    final readAt = DateTime.now().toUtc().toIso8601String();
    setState(() {
      _unread = 0;
      _preview = [
        for (final item in _preview) item.copyWith(readAt: readAt),
      ];
    });
    try {
      await context.read<AuthController>().api.markAllNotificationsRead();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _openItem(AppNotification item) async {
    _menu.close();
    if (item.isUnread) {
      try {
        await context.read<AuthController>().api.markNotificationRead(item.id);
      } on ApiException catch (error) {
        if (mounted) {
          ScaffoldMessenger.of(
            context,
          ).showSnackBar(SnackBar(content: Text(error.message)));
        }
      }
    }
    if (!mounted) {
      return;
    }
    await openNotificationTarget(context, item);
  }

  void _seeAll() {
    _menu.close();
    Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final unread = _unread;
    final label = unread > 99 ? '99+' : '$unread';
    final screenWidth = MediaQuery.sizeOf(context).width;
    final menuWidth = math.min(360.0, math.max(160.0, screenWidth - 20));
    final open = _menu.isOpen;
    final hasUnread = unread > 0 || _preview.any((item) => item.isUnread);

    return PopScope(
      canPop: !open,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop && _menu.isOpen) {
          _menu.close();
        }
      },
      child: MenuAnchor(
        controller: _menu,
        consumeOutsideTap: true,
        crossAxisUnconstrained: true,
        clipBehavior: Clip.none,
        alignmentOffset: Offset(48 - menuWidth, 4),
        style: const MenuStyle(
          backgroundColor: WidgetStatePropertyAll(Colors.transparent),
          surfaceTintColor: WidgetStatePropertyAll(Colors.transparent),
          elevation: WidgetStatePropertyAll(0),
          padding: WidgetStatePropertyAll(EdgeInsets.zero),
        ),
        onOpen: () {
          setState(() {});
          _loadPreview();
        },
        onClose: () {
          setState(() {});
          _refresh();
        },
        menuChildren: [
          _NotificationMenu(
            width: menuWidth,
            loading: _loading,
            error: _error,
            items: _preview,
            hasUnread: hasUnread,
            onRetry: _loadPreview,
            onMarkAll: _markAllRead,
            onOpen: _openItem,
            onSeeAll: _seeAll,
          ),
        ],
        builder: (context, controller, child) {
          return IconButton(
            tooltip: s.notifications,
            onPressed: () {
              if (controller.isOpen) {
                controller.close();
              } else {
                controller.open();
              }
            },
            padding: EdgeInsets.zero,
            constraints: const BoxConstraints.tightFor(width: 48, height: 48),
            icon: Badge(
              isLabelVisible: unread > 0,
              label: Text(label),
              child: Icon(
                Icons.notifications_outlined,
                size: 24,
                color: Theme.of(context).colorScheme.onPrimary,
              ),
            ),
          );
        },
      ),
    );
  }
}

int _newestFirst(AppNotification a, AppNotification b) {
  final aTime = DateTime.tryParse(a.createdAt ?? '') ?? DateTime.fromMillisecondsSinceEpoch(0);
  final bTime = DateTime.tryParse(b.createdAt ?? '') ?? DateTime.fromMillisecondsSinceEpoch(0);
  return bTime.compareTo(aTime);
}

class _NotificationMenu extends StatelessWidget {
  const _NotificationMenu({
    required this.width,
    required this.loading,
    required this.error,
    required this.items,
    required this.hasUnread,
    required this.onRetry,
    required this.onMarkAll,
    required this.onOpen,
    required this.onSeeAll,
  });

  final double width;
  final bool loading;
  final Object? error;
  final List<AppNotification> items;
  final bool hasUnread;
  final VoidCallback onRetry;
  final VoidCallback onMarkAll;
  final ValueChanged<AppNotification> onOpen;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final card = theme.cardTheme.color ?? theme.colorScheme.surface;

    return SizedBox(
      width: width,
      child: Material(
        key: const Key('notification-menu-panel'),
        color: card,
        elevation: 8,
        shadowColor: Colors.black.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 4, 4, 4),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      s.notifications,
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  TextButton(
                    onPressed: hasUnread ? onMarkAll : null,
                    style: TextButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    child: Text(s.markAllRead),
                  ),
                ],
              ),
            ),
            if (loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (error != null)
              Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  children: [
                    Text(s.somethingWentWrong, textAlign: TextAlign.center),
                    TextButton(
                      onPressed: onRetry,
                      style: TextButton.styleFrom(
                        minimumSize: const Size(48, 48),
                      ),
                      child: Text(s.retry),
                    ),
                  ],
                ),
              )
            else if (items.isEmpty)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 20),
                child: Text(
                  s.allCaughtUp,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium,
                ),
              )
            else
              for (final item in items)
                _NotificationMenuRow(item: item, onTap: () => onOpen(item)),
            TextButton(
              onPressed: onSeeAll,
              style: TextButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                shape: const RoundedRectangleBorder(),
              ),
              child: Text(s.seeAllNotifications),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationMenuRow extends StatelessWidget {
  const _NotificationMenuRow({required this.item, required this.onTap});

  final AppNotification item;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final unread = item.isUnread;
    final timeColor = unread
        ? readableAccent(context)
        : theme.colorScheme.onSurface.withValues(alpha: 0.72);

    return Material(
      key: Key('notification-menu-${item.id}'),
      color: unread ? accentTint(context) : Colors.transparent,
      child: InkWell(
        onTap: onTap,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            child: Row(
              children: [
                NotificationCategoryBadge(item: item, size: 40),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s.notificationTitle(item.type, item.title),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyLarge?.copyWith(
                          fontWeight: unread ? FontWeight.w800 : FontWeight.w600,
                        ),
                      ),
                      Text(
                        item.body,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
                        ),
                      ),
                      Text(
                        relativeTime(item.createdAt),
                        style: theme.textTheme.labelMedium?.copyWith(color: timeColor),
                      ),
                    ],
                  ),
                ),
                if (unread)
                  Padding(
                    padding: const EdgeInsets.only(left: 8),
                    child: DecoratedBox(
                      decoration: BoxDecoration(
                        color: readableAccent(context),
                        shape: BoxShape.circle,
                      ),
                      child: const SizedBox(width: 8, height: 8),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
