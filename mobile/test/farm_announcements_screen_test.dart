import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farm_announcements_screen.dart';
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
  setUp(DismissedAnnouncementStore.reset);

  testWidgets('announcements screen lists pinned and regular notices', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FarmAnnouncementsScreen(
          preview: [
            FarmAnnouncement(
              id: 1,
              title: 'Harvest day Saturday',
              body: 'Bring crates by 6am.',
              isPinned: true,
            ),
            FarmAnnouncement(
              id: 2,
              title: 'Pickup point moved',
              body: 'Barangay hall this week.',
            ),
          ],
        ),
      ),
    );
    await tester.pump();

    expect(find.text('Harvest day Saturday'), findsOneWidget);
    expect(find.text('Bring crates by 6am.'), findsOneWidget);
    expect(find.text('Pickup point moved'), findsOneWidget);
    expect(find.byKey(const Key('farm-announcement-1')), findsOneWidget);
  });

  testWidgets('new announcement pops as a toast then disappears', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const announcement = FarmAnnouncement(
      id: 3,
      title: 'Harvest day Saturday',
      body: 'Bring crates by 6am.',
      isPinned: true,
    );

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(
            userId: 7,
            announcements: [announcement],
            toastDuration: Duration(milliseconds: 50),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsOneWidget);
    expect(find.text('Bring crates by 6am.'), findsNothing);
    expect(find.byKey(const ValueKey('farm-announcement-toast')), findsOneWidget);

    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();

    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsNothing);
  });

  testWidgets('announcement toast stays about six seconds', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const announcement = FarmAnnouncement(
      id: 4,
      title: 'Pickup point moved',
      body: 'Barangay hall this week.',
    );

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(
            userId: 7,
            announcements: [announcement],
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Pickup point moved'), findsOneWidget);
    expect(find.text('Barangay hall this week.'), findsNothing);

    await tester.pump(const Duration(seconds: 5));
    expect(find.text('Pickup point moved'), findsOneWidget);

    await tester.pump(const Duration(seconds: 2));
    await tester.pumpAndSettle();
    expect(find.text('Pickup point moved'), findsNothing);
  });

  testWidgets('seen announcement does not toast again, but a newer one does', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const older = FarmAnnouncement(
      id: 1,
      title: 'Harvest day Saturday',
      body: 'Bring crates by 6am.',
      isPinned: true,
    );
    const newer = FarmAnnouncement(
      id: 2,
      title: 'Pickup point moved',
      body: 'Barangay hall this week.',
    );

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(
            userId: 7,
            announcements: [older],
            toastDuration: Duration(milliseconds: 50),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
    await tester.pumpAndSettle();

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(
            userId: 7,
            announcements: [older],
            toastDuration: Duration(milliseconds: 50),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsNothing);

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(
            userId: 7,
            announcements: [newer, older],
            toastDuration: Duration(milliseconds: 50),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.pump();

    expect(find.text('Pickup point moved'), findsOneWidget);
    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsNothing);
  });

  testWidgets('list with a missing highlight id still opens', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const FarmAnnouncementsScreen(
          highlightId: 99,
          preview: [
            FarmAnnouncement(
              id: 1,
              title: 'Pickup point moved',
              body: 'Barangay hall this week.',
            ),
          ],
        ),
      ),
    );
    await tester.pump();

    expect(tester.takeException(), isNull);
    expect(find.text('Pickup point moved'), findsOneWidget);
  });
}
