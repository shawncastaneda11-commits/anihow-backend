import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/announcements_feed_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/farm_updates_strip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app(Widget home) {
  return ChangeNotifierProvider(
    create: (_) {
      final preferences = PreferencesController();
      preferences.notificationsEnabled = false;
      return preferences;
    },
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

BuyerFarmAnnouncement _post(int id, String title, {String? body}) {
  return BuyerFarmAnnouncement(
    id: id,
    title: title,
    body: body ?? 'Fresh from the field.',
    farmId: id,
    farmName: 'Farm $id',
    publishedAt: '2026-10-03T08:00:00+08:00',
  );
}

void main() {
  testWidgets(
    'the updates strip shows at most three posts and hides when empty',
    (tester) async {
      await tester.pumpWidget(
        _app(
          Scaffold(
            body: FarmUpdatesStrip(
              items: [
                _post(1, 'One'),
                _post(2, 'Two'),
                _post(3, 'Three'),
                _post(4, 'Four'),
              ],
            ),
          ),
        ),
      );
      await tester.pump();

      expect(find.text('Updates from farms'), findsOneWidget);
      expect(find.text('One'), findsOneWidget);
      expect(find.text('Two'), findsOneWidget);
      expect(find.text('Three'), findsOneWidget);
      expect(find.text('Four'), findsNothing);

      await tester.pumpWidget(
        _app(const Scaffold(body: FarmUpdatesStrip(items: []))),
      );
      await tester.pump();

      expect(find.text('Updates from farms'), findsNothing);
    },
  );

  testWidgets('the feed renders cards and the empty state', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        AnnouncementsFeedScreen(
          loadPage: ({required bool following, required int page}) async {
            return PagedBuyerAnnouncements(
              items: [
                _post(
                  8,
                  'Harvest morning',
                  body: 'Come by the stall. ${'Basket ' * 40}',
                ),
              ],
              currentPage: 1,
              lastPage: 1,
            );
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Harvest morning'), findsOneWidget);
    expect(find.text('Farm 8'), findsOneWidget);
    expect(find.text('Read more'), findsOneWidget);
  });

  testWidgets('the feed shows the empty state', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        AnnouncementsFeedScreen(
          loadPage: ({required bool following, required int page}) async {
            return const PagedBuyerAnnouncements(
              items: [],
              currentPage: 1,
              lastPage: 1,
            );
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('No updates from farms yet.'), findsOneWidget);
  });

  testWidgets('the farms I follow toggle sends following=1', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final calls = <bool>[];
    await tester.pumpWidget(
      _app(
        AnnouncementsFeedScreen(
          loadPage: ({required bool following, required int page}) async {
            calls.add(following);
            return const PagedBuyerAnnouncements(
              items: [],
              currentPage: 1,
              lastPage: 1,
            );
          },
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(calls, [false]);
    expect(
      buyerAnnouncementQuery(
        following: false,
        page: 1,
      ).containsKey('following'),
      isFalse,
    );

    await tester.tap(find.text('Farms I follow'));
    await tester.pumpAndSettle();

    expect(calls, [false, true]);
    expect(buyerAnnouncementQuery(following: true, page: 1)['following'], 1);
  });
}
