import 'package:anihow/models/models.dart';
import 'package:anihow/push/push_open.dart';
import 'package:anihow/push/push_runtime.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/app_availability.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

UserAccount _seller({bool mustChangePassword = false}) => UserAccount(
      id: 7,
      name: 'Kuya Jun',
      email: 'jun@example.com',
      roles: const ['farmer_seller'],
      mustChangePassword: mustChangePassword,
    );

AuthController _auth() => AuthController(api: ApiClient(onUnauthorized: () {}));

void main() {
  tearDown(() => PushRuntime.pending = null);

  test('a tap waits while the sign-in is restored, then opens', () {
    final auth = _auth();
    expect(auth.restoring, isTrue);
    expect(pushOpenState(auth, AppAvailability.instance), PushOpenState.wait);

    auth
      ..restoring = false
      ..user = _seller();
    expect(pushOpenState(auth, AppAvailability.instance), PushOpenState.open);
  });

  test('a tap waits for a forced password change and is dropped when signed out', () {
    final auth = _auth()
      ..restoring = false
      ..user = _seller(mustChangePassword: true);
    expect(pushOpenState(auth, AppAvailability.instance), PushOpenState.wait);

    auth.user = null;
    expect(pushOpenState(auth, AppAvailability.instance), PushOpenState.drop);
  });

  testWidgets('a push that starts the app opens once the seller is known', (tester) async {
    final auth = _auth();
    PushRuntime.pending = const PushPayload(data: {
      'type': 'test',
      'notification_id': '',
      'related_type': '',
      'related_id': '',
    });

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: const MaterialApp(home: PushOpenBinder(child: SizedBox())),
      ),
    );
    await tester.pump();
    expect(PushRuntime.pending, isNotNull, reason: 'still restoring the sign-in');

    auth
      ..restoring = false
      ..applyAccount(_seller());
    await tester.pumpAndSettle();
    expect(PushRuntime.pending, isNull);
  });
}
