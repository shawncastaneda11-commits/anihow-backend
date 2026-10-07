import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/screens/farmer/listings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _ScriptedApi extends ApiClient {
  _ScriptedApi(this.listing) : super(onUnauthorized: () {});

  ListingItem listing;
  int toggles = 0;
  int updates = 0;
  int deletes = 0;
  bool? lastToggleConfirm;
  bool? lastUpdateConfirm;
  bool? lastDeleteConfirm;
  bool conflictOnToggle = false;
  bool conflictOnUpdate = false;
  bool conflictOnDelete = false;

  @override
  Future<List<ListingItem>> farmerListings() async => [listing];

  @override
  Future<List<FarmAnnouncement>> farmerAnnouncements() async => [];

  @override
  Future<ListingItem> toggleListingActive(
    int id, {
    required bool isActive,
    bool confirmCancelReservations = false,
  }) async {
    toggles++;
    lastToggleConfirm = confirmCancelReservations;
    if (conflictOnToggle && !confirmCancelReservations) {
      throw ApiException(
        'conflict',
        statusCode: 409,
        body: {'active_reservations_count': 3, 'reserved_quantity': 9},
      );
    }
    listing = listing.copyWith(isActive: isActive);
    return listing;
  }

  @override
  Future<ListingItem> updateListing(
    int id,
    Map<String, dynamic> body, {
    String? imagePath,
    bool confirmCancelReservations = false,
  }) async {
    updates++;
    lastUpdateConfirm = confirmCancelReservations;
    if (conflictOnUpdate && !confirmCancelReservations) {
      throw ApiException(
        'conflict',
        statusCode: 409,
        body: {'active_reservations_count': 5, 'reserved_quantity': 8.5},
      );
    }
    return listing.copyWith(isActive: body['is_active'] == true);
  }

  @override
  Future<void> deleteListing(
    int id, {
    bool confirmCancelReservations = false,
  }) async {
    deletes++;
    lastDeleteConfirm = confirmCancelReservations;
    if (conflictOnDelete && !confirmCancelReservations) {
      throw ApiException(
        'conflict',
        statusCode: 409,
        body: {'active_reservations_count': 2, 'reserved_quantity': 6},
      );
    }
  }
}

Widget _app(Widget home, AuthController auth) {
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

Future<void> _openForm(
  WidgetTester tester,
  Widget page,
  AuthController auth,
) async {
  final key = GlobalKey<NavigatorState>();
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
      ],
      child: MaterialApp(
        navigatorKey: key,
        theme: AniHowTheme.light(),
        home: const Scaffold(body: SizedBox.shrink()),
      ),
    ),
  );
  key.currentState!.push(MaterialPageRoute<void>(builder: (_) => page));
  await tester.pumpAndSettle();
}

ListingItem _listing({int? count, double? quantity}) {
  return ListingItem(
    id: 4,
    title: 'Pechay',
    pricePerUnit: '30',
    quantityAvailable: '10',
    unit: 'kg',
    category: const CategoryItem(id: 1, name: 'Pechay'),
    activeReservationsCount: count,
    reservedQuantity: quantity,
  );
}

void main() {
  testWidgets('the active toggle asks before cancelling reservations', (
    tester,
  ) async {
    final api = _ScriptedApi(_listing(count: 2, quantity: 4));
    final auth = AuthController(api: api)..restoring = false;
    await tester.pumpWidget(_app(const FarmerListingsScreen(), auth));
    await tester.pumpAndSettle();

    expect(find.text('Cancel 2 reservation(s)?'), findsNothing);
    await tester.tap(find.text('Active').first);
    await tester.pumpAndSettle();

    expect(find.text('Cancel 2 reservation(s)?'), findsOneWidget);
    expect(
      find.textContaining('4 kg is reserved by 2 buyer(s)'),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('keep-listing')));
    await tester.pumpAndSettle();
    expect(api.toggles, 0);

    await tester.tap(find.text('Active').first);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-reservations')));
    await tester.pumpAndSettle();
    expect(api.toggles, 1);
    expect(api.lastToggleConfirm, isTrue);
  });

  testWidgets('a quiet listing toggles without a reservation dialog', (
    tester,
  ) async {
    final api = _ScriptedApi(_listing());
    final auth = AuthController(api: api)..restoring = false;
    await tester.pumpWidget(_app(const FarmerListingsScreen(), auth));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Active').first);
    await tester.pumpAndSettle();

    expect(find.textContaining('reservation(s)?'), findsNothing);
    expect(api.toggles, 1);
    expect(api.lastToggleConfirm, isFalse);
  });

  testWidgets(
    'a 409 from the toggle opens the dialog with the server numbers',
    (tester) async {
      final api = _ScriptedApi(_listing())..conflictOnToggle = true;
      final auth = AuthController(api: api)..restoring = false;
      await tester.pumpWidget(_app(const FarmerListingsScreen(), auth));
      await tester.pumpAndSettle();

      await tester.tap(find.text('Active').first);
      await tester.pumpAndSettle();

      expect(find.text('Cancel 3 reservation(s)?'), findsOneWidget);
      expect(
        find.textContaining('9 kg is reserved by 3 buyer(s)'),
        findsOneWidget,
      );
      expect(api.toggles, 1);
      expect(api.lastToggleConfirm, isFalse);

      await tester.tap(find.byKey(const Key('keep-listing')));
      await tester.pumpAndSettle();
      expect(api.toggles, 1);

      await tester.tap(find.text('Active').first);
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('confirm-cancel-reservations')));
      await tester.pumpAndSettle();
      expect(api.toggles, 3);
      expect(api.lastToggleConfirm, isTrue);
    },
  );

  testWidgets('delete and the active switch confirm before they cancel', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 1200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _ScriptedApi(_listing(count: 2, quantity: 4));
    final auth = AuthController(api: api)..restoring = false;
    await _openForm(
      tester,
      ListingFormScreen(
        listing: api.listing,
        cropTypes: Future.value(const [CategoryItem(id: 1, name: 'Pechay')]),
      ),
      auth,
    );

    await tester.tap(find.byTooltip('Delete listing'));
    await tester.pumpAndSettle();
    expect(find.text('Cancel 2 reservation(s)?'), findsOneWidget);
    expect(find.text('Delete and cancel'), findsOneWidget);
    await tester.tap(find.byKey(const Key('keep-listing')));
    await tester.pumpAndSettle();
    expect(api.deletes, 0);

    await tester.tap(find.byTooltip('Delete listing'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('confirm-cancel-reservations')));
    await tester.pumpAndSettle();
    expect(api.deletes, 1);
    expect(api.lastDeleteConfirm, isTrue);
  });

  testWidgets(
    'turning the listing switch off confirms, and a 409 uses server numbers',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(400, 2200));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      final api = _ScriptedApi(_listing(count: 1, quantity: 2));
      final auth = AuthController(api: api)..restoring = false;
      await _openForm(
        tester,
        ListingFormScreen(
          listing: api.listing,
          cropTypes: Future.value(const [CategoryItem(id: 1, name: 'Pechay')]),
        ),
        auth,
      );

      await tester.tap(find.byKey(const Key('listing-active-switch')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Save listing'));
      await tester.tap(find.text('Save listing'));
      await tester.pumpAndSettle();

      expect(find.text('Cancel 1 reservation(s)?'), findsOneWidget);
      await tester.tap(find.byKey(const Key('keep-listing')));
      await tester.pumpAndSettle();
      expect(api.updates, 0);

      await tester.ensureVisible(find.text('Save listing'));
      await tester.tap(find.text('Save listing'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('confirm-cancel-reservations')));
      await tester.pumpAndSettle();
      expect(api.updates, 1);
      expect(api.lastUpdateConfirm, isTrue);
    },
  );

  testWidgets('a 409 from save opens the dialog with the server numbers', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 2200));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final conflictApi = _ScriptedApi(_listing())..conflictOnUpdate = true;
    final conflictAuth = AuthController(api: conflictApi)..restoring = false;
    await _openForm(
      tester,
      ListingFormScreen(
        listing: conflictApi.listing,
        cropTypes: Future.value(const [CategoryItem(id: 1, name: 'Pechay')]),
      ),
      conflictAuth,
    );
    await tester.tap(find.byKey(const Key('listing-active-switch')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Save listing'));
    await tester.tap(find.text('Save listing'));
    await tester.pumpAndSettle();

    expect(find.text('Cancel 5 reservation(s)?'), findsOneWidget);
    expect(
      find.textContaining('8.50 kg is reserved by 5 buyer(s)'),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const Key('keep-listing')));
    await tester.pumpAndSettle();
    expect(conflictApi.updates, 1);
    expect(conflictApi.lastUpdateConfirm, isFalse);
  });
}
