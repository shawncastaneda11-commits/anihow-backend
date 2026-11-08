import 'package:anihow/screens/app_gate.dart';
import 'package:anihow/state/app_availability.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _app() => MultiProvider(
  providers: [
    ChangeNotifierProvider(create: (_) => PreferencesController()),
    ChangeNotifierProvider(create: (_) => AuthController()),
  ],
  child: MaterialApp(
    theme: AniHowTheme.light(),
    home: const AppGate(checkOnStart: false, child: Text('home')),
  ),
);

void main() {
  tearDown(() => AppAvailability.instance.apply());

  test('versions compare part by part', () {
    expect(AppAvailability.isOlder('1.0.2', '1.0.3'), isTrue);
    expect(AppAvailability.isOlder('1.0.9', '1.0.10'), isTrue);
    expect(AppAvailability.isOlder('1.0.3', '1.0.3'), isFalse);
    expect(AppAvailability.isOlder('1.0.3', null), isFalse);
    expect(AppAvailability.isOlder('1.0.3', 'soon'), isFalse);
  });

  testWidgets('the app opens normally when nothing is set', (tester) async {
    await tester.pumpWidget(_app());
    await tester.pump();

    expect(find.text('home'), findsOneWidget);
  });

  testWidgets('maintenance shows the message instead of the app', (tester) async {
    AppAvailability.instance.apply(maintenanceMessage: 'Back at 5 PM.');
    await tester.pumpWidget(_app());
    await tester.pump();

    expect(find.text('Back at 5 PM.'), findsOneWidget);
    expect(find.text('home'), findsNothing);
  });

  testWidgets('a newer minimum version asks for an update', (tester) async {
    AppAvailability.instance.apply(minVersion: '99.0.0');
    await tester.pumpWidget(_app());
    await tester.pump();

    expect(find.text('Update AniHow'), findsOneWidget);
    expect(find.text('home'), findsNothing);
  });

  testWidgets('the update screen opens ICTD\'s download link', (tester) async {
    AppAvailability.instance.apply(
      minVersion: '99.0.0',
      downloadUrl: 'https://example.com/anihow.apk',
    );
    await tester.pumpWidget(_app());
    await tester.pump();

    expect(find.byKey(const ValueKey('app-gate-download')), findsOneWidget);
    expect(find.byKey(const ValueKey('app-gate-ask')), findsNothing);
  });

  testWidgets('without a download link the update screen says who to ask', (tester) async {
    AppAvailability.instance.apply(
      minVersion: '99.0.0',
      downloadUrl: 'javascript:alert(1)',
    );
    await tester.pumpWidget(_app());
    await tester.pump();

    expect(find.byKey(const ValueKey('app-gate-download')), findsNothing);
    expect(find.byKey(const ValueKey('app-gate-ask')), findsOneWidget);
  });
}
