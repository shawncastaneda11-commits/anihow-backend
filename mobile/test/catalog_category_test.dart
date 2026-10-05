import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/buyer/marketplace_screen.dart';
import 'package:anihow/screens/farmer/listing_form_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/produce_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _RecordingApi extends ApiClient {
  _RecordingApi() : super(onUnauthorized: () {});

  String? category;

  @override
  Future<List<ListingItem>> marketplace({
    String? search,
    int? cropTypeId,
    String? sort,
    String? category,
    double? nearLat,
    double? nearLng,
    String? growingMethod,
    int? page,
  }) async {
    this.category = category;
    return const [];
  }

  @override
  Future<List<CategoryItem>> cropTypes() async => const [];

  @override
  Future<PagedBuyerAnnouncements> buyerAnnouncements({
    bool following = false,
    int? farmId,
    int page = 1,
  }) async {
    return const PagedBuyerAnnouncements(
      items: [],
      currentPage: 1,
      lastPage: 1,
    );
  }
}

Widget _app(Widget home, {AuthController? auth}) {
  final controller = auth ?? (AuthController()..restoring = false);
  controller.restoring = false;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: controller),
      ChangeNotifierProvider(create: (_) => CartController(controller)),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: Scaffold(body: home),
    ),
  );
}

UserAccount _seller({required bool certified}) {
  return UserAccount(
    id: 4,
    name: 'Nena',
    email: 'nena@example.com',
    roles: const ['farmer_seller'],
    farmIsOrganicCertified: certified,
  );
}

void main() {
  testWidgets('category chips send the marketplace category', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _RecordingApi();
    final auth = AuthController(api: api)..restoring = false;

    await tester.pumpWidget(_app(const MarketplaceScreen(), auth: auth));
    await tester.pumpAndSettle();

    expect(api.category, isNull);

    await tester.tap(find.byKey(const ValueKey('category-value_added')));
    await tester.pumpAndSettle();

    expect(api.category, 'value_added');

    await tester.tap(find.byKey(const ValueKey('category-fresh_produce')));
    await tester.pumpAndSettle();

    expect(api.category, 'fresh_produce');

    await tester.tap(find.byKey(const ValueKey('category-all')));
    await tester.pumpAndSettle();

    expect(api.category, isNull);
  });

  testWidgets('badges render for certified and naturally grown listings', (
    tester,
  ) async {
    const certified = ListingItem(
      id: 1,
      title: 'Kamatis',
      pricePerUnit: '30',
      quantityAvailable: '8',
      organicBadge: 'certified',
      organicCertifier: 'OCCP',
    );
    const natural = ListingItem(
      id: 2,
      title: 'Sitaw',
      pricePerUnit: '30',
      quantityAvailable: '8',
      organicBadge: 'naturally_grown',
    );
    const plain = ListingItem(
      id: 3,
      title: 'Pechay',
      pricePerUnit: '30',
      quantityAvailable: '8',
    );

    await tester.pumpWidget(
      _app(
        const Column(
          children: [
            ProduceCard(listing: certified, showSeller: true),
            ProduceCard(listing: natural, showSeller: true),
            ProduceCard(listing: plain, showSeller: true),
          ],
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Certified Organic'), findsOneWidget);
    expect(find.text('Naturally grown (self-declared)'), findsOneWidget);
    expect(find.byKey(const ValueKey('growing-badge')), findsNWidgets(2));
  });

  testWidgets('the detail screen names the organic certifier', (tester) async {
    const certified = ListingItem(
      id: 1,
      title: 'Kamatis',
      pricePerUnit: '30',
      quantityAvailable: '8',
      organicBadge: 'certified',
      organicCertifier: 'OCCP',
    );

    await tester.pumpWidget(
      _app(const ListingDetailScreen(listingId: 1, preview: certified)),
    );
    await tester.pumpAndSettle();
    await tester.drag(find.byType(ListView), const Offset(0, -700));
    await tester.pumpAndSettle();

    expect(find.text('Certified Organic'), findsOneWidget);
    expect(find.text('Certified by OCCP'), findsOneWidget);
  });

  testWidgets('the certified option is hidden for an uncertified farm', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController()
      ..restoring = false
      ..user = _seller(certified: false);

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value(const [])), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(
      find.byKey(const ValueKey('listing-growing-method')),
    );
    await tester.tap(find.byKey(const ValueKey('listing-growing-method')));
    await tester.pumpAndSettle();

    expect(find.text('Not stated'), findsWidgets);
    expect(find.text('Naturally grown'), findsWidgets);
    expect(find.text('Certified Organic'), findsNothing);
  });

  testWidgets('a certified farm can choose certified organic', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController()
      ..restoring = false
      ..user = _seller(certified: true);

    await tester.pumpWidget(
      _app(ListingFormScreen(cropTypes: Future.value(const [])), auth: auth),
    );
    await tester.pumpAndSettle();

    await tester.ensureVisible(
      find.byKey(const ValueKey('listing-growing-method')),
    );
    await tester.tap(find.byKey(const ValueKey('listing-growing-method')));
    await tester.pumpAndSettle();

    expect(find.text('Certified Organic'), findsWidgets);
  });
}
