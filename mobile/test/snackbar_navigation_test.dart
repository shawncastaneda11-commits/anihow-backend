import 'package:anihow/navigation/route_observer.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('a persistent snackbar is dismissed when a new route is pushed', (tester) async {
    var logoutTapped = false;

    await tester.pumpWidget(
      MaterialApp(
        scaffoldMessengerKey: anihowScaffoldMessengerKey,
        navigatorObservers: [anihowRouteObserver],
        home: Builder(
          builder: (context) {
            return Scaffold(
              body: TextButton(
                onPressed: () {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: const Text('Added to cart.'),
                      action: SnackBarAction(label: 'View cart', onPressed: () {}),
                    ),
                  );
                },
                child: const Text('Add to cart'),
              ),
            );
          },
        ),
      ),
    );

    await tester.tap(find.text('Add to cart'));
    await tester.pump();
    expect(find.text('Added to cart.'), findsOneWidget);

    final navigator = tester.state<NavigatorState>(find.byType(Navigator));
    navigator.push(
      MaterialPageRoute<void>(
        builder: (_) => Scaffold(
          body: TextButton(
            onPressed: () => logoutTapped = true,
            child: const Text('Log out'),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Added to cart.'), findsNothing);
    expect(find.text('View cart'), findsNothing);
    await tester.tap(find.text('Log out'));
    expect(logoutTapped, isTrue);
  });

  testWidgets('a persistent snackbar is dismissed when its route is popped', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        scaffoldMessengerKey: anihowScaffoldMessengerKey,
        navigatorObservers: [anihowRouteObserver],
        home: Scaffold(
          body: Builder(
            builder: (context) {
              return TextButton(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (context) => Scaffold(
                        body: TextButton(
                          onPressed: () {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: const Text('Added to cart.'),
                                action: SnackBarAction(
                                  label: 'View cart',
                                  onPressed: () {},
                                ),
                              ),
                            );
                          },
                          child: const Text('Add to cart'),
                        ),
                      ),
                    ),
                  );
                },
                child: const Text('Open listing'),
              );
            },
          ),
        ),
      ),
    );

    await tester.tap(find.text('Open listing'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Add to cart'));
    await tester.pump();
    expect(find.text('Added to cart.'), findsOneWidget);

    tester.state<NavigatorState>(find.byType(Navigator)).pop();
    await tester.pumpAndSettle();

    expect(find.text('Added to cart.'), findsNothing);
    expect(find.text('Open listing'), findsOneWidget);
  });
}
