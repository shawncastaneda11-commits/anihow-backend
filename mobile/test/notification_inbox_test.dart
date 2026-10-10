import 'dart:async';

import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/notifications/notifications_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/notification_bell.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _InboxApi extends ApiClient {
  _InboxApi(this.items) : super(onUnauthorized: () {});

  final List<AppNotification> items;
  final List<int> reads = [];
  int deletes = 0;
  int clears = 0;

  @override
  Future<List<AppNotification>> notifications() async => items;

  @override
  Future<int> unreadNotificationCount() async =>
      items.where((item) => item.isUnread).length;

  @override
  Future<void> markNotificationRead(int id) async {
    reads.add(id);
  }

  @override
  Future<void> markAllNotificationsRead() async {}

  @override
  Future<void> deleteNotification(int id) async {
    deletes++;
  }

  @override
  Future<int> clearReadNotifications() async {
    clears++;
    return items.where((item) => !item.isUnread).length;
  }
}

AppNotification _note({
  required int id,
  required String title,
  required String body,
  required String type,
  String? createdAt,
  String? readAt,
}) {
  return AppNotification(
    id: id,
    title: title,
    body: body,
    type: type,
    createdAt: createdAt,
    readAt: readAt,
  );
}

Widget _app({required AuthController auth, required Widget home}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider.value(value: auth),
      ChangeNotifierProvider(create: (_) => PreferencesController()),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

UserAccount _user({required bool seller}) {
  return UserAccount(
    id: 1,
    name: seller ? 'Liza Cruz' : 'Maria Santos',
    email: 'maria@example.com',
    roles: [seller ? 'farmer_seller' : 'buyer'],
  );
}

void main() {
  testWidgets('a removal still reaches the server after leaving the page', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _InboxApi([
      _note(
        id: 7,
        title: 'Order placed',
        body: 'Leave me',
        type: 'order_placed',
        readAt: '2026-10-01T00:00:00Z',
      ),
    ]);
    final auth = AuthController(api: api)
      ..restoring = false
      ..user = _user(seller: false);
    await tester.pumpWidget(
      _app(
        auth: auth,
        home: const Scaffold(body: Text('home')),
      ),
    );
    final navigator = tester.state<NavigatorState>(
      find.byType(Navigator).first,
    );
    unawaited(
      navigator.push(
        MaterialPageRoute<void>(builder: (_) => const NotificationsScreen()),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();

    await tester.drag(find.text('Leave me'), const Offset(-500, 0));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.text('Leave me'), findsNothing);

    navigator.pop();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.text('home'), findsOneWidget);
    expect(api.deletes, 0);

    await tester.pump(const Duration(seconds: 5));
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();
    expect(api.deletes, 1);
  });

  testWidgets('the bell menu shows five notices and opens the inbox', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final items = [
      for (var day = 1; day <= 6; day++)
        _note(
          id: day,
          title: 'Notice $day',
          body: 'Body $day',
          type: 'report_submitted',
          createdAt: DateTime.utc(2026, 10, day).toIso8601String(),
        ),
    ];
    final api = _InboxApi(items);
    final auth = AuthController(api: api)
      ..restoring = false
      ..user = _user(seller: false);
    final s = AppStrings(false);

    await tester.pumpWidget(
      _app(
        auth: auth,
        home: Scaffold(
          appBar: AppBar(actions: const [NotificationBellButton()]),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.byTooltip(s.notifications));
    await tester.pump();
    await tester.pump();

    expect(find.byKey(const Key('notification-menu-panel')), findsOneWidget);
    expect(find.byKey(const Key('notification-menu-6')), findsOneWidget);
    expect(find.byKey(const Key('notification-menu-2')), findsOneWidget);
    expect(find.byKey(const Key('notification-menu-1')), findsNothing);

    await tester.tap(find.byKey(const Key('notification-menu-6')));
    await tester.pump();
    await tester.pump();

    expect(api.reads, [6]);
    expect(find.byKey(const Key('notification-menu-panel')), findsNothing);

    await tester.tap(find.byTooltip(s.notifications));
    await tester.pump();
    await tester.pump();
    await tester.tap(find.text(s.seeAllNotifications));
    await tester.pump();
    await tester.pump();

    expect(find.byType(NotificationsScreen), findsOneWidget);
    expect(find.byKey(const Key('notification-menu-panel')), findsNothing);
  });

  testWidgets('the system back button closes the bell menu first', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _InboxApi(const []);
    final auth = AuthController(api: api)
      ..restoring = false
      ..user = _user(seller: false);

    await tester.pumpWidget(
      _app(
        auth: auth,
        home: Scaffold(
          appBar: AppBar(actions: const [NotificationBellButton()]),
        ),
      ),
    );
    await tester.pump();
    await tester.tap(find.byTooltip(AppStrings(false).notifications));
    await tester.pump();
    await tester.pump();
    expect(find.byKey(const Key('notification-menu-panel')), findsOneWidget);

    await tester.binding.defaultBinaryMessenger.handlePlatformMessage(
      'flutter/navigation',
      const JSONMethodCodec().encodeMethodCall(const MethodCall('popRoute')),
      (_) {},
    );
    await tester.pump();

    expect(find.byKey(const Key('notification-menu-panel')), findsNothing);
    expect(find.byType(NotificationBellButton), findsOneWidget);
  });

  testWidgets('chips, sections, undo, and clear earlier', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final items = [
      _note(
        id: 1,
        title: 'Order placed',
        body: 'A new order',
        type: 'order_placed',
      ),
      _note(
        id: 2,
        title: 'Payment received',
        body: 'GCash is paid',
        type: 'payment_confirmed',
        readAt: '2026-10-01T00:00:00Z',
      ),
      _note(
        id: 3,
        title: 'Stall wrote',
        body: 'A chat line',
        type: 'order_message',
        readAt: '2026-10-02T00:00:00Z',
      ),
    ];
    final api = _InboxApi(items);
    final s = AppStrings(false);

    Future<void> pump(bool seller) async {
      final auth = AuthController(api: api)
        ..restoring = false
        ..user = _user(seller: seller);
      await tester.pumpWidget(
        _app(auth: auth, home: const NotificationsScreen()),
      );
      await tester.pump();
      await tester.pump();
    }

    await pump(false);
    expect(find.widgetWithText(FilterChip, s.listings), findsNothing);
    expect(find.text(s.newNotifications), findsOneWidget);
    expect(find.text(s.earlierNotifications), findsOneWidget);
    expect(
      tester.getTopLeft(find.text('A new order')).dy,
      lessThan(tester.getTopLeft(find.text(s.earlierNotifications)).dy),
    );

    await tester.tap(find.widgetWithText(FilterChip, s.payments));
    await tester.pump();
    expect(find.text('GCash is paid'), findsOneWidget);
    expect(find.text('A new order'), findsNothing);
    expect(find.text('A chat line'), findsNothing);

    await tester.tap(find.widgetWithText(FilterChip, s.all));
    await tester.pump();

    await tester.drag(find.text('A chat line'), const Offset(-500, 0));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.text('A chat line'), findsNothing);

    await tester.tap(find.text(s.undo));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.text('A chat line'), findsOneWidget);
    expect(api.deletes, 0);

    await tester.pump(const Duration(seconds: 5));
    expect(api.deletes, 0);
    expect(find.text('A chat line'), findsOneWidget);

    await tester.ensureVisible(find.byKey(const ValueKey('notification-2')));
    await tester.drag(
      find.byKey(const ValueKey('notification-2')),
      const Offset(-800, 0),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 500));
    expect(find.text('GCash is paid'), findsNothing);
    await tester.pump(const Duration(seconds: 1));
    await tester.pump(const Duration(seconds: 4));
    await tester.pump(const Duration(milliseconds: 500));
    await tester.pump();
    expect(api.deletes, 1);
    expect(find.text('GCash is paid'), findsNothing);

    expect(api.clears, 0);
    await tester.tap(find.text(s.clearEarlier));
    await tester.pump();
    expect(find.text(s.clearEarlierTitle), findsOneWidget);
    expect(api.clears, 0);

    await tester.tap(find.byKey(const Key('confirm-clear-earlier')));
    await tester.pump();
    expect(api.clears, 1);
    expect(find.text('A new order'), findsOneWidget);

    await pump(true);
    expect(find.widgetWithText(FilterChip, s.listings), findsOneWidget);
  });
}
