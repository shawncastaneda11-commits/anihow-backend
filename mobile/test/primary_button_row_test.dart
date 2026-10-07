import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/primary_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('compact PrimaryButton lays out beside a field in a Row', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: Scaffold(
          body: Row(
            children: [
              const Expanded(child: SizedBox(height: 52)),
              PrimaryButton(
                label: 'Ask',
                expand: false,
                onPressed: () {},
              ),
            ],
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Ask'), findsOneWidget);
    expect(tester.getSize(find.byType(PrimaryButton)).width, lessThan(360));
  });
}
