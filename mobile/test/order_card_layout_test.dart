import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('buyer order address is wider than the old list-tile column at font 1.3', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const address = 'Manggahan, General Trias';
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        builder: (context, child) {
          return MediaQuery(
            data: MediaQuery.of(context).copyWith(
              textScaler: const TextScaler.linear(1.3),
            ),
            child: child!,
          );
        },
        home: const Scaffold(
          body: BuyerOrderCard(
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
      ),
    );

    final paragraph = tester.renderObject<RenderParagraph>(find.text(address));
    expect(paragraph.constraints.maxWidth, greaterThan(160));
  });
}
