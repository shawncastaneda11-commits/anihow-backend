import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/profile_avatar_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _RecordingApi extends ApiClient {
  _RecordingApi() : super(onUnauthorized: () {});

  int avatarDeletes = 0;

  @override
  Future<UserAccount> deleteAvatar() async {
    avatarDeletes++;
    return const UserAccount(
      id: 3,
      name: 'Juan Dela Cruz',
      email: 'juan@example.com',
      roles: ['buyer'],
    );
  }
}

Widget _app(Widget home, {AuthController? auth}) {
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => auth ?? (AuthController()..restoring = false),
      ),
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
    ],
    child: MaterialApp(theme: AniHowTheme.dark(), home: home),
  );
}

void main() {
  testWidgets('avatar falls back to initials', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(home: AniHowAvatar(name: 'Rosa Santos')),
    );
    await tester.pump();

    expect(find.text('RS'), findsOneWidget);
  });

  testWidgets('avatar shows an image url when one is present', (tester) async {
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.library == 'image resource service') {
        return;
      }
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);

    await tester.pumpWidget(
      const MaterialApp(
        home: AniHowAvatar(
          name: 'Juan Dela Cruz',
          imageUrl: 'https://example.com/juan.jpg',
        ),
      ),
    );
    await tester.pump();

    final avatar = tester.widget<CircleAvatar>(find.byType(CircleAvatar));
    expect(avatar.backgroundImage, isA<NetworkImage>());
    expect(
      (avatar.backgroundImage! as NetworkImage).url,
      'https://example.com/juan.jpg',
    );
    expect(find.text('RS'), findsNothing);
  });

  testWidgets('remove photo calls the api', (tester) async {
    await tester.binding.setSurfaceSize(const Size(360, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.library == 'image resource service') {
        return;
      }
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);

    final api = _RecordingApi();
    final auth = AuthController(api: api)..restoring = false;
    auth.user = const UserAccount(
      id: 3,
      name: 'Juan Dela Cruz',
      email: 'juan@example.com',
      roles: ['buyer'],
      avatarUrl: 'https://example.com/juan.jpg',
    );

    await tester.pumpWidget(_app(const ProfileScreen(), auth: auth));
    await tester.pump();

    final s = AppStrings(false);
    expect(find.text(s.removePhoto), findsOneWidget);

    await tester.tap(find.byKey(const Key('remove-profile-photo')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(api.avatarDeletes, 1);
  });
}
