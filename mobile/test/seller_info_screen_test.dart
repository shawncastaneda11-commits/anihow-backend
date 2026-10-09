import 'package:anihow/models/models.dart';
import 'package:anihow/screens/auth/seller_info_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/seller_mailto.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/primary_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _SellerApi extends ApiClient {
  _SellerApi(this.help, {this.fail = false}) : super(onUnauthorized: () {});

  final SellerHelp help;
  final bool fail;

  @override
  Future<SellerHelp> sellerHelp() async {
    if (fail) {
      throw ApiException('offline');
    }
    return help;
  }
}

SellerHelp _help({String? phone, int days = 11}) {
  return SellerHelp(
    office: 'LPU ICTD',
    email: 'desk@example.com',
    phone: phone,
    temporaryPasswordDays: days,
    farms: const [
      SellerHelpFarm(
        id: 1,
        name: 'Apple Farm',
        municipality: 'General Trias',
        contactPerson: 'Elena Ramos',
        contactNumber: '0917 123 4567',
      ),
      SellerHelpFarm(
        id: 2,
        name: 'Hill Farm',
        municipality: 'Amadeo',
      ),
    ],
  );
}

Future<void> _pump(WidgetTester tester, ApiClient api) async {
  await tester.binding.setSurfaceSize(const Size(430, 1200));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  final auth = AuthController(api: api)..restoring = false;
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => PreferencesController()),
        ChangeNotifierProvider<AuthController>.value(value: auth),
      ],
      child: MaterialApp(
        theme: AniHowTheme.light(),
        home: const SellerInfoScreen(),
      ),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  test('mailto uses %20 and does not turn spaces into plus', () {
    final mail = sellerMailto(
      email: 'desk@example.com',
      subject: sellerAccountSubject,
      body: sellerAccountBody,
    );
    final subject = mail.split('subject=').last.split('&').first;
    expect(subject, contains('%20'));
    expect(subject, isNot(contains('+')));
    expect(mail, startsWith('mailto:desk@example.com?subject='));
  });

  testWidgets('farmer path is the default and farm path replaces the steps', (
    tester,
  ) async {
    await _pump(tester, _SellerApi(_help()));

    expect(find.textContaining('Make sure your farm is on AniHow'), findsOneWidget);
    expect(find.textContaining('within 11 days'), findsOneWidget);
    expect(find.textContaining('Content Editor'), findsNothing);

    await tester.tap(find.byKey(const Key('seller-path-farm')));
    await tester.pump();

    expect(find.textContaining('Content Editor'), findsOneWidget);
    expect(find.textContaining('Make sure your farm is on AniHow'), findsNothing);
    expect(find.textContaining('within 11 days'), findsOneWidget);
  });

  testWidgets('partner farms render a call button only with a number', (
    tester,
  ) async {
    await _pump(tester, _SellerApi(_help(phone: '09170001111')));

    expect(find.text('Apple Farm'), findsOneWidget);
    expect(find.text('General Trias · Elena Ramos'), findsOneWidget);
    expect(find.text('Hill Farm'), findsOneWidget);
    expect(find.text('Amadeo'), findsOneWidget);
    expect(find.byKey(const Key('seller-farm-call-1')), findsOneWidget);
    expect(find.byKey(const Key('seller-farm-call-2')), findsNothing);
    expect(find.text('Call LPU ICTD'), findsOneWidget);
  });

  testWidgets('phone row is hidden when the office has no number', (tester) async {
    await _pump(tester, _SellerApi(_help()));

    expect(find.text('Call LPU ICTD'), findsNothing);
    expect(find.text('desk@example.com'), findsOneWidget);
  });

  testWidgets('copy email shows a snackbar', (tester) async {
    await _pump(tester, _SellerApi(_help()));

    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      SystemChannels.platform,
      (call) async => null,
    );
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        null,
      ),
    );

    await tester.ensureVisible(find.byKey(const Key('seller-copy')));
    await tester.tap(find.byKey(const Key('seller-copy')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('Email copied'), findsOneWidget);
  });

  testWidgets('a failed load keeps the steps and hides the email', (tester) async {
    await _pump(tester, _SellerApi(_help(), fail: true));

    expect(find.textContaining('the AniHow administrator'), findsWidgets);
    expect(find.textContaining('Make sure your farm is on AniHow'), findsOneWidget);
    expect(find.text('desk@example.com'), findsNothing);
    expect(
      find.text("Couldn't load the contact details. Check your connection."),
      findsOneWidget,
    );
    expect(
      tester.widget<PrimaryButton>(find.byKey(const Key('seller-email'))).onPressed,
      isNull,
    );
    expect(
      tester.widget<TextButton>(find.byKey(const Key('seller-copy'))).onPressed,
      isNull,
    );
  });
}
