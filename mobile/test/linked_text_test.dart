import 'package:anihow/widgets/linked_text.dart';
import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

Map<String, TapGestureRecognizer> _links(WidgetTester tester) {
  final text = tester.widget<RichText>(find.byType(RichText).first).text;
  final links = <String, TapGestureRecognizer>{};
  text.visitChildren((span) {
    if (span is TextSpan) {
      final recognizer = span.recognizer;
      if (recognizer is TapGestureRecognizer) {
        links[span.text!] = recognizer;
      }
    }
    return true;
  });
  return links;
}

void main() {
  late List<Uri> opened;
  late Future<bool> Function(Uri uri) original;

  setUp(() {
    opened = [];
    original = launchTextLink;
    launchTextLink = (uri) async {
      opened.add(uri);
      return true;
    };
  });

  tearDown(() => launchTextLink = original);

  testWidgets('web links open in the browser and trailing punctuation stays text', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: LinkedText('Visit www.truofa.ph. Or https://facebook.com/truofa, thanks!'),
        ),
      ),
    );

    final links = _links(tester);
    expect(links.keys, ['www.truofa.ph', 'https://facebook.com/truofa']);
    expect(
      tester.widget<RichText>(find.byType(RichText).first).text.toPlainText(),
      'Visit www.truofa.ph. Or https://facebook.com/truofa, thanks!',
    );

    links['www.truofa.ph']!.onTap!();
    links['https://facebook.com/truofa']!.onTap!();

    expect(opened, [Uri.parse('https://www.truofa.ph'), Uri.parse('https://facebook.com/truofa')]);
  });

  testWidgets('text without a web link has nothing to tap', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: LinkedText('Pickup at the barangay hall. Call 0917 000 0000.')),
      ),
    );

    expect(_links(tester), isEmpty);
    expect(find.text('Pickup at the barangay hall. Call 0917 000 0000.'), findsOneWidget);
  });
}
