import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/screens/farmer/stock_history_screen.dart';
import 'package:anihow/screens/farmer/stock_sheets.dart';
import 'package:anihow/screens/notifications/notifications_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _ScriptedApi extends ApiClient {
  _ScriptedApi() : notes = const [], super(onUnauthorized: () {});

  Map<String, dynamic>? lastUpdate;
  final List<AppNotification> notes;

  _ScriptedApi.withNotes(this.notes) : super(onUnauthorized: () {});

  @override
  Future<ListingItem> updateListing(
    int id,
    Map<String, dynamic> body, {
    String? imagePath,
    bool confirmCancelReservations = false,
  }) async {
    lastUpdate = body;
    return const ListingItem(
      id: 9,
      title: 'Pechay',
      pricePerUnit: '40',
      quantityAvailable: '50.00',
    );
  }

  @override
  Future<List<AppNotification>> notifications() async => notes;

  @override
  Future<void> markNotificationRead(int id) async {}

  @override
  Future<StockHistoryPage> stockHistory(int id, {int page = 1}) async {
    return StockHistoryPage(
      currentPage: 1,
      lastPage: 1,
      summary: const StockSummary(
        trackedSince: '2026-10-09T04:20:52+08:00',
        harvested: '193.00',
        good: '193.00',
        sold: '0.00',
        removed: '6.00',
        available: '187.00',
        held: '0.00',
        hasEstimated: false,
        recordsWithoutCost: 1,
        costTotal: '900.00',
        unit: 'kg',
      ),
      events: const [
        StockEvent(
          type: 'harvest',
          id: 1,
          kind: 'opening',
          harvestedOn: '2026-10-09',
          quantityHarvested: '193.00',
          quantityGood: '193.00',
          quantityRejected: '0.00',
        ),
        StockEvent(
          type: 'removal',
          id: 2,
          quantity: '6.00',
          reason: 'spoiled',
          note: 'Soft spots',
          createdAt: '2026-10-09T08:00:00+08:00',
        ),
      ],
    );
  }
}

Widget _app(Widget home, {ApiClient? api, UserAccount? user}) {
  final auth = AuthController(api: api)..restoring = false;
  auth.user = user;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  test('a cost breakdown becomes one multipart field per category', () {
    final fields = listingMultipartFields({
      'quantity_harvested': '50',
      'cost_breakdown': {'seeds': '150', 'fertilizer': '', 'labor': '25.50'},
    });

    expect(fields['cost_breakdown[seeds]'], '150');
    expect(fields['cost_breakdown[labor]'], '25.50');
    expect(fields.containsKey('cost_breakdown[fertilizer]'), isFalse);
    expect(fields.containsKey('cost_breakdown'), isFalse);
    expect(fields.values.any((value) => value.contains('{')), isFalse);
  });

  testWidgets('good to sell updates as harvested and rejected change', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(
      id: 7,
      name: 'Kamatis',
      labelEn: 'Tomato',
      unit: 'kg',
    );
    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value([crop]))),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey('harvest-quantity')),
      '50',
    );
    await tester.enterText(
      find.byKey(const ValueKey('rejected-quantity')),
      '6',
    );
    await tester.pump();

    expect(find.text('Good to sell: 44 kg'), findsOneWidget);
  });

  testWidgets('breaking costs down fills the total', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value(const []))),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const ValueKey('break-down-costs')));
    await tester.tap(find.byKey(const ValueKey('break-down-costs')));
    await tester.pumpAndSettle();

    await tester.enterText(find.byKey(const ValueKey('cost-seeds')), '100');
    await tester.enterText(find.byKey(const ValueKey('cost-fertilizer')), '50');
    await tester.pump();

    final cost = tester.widget<TextField>(
      find.byKey(const ValueKey('production-cost')),
    );
    expect(cost.controller?.text, '150.00');
  });

  testWidgets('a value-added crop uses made and defective labels', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(
      id: 8,
      name: 'Atsara',
      labelEn: 'Pickles',
      unit: 'bottle',
      productCategory: 'value_added',
    );
    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value([crop]))),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Pickles').last);
    await tester.pumpAndSettle();

    expect(find.text('Date made'), findsOneWidget);
    expect(find.text('Quantity made'), findsOneWidget);
    expect(find.text('Defective'), findsOneWidget);

    await tester.enterText(
      find.byKey(const ValueKey('rejected-quantity')),
      '1',
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const ValueKey('rejection-reason')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('rejection-reason')));
    await tester.pumpAndSettle();

    expect(find.text('Defective'), findsWidgets);
    expect(find.text('Packaging damaged'), findsOneWidget);
    expect(find.text('Pests'), findsNothing);
  });

  testWidgets('editing a listing does not send quantity_available', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(id: 3, name: 'Pechay', unit: 'kg');
    const listing = ListingItem(
      id: 9,
      title: 'Pechay',
      pricePerUnit: '40',
      quantityAvailable: '50.00',
      unit: 'kg',
      category: crop,
    );
    final api = _ScriptedApi();
    await tester.pumpWidget(
      _app(
        ListingFormScreen(listing: listing, cropTypes: Future.value([crop])),
        api: api,
      ),
    );
    await tester.pumpAndSettle();

    final quantity = tester.widget<TextField>(
      find.byKey(const ValueKey('listing-quantity')),
    );
    expect(quantity.readOnly, isTrue);
    expect(find.byKey(const ValueKey('add-stock')), findsOneWidget);
    expect(find.byKey(const ValueKey('remove-stock')), findsOneWidget);

    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(api.lastUpdate, isNotNull);
    expect(api.lastUpdate!.containsKey('quantity_available'), isFalse);
  });

  testWidgets('remove stock shows how much is held by orders', (tester) async {
    const listing = ListingItem(
      id: 4,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '10',
      sellableQuantity: '8',
      unit: 'kg',
    );
    await tester.pumpWidget(
      _app(
        Builder(
          builder: (context) => TextButton(
            onPressed: () => showRemoveStockSheet(context, listing),
            child: const Text('Open remove'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open remove'));
    await tester.pumpAndSettle();

    expect(find.text('Up to 8 kg (2 held by orders)'), findsOneWidget);
  });

  testWidgets('actual harvest asks to confirm a shortfall', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const listing = ListingItem(
      id: 5,
      title: 'Kalabasa',
      pricePerUnit: '40',
      quantityAvailable: '12',
      unit: 'kg',
      needsActualHarvest: true,
      reservedQuantity: 6,
    );
    await tester.pumpWidget(
      _app(
        Builder(
          builder: (context) => TextButton(
            onPressed: () => showAddStockSheet(context, listing, actual: true),
            child: const Text('Open actual'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open actual'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byKey(const ValueKey('harvest-quantity')), '4');
    await tester.pump();
    await tester.tap(
      find.widgetWithText(FilledButton, 'Record actual harvest'),
    );
    await tester.pumpAndSettle();

    expect(find.byKey(const Key('actual-harvest-confirm')), findsOneWidget);
    expect(find.textContaining('6 kg is already reserved'), findsWidgets);
  });

  testWidgets('a new harvest date starts today and cannot read as not set', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value(const []))),
    );
    await tester.pumpAndSettle();

    final now = DateTime.now();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];
    final today = '${months[now.month - 1]} ${now.day}';
    await tester.ensureVisible(find.byKey(const ValueKey('harvest-date')));
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('harvest-date')),
        matching: find.text(today),
      ),
      findsOneWidget,
    );
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('harvest-date')),
        matching: find.text('Not set'),
      ),
      findsNothing,
    );
  });

  testWidgets('good to sell gains the unit only after one is chosen', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const crop = CategoryItem(
      id: 7,
      name: 'Kamatis',
      labelEn: 'Tomato',
      unit: 'kg',
    );
    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value([crop]))),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(find.byKey(const ValueKey('good-to-sell')));
    expect(find.text('Good to sell: 0'), findsOneWidget);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Tomato').last);
    await tester.pumpAndSettle();

    expect(find.text('Good to sell: 0 kg'), findsOneWidget);
  });

  testWidgets('a missing rejection reason is hinted and then flagged', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const listing = ListingItem(
      id: 6,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '10',
      unit: 'kg',
    );
    await tester.pumpWidget(
      _app(
        Builder(
          builder: (context) => TextButton(
            onPressed: () => showAddStockSheet(context, listing),
            child: const Text('Open add'),
          ),
        ),
      ),
    );
    await tester.tap(find.text('Open add'));
    await tester.pumpAndSettle();

    await tester.enterText(
      find.byKey(const ValueKey('rejected-quantity')),
      '2',
    );
    await tester.pump();
    expect(find.byKey(const ValueKey('rejection-reason-hint')), findsOneWidget);
    expect(find.text('Choose a reason'), findsOneWidget);
    expect(find.byKey(const ValueKey('rejection-reason-error')), findsNothing);

    await tester.tap(find.widgetWithText(FilledButton, 'Add stock'));
    await tester.pump();

    expect(
      find.byKey(const ValueKey('rejection-reason-error')),
      findsOneWidget,
    );
  });

  testWidgets('stock history formats dates, units, tiles, and cards', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(const StockHistoryScreen(listingId: 9), api: _ScriptedApi()),
    );
    await tester.pumpAndSettle();

    final tracked = DateTime.parse('2026-10-09T04:20:52+08:00').toLocal();
    expect(
      find.text(const AppStrings(false).trackedSince(tracked)),
      findsOneWidget,
    );
    expect(find.text('193 kg'), findsWidgets);
    expect(find.byKey(const ValueKey('stock-stat-harvested')), findsOneWidget);
    expect(find.byKey(const ValueKey('stock-stat-good')), findsOneWidget);
    expect(find.byKey(const ValueKey('stock-stat-sold')), findsOneWidget);
    expect(find.byKey(const ValueKey('stock-stat-left')), findsOneWidget);
    expect(find.byKey(const ValueKey('stock-stat-removed')), findsOneWidget);
    expect(find.byKey(const ValueKey('stock-stat-cost')), findsOneWidget);
    expect(find.text('Starting stock 193 kg'), findsOneWidget);
    expect(find.text('Opening stock'), findsOneWidget);
    expect(find.text('Removed 6 kg'), findsOneWidget);
    expect(find.byKey(const ValueKey('removal-2')), findsOneWidget);
  });

  testWidgets('harvest and expired-stock notifications open stock history', (
    tester,
  ) async {
    final api = _ScriptedApi.withNotes(const [
      AppNotification(
        id: 1,
        title: 'server title',
        body: 'Record the harvest',
        type: 'harvest_reminder',
        relatedId: 9,
        relatedType: 'listing',
      ),
      AppNotification(
        id: 2,
        title: 'server title',
        body: 'Stock remains',
        type: 'expired_stock_left',
        relatedId: 9,
        relatedType: 'listing',
      ),
    ]);
    await tester.pumpWidget(
      _app(
        const NotificationsScreen(),
        api: api,
        user: const UserAccount(
          id: 1,
          name: 'Ana',
          email: 'ana@example.com',
          roles: ['farmer_seller'],
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Record the actual harvest'));
    await tester.pumpAndSettle();
    expect(find.text('Stock history'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.text('Stock left after the listing ended'));
    await tester.pumpAndSettle();
    expect(find.text('Stock history'), findsOneWidget);
  });
}
