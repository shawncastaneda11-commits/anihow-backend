import 'dart:convert';
import 'dart:typed_data';

import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/buyer_order_detail_screen.dart';
import 'package:anihow/screens/buyer/pay_now_screen.dart';
import 'package:anihow/screens/farmer/farmer_orders_screen.dart';
import 'package:anihow/screens/farmer/farmer_payments_screen.dart';
import 'package:anihow/screens/farmer/stock_sheets.dart';
import 'package:anihow/screens/farmer/walk_in_sale_screen.dart';
import 'package:anihow/screens/notifications/notifications_screen.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/support/qr_gallery.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:anihow/widgets/chat_message_bubble.dart';
import 'package:anihow/widgets/status_pill.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

PaymentListItem _paymentRow(OrderRecord order) {
  return PaymentListItem(
    kind: 'order',
    id: order.id,
    buyerName: order.buyerName,
    title: order.itemSummary,
    amount: order.total,
    wallet: order.latestProof?.wallet,
    reference: order.latestProof?.reference,
    sentAt: order.latestProof?.sentAt,
    paidAt: order.paidAt,
    statusAt: order.cancelledAt ?? order.paidAt ?? order.latestProof?.sentAt,
    orderId: order.id,
  );
}

PaymentQrCode _qr(int id) {
  return PaymentQrCode(
    id: id,
    wallet: 'gcash',
    accountName: 'Nena V',
    accountLast4: '1234',
    imageUrl: 'https://example.test/qr/$id',
  );
}

OrderRecord _order({
  String status = 'placed',
  String? paymentStatus = 'awaiting_payment',
  List<PaymentQrCode> qrs = const [],
  PaymentProofRecord? proof,
  List<String> allowedNext = const [],
  DateTime? due,
  List<OrderItemRow> items = const [],
}) {
  return OrderRecord(
    id: 9,
    status: status,
    total: '30.00',
    items: items,
    orderNumber: 'AH-9',
    shopName: 'Nena Stall',
    paymentMethod: 'online_transfer',
    paymentStatus: paymentStatus,
    paymentDueAt: due ?? DateTime(2026, 10, 9, 15, 40),
    paymentQrs: qrs,
    latestProof: proof,
    allowedNext: allowedNext,
  );
}

class _Api extends ApiClient {
  _Api() : super(onUnauthorized: () {});

  Map<String, dynamic>? shopBody;
  String? reviewDecision;
  String? reviewReason;
  String? reviewNote;
  String? submittedReference;
  String? completedAmount;
  bool completeCalled = false;
  String? refundReference;
  String? reportType;
  String? reportReason;
  Object? deleteError;
  OrderRecord order = _order();
  List<PaymentListItem> payments = const [];

  @override
  Future<ShopProfile> updateFarmerShop(Map<String, dynamic> body) async {
    shopBody = body;
    return ShopProfile(
      id: 4,
      shopName: 'Nena',
      name: 'Nena',
      paymentTimeLimitHours: body['payment_time_limit_hours'] as int? ?? 24,
    );
  }

  @override
  Future<void> deletePaymentQr(int id) async {
    final error = deleteError;
    if (error != null) {
      throw error;
    }
  }

  @override
  Future<List<int>> downloadAuthorized(String url) async {
    return base64Decode(
      'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
    );
  }

  @override
  Future<OrderRecord> buyerOrder(int id) async => order;

  @override
  Future<OrderRecord> farmerOrder(int id) async => order;

  @override
  Future<OrderRecord> submitPaymentProof(
    int orderId, {
    required String referenceNumber,
    required String amount,
    required int qrId,
    String? screenshotPath,
  }) async {
    submittedReference = referenceNumber;
    order = _order(
      paymentStatus: 'payment_sent',
      qrs: order.paymentQrs,
      proof: PaymentProofRecord(
        id: 3,
        reference: referenceNumber,
        amount: amount,
        status: 'pending',
        wallet: 'gcash',
      ),
    );
    return order;
  }

  @override
  Future<OrderRecord> reviewPaymentProof(
    int orderId,
    int proofId, {
    required String decision,
    String? reason,
    String? note,
  }) async {
    reviewDecision = decision;
    reviewReason = reason;
    reviewNote = note;
    return order;
  }

  @override
  Future<OrderRecord> completeOrder(int id, {String? amountReceived}) async {
    completeCalled = true;
    completedAmount = amountReceived;
    return order.copyWith(status: 'completed');
  }

  @override
  Future<OrderRecord> refundOrder(int orderId, {required String refundReference}) async {
    this.refundReference = refundReference;
    return order;
  }

  @override
  Future<void> submitReport({
    required String targetType,
    required int targetId,
    required String reason,
    String? details,
  }) async {
    reportType = targetType;
    reportReason = reason;
  }

  @override
  Future<PagedItems<PaymentListItem>> farmerPayments({
    required String status,
    int page = 1,
  }) async {
    return PagedItems(items: payments, complete: true);
  }

  @override
  Future<List<ListingItem>> farmerListings() async {
    return const [
      ListingItem(
        id: 44,
        title: 'Sitaw',
        pricePerUnit: '30',
        quantityAvailable: '10',
        unit: 'kg',
      ),
    ];
  }
}

Future<void> _pump(WidgetTester tester, Widget home, _Api api, {UserAccount? user}) async {
  await tester.binding.setSurfaceSize(const Size(500, 2400));
  addTearDown(() => tester.binding.setSurfaceSize(null));
  final auth = AuthController(api: api)
    ..restoring = false
    ..user = user ??
        const UserAccount(
          id: 1,
          name: 'Buyer',
          email: 'buyer@example.com',
          roles: ['buyer'],
        );
  await tester.pumpWidget(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => PreferencesController()..notificationsEnabled = false),
        ChangeNotifierProvider.value(value: auth),
      ],
      child: MaterialApp(theme: AniHowTheme.light(), home: home),
    ),
  );
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 50));
}

void main() {
  testWidgets('qr list hides add at three and shows a last-qr server message', (tester) async {
    final api = _Api()
      ..deleteError = ApiException("You can't delete your last QR while a buyer is still paying.");
    final shop = ShopProfile(
      id: 4,
      shopName: 'Nena',
      name: 'Nena',
      paymentQrs: [_qr(1), _qr(2), _qr(3)],
    );
    await _pump(tester, ShopEditScreen(key: UniqueKey(), shop: shop), api);
    expect(find.byKey(const ValueKey('add-qr-code')), findsNothing);
    expect(find.byKey(const ValueKey('qr-row-1')), findsOneWidget);
    final thumb = tester.widget<AuthorizedChatImage>(
      find.descendant(
        of: find.byKey(const ValueKey('qr-row-1')),
        matching: find.byType(AuthorizedChatImage),
      ),
    );
    expect(thumb.fit, BoxFit.contain);

    final empty = ShopProfile(id: 4, shopName: 'Nena', name: 'Nena');
    await _pump(tester, ShopEditScreen(key: UniqueKey(), shop: empty), api);
    final toggle = tester.widget<SwitchListTile>(find.byKey(const ValueKey('accept-online-payment')));
    expect(toggle.onChanged, isNull);
    expect(find.text('Add a QR code first.'), findsOneWidget);

    final one = ShopProfile(id: 4, shopName: 'Nena', name: 'Nena', paymentQrs: [_qr(1)]);
    await _pump(tester, ShopEditScreen(key: UniqueKey(), shop: one), api);
    await tester.tap(find.byKey(const ValueKey('delete-qr-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('confirm-delete-qr')));
    await tester.pump();
    expect(
      find.text("You can't delete your last QR while a buyer is still paying."),
      findsOneWidget,
    );
  });

  testWidgets('payment time limit is saved with the shop', (tester) async {
    final api = _Api();
    final shop = ShopProfile(
      id: 4,
      shopName: 'Nena',
      name: 'Nena',
      paymentQrs: [_qr(1)],
      acceptsOnlinePayment: true,
    );
    await _pump(tester, ShopEditScreen(key: UniqueKey(), shop: shop), api);
    await tester.ensureVisible(find.byKey(const ValueKey('payment-time-limit')));
    await tester.tap(find.byKey(const ValueKey('payment-time-limit')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('3 hours').last);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Save shop profile'));
    await tester.pump();
    expect(api.shopBody?['payment_time_limit_hours'], 3);
    expect(find.text('On · buyers can choose Online payment'), findsOneWidget);

    final off = ShopProfile(
      id: 4,
      shopName: 'Nena',
      name: 'Nena',
      paymentQrs: [_qr(1)],
      acceptsOnlinePayment: false,
    );
    await _pump(tester, ShopEditScreen(key: UniqueKey(), shop: off), api);
    expect(find.text('Off · buyers pay cash only'), findsOneWidget);
  });

  testWidgets('pay screen shows the amount, deadline, and account', (tester) async {
    final api = _Api();
    var saved = false;
    final previous = QrGallery.saveImageBytes;
    QrGallery.saveImageBytes = (Uint8List bytes, {required String name}) async {
      saved = true;
    };
    addTearDown(() => QrGallery.saveImageBytes = previous);
    final order = _order(qrs: [_qr(7), _qr(8)]);
    await _pump(tester, PayNowScreen(order: order), api);
    expect(find.byKey(const ValueKey('pay-amount')), findsOneWidget);
    expect(find.text('Pay before 3:40 PM, Oct 9'), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-qr')), findsOneWidget);
    expect(find.textContaining('Nena V'), findsOneWidget);
    expect(find.textContaining('•••• 1234'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('pay-save-qr')));
    await tester.pump();
    expect(saved, isTrue);
  });

  testWidgets('several online orders open a pay list', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: PaySellersScreen(
          orders: [
            _order(),
            OrderRecord(
              id: 10,
              status: 'placed',
              total: '40',
              items: const [],
              shopName: 'Tonyo',
              paymentMethod: 'online_transfer',
              paymentStatus: 'awaiting_payment',
            ),
          ],
        ),
      ),
    );
    expect(find.text('Pay 2 sellers'), findsWidgets);
  });

  testWidgets('proof requires a reference and then shows payment sent', (tester) async {
    final api = _Api();
    final order = _order(qrs: [_qr(7)]);
    await _pump(tester, PayNowScreen(order: order), api);
    await tester.ensureVisible(find.byKey(const ValueKey('pay-submit')));
    await tester.tap(find.byKey(const ValueKey('pay-submit')));
    await tester.pump();
    expect(find.text('Enter the reference number.'), findsOneWidget);

    await tester.enterText(find.byKey(const ValueKey('pay-reference')), 'AB12CD');
    await tester.tap(find.byKey(const ValueKey('pay-submit')));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(api.submittedReference, 'AB12CD');
    expect(find.text('Payment sent'), findsWidgets);
    expect(find.textContaining('Waiting for Nena Stall to check it'), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-qr')), findsNothing);
    expect(find.byKey(const ValueKey('pay-reference')), findsNothing);
  });

  testWidgets('a rejected proof offers send again', (tester) async {
    final api = _Api();
    final order = _order(
      paymentStatus: 'awaiting_payment',
      qrs: [_qr(7)],
      proof: const PaymentProofRecord(
        id: 3,
        reference: 'AB12CD',
        amount: '30.00',
        status: 'rejected',
        rejectionReason: 'wrong_amount',
        rejectionNote: 'Short',
      ),
    );
    await _pump(tester, PayNowScreen(order: order), api);
    expect(find.byKey(const ValueKey('pay-rejected')), findsOneWidget);
    expect(find.textContaining('Wrong amount'), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-qr')), findsNothing);
    await tester.tap(find.byKey(const ValueKey('pay-send-again')));
    await tester.pump();
    expect(find.byKey(const ValueKey('pay-reference')), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-qr')), findsOneWidget);
  });

  testWidgets('buyer cancel hides after proof and a report uses the order target', (tester) async {
    final api = _Api();
    api.order = _order(paymentStatus: 'payment_sent');
    await _pump(
      tester,
      BuyerOrderDetailScreen(order: api.order, preview: true),
      api,
    );
    expect(find.text('Cancel order'), findsNothing);
    await tester.tap(find.byKey(const ValueKey('order-menu')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Report payment problem'));
    await tester.pumpAndSettle();
    expect(find.text('Payment problem'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('report-reason-payment_problem')));
    await tester.tap(find.byKey(const ValueKey('report-submit')));
    await tester.pump();
    expect(api.reportType, 'order');
    expect(api.reportReason, 'payment_problem');
  });

  testWidgets('tracked orders show a payment pill and cash orders do not', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AniHowTheme.light(),
        home: Scaffold(
          body: Column(
            children: const [
              PaymentTrackingPill(status: 'paid'),
              PaymentTrackingPill(status: null),
              PaymentTrackingPill(status: 'not_tracked'),
            ],
          ),
        ),
      ),
    );
    expect(find.text('Paid'), findsOneWidget);
    expect(find.text('Awaiting payment'), findsNothing);
  });

  testWidgets('seller accepts, rejects with a note, and completes a paid order without an amount', (
    tester,
  ) async {
    final api = _Api();
    api.order = _order(
      status: 'confirmed',
      paymentStatus: 'payment_sent',
      items: const [
        OrderItemRow(
          listingName: 'Pechay',
          quantity: '1',
          listedPrice: '30',
          lineSubtotal: '30',
        ),
      ],
      proof: const PaymentProofRecord(
        id: 5,
        reference: 'AB12CD',
        amount: '12.00',
        status: 'pending',
        wallet: 'gcash',
      ),
    );
    await _pump(
      tester,
      FarmerOrderDetailScreen(order: api.order),
      api,
      user: const UserAccount(id: 4, name: 'Nena', email: 'n@example.com', roles: ['farmer_seller']),
    );
    await tester.pump();
    await tester.ensureVisible(find.text('Waiting for payment'));
    expect(find.text('Waiting for payment'), findsWidgets);
    expect(find.text('Check this payment'), findsOneWidget);
    final cardTop = tester.getTopLeft(find.byKey(const ValueKey('order-payment-card'))).dy;
    final itemsTop = tester.getTopLeft(find.text('Items')).dy;
    expect(cardTop, lessThan(itemsTop));
    expect(find.byKey(const ValueKey('proof-amount-differs')), findsNothing);
    await tester.ensureVisible(find.byKey(const ValueKey('payment-received')));
    await tester.tap(find.byKey(const ValueKey('payment-received')));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('proof-amount-differs')), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('payment-received-cancel')));
    await tester.pumpAndSettle();
    expect(api.reviewDecision, isNull);
    await tester.tap(find.byKey(const ValueKey('payment-received')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('payment-received-yes')));
    await tester.pump();
    expect(api.reviewDecision, 'accept');

    await tester.tap(find.byKey(const ValueKey('payment-not-received')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('reject-other')));
    await tester.pump();
    await tester.tap(find.byKey(const ValueKey('reject-submit')));
    await tester.pump();
    expect(find.text('Add a note for Other.'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('reject-note')), 'Blurry');
    await tester.tap(find.byKey(const ValueKey('reject-submit')));
    await tester.pump();
    expect(api.reviewDecision, 'reject');
    expect(api.reviewReason, 'other');
    expect(api.reviewNote, 'Blurry');

    api.order = _order(
      status: 'ready',
      paymentStatus: 'paid',
      allowedNext: const ['completed'],
    );
    await _pump(
      tester,
      FarmerOrderDetailScreen(key: UniqueKey(), order: api.order),
      api,
      user: const UserAccount(id: 4, name: 'Nena', email: 'n@example.com', roles: ['farmer_seller']),
    );
    await tester.pump();
    await tester.ensureVisible(find.byKey(const ValueKey('complete-order')));
    await tester.tap(find.byKey(const ValueKey('complete-order')));
    await tester.pump();
    expect(api.completeCalled, isTrue);
    expect(api.completedAmount, isNull);
  });

  testWidgets('a refund needs a reference and the payments tabs list orders', (tester) async {
    final api = _Api();
    api.order = _order(status: 'cancelled', paymentStatus: 'refund_due', allowedNext: const []);
    await _pump(
      tester,
      FarmerOrderDetailScreen(order: api.order),
      api,
      user: const UserAccount(id: 4, name: 'Nena', email: 'n@example.com', roles: ['farmer_seller']),
    );
    await tester.pump();
    await tester.ensureVisible(find.byKey(const ValueKey('mark-refunded')));
    await tester.tap(find.byKey(const ValueKey('mark-refunded')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('refund-submit')));
    await tester.pump();
    expect(find.text('Enter the refund reference.'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('refund-reference')), 'RF9901');
    await tester.tap(find.byKey(const ValueKey('refund-submit')));
    await tester.pump();
    expect(api.refundReference, 'RF9901');

    api.payments = [_paymentRow(api.order)];
    await _pump(tester, const FarmerPaymentsScreen(), api);
    expect(find.textContaining('To check'), findsOneWidget);
    expect(find.text('Confirmed'), findsOneWidget);
    expect(find.text('Refund due'), findsWidgets);
    expect(find.byKey(const ValueKey('payment-order-9')), findsOneWidget);
  });

  test('payment notification titles exist in English and Filipino', () {
    const en = AppStrings(false);
    const fil = AppStrings(true);
    expect(en.notificationTitle('payment_due_soon', 'x'), 'Payment due soon');
    expect(fil.notificationTitle('payment_due_soon', 'x'), 'Malapit na ang deadline ng bayad');
    expect(en.notificationTitle('refund_completed', 'x'), 'Refund completed');
    expect(fil.notificationTitle('payment_proof_submitted', 'x'), isNot('x'));
  });

  testWidgets('a buyer payment reminder opens the pay screen', (tester) async {
    final api = _Api();
    api.order = _order(qrs: [_qr(7)]);
    await _pump(
      tester,
      const SizedBox(key: ValueKey('nav-host')),
      api,
    );
    final context = tester.element(find.byKey(const ValueKey('nav-host')));
    final navigation = openNotificationTarget(
      context,
      const AppNotification(
        id: 1,
        title: 'Payment due soon',
        body: 'Pay soon',
        type: 'payment_due_soon',
        relatedId: 9,
        relatedType: 'order',
      ),
    );
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(PayNowScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(tester.element(find.byType(PayNowScreen))).pop();
    await tester.pumpAndSettle();
    await navigation;
  });

  testWidgets('sold outside the app offers a walk-in sale for this listing', (tester) async {
    final api = _Api();
    const listing = ListingItem(
      id: 44,
      title: 'Sitaw',
      pricePerUnit: '30',
      quantityAvailable: '10',
      unit: 'kg',
    );
    await _pump(
      tester,
      Builder(
        builder: (context) => Scaffold(
          body: TextButton(
            onPressed: () => showRemoveStockSheet(context, listing),
            child: const Text('open'),
          ),
        ),
      ),
      api,
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.byKey(const ValueKey('walk-in-nudge')), findsNothing);
    await tester.tap(find.byKey(const ValueKey('removal-sold_outside')));
    await tester.pump();
    expect(find.byKey(const ValueKey('walk-in-nudge')), findsOneWidget);
    final hint = tester.widget<Text>(
      find.descendant(
        of: find.byKey(const ValueKey('walk-in-nudge')),
        matching: find.byType(Text),
      ).first,
    );
    expect(hint.style?.fontWeight, isNot(FontWeight.w700));
    expect(
      find.descendant(
        of: find.byKey(const ValueKey('walk-in-nudge')),
        matching: find.byType(OutlinedButton),
      ),
      findsOneWidget,
    );
    await tester.tap(find.byKey(const ValueKey('record-walk-in-from-stock')));
    await tester.pumpAndSettle();
    final screen = tester.widget<WalkInSaleScreen>(find.byType(WalkInSaleScreen));
    expect(screen.listingId, 44);
  });

  testWidgets('payment tabs stay readable on the green app bar', (tester) async {
    final api = _Api();
    await _pump(tester, const FarmerPaymentsScreen(), api);
    final bar = tester.widget<TabBar>(find.byType(TabBar));
    expect(bar.labelColor, Colors.white);
    expect(bar.labelColor, isNot(AniHowColors.brand));
  });

  testWidgets('a deadline under an hour uses the amber chip', (tester) async {
    final api = _Api();
    final soon = _order(
      qrs: [_qr(7)],
      due: DateTime.now().add(const Duration(minutes: 20)),
    );
    await _pump(tester, PayNowScreen(order: soon), api);
    final chip = tester.widget<DecoratedBox>(find.byKey(const ValueKey('pay-deadline')));
    expect((chip.decoration as BoxDecoration).color, const Color(0xFFFFF4D6));

    final later = _order(
      qrs: [_qr(7)],
      due: DateTime.now().add(const Duration(hours: 5)),
    );
    await _pump(tester, PayNowScreen(key: UniqueKey(), order: later), api);
    final calm = tester.widget<DecoratedBox>(find.byKey(const ValueKey('pay-deadline')));
    expect((calm.decoration as BoxDecoration).color, AniHowColors.inStockBg);
  });

  testWidgets('a paid order shows the confirmation card without the QR form', (tester) async {
    final api = _Api();
    final order = _order(
      paymentStatus: 'paid',
      proof: PaymentProofRecord(
        id: 3,
        reference: 'AB12CD',
        amount: '30.00',
        status: 'accepted',
        wallet: 'gcash',
        reviewedAt: DateTime(2026, 10, 8, 16, 10),
      ),
    );
    await _pump(tester, PayNowScreen(order: order), api);
    expect(find.byKey(const ValueKey('pay-paid-card')), findsOneWidget);
    expect(find.text('Payment confirmed'), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-qr')), findsNothing);
    expect(find.byKey(const ValueKey('pay-reference')), findsNothing);
  });

  testWidgets('each payments tab shows its own row and an empty state', (tester) async {
    final api = _Api();
    api.payments = [
      PaymentListItem(
        kind: 'order',
        id: 9,
        buyerName: 'Maria',
        title: 'Pechay',
        items: const ['Pechay'],
        amount: '20.00',
        wallet: 'gcash',
        reference: 'AB12CD34',
        sentAt: DateTime.now().subtract(const Duration(minutes: 4)),
        paidAt: DateTime(2026, 10, 8, 16, 10),
        statusAt: DateTime(2026, 10, 7),
        orderId: 9,
      ),
    ];
    await _pump(tester, const FarmerPaymentsScreen(), api);
    await tester.pump();
    expect(find.textContaining('To check (1)'), findsOneWidget);
    expect(find.text('Maria'), findsOneWidget);
    expect(find.text('Check now'), findsOneWidget);
    expect(find.text('GCash · ref AB12CD34'), findsOneWidget);
    expect(find.textContaining('Sent'), findsWidgets);

    await tester.tap(find.text('Confirmed'));
    await tester.pumpAndSettle();
    expect(find.text('Paid'), findsOneWidget);
    expect(find.text('Paid Oct 8, 4:10 PM'), findsOneWidget);

    await tester.tap(find.text('Refund due').first);
    await tester.pumpAndSettle();
    expect(find.text('Since Oct 7'), findsOneWidget);
    expect(find.text('Pechay'), findsOneWidget);

    api.payments = const [];
    await _pump(tester, FarmerPaymentsScreen(key: UniqueKey()), api);
    await tester.pump();
    expect(find.text('Nothing here right now.'), findsWidgets);
  });
}
