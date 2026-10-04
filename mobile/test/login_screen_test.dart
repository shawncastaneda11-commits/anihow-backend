import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/screens/login_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _RecordingAuth extends AuthController {
  bool? sentRemember;

  @override
  Future<bool> login(String email, String password, {bool remember = true}) async {
    sentRemember = remember;
    return false;
  }
}

Widget _app(_RecordingAuth auth) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(create: (_) => PreferencesController()),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: const LoginScreen(),
    ),
  );
}

void main() {
  testWidgets('remember me is checked by default', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(_app(_RecordingAuth()));
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.rememberMe), findsOneWidget);
    expect(tester.widget<CheckboxListTile>(find.byType(CheckboxListTile)).value, isTrue);
    expect(tester.getSize(find.byType(CheckboxListTile)).height, 48);
  });

  testWidgets('unchecking remember me sends remember false', (tester) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = _RecordingAuth();
    await tester.pumpWidget(_app(auth));
    await tester.pump();

    await tester.tap(find.byType(CheckboxListTile));
    await tester.pump();
    await tester.tap(find.widgetWithText(FilledButton, AppStrings(false).signIn));
    await tester.pump();

    expect(auth.sentRemember, isFalse);
  });
}
