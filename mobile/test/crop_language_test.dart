import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/profile/settings_screen.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/state/theme_controller.dart';
import 'package:anihow/support/crop_language.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

void main() {
  const crop = CategoryItem(
    id: 1,
    name: 'tomato',
    labelEn: 'Tomato',
    labelFil: 'Kamatis',
  );

  test('English shows the English crop label', () {
    expect(crop.labelFor(CropLanguage.english), 'Tomato');
  });

  test('Filipino shows the Filipino crop label', () {
    expect(crop.labelFor(CropLanguage.filipino), 'Kamatis');
  });

  test('saved bilingual preference becomes Filipino', () {
    expect(PreferencesController.languageFromStored('bilingual'), CropLanguage.filipino);
    expect(PreferencesController.languageFromStored('filipino'), CropLanguage.filipino);
    expect(PreferencesController.languageFromStored('english'), CropLanguage.english);
    expect(PreferencesController.languageFromStored(null), CropLanguage.english);
  });

  test('Filipino strings switch Settings chrome', () {
    const english = AppStrings(false);
    const filipino = AppStrings(true);

    expect(english.settings, 'Settings');
    expect(filipino.settings, 'Mga setting');
    expect(filipino.faq, 'Mga tanong');
    expect(filipino.unverified, 'Hindi pa');
    expect(filipino.logOut, 'Mag-log out');
  });

  testWidgets('Settings has English and Filipino only, and Filipino copy', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final prefs = PreferencesController()..language = CropLanguage.filipino;
    final theme = ThemeController();
    final auth = AuthController()..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider.value(value: prefs),
          ChangeNotifierProvider.value(value: theme),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const SettingsScreen(),
        ),
      ),
    );

    expect(find.text('Mga setting'), findsOneWidget);
    expect(find.text('Mga tanong'), findsOneWidget);
    expect(find.text('Hindi pa'), findsOneWidget);
    expect(find.text('Bilingual'), findsNothing);
    expect(find.text('English'), findsOneWidget);
    expect(find.text('Filipino'), findsOneWidget);
  });
}
