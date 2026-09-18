import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../navigation/route_observer.dart';
import '../screens/notifications/notifications_screen.dart';
import '../state/auth_controller.dart';

class NotificationBellButton extends StatefulWidget {
  const NotificationBellButton({super.key});

  @override
  State<NotificationBellButton> createState() => _NotificationBellButtonState();
}

class _NotificationBellButtonState extends State<NotificationBellButton>
    with WidgetsBindingObserver, RouteAware {
  int _unread = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) => _refresh());
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
    try {
      final count = await context.read<AuthController>().api.unreadNotificationCount();
      if (mounted) {
        setState(() => _unread = count);
      }
    } catch (_) {
      // Keep the last known count if the poll fails.
    }
  }

  Future<void> _openInbox() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const NotificationsScreen()),
    );
    await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    final label = _unread > 99 ? '99+' : '$_unread';
    return IconButton(
      tooltip: 'Notifications',
      onPressed: _openInbox,
      icon: Badge(
        isLabelVisible: _unread > 0,
        label: Text(label),
        child: const Icon(Icons.notifications_outlined, color: Colors.white),
      ),
    );
  }
}
