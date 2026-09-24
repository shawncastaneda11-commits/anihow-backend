import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farmer_sales_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: home,
    ),
  );
}

const _empty = FarmerAnalytics(
  period: 'week',
  summary: FarmerAnalyticsSummary(
    completedOrders: 0,
    unitsSold: 0,
    grossSales: 0,
    averageDiscount: 0,
  ),
  salesPerPeriod: [],
  unitsPerCropType: [],
  bestSelling: [],
  walkInShare: FarmerWalkInShare(
    walkInOrders: 0,
    walkInSales: 0,
    appOrders: 0,
    appSales: 0,
  ),
);

const _populated = FarmerAnalytics(
  period: 'week',
  summary: FarmerAnalyticsSummary(
    completedOrders: 2,
    unitsSold: 4,
    grossSales: 120,
    averageDiscount: 10,
  ),
  salesPerPeriod: [
    FarmerSalesPoint(period: '2026-09-23', orders: 1, revenue: 30),
    FarmerSalesPoint(period: '2026-09-24', orders: 1, revenue: 90),
  ],
  unitsPerCropType: [
    FarmerCropSales(crop: 'Kamatis', unit: 'kg', units: 4, revenue: 120),
  ],
  bestSelling: [
    FarmerCropSales(crop: 'Kamatis', unit: 'kg', units: 4, revenue: 120),
  ],
  walkInShare: FarmerWalkInShare(
    walkInOrders: 1,
    walkInSales: 90,
    appOrders: 1,
    appSales: 30,
  ),
);

void main() {
  testWidgets('sales screen empty state uses the hint card', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const Scaffold(body: FarmerSalesScreen(preview: _empty))),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byKey(const Key('sales-empty')), findsOneWidget);
    expect(find.text(s.mySalesEmpty), findsOneWidget);
    expect(find.byKey(const Key('sales-chart')), findsNothing);
    expect(tester.getSize(find.byKey(const Key('sales-period-week'))).height, 48);
  });

  testWidgets('sales screen populated state shows tiles, bars, and crops', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const Scaffold(body: FarmerSalesScreen(preview: _populated))),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byKey(const Key('sales-empty')), findsNothing);
    expect(find.byKey(const Key('sales-chart')), findsOneWidget);
    expect(find.text(s.completedOrders), findsOneWidget);
    expect(find.text(s.grossSales), findsOneWidget);
    expect(find.text(AniHowMoney.peso(120)), findsWidgets);
    expect(find.text('Kamatis'), findsWidgets);
    expect(find.text(s.walkInSales), findsOneWidget);
    await tester.scrollUntilVisible(find.text(s.bestSellers), 80);
    expect(find.text(s.bestSellers), findsOneWidget);
  });
}
