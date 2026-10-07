import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/screens/auth/forgot_password_screen.dart';
import 'package:anihow/screens/login_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _RecordingAuth extends AuthController {
  @override
  Future<bool> login(
    String email,
    String password, {
    bool remember = true,
  }) async {
    return false;
  }
}

class _RecordingApi extends ApiClient {
  _RecordingApi() : super(onUnauthorized: () {});

  int forgotCalls = 0;
  int resetCalls = 0;

  @override
  Future<void> forgotPassword(String email) async {
    forgotCalls += 1;
  }

  @override
  Future<void> resetPassword(
    String email,
    String code,
    String password,
    String passwordConfirmation,
  ) async {
    resetCalls += 1;
  }
}

Widget _loginApp() {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider<AuthController>.value(value: _RecordingAuth()),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: const LoginScreen()),
  );
}

void main() {
  testWidgets('forgot password button opens the reset screen', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(_loginApp());
    await tester.pump();

    final s = AppStrings(false);
    final button = find.widgetWithText(TextButton, s.forgotPassword);
    expect(button, findsOneWidget);
    expect(tester.getSize(find.byKey(const Key('forgot-password'))).height, 48);

    await tester.tap(button);
    await tester.pumpAndSettle();

    expect(find.byType(ForgotPasswordScreen), findsOneWidget);
    expect(find.widgetWithText(FilledButton, s.sendCode), findsOneWidget);
  });

  testWidgets('sending a code moves to the reset step', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _RecordingApi();
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: ForgotPasswordScreen(client: api),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await tester.enterText(find.byType(TextField), 'maria@example.com');
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, s.sendCode));
    await tester.pumpAndSettle();

    expect(api.forgotCalls, 1);
    expect(find.widgetWithText(FilledButton, s.resetPassword), findsOneWidget);
    expect(find.text('000000'), findsOneWidget);
  });

  testWidgets('mismatched passwords block submit', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _RecordingApi();
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: ForgotPasswordScreen(client: api),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    await tester.enterText(find.byType(TextField), 'maria@example.com');
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, s.sendCode));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, '123456');
    await tester.enterText(find.byType(TextField).at(1), 'new-password-123');
    await tester.enterText(find.byType(TextField).at(2), 'different-password');
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, s.resetPassword));
    await tester.pump();

    expect(api.resetCalls, 0);
    expect(find.text(s.passwordsDoNotMatch), findsOneWidget);
  });

  testWidgets('a successful reset shows the snackbar', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final api = _RecordingApi();
    final s = AppStrings(false);
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: Builder(
          builder: (context) => Scaffold(
            body: TextButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => ForgotPasswordScreen(client: api),
                  ),
                );
              },
              child: const Text('open'),
            ),
          ),
        ),
      ),
    );
    await tester.pump();
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'maria@example.com');
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, s.sendCode));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, '123456');
    await tester.enterText(find.byType(TextField).at(1), 'new-password-123');
    await tester.enterText(find.byType(TextField).at(2), 'new-password-123');
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, s.resetPassword));
    await tester.pumpAndSettle();

    expect(api.resetCalls, 1);
    expect(find.text(s.passwordResetDone), findsOneWidget);
    expect(find.byType(ForgotPasswordScreen), findsNothing);
  });
}
