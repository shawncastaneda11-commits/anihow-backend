import 'package:anihow/models/models.dart';
import 'package:anihow/screens/farmer/farmer_orders_screen.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Future<List<String?>> _openDialog(WidgetTester tester) async {
  final results = <String?>[];
  await tester.pumpWidget(
    ChangeNotifierProvider(
      create: (_) => PreferencesController(),
      child: MaterialApp(
        home: Scaffold(
          body: Builder(
            builder: (context) {
              return TextButton(
                onPressed: () async => results.add(
                  await askAmountReceived(
                    context,
                    const OrderRecord(
                      id: 1,
                      status: 'ready',
                      total: '100',
                      items: [],
                    ),
                  ),
                ),
                child: const Text('Complete'),
              );
            },
          ),
        ),
      ),
    ),
  );
  await tester.tap(find.text('Complete'));
  await tester.pumpAndSettle();
  return results;
}

void main() {
  testWidgets('cash below the order total stays open with a red note', (
    tester,
  ) async {
    final results = await _openDialog(tester);

    await tester.enterText(find.byType(TextField), '90');
    await tester.tap(find.text('Record'));
    await tester.pump();

    expect(find.text('This is less than the order total of ₱100.00.'), findsOneWidget);
    expect(results, isEmpty);

    await tester.enterText(find.byType(TextField), '');
    await tester.tap(find.text('Record'));
    await tester.pump();
    expect(find.text('Enter the cash amount received.'), findsOneWidget);

    await tester.enterText(find.byType(TextField), '100');
    await tester.tap(find.text('Record'));
    await tester.pumpAndSettle();
    expect(results, ['100']);
  });

  testWidgets('closing the cash-received dialog does not use a disposed controller', (
    tester,
  ) async {
    FlutterError? flutterError;
    final previousOnError = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.exception is FlutterError) {
        flutterError = details.exception as FlutterError;
      }
      previousOnError?.call(details);
    };
    addTearDown(() => FlutterError.onError = previousOnError);

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => PreferencesController(),
        child: MaterialApp(
          home: Scaffold(
            body: Builder(
              builder: (context) {
                return TextButton(
                  onPressed: () => askAmountReceived(
                    context,
                    const OrderRecord(
                      id: 1,
                      status: 'ready',
                      total: '100',
                      items: [],
                    ),
                  ),
                  child: const Text('Complete'),
                );
              },
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('Complete'));
    await tester.pumpAndSettle();
    expect(find.text('Cash received'), findsOneWidget);

    await tester.tap(find.text('Record'));
    await tester.pump();
    await tester.pumpAndSettle();

    expect(flutterError, isNull);
    expect(find.text('Cash received'), findsNothing);
  });
}
