@Tags(['live'])
library;

// Live test: needs `php artisan serve` and `php artisan db:seed --class=SmokeTestSeeder`. Shares seed accounts, so run with --concurrency=1.

import 'package:anihow/config/api_config.dart';
import 'package:anihow/models/models.dart';
import 'package:anihow/services/cart_requests.dart';
import 'package:anihow/services/tawad_requests.dart';
import 'package:anihow/theme/anihow_space.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('tawad UI client paths create both types, refuse ceiling and floor, then checkout', () async {
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
          'device_name': 'anihow-pass9',
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
    final listingJson = Map<String, dynamic>.from((listings.data['data'] as List).first as Map);
    var listing = ListingItem.fromJson(listingJson);
    final listingId = listing.id;
    final price = double.parse(listing.pricePerUnit);
    final maxDiscount = double.parse(listing.category?.maxDiscount ?? '20');
    final floor = double.parse(listing.category?.floorPrice ?? '25');

    final flat = await send(
      'POST',
      TawadRequests.storePath(listingId),
      token: sellerToken,
      body: TawadRequests.save(type: TawadRequests.flat, discountAmount: '5'),
    );
    expect(flat.statusCode, 201, reason: '${flat.data}');
    expect(flat.data['data']['type'], TawadRequests.flat);

    final shownFlat = ListingItem.fromJson(
      Map<String, dynamic>.from(
        (await send('GET', '/farmer/listings/$listingId', token: sellerToken)).data['data'] as Map,
      ),
    );
    expect(shownFlat.tawad?.isFlat, isTrue);
    expect(double.parse(shownFlat.tawad!.discountAmount), 5);

    final minimum = await send(
      'POST',
      TawadRequests.storePath(listingId),
      token: sellerToken,
      body: TawadRequests.save(
        type: TawadRequests.minQuantity,
        discountAmount: '12',
        minQuantity: '4',
      ),
    );
    expect(minimum.statusCode, 201, reason: '${minimum.data}');
    expect(minimum.data['data']['type'], TawadRequests.minQuantity);
    final ruleId = minimum.data['data']['id'] as int;

    final shownMin = ListingItem.fromJson(
      Map<String, dynamic>.from(
        (await send('GET', '/farmer/listings/$listingId', token: sellerToken)).data['data'] as Map,
      ),
    );
    expect(shownMin.tawad?.id, ruleId);
    expect(shownMin.tawad?.summary, contains('₱12.00'));

    final ceiling = await send(
      'POST',
      TawadRequests.storePath(listingId),
      token: sellerToken,
      body: TawadRequests.save(
        type: TawadRequests.flat,
        discountAmount: '${maxDiscount + 1}',
      ),
    );
    expect(ceiling.statusCode, 422);
    expect('${ceiling.data}', contains('maximum tawad'));

    final floorBreach = await send(
      'POST',
      TawadRequests.storePath(listingId),
      token: sellerToken,
      body: TawadRequests.save(
        type: TawadRequests.flat,
        discountAmount: (price - floor + 1).toStringAsFixed(2),
      ),
    );
    expect(floorBreach.statusCode, 422);
    expect('${floorBreach.data}', contains('floor'));

    final stillMin = ListingItem.fromJson(
      Map<String, dynamic>.from(
        (await send('GET', '/farmer/listings/$listingId', token: sellerToken)).data['data'] as Map,
      ),
    );
    expect(stillMin.tawad?.id, ruleId);

    final buyerLogin = await send(
      'POST',
      '/auth/login',
      body: {
        'email': 'smoke.buyer@anihow.local',
        'password': 'password',
        'device_name': 'anihow-pass9',
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
      body: CartRequests.addItem(listingId: listingId, quantity: '4'),
    );
    expect(added.statusCode, 201);
    final line = CartLine.fromJson(Map<String, dynamic>.from(added.data['data'] as Map));
    expect(double.parse(line.listedPrice), price);
    expect(double.parse(line.tawadAmount), 12);
    expect(
      double.parse(line.lineTotal),
      closeTo(double.parse(line.lineSubtotal) - 12, 0.001),
    );
    expect('Tawad ${AniHowMoney.peso(line.tawadAmount)}', 'Tawad ₱12.00');
    expect('Listed ${AniHowMoney.peso(line.lineSubtotal)}', 'Listed ₱120.00');
    expect('Total ${AniHowMoney.peso(line.lineTotal)}', 'Total ₱108.00');

    final placed = await send(
      'POST',
      CartRequests.checkoutPath,
      token: buyerToken,
      body: CartRequests.checkout(
        fulfillmentPreference: CartRequests.buyerPickup,
        fulfillmentNote: 'Pass 9 tawad checkout.',
      ),
    );
    expect(placed.statusCode, 201);
    final order = OrderRecord.fromJson(
      Map<String, dynamic>.from((placed.data['data'] as List).first as Map),
    );
    expect(double.parse(order.items.single.listedPrice), price);
    expect(double.parse(order.tawadDisplay), 12);
    expect(
      double.parse(order.total),
      closeTo(double.parse(order.listedTotal) - double.parse(order.tawadDisplay), 0.001),
    );

    final ended = await send(
      'DELETE',
      TawadRequests.destroyPath(listingId, ruleId),
      token: sellerToken,
    );
    expect(ended.statusCode, 200);
    final afterEnd = ListingItem.fromJson(
      Map<String, dynamic>.from(
        (await send('GET', '/farmer/listings/$listingId', token: sellerToken)).data['data'] as Map,
      ),
    );
    expect(afterEnd.tawad, isNull);
  });
}
