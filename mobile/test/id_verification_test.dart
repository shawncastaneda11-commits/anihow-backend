import 'package:anihow/models/models.dart';
import 'package:anihow/screens/auth/verify_id_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/id_verification_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

UserAccount _buyer(IdVerificationInfo info) => UserAccount(
  id: 7,
  name: 'Maria Santos',
  email: 'maria@example.com',
  roles: const ['buyer'],
  emailVerifiedAt: '2026-10-01',
  idVerification: info,
);

class _IdApi extends ApiClient {
  _IdApi() : super(onUnauthorized: () {});

  ({String idType, String frontPath, String? selfiePath})? sent;

  @override
  Future<UserAccount> submitIdVerification({
    required String idType,
    required String frontPath,
    String? selfiePath,
  }) async {
    sent = (idType: idType, frontPath: frontPath, selfiePath: selfiePath);
    return _buyer(
      const IdVerificationInfo(required: true, status: 'pending'),
    );
  }
}

Widget _app(AuthController auth, Widget home) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
      ChangeNotifierProvider<AuthController>.value(value: auth),
    ],
    child: MaterialApp(theme: AniHowTheme.light(), home: home),
  );
}

AuthController _auth(_IdApi api, IdVerificationInfo info) {
  return AuthController(api: api)
    ..restoring = false
    ..user = _buyer(info);
}

void main() {
  test('reads the ID check from the account JSON', () {
    final account = UserAccount.fromJson({
      'id': 7,
      'name': 'Maria',
      'email': 'maria@example.com',
      'roles': ['buyer'],
      'id_verification': {
        'required': true,
        'verified': false,
        'status': 'rejected',
        'id_type': 'passport',
        'rejection_reason': 'Blurry photo.',
        'submitted_at': '2026-10-12T02:24:00+00:00',
        'reviewed_at': '2026-10-12T05:00:00+00:00',
      },
    });

    expect(account.mustVerifyIdToOrder, isTrue);
    expect(account.idVerification?.isRejected, isTrue);
    expect(account.idVerification?.rejectionReason, 'Blurry photo.');
    expect(account.idVerification?.reviewedAt, isNotNull);

    final older = UserAccount.fromJson({
      'id': 8,
      'roles': ['buyer'],
      'id_verification': {'required': false, 'status': 'none'},
    });
    expect(older.mustVerifyIdToOrder, isFalse);
    expect(UserAccount.fromJson({'id': 9}).idVerification, isNull);
  });

  testWidgets('submit waits for the ID photo and consent, then sends the type', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(412, 1400));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _IdApi();
    final auth = _auth(api, const IdVerificationInfo(required: true));
    final picked = <bool>[];

    await tester.pumpWidget(
      _app(
        auth,
        Builder(
          builder: (context) => Scaffold(
            body: Center(
              child: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => VerifyIdScreen(
                      afterSignUp: true,
                      pickPhoto: (source, {required selfie}) async {
                        picked.add(selfie);
                        return selfie ? '/tmp/selfie.jpg' : '/tmp/front.jpg';
                      },
                    ),
                  ),
                ),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();

    expect(find.byKey(const ValueKey('verify-id-skip')), findsOneWidget);
    FilledButton submit() => tester.widget<FilledButton>(
      find.descendant(
        of: find.byKey(const ValueKey('id-submit')),
        matching: find.byType(FilledButton),
      ),
    );
    expect(submit().onPressed, isNull);

    await tester.tap(find.byKey(const ValueKey('id-type-passport')));
    await tester.tap(find.byKey(const ValueKey('id-front')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('id-source-gallery')));
    await tester.pumpAndSettle();
    expect(picked, [false]);
    expect(find.text('Retake'), findsOneWidget);
    expect(submit().onPressed, isNull);

    await tester.tap(find.byKey(const ValueKey('id-consent')));
    await tester.pump();
    expect(submit().onPressed, isNotNull);

    await tester.tap(find.byKey(const ValueKey('id-submit')));
    await tester.pumpAndSettle();

    expect(api.sent?.idType, 'passport');
    expect(api.sent?.frontPath, '/tmp/front.jpg');
    expect(api.sent?.selfiePath, isNull);
    expect(auth.user?.idVerification?.isPending, isTrue);
    expect(find.byType(VerifyIdScreen), findsNothing);
  });

  testWidgets('the profile card follows the check from not yet to verified', (
    tester,
  ) async {
    Future<void> show(IdVerificationInfo info) async {
      await tester.pumpWidget(
        _app(
          _auth(_IdApi(), info),
          Scaffold(body: IdVerificationCard(info: info)),
        ),
      );
    }

    await show(const IdVerificationInfo(required: true));
    expect(find.text('Verify your ID to place orders'), findsOneWidget);
    expect(find.text('Verify my ID'), findsOneWidget);

    await show(const IdVerificationInfo(required: false));
    expect(find.text('Get a Verified badge'), findsOneWidget);

    await show(
      IdVerificationInfo(
        required: true,
        status: 'pending',
        submittedAt: DateTime(2026, 10, 12),
      ),
    );
    expect(find.text('We are checking your ID'), findsOneWidget);
    expect(find.byKey(const ValueKey('id-card-action')), findsNothing);

    await show(
      const IdVerificationInfo(
        required: true,
        status: 'rejected',
        rejectionReason: 'The ID has expired.',
      ),
    );
    expect(find.text('Reason: The ID has expired.'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);

    await show(
      IdVerificationInfo(
        verified: true,
        status: 'approved',
        reviewedAt: DateTime(2026, 10, 11),
      ),
    );
    expect(find.text('Your ID is verified'), findsOneWidget);
    expect(find.textContaining('has been deleted'), findsOneWidget);
  });

  testWidgets('checkout explains the hold and links to the ID check', (
    tester,
  ) async {
    const none = IdVerificationInfo(required: true);
    await tester.pumpWidget(
      _app(
        _auth(_IdApi(), none),
        const Scaffold(body: IdOrderGate(info: none)),
      ),
    );
    expect(find.textContaining('Your cart is saved'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('id-order-gate-verify')));
    await tester.pumpAndSettle();
    expect(find.byType(VerifyIdScreen), findsOneWidget);
    expect(find.byKey(const ValueKey('verify-id-skip')), findsNothing);
    Navigator.of(tester.element(find.byType(VerifyIdScreen))).pop();
    await tester.pumpAndSettle();

    const pending = IdVerificationInfo(required: true, status: 'pending');
    await tester.pumpWidget(
      _app(
        _auth(_IdApi(), pending),
        const Scaffold(body: IdOrderGate(info: pending)),
      ),
    );
    expect(
      find.textContaining('Waiting for ID review', findRichText: true),
      findsOneWidget,
    );
    expect(find.byKey(const ValueKey('id-order-gate-verify')), findsNothing);
  });

  testWidgets('reserving asks an unverified new buyer to verify first', (
    tester,
  ) async {
    bool? allowed;
    Future<void> tryReserve(IdVerificationInfo info) async {
      allowed = null;
      await tester.pumpWidget(
        _app(
          _auth(_IdApi(), info),
          Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () async {
                  allowed = await ensureIdForOrdering(context);
                },
                child: const Text('reserve'),
              ),
            ),
          ),
        ),
      );
      await tester.tap(find.text('reserve'));
      await tester.pumpAndSettle();
    }

    await tryReserve(const IdVerificationInfo(required: false));
    expect(allowed, isTrue);

    await tryReserve(const IdVerificationInfo(required: true));
    expect(
      find.text('Reservations need a verified ID, just like orders.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Not now'));
    await tester.pumpAndSettle();
    expect(allowed, isFalse);
  });
}
