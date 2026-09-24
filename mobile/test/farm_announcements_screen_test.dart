import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farm_announcements_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

  testWidgets('announcement banner can be dismissed', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    var dismissed = false;
    final announcement = const FarmAnnouncement(
      id: 3,
      title: 'Harvest day Saturday',
      body: 'Bring crates by 6am.',
      isPinned: true,
    );

    await tester.pumpWidget(
      _app(
        home: Scaffold(
          body: FarmAnnouncementBanner(
            announcement: announcement,
            onDismiss: () => dismissed = true,
          ),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsOneWidget);

    await tester.drag(find.byType(Dismissible), const Offset(-500, 0));
    await tester.pumpAndSettle();

    expect(dismissed, isTrue);
  });

  testWidgets('dismissed banner stays hidden after rebuild and a newer notice still shows', (tester) async {
    SharedPreferences.setMockInitialValues({});
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
          body: FarmerAnnouncementHomeBanner(userId: 7, announcements: [older]),
        ),
      ),
    );
    await tester.pumpAndSettle();

    final s = AppStrings(false);
    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsOneWidget);

    await tester.drag(find.byType(Dismissible), const Offset(-500, 0));
    await tester.pumpAndSettle();

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(userId: 7, announcements: [older]),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('${s.announcementPinned}: Harvest day Saturday'), findsNothing);

    await tester.pumpWidget(
      _app(
        home: const Scaffold(
          body: FarmerAnnouncementHomeBanner(userId: 7, announcements: [newer, older]),
        ),
      ),
    );
    await tester.pumpAndSettle();

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
