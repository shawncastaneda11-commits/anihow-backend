import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/screens/misconfigured_build_screen.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('misconfigured screen shows English copy and the developer reason', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: const MisconfiguredBuildScreen(reason: 'API_BASE_URL is empty'),
      ),
    );
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.misconfiguredBuildTitle), findsOneWidget);
    expect(find.text(s.misconfiguredBuildBody), findsOneWidget);
    expect(find.text('API_BASE_URL is empty'), findsOneWidget);
  });

  testWidgets('misconfigured screen shows Filipino copy and the developer reason', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: const MisconfiguredBuildScreen(
          reason: 'REVERB_SCHEME must be wss',
          filipino: true,
        ),
      ),
    );
    await tester.pump();

    final s = AppStrings(true);
    expect(find.text(s.misconfiguredBuildTitle), findsOneWidget);
    expect(find.text(s.misconfiguredBuildBody), findsOneWidget);
    expect(find.text('REVERB_SCHEME must be wss'), findsOneWidget);
    expect(find.text(AppStrings(false).misconfiguredBuildTitle), findsNothing);
  });
}
