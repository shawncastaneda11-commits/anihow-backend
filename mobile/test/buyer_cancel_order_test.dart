import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_order_detail_screen.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

OrderRecord _order(String status) {
  return OrderRecord(
    id: 12,
    status: status,
    total: '80',
    items: const [],
    shopName: 'Aling Nena Produce',
  );
}

OrderRecord _cancelled() {
  return _order('placed').copyWith(
    status: 'cancelled',
    statusLabel: 'Cancelled',
    cancellationReason: 'buyer_cancelled',
  );
}

class _OrdersApi extends ApiClient {
  _OrdersApi({this.fail, this.statusCode}) : super(onUnauthorized: () {});

  final String? fail;
  final int? statusCode;
  int cancelCalls = 0;
  String? note;
  int loads = 0;

  @override
  Future<OrderRecord> buyerOrder(int id) async {
    loads++;
    if (loads > 1 && fail != null) {
      return _order('confirmed');
    }
    return _order('placed');
  }

  @override
  Future<OrderRecord> cancelBuyerOrder(int id, {String? note}) async {
    cancelCalls++;
    this.note = note;
    if (fail != null) {
      throw ApiException(fail!, statusCode: statusCode);
    }
    return _cancelled();
  }
}

Widget _app(Widget home, {AuthController? auth}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      if (auth != null) ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  testWidgets('cancel order is only offered while the order is placed', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        Scaffold(
          body: ListView(
            children: [
              for (final status in [
                'placed',
                'confirmed',
                'ready',
                'completed',
                'cancelled',
              ])
                BuyerOrderCard(order: _order(status)),
            ],
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Cancel order'), findsOneWidget);
    expect(find.text('Pending'), findsWidgets);
    expect(find.text('Confirmed'), findsWidgets);
    expect(find.text('Ready for pickup'), findsWidgets);
    expect(find.text('Order complete'), findsWidgets);
    expect(find.text('Cancelled'), findsWidgets);

    await tester.pumpWidget(
      _app(
        const BuyerOrderDetailScreen(
          order: OrderRecord(
            id: 3,
            status: 'confirmed',
            total: '80',
            items: [],
            shopName: 'Aling Nena Produce',
          ),
          preview: true,
        ),
      ),
    );
    await tester.pump();
    expect(find.text('Cancel order'), findsNothing);
  });

  testWidgets('keep order leaves a placed order alone', (tester) async {
    final api = _OrdersApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      _app(const BuyerOrderDetailScreen(orderId: 12), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('cancel-buyer-order')));
    await tester.pumpAndSettle();
    expect(find.text('Cancel this order?'), findsOneWidget);
    expect(find.text('Note (optional)'), findsOneWidget);

    await tester.tap(find.text('Keep order'));
    await tester.pumpAndSettle();

    expect(api.cancelCalls, 0);
    expect(find.text('Pending'), findsWidgets);
    expect(find.text('Cancel order'), findsOneWidget);
    expect(find.text('Cancel this order?'), findsNothing);
    expect(AppStrings(true).keepOrder, 'Panatilihin ang order');
    expect(AppStrings(true).cancelThisOrder, 'Kanselahin ang order na ito?');
  });

  testWidgets('cancel order calls the API and shows the cancelled status', (
    tester,
  ) async {
    final api = _OrdersApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      _app(const BuyerOrderDetailScreen(orderId: 12), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('cancel-buyer-order')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-order')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(api.cancelCalls, 1);
    expect(find.text('Order cancelled.'), findsOneWidget);
    expect(find.text('Cancelled'), findsWidgets);
    expect(find.text('Cancelled by buyer'), findsWidgets);
    expect(find.byKey(const Key('cancel-buyer-order')), findsNothing);
    expect(AppStrings(true).orderCancelled, 'Kinansela ang order.');
  });

  testWidgets('a cancel error shows the server message and reloads', (
    tester,
  ) async {
    final api = _OrdersApi(
      fail: 'A buyer can only cancel an order before it is confirmed.',
    );
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      _app(const BuyerOrderDetailScreen(orderId: 12), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('cancel-buyer-order')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-order')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(
      find.text('A buyer can only cancel an order before it is confirmed.'),
      findsOneWidget,
    );
    expect(find.byKey(const Key('cancel-buyer-order')), findsNothing);
    expect(find.text('Confirmed'), findsWidgets);
  });

  testWidgets('a 403 cancel tells the buyer to verify email', (tester) async {
    final api = _OrdersApi(fail: 'Forbidden', statusCode: 403);
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(
      _app(const BuyerOrderDetailScreen(orderId: 12), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('cancel-buyer-order')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-order')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('Verify email to order.'), findsOneWidget);
    expect(find.text('Forbidden'), findsNothing);
  });
}
