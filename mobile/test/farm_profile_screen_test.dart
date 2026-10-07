import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farm/farm_profile_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/farm_map_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app({required Widget home}) {
  return ChangeNotifierProvider(
    create: (_) => PreferencesController(),
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

void main() {
  testWidgets('farm screen renders a cover photo when one is present', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FarmProfileScreen(
          farmId: 1,
          preview: FarmProfile(
            id: 1,
            name: 'Manggahan Farm',
            barangay: 'Manggahan',
            municipality: 'General Trias',
            description: 'Morning harvest.',
            pickupPoint: 'Barangay hall',
            coverPhotoUrl: 'https://example.com/cover.jpg',
            contactPerson: 'Aling Nena',
            contactNumber: '09171230001',
            storefronts: [FarmStorefront(id: 4, shopName: 'Nena Stall')],
            announcements: [
              FarmAnnouncement(
                id: 9,
                title: 'Harvest day Saturday',
                body: 'Bring crates by 6am.',
                isPinned: true,
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Manggahan Farm'), findsOneWidget);
    expect(find.text('Manggahan, General Trias'), findsOneWidget);
    expect(find.text('Morning harvest.'), findsOneWidget);
    expect(find.text('Barangay hall'), findsOneWidget);
    expect(find.text('Nena Stall'), findsOneWidget);
    expect(find.text('Harvest day Saturday'), findsOneWidget);
    expect(find.text('Bring crates by 6am.'), findsOneWidget);
    expect(find.byKey(const Key('farm-cover')), findsOneWidget);
    expect(find.byKey(const Key('farm-cover-fallback')), findsNothing);
    expect(find.byType(BackButton), findsNothing);
  });

  testWidgets(
    'farm profile shows a back button when opened from another screen',
    (tester) async {
      await tester.binding.setSurfaceSize(const Size(360, 800));
      addTearDown(() => tester.binding.setSurfaceSize(null));

      const farm = FarmProfile(
        id: 1,
        name: 'Manggahan Farm',
        storefronts: [FarmStorefront(id: 4, shopName: 'Nena Stall')],
      );

      await tester.pumpWidget(
        _app(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) =>
                          const FarmProfileScreen(farmId: 1, preview: farm),
                    ),
                  );
                },
                child: const Text('Open farm'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('Open farm'));
      await tester.pumpAndSettle();

      expect(find.byType(BackButton), findsOneWidget);
      expect(find.text('Nena Stall'), findsOneWidget);

      await tester.tap(find.byType(BackButton));
      await tester.pumpAndSettle();
      expect(find.text('Open farm'), findsOneWidget);
    },
  );

  testWidgets('farm screen falls back when there is no cover photo', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FarmProfileScreen(
          farmId: 2,
          preview: FarmProfile(id: 2, name: 'San Francisco Farm'),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text('San Francisco Farm'), findsOneWidget);
    expect(find.text(s.noFarmPhotos), findsOneWidget);
    expect(find.text(s.noFarmStorefronts), findsOneWidget);
    expect(find.byKey(const Key('farm-cover-fallback')), findsOneWidget);
    expect(find.byKey(const Key('farm-cover')), findsNothing);
    expect(find.byKey(const Key('farm-map')), findsNothing);
    expect(find.text(s.openInGoogleMaps), findsNothing);
  });

  testWidgets('a farm pin shows the map and a Google Maps directions link', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.library == 'image resource service') {
        return;
      }
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);

    await tester.pumpWidget(
      _app(
        home: const FarmProfileScreen(
          farmId: 3,
          preview: FarmProfile(
            id: 3,
            name: 'Manggahan Farm',
            pickupPoint: 'Barangay hall',
            latitude: 14.2,
            longitude: 120.9,
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.scrollUntilVisible(find.byKey(const Key('farm-map')), 200);

    expect(find.byKey(const Key('farm-map')), findsOneWidget);
    expect(find.text('© OpenStreetMap contributors'), findsOneWidget);
    expect(find.text('Barangay hall'), findsOneWidget);
    final card = tester.widget<FarmMapCard>(find.byType(FarmMapCard));
    expect(
      card.directionsUrl,
      'https://www.google.com/maps/dir/?api=1&destination=14.2,120.9',
    );
    expect(find.byKey(const Key('open-in-google-maps')), findsOneWidget);
  });
}
