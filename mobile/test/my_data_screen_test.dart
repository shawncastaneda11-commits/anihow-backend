import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/profile/my_data_screen.dart';
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

const _buyer = UserAccount(
  id: 3,
  name: 'Ana Buyer',
  email: 'ana@example.com',
  roles: ['buyer'],
  phone: '09170001111',
  location: 'General Trias',
);

const _pending = AccountDeletionRequest(
  id: 1,
  status: 'pending',
);

void main() {
  testWidgets('My data shows edit fields and a locked email', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(home: const MyDataScreen(preview: MyDataPreview(user: _buyer))),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.myData), findsOneWidget);
    expect(find.text(s.emailNotEditable), findsOneWidget);
    expect(find.text('Ana Buyer'), findsOneWidget);
    expect(find.text('ana@example.com'), findsOneWidget);
    expect(find.text(s.saveProfile), findsOneWidget);
    expect(find.text(s.downloadMyData), findsOneWidget);
    expect(find.text(s.requestAccountDeletion), findsWidgets);
    expect(find.text(s.deletionKeptHint), findsOneWidget);
    expect(find.text(s.cancelDeletionRequest), findsNothing);
  });

  testWidgets('My data pending state shows cancel', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      _app(
        home: const MyDataScreen(
          preview: MyDataPreview(user: _buyer, deletion: _pending),
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.deletionRequested), findsOneWidget);
    expect(find.text(s.cancelDeletionRequest), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, s.requestAccountDeletion), findsNothing);
  });

  testWidgets('My data shows the open-orders error', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 900));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    const error =
        'You have open orders (placed, confirmed, or ready) that must be completed or cancelled before you can request account deletion.';

    await tester.pumpWidget(
      _app(
        home: const MyDataScreen(
          preview: MyDataPreview(user: _buyer, deletionError: error),
        ),
      ),
    );
    await tester.pump();

    expect(find.text(error), findsOneWidget);
    expect(find.text(AppStrings(false).requestAccountDeletion), findsWidgets);
  });
}
