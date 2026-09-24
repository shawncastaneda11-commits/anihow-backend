import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home, double textScale = 1}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(
      theme: AniHowTheme.light(),
      builder: (context, child) {
        return MediaQuery(
          data: MediaQuery.of(context).copyWith(
            textScaler: TextScaler.linear(textScale),
          ),
          child: child!,
        );
      },
      home: Scaffold(body: home),
    ),
  );
}

void main() {
  testWidgets('buyer order address is wider than the old list-tile column at font 1.3', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const address = 'Manggahan, General Trias';
    await tester.pumpWidget(
      _app(
        textScale: 1.3,
        home: const BuyerOrderCard(
          order: OrderRecord(
            id: 1,
            status: 'completed',
            statusLabel: 'Completed',
            total: '160',
            subtotal: '180',
            tawadTotal: '20',
            items: [],
            orderNumber: 'AH-260921-MQ2V6',
            shopName: 'Mang Tonyo Farm',
            location: address,
          ),
        ),
      ),
    );

    final paragraph = tester.renderObject<RenderParagraph>(find.text(address));
    expect(paragraph.constraints.maxWidth, greaterThan(160));
  });

  testWidgets('buyer order card shows seller unresponsive in English', (tester) async {
    await tester.pumpWidget(
      _app(
        home: const BuyerOrderCard(
          order: OrderRecord(
            id: 2,
            status: 'cancelled',
            statusLabel: 'Cancelled',
            total: '60',
            items: [],
            shopName: 'Mang Tonyo Farm',
            cancellationReason: 'seller_unresponsive',
          ),
        ),
      ),
    );

    expect(find.text('Seller unresponsive'), findsOneWidget);
  });

  testWidgets('buyer order card shows chat for app orders', (tester) async {
    await tester.pumpWidget(
      _app(
        home: const BuyerOrderCard(
          order: OrderRecord(
            id: 1,
            status: 'confirmed',
            statusLabel: 'Confirmed',
            total: '160',
            items: [],
            shopName: 'Mang Tonyo Farm',
          ),
        ),
      ),
    );

    expect(find.text('Chat with stall'), findsOneWidget);
  });

  testWidgets('buyer order card hides chat for walk-in sales', (tester) async {
    await tester.pumpWidget(
      _app(
        home: const BuyerOrderCard(
          order: OrderRecord(
            id: 2,
            status: 'completed',
            statusLabel: 'Completed',
            total: '80',
            items: [],
            shopName: 'Mang Tonyo Farm',
            isWalkIn: true,
          ),
        ),
      ),
    );

    expect(find.text('Chat with stall'), findsNothing);
  });
}
