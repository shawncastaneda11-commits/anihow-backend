import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farm/farm_profile_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
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

void main() {
  testWidgets('farm screen renders a cover photo when one is present', (tester) async {
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
            storefronts: [
              FarmStorefront(id: 4, shopName: 'Nena Stall'),
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
    expect(find.byKey(const Key('farm-cover')), findsOneWidget);
    expect(find.byKey(const Key('farm-cover-fallback')), findsNothing);
  });

  testWidgets('farm screen falls back when there is no cover photo', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FarmProfileScreen(
          farmId: 2,
          preview: FarmProfile(
            id: 2,
            name: 'San Francisco Farm',
          ),
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
  });
}
