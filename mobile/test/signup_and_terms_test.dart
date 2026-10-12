import 'package:anihow/main.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/auth/terms_screen.dart';
import 'package:anihow/screens/login_screen.dart';
import 'package:anihow/screens/register_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/password_rules.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

const _buyer = UserAccount(
  id: 7,
  name: 'Maria Santos',
  email: 'maria@example.com',
  roles: ['buyer'],
  emailVerifiedAt: '2026-10-01',
  needsTerms: true,
);

class _AuthApi extends ApiClient {
  _AuthApi() : super(onUnauthorized: () {});

  String? savedEmail;
  int saveCalls = 0;
  int acceptCalls = 0;
  ({String name, String email, String password, String confirm})? registered;

  @override
  Future<String?> readRememberedEmail() async => savedEmail;

  @override
  Future<void> saveRememberedEmail(String? email) async {
    saveCalls += 1;
    savedEmail = email;
  }

  @override
  Future<({UserAccount user, String token})> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    return (user: _buyer.withNeedsTerms(false), token: 't');
  }

  @override
  Future<({UserAccount user, String token, String? verificationCode})>
  registerBuyer({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    registered = (
      name: name,
      email: email,
      password: password,
      confirm: passwordConfirmation,
    );
    return (user: _buyer.withNeedsTerms(false), token: 't', verificationCode: null);
  }

  @override
  Future<UserAccount> acceptTerms() async {
    acceptCalls += 1;
    return _buyer.withNeedsTerms(false);
  }
}

Widget _app(AuthController auth, Widget home) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

AuthController _auth(_AuthApi api, {UserAccount? user}) {
  return AuthController(api: api)
    ..restoring = false
    ..user = user;
}

void main() {
  test('password rules match the server: length, cases, number, symbol', () {
    expect(PasswordRules.check('Gu#2026'), [false, true, true, true, true]);
    expect(PasswordRules.allMet('gulay#2026'), isFalse);
    expect(PasswordRules.allMet('GULAY#2026'), isFalse);
    expect(PasswordRules.allMet('Gulay2026'), isFalse);
    expect(PasswordRules.allMet('Gulay#2026'), isTrue);
  });

  testWidgets('sign-up stays locked until the password, match and terms are done', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _AuthApi();
    await tester.pumpWidget(
      _app(_auth(api), const Scaffold(body: RegisterScreen())),
    );

    FilledButton submit() => tester.widget<FilledButton>(
      find.descendant(
        of: find.byKey(const ValueKey('register-submit')),
        matching: find.byType(FilledButton),
      ),
    );
    expect(submit().onPressed, isNull);

    await tester.enterText(find.byKey(const ValueKey('register-name')), 'Maria');
    await tester.enterText(
      find.byKey(const ValueKey('register-email')),
      'maria@example.com',
    );
    final fields = find.descendant(
      of: find.byKey(const ValueKey('register-password')),
      matching: find.byType(TextField),
    );
    await tester.enterText(fields, 'gulay2026');
    await tester.pump();
    expect(find.byKey(const ValueKey('password-rule-1-unmet')), findsOneWidget);
    expect(find.byKey(const ValueKey('password-rule-2-met')), findsOneWidget);
    expect(find.byKey(const ValueKey('password-rule-4-unmet')), findsOneWidget);
    expect(find.textContaining('a stronger password'), findsOneWidget);

    await tester.enterText(fields, 'Gulay#2026');
    await tester.enterText(
      find.descendant(
        of: find.byKey(const ValueKey('register-confirm')),
        matching: find.byType(TextField),
      ),
      'Gulay#2025',
    );
    await tester.pump();
    expect(find.text('Strong'), findsOneWidget);
    expect(find.text('Passwords do not match yet'), findsOneWidget);
    expect(submit().onPressed, isNull);

    await tester.enterText(
      find.descendant(
        of: find.byKey(const ValueKey('register-confirm')),
        matching: find.byType(TextField),
      ),
      'Gulay#2026',
    );
    await tester.pump();
    expect(find.text('Passwords match'), findsOneWidget);
    expect(find.textContaining('agreeing to the terms'), findsOneWidget);
    expect(submit().onPressed, isNull);

    await tester.tap(find.byKey(const ValueKey('register-terms')));
    await tester.pump();
    expect(submit().onPressed, isNotNull);

    await tester.tap(find.byKey(const ValueKey('register-submit')));
    await tester.pumpAndSettle();
    expect(api.registered?.email, 'maria@example.com');
    expect(api.registered?.password, 'Gulay#2026');
  });

  testWidgets('the Privacy Policy link opens the policy tab', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 1600));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.pumpWidget(
      _app(_auth(_AuthApi()), const Scaffold(body: RegisterScreen())),
    );

    await tester.tapOnText(find.textRange.ofSubstring('Privacy Policy'));
    await tester.pumpAndSettle();

    expect(find.byType(TermsScreen), findsOneWidget);
    expect(find.textContaining('What we collect'), findsOneWidget);
    expect(find.textContaining('Near me'), findsOneWidget);
  });

  testWidgets('an app user who has not accepted sees the terms first', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _AuthApi();
    final auth = _auth(api, user: _buyer);
    await tester.pumpWidget(_app(auth, const RoleGate()));
    await tester.pump();
    expect(find.byType(TermsGateScreen), findsOneWidget);

    await tester.pumpWidget(_app(auth, const TermsGateScreen()));
    await tester.pump();
    FilledButton accept() => tester.widget<FilledButton>(
      find.descendant(
        of: find.byKey(const ValueKey('terms-gate-accept')),
        matching: find.byType(FilledButton),
      ),
    );
    expect(accept().onPressed, isNull);

    await tester.tap(find.byKey(const ValueKey('terms-gate-agree')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('terms-gate-accept')));
    await tester.pump();

    expect(api.acceptCalls, 1);
    expect(auth.user?.needsTerms, isFalse);
  });

  testWidgets('Remember me fills in the last email and only keeps it when on', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _AuthApi()..savedEmail = 'maria@example.com';
    final auth = _auth(api);
    await tester.pumpWidget(_app(auth, const LoginScreen()));
    await tester.pump();

    expect(find.text('maria@example.com'), findsOneWidget);

    await auth.login('maria@example.com', 'Gulay#2026', remember: false);
    expect(api.savedEmail, isNull);

    await auth.login('maria@example.com', 'Gulay#2026');
    expect(api.savedEmail, 'maria@example.com');
  });
}
