import 'dart:io';

import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farmer_shell.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _shell(
  AuthController auth, {
  CropLanguage language = CropLanguage.english,
  double textScale = 1,
}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) {
          final preferences = PreferencesController();
          preferences.notificationsEnabled = false;
          preferences.language = language;
          return preferences;
        },
      ),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.dark(),
      builder: (context, child) => MediaQuery(
        data: MediaQuery.of(context).copyWith(textScaler: TextScaler.linear(textScale)),
        child: child!,
      ),
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
        roles: ['farmer_seller'],
        shopName: 'Aling Nena Produce',
        farmName: 'Aling Nena Produce',
      )
      ..restoring = false;

    await tester.pumpWidget(_shell(auth));
    await tester.pump();

    final s = AppStrings(false);
    expect(find.byType(FloatingActionButton), findsNothing);
    expect(find.byIcon(Icons.help_outline), findsNothing);
    expect(find.text(s.chats), findsOneWidget);
    expect(find.text('Seller chats'), findsNothing);

    await tester.tap(find.text(s.chats));
    await tester.pump();

    expect(find.text(s.chats), findsWidgets);
    expect(find.text('Seller chats'), findsOneWidget);
    expect(find.text(s.myListings), findsNothing);
  });

  testWidgets('Filipino bottom-bar labels stay on one line on a narrow phone with large text', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(360, 780));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.runAsync(_loadRoboto);

    final auth = AuthController()
      ..user = const UserAccount(
        id: 2,
        name: 'Nena',
        email: 'nena@example.com',
        roles: ['farmer_seller'],
        shopName: 'Aling Nena Produce',
        farmName: 'Aling Nena Produce',
      )
      ..restoring = false;

    await tester.pumpWidget(_shell(auth, language: CropLanguage.filipino, textScale: 1.5));
    await tester.pump();

    final s = AppStrings(true);
    final labels = [s.navListings, s.navOrders, s.navMySales, s.navCropCare, s.navChats];
    final heights = [
      for (final label in labels) tester.getSize(find.text(label).last).height,
    ];

    expect(heights.toSet(), hasLength(1), reason: 'labels $labels had heights $heights');
  });
}

/// Test text is drawn as squares unless a real font is loaded, which would make
/// every label look too wide. Load the Roboto the app uses from the Flutter SDK.
Future<void> _loadRoboto() async {
  final root = Platform.environment['FLUTTER_ROOT'];
  final folder = root == null ? null : Directory('$root/bin/cache/artifacts/material_fonts');
  if (folder == null || !folder.existsSync()) {
    return;
  }
  final loader = FontLoader('Roboto');
  for (final file in folder.listSync().whereType<File>()) {
    final name = file.uri.pathSegments.last.toLowerCase();
    if (name == 'roboto-regular.ttf' || name == 'roboto-medium.ttf' || name == 'roboto-bold.ttf') {
      loader.addFont(file.readAsBytes().then(ByteData.sublistView));
    }
  }
  await loader.load();
}
