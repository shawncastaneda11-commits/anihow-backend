import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/push/push_runtime.dart';
import 'package:anihow/screens/farmer/farmer_sales_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
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
  windowStart: '2026-09-18',
  windowEnd: '2026-09-24',
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
  windowStart: '2026-09-18',
  windowEnd: '2026-09-24',
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
    expect(find.byKey(const Key('sales-window')), findsOneWidget);
    expect(
      find.text(s.compactRange('2026-09-18', '2026-09-24')),
      findsOneWidget,
    );
    expect(find.text(s.mySalesEmpty), findsOneWidget);
    expect(find.byKey(const Key('sales-chart')), findsNothing);
    expect(tester.getSize(find.byKey(const Key('sales-filter'))).height, 48);
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
    expect(find.byKey(const Key('sales-window')), findsOneWidget);
    expect(
      find.text(s.compactRange('2026-09-18', '2026-09-24')),
      findsOneWidget,
    );
    expect(find.byKey(const Key('sales-chart')), findsOneWidget);
    expect(find.text(s.ordersCount(2)), findsOneWidget);
    expect(find.text(s.totalSales), findsOneWidget);
    expect(find.text(AniHowMoney.peso(120)), findsWidgets);
    expect(find.text('Kamatis'), findsWidgets);
    expect(find.text(s.bestSellers), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text(s.walkInSales),
      200,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text(s.walkInSales), findsOneWidget);
  });

  test('null cost fields stay null instead of zero', () {
    final cost = FarmerHarvestCost.fromJson({
      'records_with_cost': 2,
      'records_without_cost': 1,
      'cost_total': null,
      'potential_profit': null,
      'actual_profit': null,
    });

    expect(cost.costTotal, isNull);
    expect(cost.potentialProfit, isNull);
    expect(cost.actualProfit, isNull);
    expect(cost.recordsWithCost, 2);
  });

  testWidgets('range chips send week, year, and yearly params', (tester) async {
    await _surface(tester);
    final api = _AnalyticsApi(_base(range: _monthRange, sales: _paidSales));
    await tester.pumpWidget(_live(api: api));
    await tester.pumpAndSettle();

    expect(api.calls.single.range, 'month');
    expect(api.calls.single.category, 'all');
    expect(api.calls.single.from, isNull);
    expect(api.calls.single.year, isNull);

    await _apply(tester, const Key('sales-range-week'));
    expect(api.calls.last.range, 'week');
    expect(api.calls.last.from, isNull);
    expect(api.calls.last.to, isNull);
    expect(api.calls.last.year, isNull);

    await _apply(tester, const Key('sales-range-year'));
    expect(api.calls.last.range, 'year');

    await tester.tap(find.byKey(const Key('sales-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-range-yearly')));
    await tester.pumpAndSettle();
    expect(find.text('2025'), findsOneWidget);
    expect(find.text('2026'), findsOneWidget);
    await tester.tap(find.byKey(const Key('sales-year-2026')));
    await tester.tap(find.byKey(const Key('sales-apply')));
    await tester.pumpAndSettle();
    expect(api.calls.last.range, 'yearly');
    expect(api.calls.last.year, 2026);
    expect(api.calls.last.from, isNull);
    expect(find.text('2026'), findsWidgets);
  });

  testWidgets('custom range over 366 days is blocked before the request', (
    tester,
  ) async {
    await _surface(tester);
    final api = _AnalyticsApi(_base(range: _monthRange, sales: _paidSales));
    await tester.pumpWidget(
      _live(
        api: api,
        chooseCustomRange: (_) async => DateTimeRange(
          start: DateTime(2025, 1, 1),
          end: DateTime(2026, 1, 2),
        ),
      ),
    );
    await tester.pumpAndSettle();
    final before = api.calls.length;

    await tester.tap(find.byKey(const Key('sales-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-range-custom')));
    await tester.pumpAndSettle();

    expect(find.text(AppStrings(false).pickAtMost366), findsOneWidget);
    expect(api.calls.length, before);
    expect(find.text(AppStrings(false).thisMonth), findsWidgets);
  });

  testWidgets('a 422 keeps the previous range and shows the server message', (
    tester,
  ) async {
    await _surface(tester);
    final api = _AnalyticsApi(_base(range: _monthRange, sales: _paidSales))
      ..customError = ApiException('Pick at most 366 days.', statusCode: 422);
    await tester.pumpWidget(
      _live(
        api: api,
        chooseCustomRange: (_) async => DateTimeRange(
          start: DateTime(2026, 10, 1),
          end: DateTime(2026, 10, 3),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byKey(const Key('sales-filter')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-range-custom')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-apply')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('Pick at most 366 days.'), findsOneWidget);
    expect(api.calls.last.range, 'custom');
    expect(
      find.text(AppStrings(false).compactRange('2026-10-01', '2026-10-09')),
      findsOneWidget,
    );
  });

  testWidgets('category toggle is hidden without value-added and sends fresh', (
    tester,
  ) async {
    await _surface(tester);
    final hidden = _AnalyticsApi(_base(range: _monthRange, sales: _paidSales));
    await tester.pumpWidget(_live(api: hidden, user: _freshOnly));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-filter')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('sales-category-fresh')), findsNothing);
    await tester.tap(find.byKey(const Key('sales-range-week')));
    await tester.tap(find.byKey(const Key('sales-apply')));
    await tester.pumpAndSettle();
    expect(hidden.calls.last.category, 'all');
  });

  testWidgets('fresh category is sent when value-added is on', (tester) async {
    await _surface(tester);
    final shown = _AnalyticsApi(_base(range: _monthRange, sales: _paidSales));
    await tester.pumpWidget(_live(api: shown));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('sales-filter')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('sales-category-fresh')), findsOneWidget);
    await tester.tap(find.byKey(const Key('sales-category-fresh')));
    await tester.tap(find.byKey(const Key('sales-apply')));
    await tester.pumpAndSettle();
    expect(shown.calls.last.category, 'fresh');
    expect(shown.calls.last.range, 'month');
  });

  testWidgets('pie legend lists five crops and Others', (tester) async {
    await _surface(tester);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _piePreview))),
    );
    await tester.pump();

    for (final crop in ['Tomato', 'Pechay', 'Okra', 'Eggplant', 'Carrot']) {
      expect(find.text(crop), findsOneWidget);
    }
    expect(find.text('Others (2 crops)'), findsOneWidget);
    expect(find.text('12 bundles sold'), findsOneWidget);
    expect(find.text('10%'), findsWidgets);
    await tester.ensureVisible(find.text('Others (2 crops)'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Others (2 crops)'));
    await tester.pumpAndSettle();
    expect(find.text('Mango'), findsOneWidget);
  });

  testWidgets('future months render as outlines', (tester) async {
    await _surface(tester);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _futurePreview))),
    );
    await tester.pump();

    expect(find.byKey(const Key('sales-future-2026-11')), findsOneWidget);
    expect(find.text('Nov'), findsOneWidget);
  });

  testWidgets('harvest note includes the estimated line', (tester) async {
    await _surface(tester);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _harvestPreview(1)))),
    );
    await tester.pump();
    await tester.tap(find.text('Harvest'));
    await tester.pumpAndSettle();

    final s = AppStrings(false);
    expect(find.byKey(const Key('sales-harvest-note')), findsOneWidget);
    expect(find.textContaining(s.harvestNote), findsOneWidget);
    expect(find.textContaining(s.estimatedHarvests(1)), findsOneWidget);
  });

  testWidgets('no cost recorded and a negative profit stay distinct', (
    tester,
  ) async {
    await _surface(tester);
    final s = AppStrings(false);
    await tester.pumpWidget(
      _app(
        home: Scaffold(
          body: FarmerSalesScreen(preview: _harvestPreview(0, withCost: false)),
        ),
      ),
    );
    await tester.pump();
    await tester.tap(find.text(s.harvestTab));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('sales-crop-bar-Pechay-bundle')),
      200,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.byKey(const Key('sales-crop-bar-Pechay-bundle')), findsOneWidget);
    expect(find.text('Sold 4 bundles'), findsOneWidget);
    expect(find.text('Left 3 bundles'), findsOneWidget);
    expect(find.textContaining('Removed 0'), findsNothing);
    expect(find.textContaining('Rejected:'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text(s.noCostRecorded),
      200,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text(s.noCostRecorded), findsOneWidget);
    expect(find.text(s.noCostHint), findsOneWidget);
  });

  testWidgets('profit card shows a negative actual profit', (tester) async {
    await _surface(tester);
    final s = AppStrings(false);
    await tester.pumpWidget(
      _app(
        home: Scaffold(
          body: FarmerSalesScreen(preview: _harvestPreview(0, withCost: true)),
        ),
      ),
    );
    await tester.pump();
    await tester.tap(find.text(s.harvestTab));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text(s.costCoverage(2, 3)),
      200,
      scrollable: find.byType(Scrollable).last,
    );
    expect(find.text(s.noCostRecorded), findsNothing);
    expect(find.textContaining('\u2212${AniHowMoney.peso(40)}'), findsOneWidget);
    expect(find.text(s.costCoverage(2, 3)), findsOneWidget);
  });

  testWidgets('empty sales and harvest each have their own sentence', (
    tester,
  ) async {
    await _surface(tester);
    final s = AppStrings(false);
    await tester.pumpWidget(
      _app(home: const Scaffold(body: FarmerSalesScreen(preview: _emptyBoth))),
    );
    await tester.pump();
    expect(find.byKey(const Key('sales-filter')), findsOneWidget);
    expect(find.text(s.mySalesEmpty), findsOneWidget);
    expect(find.byKey(const Key('sales-chart')), findsNothing);

    await tester.tap(find.text(s.harvestTab));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('sales-filter')), findsOneWidget);
    expect(find.text(s.noHarvests), findsOneWidget);
  });

  testWidgets('a null cost total never renders as zero pesos', (tester) async {
    await _surface(tester);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _nullCostPreview))),
    );
    await tester.pump();
    await tester.tap(find.text(AppStrings(false).harvestTab));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.byKey(const Key('sales-profit')),
      200,
      scrollable: find.byType(Scrollable).last,
    );

    expect(find.text('₱0.00'), findsNothing);
    expect(find.textContaining('—'), findsWidgets);
    expect(find.text(AniHowMoney.peso(12)), findsWidgets);
  });

  testWidgets('changing the filter on Harvest keeps the Harvest tab', (tester) async {
    await _surface(tester);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _harvestPreview(1)))),
    );
    await tester.pump();
    await tester.tap(find.text('Harvest'));
    await tester.pumpAndSettle();
    await _apply(tester, const Key('sales-range-week'));
    expect(find.textContaining(AppStrings(false).harvestNote), findsOneWidget);
    expect(find.byKey(const Key('sales-chart')), findsNothing);
  });

  testWidgets('yearly summary shows the best month', (tester) async {
    await _surface(tester);
    final s = AppStrings(false);
    await tester.pumpWidget(
      _app(home: Scaffold(body: FarmerSalesScreen(preview: _futurePreview))),
    );
    await tester.pump();
    await _apply(tester, const Key('sales-range-yearly'), year: 2026);
    expect(
      find.text(s.bestMonthLine('Jun', AniHowMoney.peso(40))),
      findsOneWidget,
    );
    expect(find.text(s.yearTotalLabel(2026)), findsOneWidget);
  });
}

Future<void> _apply(WidgetTester tester, Key rangeKey, {int? year}) async {
  await tester.tap(find.byKey(const Key('sales-filter')));
  await tester.pumpAndSettle();
  await tester.tap(find.byKey(rangeKey));
  await tester.pumpAndSettle();
  if (year != null) {
    await tester.tap(find.byKey(Key('sales-year-$year')));
    await tester.pumpAndSettle();
  }
  await tester.tap(find.byKey(const Key('sales-apply')));
  await tester.pumpAndSettle();
}

Future<void> _surface(WidgetTester tester) async {
  await tester.binding.setSurfaceSize(const Size(360, 800));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  addTearDown(PushRuntime.reset);
}

class _Call {
  const _Call({
    required this.range,
    required this.category,
    this.from,
    this.to,
    this.year,
  });

  final String range;
  final String category;
  final String? from;
  final String? to;
  final int? year;
}

class _AnalyticsApi extends ApiClient {
  _AnalyticsApi(this.body) : super(onUnauthorized: () {});

  FarmerAnalytics body;
  final List<_Call> calls = [];
  ApiException? customError;

  @override
  Future<FarmerAnalytics> farmerAnalytics({
    String range = 'month',
    String? from,
    String? to,
    int? year,
    String category = 'all',
  }) async {
    calls.add(
      _Call(range: range, category: category, from: from, to: to, year: year),
    );
    final error = customError;
    if (range == 'custom' && error != null) {
      throw error;
    }
    return body;
  }
}

const _seller = UserAccount(
  id: 1,
  name: 'Ana',
  email: 'ana@example.com',
  roles: ['farmer'],
);

const _freshOnly = UserAccount(
  id: 1,
  name: 'Ana',
  email: 'ana@example.com',
  roles: ['farmer'],
  farmFeatures: FarmFeatures(valueAdded: false),
);

Widget _live({
  required _AnalyticsApi api,
  UserAccount user = _seller,
  SalesRangePicker? chooseCustomRange,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider(
        create: (_) {
          final auth = AuthController(api: api);
          auth.user = user;
          return auth;
        },
      ),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(
        body: FarmerSalesScreen(chooseCustomRange: chooseCustomRange),
      ),
    ),
  );
}

const _zeroShare = FarmerWalkInShare(
  walkInOrders: 0,
  walkInSales: 0,
  appOrders: 0,
  appSales: 0,
);

const _zeroSummary = FarmerAnalyticsSummary(
  completedOrders: 0,
  unitsSold: 0,
  grossSales: 0,
  averageDiscount: 0,
);

const _monthRange = FarmerAnalyticsRange(
  key: 'month',
  from: '2026-10-01',
  to: '2026-10-09',
  grouping: 'day',
  category: 'all',
  availableYears: [2025, 2026],
);

const _paidBucket = FarmerSalesBucket(orders: 1, sales: 40);
const _emptyBucket = FarmerSalesBucket(orders: 0, sales: 0);

const _paidSales = FarmerSalesReport(
  totals: FarmerSalesTotals(
    sales: 40,
    orders: 1,
    averageOrder: 40,
    tawadTotal: 0,
    averageTawad: 0,
  ),
  pie: FarmerSalesPie(slices: []),
  topCrops: [],
  perPeriod: [
    FarmerSalesPeriod(
      key: '2026-10-01',
      start: '2026-10-01',
      end: '2026-10-01',
      future: false,
      orders: 1,
      sales: 40,
    ),
  ],
  paymentSplit: FarmerPaymentSplit(online: _paidBucket, cash: _emptyBucket),
  sourceSplit: FarmerSourceSplit(app: _paidBucket, walkIn: _emptyBucket),
);

FarmerAnalytics _base({
  FarmerAnalyticsRange? range,
  FarmerSalesReport? sales,
  FarmerHarvestReport? harvest,
}) {
  return FarmerAnalytics(
    period: 'month',
    windowStart: range?.from ?? '2026-10-01',
    windowEnd: range?.to ?? '2026-10-09',
    summary: _zeroSummary,
    salesPerPeriod: const [],
    unitsPerCropType: const [],
    bestSelling: const [],
    walkInShare: _zeroShare,
    range: range,
    sales: sales,
    harvest: harvest,
  );
}

final _piePreview = _base(
  range: _monthRange,
  sales: FarmerSalesReport(
    totals: const FarmerSalesTotals(
      sales: 100,
      orders: 5,
      averageOrder: 20,
      tawadTotal: 0,
      averageTawad: 0,
    ),
    pie: const FarmerSalesPie(
      slices: [
        FarmerPieSlice(crop: 'Tomato', sales: 30, percent: 30),
        FarmerPieSlice(crop: 'Pechay', sales: 20, percent: 20),
        FarmerPieSlice(crop: 'Okra', sales: 15, percent: 15),
        FarmerPieSlice(crop: 'Eggplant', sales: 15, percent: 15),
        FarmerPieSlice(crop: 'Carrot', sales: 10, percent: 10),
      ],
      others: FarmerPieOthers(crops: 2, sales: 10, percent: 10),
    ),
    topCrops: const [
      FarmerTopCrop(crop: 'Tomato', unit: 'bundle', quantity: 12, sales: 30),
      FarmerTopCrop(crop: 'Mango', unit: 'kg', quantity: 2, sales: 10),
    ],
    perPeriod: const [
      FarmerSalesPeriod(
        key: '2026-10-01',
        start: '2026-10-01',
        end: '2026-10-01',
        future: false,
        orders: 5,
        sales: 100,
      ),
    ],
    paymentSplit: const FarmerPaymentSplit(online: _paidBucket, cash: _paidBucket),
    sourceSplit: const FarmerSourceSplit(app: _paidBucket, walkIn: _paidBucket),
  ),
);

final _futurePreview = _base(
  range: const FarmerAnalyticsRange(
    key: 'yearly',
    from: '2026-01-01',
    to: '2026-12-31',
    grouping: 'month',
    category: 'all',
    availableYears: [2026],
  ),
  sales: const FarmerSalesReport(
    totals: FarmerSalesTotals(
      sales: 40,
      orders: 1,
      averageOrder: 40,
      tawadTotal: 0,
      averageTawad: 0,
    ),
    pie: FarmerSalesPie(slices: []),
    topCrops: [],
    perPeriod: [
      FarmerSalesPeriod(
        key: '2026-11',
        start: '2026-11-01',
        end: '2026-11-30',
        future: true,
        orders: 0,
        sales: 0,
      ),
    ],
    paymentSplit: FarmerPaymentSplit(online: _paidBucket, cash: _emptyBucket),
    sourceSplit: FarmerSourceSplit(app: _paidBucket, walkIn: _emptyBucket),
    yearTotal: FarmerYearTotal(sales: 40, orders: 1, bestMonth: FarmerBestMonth(key: '2026-06', sales: 40)),
  ),
);

const _crop = FarmerHarvestCrop(
  crop: 'Pechay',
  unit: 'bundle',
  harvested: 10,
  rejected: 1,
  good: 9,
  rejectedByReason: {'pests': 1},
  sold: 4,
  waiting: 2,
  removed: 0,
  removedByReason: {
    'spoiled': 0,
    'damaged': 0,
    'sold_outside': 0,
    'correction': 0,
  },
  remaining: 3,
  potentialIncome: 12,
  actualIncome: 12,
);

FarmerAnalytics _harvestPreview(int estimated, {bool withCost = false}) {
  return _base(
    range: _monthRange,
    sales: _paidSales,
    harvest: FarmerHarvestReport(
      records: 3,
      estimatedRecords: estimated,
      unlinkedRecords: 0,
      crops: const [_crop],
      income: const FarmerHarvestIncome(potential: 12, actual: 12),
      cost: withCost
          ? const FarmerHarvestCost(
              recordsWithCost: 2,
              recordsWithoutCost: 1,
              costTotal: 20,
              potentialProfit: 10,
              actualProfit: -40,
            )
          : const FarmerHarvestCost(
              recordsWithCost: 0,
              recordsWithoutCost: 3,
            ),
    ),
  );
}

const _emptyBoth = FarmerAnalytics(
  period: 'month',
  windowStart: '2026-10-01',
  windowEnd: '2026-10-09',
  summary: _zeroSummary,
  salesPerPeriod: [],
  unitsPerCropType: [],
  bestSelling: [],
  walkInShare: _zeroShare,
  range: _monthRange,
  sales: FarmerSalesReport(
    totals: FarmerSalesTotals(
      sales: 0,
      orders: 0,
      averageOrder: 0,
      tawadTotal: 0,
      averageTawad: 0,
    ),
    pie: FarmerSalesPie(slices: []),
    topCrops: [],
    perPeriod: [],
    paymentSplit: FarmerPaymentSplit(online: _emptyBucket, cash: _emptyBucket),
    sourceSplit: FarmerSourceSplit(app: _emptyBucket, walkIn: _emptyBucket),
  ),
  harvest: FarmerHarvestReport(
    records: 0,
    estimatedRecords: 0,
    unlinkedRecords: 0,
    crops: [],
    income: FarmerHarvestIncome(potential: 0, actual: 0),
    cost: FarmerHarvestCost(recordsWithCost: 0, recordsWithoutCost: 0),
  ),
);

final _nullCostPreview = _base(
  range: _monthRange,
  sales: _paidSales,
  harvest: const FarmerHarvestReport(
    records: 2,
    estimatedRecords: 0,
    unlinkedRecords: 0,
    crops: [_crop],
    income: FarmerHarvestIncome(potential: 12, actual: 12),
    cost: FarmerHarvestCost(
      recordsWithCost: 2,
      recordsWithoutCost: 0,
      costTotal: null,
      potentialProfit: null,
      actualProfit: null,
    ),
  ),
);
