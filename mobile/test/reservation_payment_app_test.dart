import 'package:anihow/l10n/app_strings.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/screens/buyer/listing_detail_screen.dart';
import 'package:anihow/screens/buyer/order_history_screen.dart';
import 'package:anihow/screens/buyer/pay_now_screen.dart';
import 'package:anihow/screens/buyer/reservation_detail_screen.dart';
import 'package:anihow/screens/farmer/farmer_orders_screen.dart';
import 'package:anihow/screens/farmer/farmer_payments_screen.dart';
import 'package:anihow/screens/notifications/notifications_screen.dart';
import 'package:anihow/screens/profile/profile_screen.dart';
import 'package:anihow/services/api_client.dart';
import 'package:anihow/state/auth_controller.dart';
import 'package:anihow/state/cart_controller.dart';
import 'package:anihow/state/preferences_controller.dart';
import 'package:anihow/theme/anihow_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

class _Api extends ApiClient {
  _Api() : super(onUnauthorized: () {});

  String? reviewDecision;
  String? refundReference;
  Object? shopError;
  OrderRecord? detailOrder;

  @override
  Future<ReservationRecord> reserveListing({
    required int listingId,
    required String quantity,
    required String fulfillmentPreference,
    String? fulfillmentNote,
  }) async {
    return _reservation();
  }

  bool sawBuyerReservations = false;

  @override
  Future<List<ReservationRecord>> buyerReservations() async {
    sawBuyerReservations = true;
    return [_reservation()];
  }

  @override
  Future<List<CartLine>> cartItems() async => const [];

  @override
  Future<ReservationRecord?> farmerReservation(int id) async => _reservation(
    paymentStatus: 'payment_sent',
  );

  @override
  Future<ReservationRecord> reviewReservationProof(
    int reservationId,
    int proofId, {
    required String decision,
    String? reason,
    String? note,
  }) async {
    reviewDecision = decision;
    return _reservation(paymentStatus: 'paid');
  }

  @override
  Future<ReservationRecord> refundReservation(
    int reservationId, {
    required String refundReference,
  }) async {
    this.refundReference = refundReference;
    return _reservation(paymentStatus: 'refunded', refundReference: refundReference);
  }

  @override
  Future<ShopProfile> updateFarmerShop(Map<String, dynamic> body) async {
    final error = shopError;
    if (error != null) {
      throw error;
    }
    return const ShopProfile(id: 4, shopName: 'Jun', name: 'Jun');
  }

  @override
  Future<PagedItems<PaymentListItem>> farmerPayments({
    required String status,
    int page = 1,
  }) async {
    return PagedItems(
      items: [
        const PaymentListItem(
          kind: 'order',
          id: 9,
          buyerName: 'Maria',
          title: 'Sitaw',
          amount: '30.00',
          orderId: 9,
        ),
        const PaymentListItem(
          kind: 'reservation',
          id: 4,
          buyerName: 'Ana',
          title: 'Pechay',
          amount: '80.00',
          wallet: 'gcash',
          reference: 'ABC12345',
          reservationId: 4,
        ),
      ],
      complete: true,
    );
  }

  @override
  Future<OrderRecord> farmerOrder(int id) async {
    return detailOrder ??
        const OrderRecord(id: 9, status: 'placed', total: '30', items: []);
  }
}

ReservationRecord _reservation({
  int id = 4,
  String paymentStatus = 'awaiting_payment',
  PaymentProofRecord? proof,
  String? refundReference,
}) {
  return ReservationRecord(
    id: id,
    listingId: 8,
    listingName: 'Pechay',
    quantity: 2,
    lineTotal: 80,
    status: paymentStatus == 'refund_due' ? 'cancelled' : 'active',
    unit: 'kg',
    paymentStatus: paymentStatus,
    paymentDueAt: DateTime.now().add(const Duration(hours: 5)),
    paymentQrs: const [
      PaymentQrCode(
        id: 3,
        wallet: 'gcash',
        accountName: 'Jun',
        accountLast4: '1234',
      ),
    ],
    latestProof: proof,
    refundReference: refundReference,
    buyerName: 'Ana',
  );
}

Future<void> _pump(WidgetTester tester, Widget home, _Api api, {UserAccount? user}) async {
  await tester.binding.setSurfaceSize(const Size(500, 1600));
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
        ChangeNotifierProvider(
          create: (_) => PreferencesController()..notificationsEnabled = false,
        ),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider(create: (_) => CartController(auth)),
      ],
      child: MaterialApp(theme: AniHowTheme.light(), home: home),
    ),
  );
  await tester.pump();
}

void main() {
  test('a reservation always asks for the proof payment flow', () {
    final body = buyerReservationBody(
      listingId: 8,
      quantity: '2',
      fulfillmentPreference: 'buyer_pickup',
    );
    expect(body['payment_flow'], 'proof');
  });

  test('old reservations parse without payment fields', () {
    final row = ReservationRecord.fromJson({
      'id': 1,
      'listing_name': 'Sitaw',
      'quantity': 1,
      'line_total': 10,
      'status': 'active',
    });
    expect(row.paymentStatus, isNull);
    expect(row.latestProof, isNull);
    expect(row.paymentQrs, isEmpty);
    expect(row.canBuyerCancel, isTrue);

    final item = PaymentListItem.fromJson({
      'kind': 'reservation',
      'id': 4,
      'buyer_name': 'Ana',
      'title': 'Pechay',
      'amount': 80,
      'reservation_id': 4,
    });
    expect(item.isReservation, isTrue);
    expect(item.orderId, isNull);
  });

  test('harvests without a cost use singular and plural copy', () {
    const english = AppStrings(false);
    const filipino = AppStrings(true);
    expect(english.harvestsWithoutCost(1), '1 harvest without cost');
    expect(english.harvestsWithoutCost(2), '2 harvests without cost');
    expect(filipino.harvestsWithoutCost(1), '1 ani na walang gastos');
    expect(filipino.harvestsWithoutCost(2), '2 ani na walang gastos');
    expect(english.sellerNoReservations, "This seller doesn't take reservations yet.");
    expect(filipino.reservationsClosed, 'Sarado na ang reserbasyon. Puwede kang umorder kapag bumukas na.');
  });

  testWidgets('reserving opens the pay screen', (tester) async {
    final api = _Api();
    await _pump(
      tester,
      ListingDetailScreen(
        listingId: 8,
        preview: ListingItem(
          id: 8,
          title: 'Pechay',
          pricePerUnit: '40',
          quantityAvailable: '20',
          unit: 'kg',
          isUpcoming: true,
          canReserve: true,
          availableFrom: DateTime.now().add(const Duration(days: 3)),
        ),
      ),
      api,
    );
    await tester.tap(find.byKey(const ValueKey('reserve-harvest')));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Reserve').last);
    await tester.pumpAndSettle();
    expect(find.byType(PayNowScreen), findsOneWidget);
    expect(find.text('Reservation · Pechay'), findsOneWidget);
  });

  testWidgets('a disabled reserve explains why', (tester) async {
    final api = _Api();
    await _pump(
      tester,
      ListingDetailScreen(
        listingId: 8,
        preview: ListingItem(
          id: 8,
          title: 'Pechay',
          pricePerUnit: '40',
          quantityAvailable: '20',
          unit: 'kg',
          isUpcoming: true,
          canReserve: false,
          acceptsOnlinePayment: false,
        ),
      ),
      api,
    );
    expect(find.text("This seller doesn't take reservations yet."), findsOneWidget);
    expect(tester.widget<FilledButton>(find.byKey(const ValueKey('reserve-harvest'))).onPressed, isNull);

    await _pump(
      tester,
      ListingDetailScreen(
        key: UniqueKey(),
        listingId: 8,
        preview: ListingItem(
          id: 8,
          title: 'Pechay',
          pricePerUnit: '40',
          quantityAvailable: '20',
          unit: 'kg',
          isUpcoming: true,
          canReserve: false,
          acceptsOnlinePayment: true,
          availableFrom: DateTime.now().add(const Duration(minutes: 20)),
        ),
      ),
      api,
    );
    expect(find.text('Reservations closed. You can order once it opens.'), findsOneWidget);
  });

  testWidgets('reservation pay screen shows awaiting, sent, rejected, and paid', (tester) async {
    final api = _Api();
    await _pump(tester, PayNowScreen(reservation: _reservation()), api);
    expect(find.textContaining('Pay before'), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-reference')), findsOneWidget);

    await _pump(
      tester,
      PayNowScreen(
        key: UniqueKey(),
        reservation: _reservation(
          paymentStatus: 'payment_sent',
          proof: PaymentProofRecord(
            id: 2,
            reference: 'ABC12345',
            amount: '80.00',
            status: 'pending',
            wallet: 'gcash',
          ),
        ),
      ),
      api,
    );
    expect(find.byKey(const ValueKey('pay-sent-status')), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-reference')), findsNothing);

    await _pump(
      tester,
      PayNowScreen(
        key: UniqueKey(),
        reservation: _reservation(
          proof: PaymentProofRecord(
            id: 2,
            reference: 'ABC12345',
            amount: '80.00',
            status: 'rejected',
            rejectionReason: 'not_received',
          ),
        ),
      ),
      api,
    );
    expect(find.byKey(const ValueKey('pay-rejected')), findsOneWidget);
    expect(find.byKey(const ValueKey('pay-send-again')), findsOneWidget);

    await _pump(
      tester,
      PayNowScreen(
        key: UniqueKey(),
        reservation: _reservation(paymentStatus: 'paid'),
      ),
      api,
    );
    expect(
      find.text(
        'Payment confirmed — your reservation is secured. It becomes an order on harvest day.',
      ),
      findsOneWidget,
    );
  });

  testWidgets('the reservations list shows a pill, pay now, and cancel only while awaiting', (
    tester,
  ) async {
    await _pump(
      tester,
      BuyerReservationsList(
        reservations: [
          _reservation(),
          _reservation(id: 6, paymentStatus: 'payment_sent'),
          const ReservationRecord(
            id: 5,
            listingName: 'Sitaw',
            quantity: 1,
            lineTotal: 20,
            status: 'active',
            paymentStatus: 'not_tracked',
          ),
        ],
        onCancel: (_) async {},
      ),
      _Api(),
    );
    expect(find.text('Awaiting payment'), findsOneWidget);
    expect(find.text('Payment sent'), findsOneWidget);
    expect(find.byKey(const ValueKey('reservation-pay-now-4')), findsOneWidget);
    expect(find.byKey(const ValueKey('reservation-view-payment-6')), findsOneWidget);
    expect(find.byKey(const ValueKey('reservation-cancel-4')), findsOneWidget);
    expect(find.byKey(const ValueKey('reservation-cancel-5')), findsOneWidget);
    expect(find.text('Cancel reservation'), findsNWidgets(2));
  });

  testWidgets('seller reservation review needs the dialog and a refund reference', (
    tester,
  ) async {
    final api = _Api();
    await _pump(
      tester,
      ReservationDetailScreen(
        forSeller: true,
        reservation: _reservation(
          paymentStatus: 'payment_sent',
          proof: const PaymentProofRecord(
            id: 6,
            reference: 'ABC12345',
            amount: '80.00',
            status: 'pending',
            wallet: 'gcash',
          ),
        ),
      ),
      api,
      user: const UserAccount(
        id: 4,
        name: 'Jun',
        email: 'jun@example.com',
        roles: ['farmer_seller'],
      ),
    );
    await tester.tap(find.byKey(const ValueKey('payment-received')));
    await tester.pumpAndSettle();
    expect(find.textContaining('Did '), findsOneWidget);
    expect(find.textContaining('GCash'), findsWidgets);
    await tester.tap(find.byKey(const ValueKey('payment-received-yes')));
    await tester.pumpAndSettle();
    expect(api.reviewDecision, 'accept');

    await _pump(
      tester,
      ReservationDetailScreen(
        key: UniqueKey(),
        forSeller: true,
        reservation: _reservation(paymentStatus: 'refund_due'),
      ),
      api,
    );
    await tester.tap(find.byKey(const ValueKey('mark-refunded')));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('refund-submit')));
    await tester.pump();
    expect(find.text('Enter the refund reference.'), findsOneWidget);
    await tester.enterText(find.byKey(const ValueKey('refund-reference')), 'RF11AA');
    await tester.tap(find.byKey(const ValueKey('refund-submit')));
    await tester.pumpAndSettle();
    expect(api.refundReference, 'RF11AA');
  });

  testWidgets('payments list routes orders and reservations', (tester) async {
    final api = _Api();
    await _pump(
      tester,
      const FarmerPaymentsScreen(),
      api,
      user: const UserAccount(
        id: 4,
        name: 'Jun',
        email: 'jun@example.com',
        roles: ['farmer_seller'],
      ),
    );
    expect(find.byKey(const ValueKey('payment-order-9')), findsOneWidget);
    expect(find.byKey(const ValueKey('payment-reservation-4')), findsOneWidget);
    expect(find.text('Reservation'), findsOneWidget);

    await tester.tap(find.byKey(const ValueKey('payment-reservation-4')));
    await tester.pumpAndSettle();
    expect(find.byType(ReservationDetailScreen), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const ValueKey('payment-order-9')));
    await tester.pumpAndSettle();
    expect(find.byType(FarmerOrderDetailScreen), findsOneWidget);
  });

  testWidgets('a reservation notification opens pay or the reservation', (tester) async {
    final api = _Api();
    await _pump(tester, const SizedBox(key: ValueKey('nav-host')), api);
    final context = tester.element(find.byKey(const ValueKey('nav-host')));
    final navigation = openNotificationTarget(
      context,
      const AppNotification(
        id: 1,
        title: 'Payment due soon',
        body: 'Pay for Pechay',
        type: 'payment_due_soon',
        relatedId: 4,
        relatedType: 'reservation',
      ),
    );
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(api.sawBuyerReservations, isTrue);
    expect(find.byType(PayNowScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(tester.element(find.byType(PayNowScreen, skipOffstage: false))).pop();
    await tester.pumpAndSettle();
    await navigation;

    await _pump(
      tester,
      const SizedBox(key: ValueKey('nav-host')),
      api,
      user: const UserAccount(
        id: 4,
        name: 'Jun',
        email: 'jun@example.com',
        roles: ['farmer_seller'],
      ),
    );
    final seller = tester.element(find.byKey(const ValueKey('nav-host')));
    final sellerNav = openNotificationTarget(
      seller,
      const AppNotification(
        id: 2,
        title: 'Payment to check',
        body: 'Ana paid',
        type: 'payment_proof_submitted',
        relatedId: 4,
        relatedType: 'reservation',
      ),
    );
    await tester.pump();
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));
    expect(find.byType(ReservationDetailScreen, skipOffstage: false), findsOneWidget);
    Navigator.of(
      tester.element(find.byType(ReservationDetailScreen, skipOffstage: false)),
    ).pop();
    await tester.pumpAndSettle();
    await sellerNav;
  });

  testWidgets('turning online payment off shows the server message', (tester) async {
    final api = _Api()
      ..shopError = ApiException('Finish or cancel your reservations first.');
    await _pump(
      tester,
      ShopEditScreen(
        shop: const ShopProfile(
          id: 4,
          shopName: 'Jun',
          name: 'Jun',
          acceptsOnlinePayment: true,
          paymentQrs: [
            PaymentQrCode(
              id: 1,
              wallet: 'gcash',
              accountName: 'Jun',
              accountLast4: '1234',
            ),
          ],
        ),
      ),
      api,
      user: const UserAccount(
        id: 4,
        name: 'Jun',
        email: 'jun@example.com',
        roles: ['farmer_seller'],
      ),
    );
    await tester.ensureVisible(find.byKey(const ValueKey('accept-online-payment')));
    await tester.tap(find.byKey(const ValueKey('accept-online-payment')));
    await tester.pump();
    await tester.ensureVisible(find.text('Save shop profile'));
    await tester.tap(find.text('Save shop profile'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(find.text('Finish or cancel your reservations first.'), findsOneWidget);
  });

  testWidgets('a paid online order says paid online instead of cash received', (tester) async {
    final paid = OrderRecord(
      id: 9,
      status: 'completed',
      total: '18.00',
      items: const [],
      paymentMethod: 'online_transfer',
      paymentStatus: 'paid',
      amountReceived: '18.00',
      latestProof: const PaymentProofRecord(wallet: 'gcash', amount: '18.00'),
    );
    final api = _Api()..detailOrder = paid;
    await _pump(
      tester,
      FarmerOrderDetailScreen(order: paid),
      api,
      user: const UserAccount(
        id: 4,
        name: 'Jun',
        email: 'jun@example.com',
        roles: ['farmer_seller'],
      ),
    );
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.text('Paid online ₱18.00 · GCash'));
    expect(find.text('Paid online ₱18.00 · GCash'), findsOneWidget);
    expect(find.textContaining('Cash received'), findsNothing);
  });
}
