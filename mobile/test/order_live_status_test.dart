import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

void main() {
  testWidgets('a status change from the API appears without a tap', (
    tester,
  ) async {
    final api = _LiveApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(
            create: (_) {
              final preferences = PreferencesController();
              preferences.notificationsEnabled = false;
              return preferences;
            },
          ),
          ChangeNotifierProvider.value(value: auth),
          ChangeNotifierProvider(create: (_) => CartController(auth)),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const OrderHistoryScreen(),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Pending'), findsWidgets);
    expect(find.text('Confirmed'), findsNothing);

    await tester.pump(const Duration(seconds: 10));
    await tester.pump();
    await tester.pump();

    expect(find.text('Confirmed'), findsWidgets);
    expect(api.orderCalls, greaterThan(1));

    await tester.pumpWidget(const SizedBox.shrink());
  });
}

class _LiveApi extends ApiClient {
  _LiveApi() : super(onUnauthorized: () {});

  int orderCalls = 0;

  @override
  Future<List<OrderRecord>> buyerOrders() async {
    orderCalls += 1;
    final confirmed = orderCalls > 1;
    return [
      OrderRecord(
        id: 4,
        status: confirmed ? 'confirmed' : 'placed',
        total: '40',
        items: const [],
        shopName: 'Nena Farm',
        placedAt: '2026-10-05T08:00:00',
        confirmedAt: confirmed ? '2026-10-05T09:00:00' : null,
      ),
    ];
  }

  @override
  Future<List<ReservationRecord>> buyerReservations() async => const [];

  @override
  Future<List<AppNotification>> notifications() async => const [];
}
