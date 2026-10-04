import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farmer_shell.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _shell(AuthController auth) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.dark(),
      home: FarmerShell(
        preview: FarmerShellPreview(
          pages: const [
            SizedBox.shrink(),
            SizedBox.shrink(),
            SizedBox.shrink(),
            SizedBox.shrink(),
            Text('Seller chats'),
          ],
        ),
      ),
    ),
  );
}

void main() {
  testWidgets('seller chats live in the bottom bar, not a floating button', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 915));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final auth = AuthController()
      ..user = const UserAccount(
        id: 2,
        name: 'Nena',
        email: 'nena@example.com',
        roles: ['farmer-seller'],
        shopName: 'Aling Nena Produce',
      )
      ..restoring = false;

    await tester.pumpWidget(_shell(auth));
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byType(FloatingActionButton), findsNothing);
    expect(find.text(s.chats), findsOneWidget);
    expect(find.text('Seller chats'), findsNothing);

    await tester.tap(find.text(s.chats));
    await tester.pump();

    expect(find.text(s.chats), findsWidgets);
    expect(find.text('Seller chats'), findsOneWidget);
    expect(find.text(s.myListings), findsNothing);
  });
}
