import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/auth/seller_info_screen.dart';
import 'package:anihow/screens/profile/help_screen.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/screens/profile/settings_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/state/theme_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _HelpApi extends ApiClient {
  _HelpApi({this.fail = false}) : super(onUnauthorized: () {});

  final bool fail;

  @override
  Future<SellerHelp> sellerHelp() async {
    if (fail) {
      throw ApiException('offline');
    }
    return const SellerHelp(
      office: 'LPU ICTD',
      email: 'desk@example.com',
      temporaryPasswordDays: 7,
      farms: [],
    );
  }

  @override
  Future<Map<String, bool>> pushPreferences() async => {};
}

UserAccount _user(List<String> roles) {
  return UserAccount(id: 1, name: 'Rosa', email: 'rosa@example.com', roles: roles);
}

Widget _app({
  required Widget home,
  required AuthController auth,
  PreferencesController? prefs,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider.value(value: prefs ?? PreferencesController()),
      ChangeNotifierProvider(create: (_) => ThemeController()),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

Future<void> _pumpHelp(
  WidgetTester tester, {
  required List<String> roles,
  bool fail = false,
  CropLanguage? language,
}) async {
  await tester.binding.setSurfaceSize(const Size(430, 900));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  final auth = AuthController(api: _HelpApi(fail: fail))
    ..restoring = false
    ..user = _user(roles);
  final prefs = PreferencesController();
  if (language != null) {
    prefs.language = language;
  }
  await tester.pumpWidget(_app(home: const HelpScreen(), auth: auth, prefs: prefs));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('a farmer sees only the seller guide', (tester) async {
    await _pumpHelp(tester, roles: const ['farmer_seller']);

    expect(find.text('For sellers'), findsOneWidget);
    expect(find.text('Listings and photos'), findsOneWidget);
    expect(find.text('For buyers'), findsNothing);
    expect(find.text('Finding produce'), findsNothing);
    expect(find.byType(TabBar), findsNothing);
  });

  testWidgets('a buyer sees only the buyer guide', (tester) async {
    await _pumpHelp(tester, roles: const ['buyer']);

    expect(find.text('For buyers'), findsOneWidget);
    expect(find.text('Finding produce'), findsOneWidget);
    expect(find.text('For sellers'), findsNothing);
    expect(find.text('Listings and photos'), findsNothing);
    expect(find.byType(TabBar), findsNothing);
  });

  testWidgets('only one topic is open at a time', (tester) async {
    await _pumpHelp(tester, roles: const ['buyer']);

    await tester.tap(find.text('Finding produce'));
    await tester.pump();
    expect(find.textContaining('Search produce'), findsOneWidget);

    await tester.tap(find.text('Ordering and pick-up'));
    await tester.pump();
    expect(find.textContaining('Search produce'), findsNothing);
    expect(find.textContaining('Add to cart'), findsOneWidget);
  });

  testWidgets('the footer hides contact details when help fails', (tester) async {
    await _pumpHelp(tester, roles: const ['buyer'], fail: true);

    expect(find.text('Ask the FAQ bot.'), findsOneWidget);
    expect(find.text('Ask the FAQ bot'), findsOneWidget);
    expect(find.text('desk@example.com'), findsNothing);
    expect(find.text('Email LPU ICTD'), findsNothing);
  });

  testWidgets('Filipino renders the buyer topic', (tester) async {
    await _pumpHelp(
      tester,
      roles: const ['buyer'],
      language: CropLanguage.filipino,
    );

    expect(find.text('Paghanap ng ani'), findsOneWidget);
    expect(find.text('Para sa mga buyer'), findsOneWidget);
    expect(find.text(AppStrings(true).stillStuck), findsOneWidget);
  });

  testWidgets('a buyer profile row opens the seller screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final auth = AuthController(api: _HelpApi())
      ..restoring = false
      ..user = _user(const ['buyer']);

    await tester.pumpWidget(_app(home: const ProfileScreen(), auth: auth));
    await tester.pump();
    await tester.scrollUntilVisible(
      find.text('Want to sell on AniHow?'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Want to sell on AniHow?'));
    await tester.pumpAndSettle();
    expect(find.byType(SellerInfoScreen), findsOneWidget);
  });

  testWidgets('buyer settings rows open the seller and help screens', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(430, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final auth = AuthController(api: _HelpApi())
      ..restoring = false
      ..user = _user(const ['buyer']);

    await tester.pumpWidget(_app(home: const SettingsScreen(), auth: auth));
    await tester.pump();
    expect(find.byKey(const Key('help-how-it-works')), findsOneWidget);
    await tester.tap(find.text('Want to be a seller?'));
    await tester.pumpAndSettle();
    expect(find.byType(SellerInfoScreen), findsOneWidget);
  });

  testWidgets('how AniHow works opens the help screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final auth = AuthController(api: _HelpApi())
      ..restoring = false
      ..user = _user(const ['buyer']);

    await tester.pumpWidget(_app(home: const SettingsScreen(), auth: auth));
    await tester.pump();
    await tester.tap(find.byKey(const Key('help-how-it-works')));
    await tester.pumpAndSettle();
    expect(find.byType(HelpScreen), findsOneWidget);
  });

  testWidgets('a farmer does not see the seller settings row', (tester) async {
    await tester.binding.setSurfaceSize(const Size(430, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final auth = AuthController(api: _HelpApi())
      ..restoring = false
      ..user = _user(const ['farmer_seller']);

    await tester.pumpWidget(_app(home: const SettingsScreen(), auth: auth));
    await tester.pump();

    expect(find.text('Want to be a seller?'), findsNothing);
    expect(find.byKey(const Key('help-how-it-works')), findsOneWidget);
    expect(find.text('FAQ'), findsOneWidget);
  });
}
