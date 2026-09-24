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
}
