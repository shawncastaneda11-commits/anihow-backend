import 'dart:math' as math;

import 'package:anihow/models/models.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/order_progress.dart';
import 'package:anihow/widgets/status_pill.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

OrderRecord _order({
  required String status,
  String? fulfillmentPreference,
  String? placedAt,
  String? confirmedAt,
  String? readyAt,
  String? completedAt,
  String? cancellationReason,
}) {
  return OrderRecord(
    id: 7,
    status: status,
    total: '80',
    items: const [],
    fulfillmentPreference: fulfillmentPreference,
    placedAt: placedAt,
    confirmedAt: confirmedAt,
    readyAt: readyAt,
    completedAt: completedAt,
    cancellationReason: cancellationReason,
  );
}

Future<void> _pump(WidgetTester tester, OrderRecord order) async {
  await tester.pumpWidget(
    MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: OrderProgress(order: order)),
    ),
  );
}

void main() {
  testWidgets('placed shows the pending step', (tester) async {
    await _pump(
      tester,
      _order(status: 'placed', placedAt: '2026-10-05T08:00:00'),
    );

    expect(find.text('Pending'), findsOneWidget);
    expect(find.text("Waiting for the farmer's approval"), findsOneWidget);
    expect(find.text('2026-10-05 08:00'), findsOneWidget);
    expect(find.byKey(const Key('order-progress')), findsOneWidget);
  });

  testWidgets('confirmed shows the farmer approval step', (tester) async {
    await _pump(
      tester,
      _order(
        status: 'confirmed',
        placedAt: '2026-10-05T08:00:00',
        confirmedAt: '2026-10-05T09:15:00',
      ),
    );

    expect(find.text('Confirmed'), findsOneWidget);
    expect(find.text('The farmer approved your order'), findsOneWidget);
    expect(find.text('2026-10-05 09:15'), findsOneWidget);
  });

  testWidgets('ready for pickup uses the pickup wording', (tester) async {
    await _pump(
      tester,
      _order(status: 'ready', fulfillmentPreference: 'buyer_pickup'),
    );

    expect(find.text('Ready for pickup'), findsWidgets);
    expect(find.text('Out for delivery'), findsNothing);
  });

  testWidgets('ready for delivery uses the delivery wording', (tester) async {
    await _pump(
      tester,
      _order(status: 'ready', fulfillmentPreference: 'seller_delivers'),
    );

    expect(find.text('Out for delivery'), findsWidgets);
    expect(find.text('Ready for pickup'), findsNothing);
  });

  testWidgets('completed shows the fulfilled step', (tester) async {
    await _pump(
      tester,
      _order(status: 'completed', completedAt: '2026-10-05T18:00:00'),
    );

    expect(find.text('Order complete'), findsOneWidget);
    expect(find.text('Your order is fulfilled'), findsOneWidget);
    expect(find.text('2026-10-05 18:00'), findsOneWidget);
  });

  testWidgets('cancelled shows a banner instead of the steps', (tester) async {
    await _pump(
      tester,
      _order(status: 'cancelled', cancellationReason: 'buyer_cancelled'),
    );

    expect(find.byKey(const Key('order-cancelled-banner')), findsOneWidget);
    expect(find.byKey(const Key('order-progress')), findsNothing);
    expect(find.text('Cancelled'), findsOneWidget);
    expect(find.text('Cancelled by buyer'), findsOneWidget);
  });

  testWidgets('status pills clear 4.5 to 1 in light and dark', (tester) async {
    const cases = ['placed', 'confirmed', 'ready', 'completed', 'cancelled'];
    for (final theme in [AniHowTheme.light(), AniHowTheme.dark()]) {
      final surface = theme.brightness == Brightness.dark
          ? AniHowColors.darkCard
          : AniHowColors.card;
      for (final status in cases) {
        await tester.pumpWidget(
          MaterialApp(
            home: Theme(
              data: theme,
              child: Scaffold(
                backgroundColor: surface,
                body: Center(child: StatusPill.order(status)),
              ),
            ),
          ),
        );
        final label = status == 'placed'
            ? 'Pending'
            : status == 'confirmed'
            ? 'Confirmed'
            : status == 'ready'
            ? 'Ready for pickup'
            : status == 'completed'
            ? 'Order complete'
            : 'Cancelled';
        final text = tester.widget<Text>(find.text(label));
        final box = tester.widget<Container>(
          find.ancestor(of: find.text(label), matching: find.byType(Container)),
        );
        final tint = (box.decoration! as BoxDecoration).color!;
        final ratio = _contrast(text.style!.color!, _composite(tint, surface));
        expect(
          ratio,
          greaterThanOrEqualTo(4.5),
          reason:
              '$status on ${theme.brightness.name} is $ratio',
        );
      }
    }
  });
}

double _channel(double value) {
  return value <= 0.04045
      ? value / 12.92
      : math.pow((value + 0.055) / 1.055, 2.4).toDouble();
}

double _luminance(Color color) {
  return 0.2126 * _channel(color.r) +
      0.7152 * _channel(color.g) +
      0.0722 * _channel(color.b);
}

Color _composite(Color foreground, Color background) {
  final alpha = foreground.a;
  return Color.from(
    alpha: 1,
    red: foreground.r * alpha + background.r * (1 - alpha),
    green: foreground.g * alpha + background.g * (1 - alpha),
    blue: foreground.b * alpha + background.b * (1 - alpha),
  );
}

double _contrast(Color first, Color second) {
  final left = _luminance(first);
  final right = _luminance(second);
  final lighter = math.max(left, right);
  final darker = math.min(left, right);
  return (lighter + 0.05) / (darker + 0.05);
}
