import 'package:anihow/main.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/admin_gate_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

UserAccount _account({
  required List<String> roles,
  bool mustChangePassword = false,
}) {
  return UserAccount(
    id: 7,
    name: 'Nena',
    email: 'nena@example.com',
    roles: roles,
    mustChangePassword: mustChangePassword,
  );
}

class _QuietApi extends ApiClient {
  _QuietApi(this.account) : super(onUnauthorized: () {});

  UserAccount account;

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {}

  @override
  Future<UserAccount> currentUser() async => account;

  @override
  Future<void> logout({String? deviceToken}) async {}
}

Widget _gate(AuthController auth, {PreferencesController? preferences}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider<AuthController>.value(value: auth),
      ChangeNotifierProvider(
        create: (_) => preferences ?? PreferencesController(),
      ),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: const RoleGate()),
  );
}

void main() {
  test('password_change_required is the forced-change code', () {
    expect(
      isPasswordChangeRequired(403, {'code': 'password_change_required'}),
      isTrue,
    );
    expect(isPasswordChangeRequired(403, {'code': 'other'}), isFalse);
    expect(
      isPasswordChangeRequired(401, {'code': 'password_change_required'}),
      isFalse,
    );
  });

  testWidgets('a flagged farmer sees the forced password screen', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController();
    auth.restoring = false;
    auth.user = _account(
      roles: const ['farmer_seller'],
      mustChangePassword: true,
    );

    await tester.pumpWidget(_gate(auth));
    await tester.pump();

    expect(find.text('Set your own password'), findsOneWidget);
    expect(
      find.text(
        'You signed in with a temporary password from the administrator. Choose a new one to continue.',
      ),
      findsOneWidget,
    );
    expect(find.text('Sign out'), findsOneWidget);
    expect(find.byType(BackButton), findsNothing);
  });

  testWidgets('the forced screen uses Filipino copy', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController();
    auth.restoring = false;
    auth.user = _account(roles: const ['buyer'], mustChangePassword: true);
    final preferences = PreferencesController()
      ..language = CropLanguage.filipino;

    await tester.pumpWidget(_gate(auth, preferences: preferences));
    await tester.pump();

    expect(find.text('Itakda ang sarili mong password'), findsOneWidget);
    expect(find.text('Mag-sign out'), findsOneWidget);
  });

  testWidgets('a password_change_required response opens the forced screen', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController();
    auth.restoring = false;
    auth.user = _account(roles: const ['super_admin']);

    await tester.pumpWidget(_gate(auth));
    await tester.pump();

    expect(find.byType(AdminGateScreen), findsOneWidget);

    auth.requirePasswordChange();
    await tester.pump();

    expect(find.text('Set your own password'), findsOneWidget);
    expect(find.byType(AdminGateScreen), findsNothing);
  });

  testWidgets('saving a new password refreshes the user and shows the shell', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final cleared = _account(roles: const ['super_admin']);
    final api = _QuietApi(cleared);
    final auth = AuthController(api: api);
    auth.restoring = false;
    auth.user = _account(
      roles: const ['super_admin'],
      mustChangePassword: true,
    );

    await tester.pumpWidget(_gate(auth));
    await tester.pump();

    expect(find.text('Set your own password'), findsOneWidget);

    await tester.enterText(find.byType(TextField).at(0), 'temp-password');
    await tester.enterText(find.byType(TextField).at(1), 'new-password-1');
    await tester.enterText(find.byType(TextField).at(2), 'new-password-1');
    await tester.tap(find.widgetWithText(FilledButton, 'Save password'));
    await tester.pump();
    await tester.pump();

    expect(auth.user?.mustChangePassword, isFalse);
    expect(find.byType(AdminGateScreen), findsOneWidget);
    expect(find.text('Set your own password'), findsNothing);
  });
}
