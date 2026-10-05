import 'package:anihow/models/models.dart';
import 'package:anihow/screens/chat/order_chat_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/chat_message_bubble.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

OrderMessage _message({
  required int id,
  String body = 'Hello',
  int? orderId,
  String? orderNumber,
  int? listingId,
  String? listingTitle,
  String? listingPrice,
  String? listingUnit,
  String? listingThumbnailUrl,
}) {
  return OrderMessage(
    id: id,
    body: body,
    authorId: 8,
    authorName: 'Nena',
    orderId: orderId,
    orderNumber: orderNumber,
    listingId: listingId,
    listingTitle: listingTitle,
    listingPrice: listingPrice,
    listingUnit: listingUnit,
    listingThumbnailUrl: listingThumbnailUrl,
  );
}

Widget _bubble(OrderMessage message) {
  return MaterialApp(
    theme: AniHowTheme.light(),
    home: Scaffold(body: ChatMessageBubble(message: message, mine: false)),
  );
}

void main() {
  testWidgets('a tagged message shows the order number', (tester) async {
    await tester.pumpWidget(
      _bubble(
        _message(
          id: 1,
          orderId: 12,
          orderNumber: 'AH-261005-QWTJN',
          body: 'On my way',
        ),
      ),
    );

    expect(find.text('Order AH-261005-QWTJN'), findsOneWidget);
    expect(find.text('On my way'), findsOneWidget);
  });

  testWidgets('the order tag is only on the first message of a run', (
    tester,
  ) async {
    final messages = [
      _message(
        id: 1,
        orderId: 12,
        orderNumber: 'AH-261005-QWTJN',
        body: 'One',
      ),
      _message(
        id: 2,
        orderId: 12,
        orderNumber: 'AH-261005-QWTJN',
        body: 'Two',
      ),
      _message(
        id: 3,
        orderId: 13,
        orderNumber: 'AH-261005-OTHER',
        body: 'Three',
      ),
    ];
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: Scaffold(
          body: Column(
            children: [
              for (var index = 0; index < messages.length; index++)
                ChatMessageBubble(
                  message: messages[index],
                  mine: false,
                  showOrderTag: showsOrderTag(messages, index),
                ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Order AH-261005-QWTJN'), findsOneWidget);
    expect(find.text('Order AH-261005-OTHER'), findsOneWidget);
  });

  testWidgets('a tagged message without a number says Order', (tester) async {
    await tester.pumpWidget(_bubble(_message(id: 4, orderId: 9, body: 'Soon')));

    expect(find.text('Order'), findsOneWidget);
  });

  testWidgets('a product card shows the snapshot and stays tappable', (
    tester,
  ) async {
    final previous = FlutterError.onError;
    FlutterError.onError = (details) {
      if (details.library == 'image resource service') {
        return;
      }
      previous?.call(details);
    };
    addTearDown(() => FlutterError.onError = previous);

    await tester.pumpWidget(
      _bubble(
        _message(
          id: 4,
          listingId: 9,
          listingTitle: 'Morning tomatoes',
          listingPrice: '40.0000',
          listingUnit: 'kg',
          listingThumbnailUrl: 'https://example.com/tomato.jpg',
        ),
      ),
    );

    expect(find.byKey(const ValueKey('listing-card-4')), findsOneWidget);
    expect(find.text('Morning tomatoes'), findsOneWidget);
    expect(find.text('40.0000 / kg'), findsOneWidget);
    expect(find.byType(InkWell), findsOneWidget);
  });

  testWidgets('a product card without a photo is display-only', (tester) async {
    await tester.pumpWidget(
      _bubble(
        _message(
          id: 5,
          listingTitle: 'Morning tomatoes',
          listingPrice: '40.0000',
          listingUnit: 'kg',
        ),
      ),
    );

    expect(find.byKey(const ValueKey('listing-card-5')), findsOneWidget);
    expect(find.byKey(const ValueKey('listing-placeholder-5')), findsOneWidget);
    expect(find.text('Morning tomatoes'), findsOneWidget);
    expect(find.byType(InkWell), findsNothing);
  });

  testWidgets('the order screen scrolls to the first message of that order', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(400, 640));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final messages = [
      for (var id = 1; id <= 20; id++)
        _message(
          id: id,
          orderId: id == 14 ? 7 : null,
          body: List.filled(6, 'Message $id about the handover').join('\n'),
        ),
    ];
    final api = _ThreadApi(messages);
    final auth = AuthController(api: api)
      ..user = const UserAccount(
        id: 3,
        name: 'Ana',
        email: 'ana@example.com',
        roles: ['buyer'],
      )
      ..restoring = false;

    await tester.pumpWidget(
      MultiProvider(
        providers: [
          ChangeNotifierProvider(
            create: (_) {
              final preferences = PreferencesController();
              preferences.notificationsEnabled = false;
              return preferences;
            },
          ),
          ChangeNotifierProvider.value(value: auth),
        ],
        child: MaterialApp(
          theme: AniHowTheme.light(),
          home: const OrderChatScreen(
            order: OrderRecord(
              id: 7,
              status: 'placed',
              total: '40',
              items: [],
              sellerId: 4,
              shopName: 'Nena Stall',
            ),
          ),
        ),
      ),
    );

    await tester.pump();
    await tester.pump();
    for (var step = 0; step < 30; step++) {
      await tester.pump();
    }

    expect(find.text('Order'), findsOneWidget);
    final top = tester.getTopLeft(find.text('Order')).dy;
    expect(top, greaterThan(0));
    expect(top, lessThan(640));

    await tester.pumpWidget(const SizedBox.shrink());
  });
}

class _ThreadApi extends ApiClient {
  _ThreadApi(this.messages) : super(onUnauthorized: () {});

  final List<OrderMessage> messages;

  @override
  Future<StallChat> openStallChat(int sellerId) async {
    return const StallChat(
      id: 9,
      sellerId: 4,
      shopName: 'Nena Stall',
      buyerName: 'Ana',
      buyerId: 3,
    );
  }

  @override
  Future<List<OrderMessage>> stallMessages(
    int conversationId, {
    int? afterId,
  }) async {
    if (afterId != null) {
      return const [];
    }
    return messages;
  }
}
