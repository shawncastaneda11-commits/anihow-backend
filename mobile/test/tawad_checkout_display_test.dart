import 'package:anihow/config/api_config.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/services/cart_requests.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Creates a tawad through the farmer API and prints the checkout strings
/// PriceBreakdown / CheckoutScreen emit. Not Pass 9 (no tawad management UI).
void main() {
  test('checkout display with a real tawad from a newly created rule', () async {
    final dio = Dio(
      BaseOptions(
        baseUrl: '${ApiConfig.localHost}/api',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        validateStatus: (_) => true,
        connectTimeout: const Duration(seconds: 8),
        receiveTimeout: const Duration(seconds: 20),
      ),
    );

    Future<Response<dynamic>> send(
      String method,
      String path, {
      String? token,
      Map<String, dynamic>? body,
    }) {
      return dio.request<dynamic>(
        path,
        data: body,
        options: Options(
          method: method,
          headers: {
            if (token != null) 'Authorization': 'Bearer $token',
          },
        ),
      );
    }

    late final Response<dynamic> sellerLogin;
    try {
      sellerLogin = await send(
        'POST',
        '/auth/login',
        body: {
          'email': 'smoke.sellera@anihow.local',
          'password': 'password',
          'device_name': 'anihow-tawad-display',
        },
      );
    } on DioException catch (error) {
      markTestSkipped('API not reachable at ${ApiConfig.localHost}/api: ${error.message}');
      return;
    }
    expect(sellerLogin.statusCode, 200);
    final sellerToken = sellerLogin.data['token'] as String;

    final listings = await send('GET', '/farmer/listings', token: sellerToken);
    expect(listings.statusCode, 200);
    final listing = ((listings.data['data'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .first;
    final listingId = listing['id'] as int;
    final listedUnit = listing['price_per_unit'];

    const discount = 12;
    const minQuantity = 4;
    final rule = await send(
      'POST',
      '/farmer/listings/$listingId/tawad',
      token: sellerToken,
      body: {
        'type': 'min_quantity',
        'discount_amount': discount,
        'min_quantity': minQuantity,
      },
    );
    expect(rule.statusCode, 201, reason: '${rule.data}');
    expect((rule.data['data']['discount_amount'] as num).toDouble(), discount);
    expect(rule.data['data']['type'], 'min_quantity');

    final buyerLogin = await send(
      'POST',
      '/auth/login',
      body: {
        'email': 'smoke.buyer@anihow.local',
        'password': 'password',
        'device_name': 'anihow-tawad-display',
      },
    );
    expect(buyerLogin.statusCode, 200);
    final buyerToken = buyerLogin.data['token'] as String;

    final cart = await send('GET', CartRequests.cartPath, token: buyerToken);
    for (final row in (cart.data['data'] as List?) ?? const []) {
      if (row is Map && row['id'] is int) {
        await send('DELETE', CartRequests.cartItemPath(row['id'] as int), token: buyerToken);
      }
    }

    final added = await send(
      'POST',
      CartRequests.cartPath,
      token: buyerToken,
      body: CartRequests.addItem(listingId: listingId, quantity: '$minQuantity'),
    );
    expect(added.statusCode, 201);

    final loaded = await send('GET', CartRequests.cartPath, token: buyerToken);
    expect(loaded.statusCode, 200);
    final snapshot = CartSnapshot(
      items: ((loaded.data['data'] as List?) ?? const [])
          .whereType<Map>()
          .map((row) => CartLine.fromJson(Map<String, dynamic>.from(row)))
          .toList(),
    );
    expect(snapshot.items, hasLength(1));
    final line = snapshot.items.single;
    final group = snapshot.groupsBySeller.single;

    final previewUnit = 'Listed unit ${AniHowMoney.peso(line.listedPrice)}';
    final previewListed = 'Listed ${AniHowMoney.peso(line.lineSubtotal)}';
    final previewTawad = 'Tawad ${AniHowMoney.peso(line.tawadAmount)}';
    final previewTotal = 'Total ${AniHowMoney.peso(line.lineTotal)}';
    final previewGroupListed = 'Listed ${AniHowMoney.peso(group.listedSubtotal)}';
    final previewGroupTawad = 'Tawad ${AniHowMoney.peso(group.tawadTotal)}';
    final previewGroupTotal = 'Total ${AniHowMoney.peso(group.total)}';

    // ignore: avoid_print
    print('CHECKOUT PREVIEW RENDERED:');
    // ignore: avoid_print
    print('  $previewUnit');
    // ignore: avoid_print
    print('  $previewListed');
    // ignore: avoid_print
    print('  $previewTawad');
    // ignore: avoid_print
    print('  $previewTotal');
    // ignore: avoid_print
    print('  group $previewGroupListed / $previewGroupTawad / $previewGroupTotal');

    expect(double.parse(line.listedPrice), double.parse('$listedUnit'));
    expect(double.parse(line.tawadAmount), discount);
    expect(previewTawad, isNot('Tawad ₱0.00'));
    expect(
      double.parse(line.lineTotal),
      closeTo(double.parse(line.lineSubtotal) - double.parse(line.tawadAmount), 0.001),
    );
    expect(previewUnit, 'Listed unit ${AniHowMoney.peso(listedUnit)}');
    expect(previewListed, 'Listed ${AniHowMoney.peso(line.lineSubtotal)}');
    expect(previewTawad, 'Tawad ${AniHowMoney.peso(discount)}');
    expect(previewTotal, 'Total ${AniHowMoney.peso(line.lineTotal)}');

    final placed = await send(
      'POST',
      CartRequests.checkoutPath,
      token: buyerToken,
      body: CartRequests.checkout(
        fulfillmentPreference: CartRequests.buyerPickup,
        fulfillmentNote: 'Tawad display check.',
      ),
    );
    expect(placed.statusCode, 201);
    final order = OrderRecord.fromJson(
      Map<String, dynamic>.from((placed.data['data'] as List).first as Map),
    );
    final item = order.items.single;

    final placedUnit = 'Listed unit ${AniHowMoney.peso(item.listedPrice)}';
    final placedListed = 'Listed ${AniHowMoney.peso(item.lineSubtotal)}';
    final placedTawad = 'Tawad ${AniHowMoney.peso(item.tawadAmount ?? '0')}';
    final placedTotal = 'Total ${AniHowMoney.peso(item.lineTotal ?? item.lineSubtotal)}';
    final orderListed = 'Listed ${AniHowMoney.peso(order.listedTotal)}';
    final orderTawad = 'Tawad ${AniHowMoney.peso(order.tawadDisplay)}';
    final orderTotal = 'Total ${AniHowMoney.peso(order.total)}';

    // ignore: avoid_print
    print('ORDER PLACED RENDERED:');
    // ignore: avoid_print
    print('  $placedUnit');
    // ignore: avoid_print
    print('  $placedListed');
    // ignore: avoid_print
    print('  $placedTawad');
    // ignore: avoid_print
    print('  $placedTotal');
    // ignore: avoid_print
    print('  order $orderListed / $orderTawad / $orderTotal');

    expect(double.parse(item.listedPrice), double.parse('$listedUnit'));
    expect(double.parse(item.tawadAmount!), discount);
    expect(placedTawad, isNot('Tawad ₱0.00'));
    expect(
      double.parse(order.total),
      closeTo(double.parse(order.listedTotal) - double.parse(order.tawadDisplay), 0.001),
    );
    expect(placedUnit, 'Listed unit ${AniHowMoney.peso(listedUnit)}');
    expect(orderTawad, 'Tawad ${AniHowMoney.peso(discount)}');
    expect(orderTotal, 'Total ${AniHowMoney.peso(order.total)}');
  });
}
