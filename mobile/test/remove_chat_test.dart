import 'package:anihow/models/models.dart';
import 'package:anihow/screens/chat/order_chats_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

class _ChatApi extends ApiClient {
  _ChatApi({this.failRemove = false}) : super(onUnauthorized: () {});

  bool failRemove;
  int removes = 0;
  List<StallChat> chats = [
    const StallChat(
      id: 4,
      sellerId: 2,
      shopName: 'Aling Nena',
      buyerName: 'Carla',
      latestBody: 'Bring a bag',
      latestAt: '2026-10-06T10:00:00+08:00',
    ),
  ];

  @override
  Future<List<OrderRecord>> buyerOrders() async => const [];

  @override
  Future<List<StallChat>> stallChats() async => List<StallChat>.from(chats);

  @override
  Future<void> removeStallChat(int conversationId) async {
    removes++;
    if (failRemove) {
      throw ApiException('Could not remove the chat.');
    }
  }
}

Widget _app(_ChatApi api) {
  final auth = AuthController(api: api)..restoring = false;
  return MultiProvider(
    providers: [
      ChangeNotifierProvider(
        create: (_) => PreferencesController()..notificationsEnabled = false,
      ),
      ChangeNotifierProvider.value(value: auth),
    ],
    child: MaterialApp(
      theme: AniHowTheme.light(),
      home: const OrderChatsScreen(),
    ),
  );
}

Future<void> _openList(WidgetTester tester, _ChatApi api) async {
  await tester.pumpWidget(_app(api));
  await tester.pumpAndSettle();
}

void main() {
  setUp(() {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('swiping a chat asks, and cancel keeps the row', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ChatApi();
    await _openList(tester, api);

    await tester.drag(find.byType(Dismissible), const Offset(-400, 0));
    await tester.pumpAndSettle();

    expect(find.text('Remove this chat?'), findsOneWidget);
    expect(find.textContaining('Aling Nena can still see it'), findsOneWidget);

    await tester.tap(find.text('Cancel'));
    await tester.pumpAndSettle();

    expect(find.text('Aling Nena'), findsOneWidget);
    expect(api.removes, 0);
  });

  testWidgets('confirming remove calls the api and a refresh keeps it hidden', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ChatApi();
    await _openList(tester, api);

    await tester.drag(find.byType(Dismissible), const Offset(-400, 0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Remove'));
    await tester.pumpAndSettle();

    expect(api.removes, 1);
    expect(find.text('Aling Nena'), findsNothing);
    expect(find.text('Chat removed'), findsOneWidget);

    await tester.pump(const Duration(seconds: 10));
    await tester.pumpAndSettle();
    expect(find.text('Aling Nena'), findsNothing);

    api.chats = [
      const StallChat(
        id: 4,
        sellerId: 2,
        shopName: 'Aling Nena',
        buyerName: 'Carla',
        latestBody: 'I am here',
        latestAt: '2026-10-06T12:00:00+08:00',
      ),
    ];
    await tester.pump(const Duration(seconds: 10));
    await tester.pumpAndSettle();
    expect(find.text('Aling Nena'), findsOneWidget);
    expect(find.text('I am here'), findsOneWidget);
  });

  testWidgets('a failed remove puts the row back', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ChatApi(failRemove: true);
    await _openList(tester, api);

    await tester.drag(find.byType(Dismissible), const Offset(-400, 0));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Remove'));
    await tester.pumpAndSettle();

    expect(api.removes, 1);
    expect(find.text('Aling Nena'), findsOneWidget);
    expect(find.text('Could not remove the chat.'), findsOneWidget);
  });

  testWidgets('the menu removes the chat too', (tester) async {
    await tester.binding.setSurfaceSize(const Size(400, 800));
    addTearDown(() => tester.binding.setSurfaceSize(null));
    final api = _ChatApi();
    await _openList(tester, api);

    await tester.tap(find.byIcon(Icons.more_vert));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Remove chat'));
    await tester.pumpAndSettle();
    expect(find.text('Remove this chat?'), findsOneWidget);

    await tester.tap(find.byKey(const Key('confirm-remove-chat')));
    await tester.pumpAndSettle();

    expect(api.removes, 1);
    expect(find.text('Aling Nena'), findsNothing);
    expect(find.text('Chat removed'), findsOneWidget);
  });
}
