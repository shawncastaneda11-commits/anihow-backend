import 'package:anihow/config/api_config.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/services/cart_requests.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

/// Hits the running API with the same paths and JSON maps ApiClient sends.
void main() {
  test('multi-seller cart splits at checkout through client request shapes', () async {
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

    late final Response<dynamic> login;
    try {
      login = await send(
        'POST',
        '/auth/login',
        body: {
          'email': 'smoke.buyer@anihow.local',
          'password': 'password',
          'device_name': 'anihow-pass8',
        },
      );
    } on DioException catch (error) {
      markTestSkipped('API not reachable at ${ApiConfig.localHost}/api: ${error.message}');
      return;
    }
    expect(login.statusCode, 200, reason: 'Need the API at ${ApiConfig.localHost}/api');
    final token = login.data['token'] as String;

    final market = await send('GET', '/buyer/marketplace', token: token);
    expect(market.statusCode, 200);
    final listings = ((market.data['data'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
    final bySeller = <int, Map<String, dynamic>>{};
    for (final listing in listings) {
      final seller = listing['seller'];
      if (seller is! Map) {
        continue;
      }
      final sellerId = seller['id'];
      if (sellerId is int) {
        bySeller.putIfAbsent(sellerId, () => listing);
      }
    }
    expect(bySeller.length, greaterThanOrEqualTo(2), reason: 'Need listings from two sellers');
    final pair = bySeller.values.take(2).toList();

    final cart = await send('GET', CartRequests.cartPath, token: token);
    expect(cart.statusCode, 200);
    for (final item in (cart.data['data'] as List?) ?? const []) {
      if (item is Map && item['id'] is int) {
        final deleted = await send('DELETE', CartRequests.cartItemPath(item['id'] as int), token: token);
        expect(deleted.statusCode, 200);
      }
    }

    final firstAdd = CartRequests.addItem(listingId: pair[0]['id'] as int, quantity: '6');
    final secondAdd = CartRequests.addItem(listingId: pair[1]['id'] as int, quantity: '2');
    expect(firstAdd.keys, unorderedEquals(['listing_id', 'quantity']));

    final addedA = await send('POST', CartRequests.cartPath, token: token, body: firstAdd);
    final addedB = await send('POST', CartRequests.cartPath, token: token, body: secondAdd);
    expect(addedA.statusCode, 201);
    expect(addedB.statusCode, 201);

    final loaded = await send('GET', CartRequests.cartPath, token: token);
    expect(loaded.statusCode, 200);
    final snapshot = CartSnapshot(
      items: ((loaded.data['data'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => CartLine.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
    expect(snapshot.upcomingOrderCount, 2);
    expect(snapshot.splitMessage, contains('2 orders'));
    expect(snapshot.items.every((item) => item.listedPrice.isNotEmpty), isTrue);

    final checkoutBody = CartRequests.checkout(
      fulfillmentPreference: CartRequests.buyerPickup,
      fulfillmentNote: 'Pass 8 multi-seller split.',
    );
    expect(checkoutBody.containsKey('payment_method'), isFalse);

    final placed = await send(
      'POST',
      CartRequests.checkoutPath,
      token: token,
      body: checkoutBody,
    );
    expect(placed.statusCode, 201);
    final orders = ((placed.data['data'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => OrderRecord.fromJson(Map<String, dynamic>.from(item)))
        .toList();
    expect(orders.length, 2);
    expect(orders.map((order) => order.sellerId).toSet().length, 2);
    expect(placed.data['message'] as String, contains('split'));
    for (final order in orders) {
      expect(order.status, 'placed');
      expect(order.paymentLabel, 'Cash on handover');
      expect(order.items, isNotEmpty);
      expect(order.items.first.listedPrice, isNotEmpty);
    }
  });
}
