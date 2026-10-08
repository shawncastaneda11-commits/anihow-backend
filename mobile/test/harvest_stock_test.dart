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
        trackedSince: '2026-10-01',
        harvested: '20.00',
        good: '20.00',
        sold: '0.00',
        removed: '0.00',
        available: '20.00',
        held: '0.00',
        hasEstimated: true,
        recordsWithoutCost: 2,
      ),
      events: const [
        StockEvent(
          type: 'harvest',
          id: 1,
          kind: 'opening',
          quantityGood: '20.00',
        ),
        StockEvent(
          type: 'harvest',
          id: 2,
          kind: 'estimated',
          quantityGood: '5.00',
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

  testWidgets('stock history shows opening, estimated, and no cost', (
    tester,
  ) async {
    await tester.pumpWidget(
      _app(const StockHistoryScreen(listingId: 9), api: _ScriptedApi()),
    );
    await tester.pumpAndSettle();

    expect(find.text('No cost recorded'), findsWidgets);
    expect(find.text('Opening stock'), findsOneWidget);
    expect(find.text('Estimated'), findsOneWidget);
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
