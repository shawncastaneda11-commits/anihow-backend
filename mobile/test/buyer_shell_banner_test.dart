import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_shell.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/app_header.dart';
import 'package:anihow/widgets/unverified_email_banner.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

/// The banner used to be the first child of the Scaffold body, wrapped in
/// SafeArea. Scaffold (and edge-to-edge) already zero MediaQuery.padding.top
/// on that body, so SafeArea was a no-op and the banner painted at y=0 under
/// the status bar — above the page AppHeader / nested AppBar.
const _firstItemKey = Key('first-list-item');

Widget _shell({
  required AuthController auth,
  required int index,
  required List<Widget> pages,
  required double inset,
  required double textScale,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) {
        final preferences = PreferencesController();
        preferences.notificationsEnabled = false;
        return preferences;
      }),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      builder: (context, child) {
        final media = MediaQuery.of(context);
        return MediaQuery(
          data: media.copyWith(
            padding: EdgeInsets.only(top: inset),
            viewPadding: EdgeInsets.only(top: inset),
            textScaler: TextScaler.linear(textScale),
          ),
          child: child!,
        );
      },
      home: BuyerShell(
        preview: BuyerShellPreview(index: index, pages: pages),
      ),
    ),
  );
}

AuthController _unverifiedAuth() {
  final auth = AuthController();
  auth.user = const UserAccount(
    id: 1,
    name: 'Maria Buyer',
    email: 'maria@example.com',
    roles: ['buyer'],
  );
  auth.restoring = false;
  return auth;
}

Widget _marketplaceLikePage() {
  return Column(
    children: [
      const AppHeader(title: 'Market'),
      const UnverifiedEmailBanner(),
      Expanded(
        child: ListView(
          children: const [
            ListTile(key: _firstItemKey, title: Text('First produce')),
          ],
        ),
      ),
    ],
  );
}

Widget _listPage() {
  return ListView(
    children: const [
      ListTile(key: _firstItemKey, title: Text('First produce')),
    ],
  );
}

void main() {
  for (final inset in [24.0, 48.0]) {
    for (final textScale in [1.0, 2.0]) {
      testWidgets(
        'banner sits below status bar ($inset) and list at scale $textScale without AppBar',
        (tester) async {
          await tester.binding.setSurfaceSize(const Size(360, 800));
          addTearDown(() => tester.binding.setSurfaceSize(null));

          await tester.pumpWidget(
            _shell(
              auth: _unverifiedAuth(),
              index: 0,
              pages: [
                _marketplaceLikePage(),
                const SizedBox.shrink(),
                const SizedBox.shrink(),
                const SizedBox.shrink(),
              ],
              inset: inset,
              textScale: textScale,
            ),
          );
          await tester.pump();

          final s = AppStrings(false);
          expect(find.text(s.verifyBanner), findsOneWidget);

          final banner = tester.getRect(find.byKey(UnverifiedEmailBanner.bannerKey));
          final firstItem = tester.getRect(find.byKey(_firstItemKey));
          expect(banner.top, greaterThanOrEqualTo(inset));
          expect(banner.bottom, lessThanOrEqualTo(firstItem.top));
        },
      );

      testWidgets(
        'banner sits below status bar ($inset) and list at scale $textScale with AppBar',
        (tester) async {
          await tester.binding.setSurfaceSize(const Size(360, 800));
          addTearDown(() => tester.binding.setSurfaceSize(null));

          await tester.pumpWidget(
            _shell(
              auth: _unverifiedAuth(),
              index: 2,
              pages: [
                const SizedBox.shrink(),
                const SizedBox.shrink(),
                _listPage(),
                const SizedBox.shrink(),
              ],
              inset: inset,
              textScale: textScale,
            ),
          );
          await tester.pump();

          final banner = tester.getRect(find.byKey(UnverifiedEmailBanner.bannerKey));
          final firstItem = tester.getRect(find.byKey(_firstItemKey));
          expect(banner.top, greaterThanOrEqualTo(inset));
          expect(banner.bottom, lessThanOrEqualTo(firstItem.top));
        },
      );
    }
  }
}
