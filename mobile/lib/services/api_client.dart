import 'dart:convert';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/api_config.dart';
import '../models/models.dart';
import 'cart_requests.dart';
import 'tawad_requests.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.body});
  final String message;
  final int? statusCode;
  final Map<String, dynamic>? body;

  @override
  String toString() => message;
}

class PagedItems<T> {
  const PagedItems({required this.items, required this.complete});

  final List<T> items;
  final bool complete;
}

class MarketplaceFeed {
  const MarketplaceFeed({required this.items, this.mixDay});

  final List<ListingItem> items;
  final String? mixDay;
}

const _datesClearedAsEmpty = {
  'available_from',
  'available_until',
  'harvested_on',
  'growing_method',
};

Map<String, dynamic> buyerAnnouncementQuery({
  required bool following,
  int? farmId,
  required int page,
}) {
  return {'page': page, if (following) 'following': 1, 'farm_id': ?farmId};
}

/// Multipart fields for a listing save. A cleared availability or harvest date
/// is sent as an empty string so the server can null it. Other null fields
/// stay out of the body.
Map<String, String> listingMultipartFields(Map<String, dynamic> body) {
  return {
    for (final entry in body.entries)
      if (entry.value != null)
        entry.key: '${entry.value}'
      else if (_datesClearedAsEmpty.contains(entry.key))
        entry.key: '',
  };
}

class ApiClient {
  ApiClient({required this.onUnauthorized})
    : _dio = Dio(
        BaseOptions(
          baseUrl: ApiConfig.baseUrl,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
          connectTimeout: const Duration(seconds: 20),
          receiveTimeout: const Duration(seconds: 20),
        ),
      ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await readToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          options.headers['Accept-Language'] = acceptLanguage;
          if (options.data is FormData) {
            options.headers.remove('Content-Type');
          }
          handler.next(options);
        },
        onError: (error, handler) {
          final path = error.requestOptions.path;
          final isCredentialAttempt =
              path.contains('/auth/login') ||
              path.contains('/auth/register') ||
              path.contains('/auth/forgot-password') ||
              path.contains('/auth/reset-password');
          if (error.response?.statusCode == 401 && !isCredentialAttempt) {
            onUnauthorized();
          }
          handler.next(error);
        },
      ),
    );
  }

  static const _tokenKey = 'anihow_token';
  static const _storage = FlutterSecureStorage();

  final Dio _dio;
  final void Function() onUnauthorized;
  String acceptLanguage = 'en';

  /// Set only when the user turns Remember me off. It is never written to disk.
  String? _sessionToken;

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<String?> readToken() async {
    final sessionToken = _sessionToken;
    if (sessionToken != null && sessionToken.isNotEmpty) {
      return sessionToken;
    }

    return _storage.read(key: _tokenKey);
  }

  Future<void> clearToken() async {
    _sessionToken = null;
    await _storage.delete(key: _tokenKey);
  }

  Future<({UserAccount user, String token})> login({
    required String email,
    required String password,
    bool remember = true,
  }) async {
    final response = await _post('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'anihow-mobile',
      'remember': remember,
    });
    final token = response['token'] as String;
    if (remember) {
      _sessionToken = null;
      await saveToken(token);
    } else {
      await clearToken();
      _sessionToken = token;
    }
    return (user: UserAccount.fromJson(_asMap(response['data'])), token: token);
  }

  Future<void> forgotPassword(String email) async {
    await _post('/auth/forgot-password', {'email': email});
  }

  Future<void> resetPassword(
    String email,
    String code,
    String password,
    String passwordConfirmation,
  ) async {
    await _post('/auth/reset-password', {
      'email': email,
      'code': code,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  Future<({UserAccount user, String token, String? verificationCode})>
  registerBuyer({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    final response = await _post('/auth/register', {
      'name': name,
      'email': email,
      'password': password,
      'password_confirmation': passwordConfirmation,
      if (phone != null && phone.isNotEmpty) 'phone': phone,
      'device_name': 'anihow-mobile',
    });
    final token = response['token'] as String;
    await saveToken(token);
    return (
      user: UserAccount.fromJson(_asMap(response['data'])),
      token: token,
      verificationCode: response['verification_code'] as String?,
    );
  }

  Future<UserAccount> currentUser() async {
    final response = await _get('/auth/user');
    return UserAccount.fromJson(_asMap(response['data'] ?? response));
  }

  Future<UserAccount> updateProfile({
    required String name,
    String? phone,
    String? location,
  }) async {
    final response = await _patchJson('/auth/user', {
      'name': name,
      'phone': phone,
      'location': location,
    });
    return UserAccount.fromJson(_asMap(response['data'] ?? response));
  }

  Future<Map<String, dynamic>> exportOwnData() => _get('/auth/user/export');

  Future<String> saveOwnDataExport() async {
    final data = await exportOwnData();
    final date = DateTime.now().toIso8601String().split('T').first;
    final file = File('${Directory.systemTemp.path}/anihow-my-data-$date.json');
    await file.writeAsString(const JsonEncoder.withIndent('  ').convert(data));
    return file.path;
  }

  Future<AccountDeletionRequest?> deletionRequest() async {
    final response = await _get('/auth/user/deletion-request');
    final data = response['data'];
    if (data is Map) {
      return AccountDeletionRequest.fromJson(Map<String, dynamic>.from(data));
    }
    return null;
  }

  Future<AccountDeletionRequest> requestAccountDeletion({
    String? reason,
  }) async {
    final response = await _post('/auth/user/deletion-request', {
      if (reason != null && reason.isNotEmpty) 'reason': reason,
    });
    return AccountDeletionRequest.fromJson(
      _asMap(response['data'] ?? response),
    );
  }

  Future<void> cancelAccountDeletion() =>
      _delete('/auth/user/deletion-request');

  Future<void> logout() async {
    try {
      await _post('/auth/logout', {});
    } catch (_) {
      // Token is cleared locally even if the API call fails.
    } finally {
      await clearToken();
    }
  }

  Future<MarketplaceFeed> marketplace({
    String? search,
    int? cropTypeId,
    String? sort,
    String? category,
    double? nearLat,
    double? nearLng,
    String? growingMethod,
    int? page,
    String? mixDay,
  }) async {
    try {
      final response = await _dio.get(
        '/buyer/marketplace',
        queryParameters: {
          if (search != null && search.isNotEmpty) 'search': search,
          'crop_type_id': ?cropTypeId,
          if (sort != null && sort.isNotEmpty) 'sort': sort,
          if (category != null && category.isNotEmpty) 'category': category,
          if (nearLat != null && nearLng != null) 'near_lat': nearLat,
          if (nearLat != null && nearLng != null) 'near_lng': nearLng,
          if (growingMethod != null && growingMethod.isNotEmpty)
            'growing_method': growingMethod,
          if (page != null && page > 1) 'page': page,
          if (mixDay != null && mixDay.isNotEmpty) 'mix_day': mixDay,
        },
      );
      final body = _asMap(response.data);
      final meta = _asMap(body['meta']);
      final mix = meta['mix_day'];

      return MarketplaceFeed(
        items: _parseList(response.data, ListingItem.fromJson),
        mixDay: mix is String && mix.isNotEmpty ? mix : null,
      );
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<ListingItem> marketplaceShow(int id) async {
    final response = await _get('/buyer/marketplace/$id');
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> submitReport({
    required String targetType,
    required int targetId,
    required String reason,
    String? details,
  }) {
    return _post('/reports', {
      'target_type': targetType,
      'target_id': targetId,
      'reason': reason,
      if (details != null && details.isNotEmpty) 'details': details,
    });
  }

  Future<void> submitReview({
    required int orderId,
    required int rating,
    String? comment,
  }) async {
    await _post('/buyer/reviews', {
      'order_id': orderId,
      'rating': rating,
      if (comment != null && comment.isNotEmpty) 'comment': comment,
    });
  }

  Future<List<FavoriteRecord>> favorites() {
    return _list('/buyer/favorites', parse: FavoriteRecord.fromJson);
  }

  Future<void> addFavorite(int listingId) =>
      _post('/buyer/favorites', {'listing_id': listingId});

  Future<void> removeFavorite(int listingId) =>
      _delete('/buyer/favorites/$listingId');

  Future<List<ShopFavoriteRecord>> shopFavorites() {
    return _list('/buyer/shop-favorites', parse: ShopFavoriteRecord.fromJson);
  }

  Future<void> addShopFavorite(int sellerId) =>
      _post('/buyer/shop-favorites', {'farmer_seller_id': sellerId});

  Future<void> removeShopFavorite(int sellerId) =>
      _delete('/buyer/shop-favorites/$sellerId');

  Future<List<FarmFavoriteRecord>> farmFavorites() {
    return _list('/buyer/farm-favorites', parse: FarmFavoriteRecord.fromJson);
  }

  Future<void> addFarmFavorite(int farmId) =>
      _post('/buyer/farm-favorites', {'farm_id': farmId});

  Future<void> removeFarmFavorite(int farmId) =>
      _delete('/buyer/farm-favorites/$farmId');

  Future<ListingItem> farmerListing(int id) async {
    final response = await _get('/farmer/listings/$id');
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<ListingItem>> farmerListings() {
    return _list('/farmer/listings', parse: ListingItem.fromJson);
  }

  Future<PagedItems<ListingItem>> farmerListingsPaged() {
    return _listPages('/farmer/listings', parse: ListingItem.fromJson);
  }

  Future<ListingItem> createListing(
    Map<String, dynamic> body, {
    String? imagePath,
  }) async {
    final response = await _sendListing(
      '/farmer/listings',
      body,
      imagePath: imagePath,
    );
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<ListingItem> updateListing(
    int id,
    Map<String, dynamic> body, {
    String? imagePath,
    bool confirmCancelReservations = false,
  }) async {
    final payload = {
      ...body,
      if (confirmCancelReservations) 'confirm_cancel_reservations': true,
    };
    final response = imagePath == null
        ? await _put('/farmer/listings/$id', payload)
        : await _sendListing(
            '/farmer/listings/$id',
            payload,
            imagePath: imagePath,
          );
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<ListingItem> toggleListingActive(
    int id, {
    required bool isActive,
    bool confirmCancelReservations = false,
  }) async {
    try {
      final response = await _dio.patch(
        '/farmer/listings/$id/active',
        data: {
          'is_active': isActive,
          if (confirmCancelReservations) 'confirm_cancel_reservations': true,
        },
      );
      return ListingItem.fromJson(
        _asMap(_asMap(response.data)['data'] ?? response.data),
      );
    } on DioException catch (error) {
      throw _apiException(error);
    }
  }

  Future<void> deleteListing(
    int id, {
    bool confirmCancelReservations = false,
  }) async {
    try {
      await _dio.delete(
        '/farmer/listings/$id',
        data: {
          if (confirmCancelReservations) 'confirm_cancel_reservations': true,
        },
      );
    } on DioException catch (error) {
      throw _apiException(error);
    }
  }

  Future<TawadRule> saveTawad({
    required int listingId,
    required String type,
    required String discountAmount,
    String? minQuantity,
  }) async {
    final response = await _post(
      TawadRequests.storePath(listingId),
      TawadRequests.save(
        type: type,
        discountAmount: discountAmount,
        minQuantity: minQuantity,
      ),
    );
    return TawadRule.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> endTawad({required int listingId, required int tawadRuleId}) {
    return _delete(TawadRequests.destroyPath(listingId, tawadRuleId));
  }

  Future<List<CropCareArticle>> cropCare({
    String? search,
    String? category,
    int? cropTypeId,
    int? farmId,
  }) async {
    final pages = await _listPages(
      '/crop-care',
      query: {
        if (search != null && search.isNotEmpty) 'search': search,
        if (category != null && category.isNotEmpty) 'category': category,
        'crop_type_id': ?cropTypeId,
        'farm_id': ?farmId,
      },
      parse: CropCareArticle.fromJson,
    );
    return pages.items;
  }

  Future<CropCareArticle> cropCareShow(int id) async {
    final response = await _get('/crop-care/$id');
    return CropCareArticle.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<CategoryItem>> cropTypes() {
    return _list('/crop-types', parse: CategoryItem.fromJson);
  }

  Future<ReservationRecord> reserveListing({
    required int listingId,
    required String quantity,
    required String fulfillmentPreference,
    String? fulfillmentNote,
  }) async {
    final response = await _post('/buyer/reservations', {
      'listing_id': listingId,
      'quantity': quantity,
      'fulfillment_preference': fulfillmentPreference,
      if (fulfillmentNote != null && fulfillmentNote.isNotEmpty)
        'fulfillment_note': fulfillmentNote,
    });
    return ReservationRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<ReservationRecord>> buyerReservations() {
    return _list('/buyer/reservations', parse: ReservationRecord.fromJson);
  }

  Future<void> cancelBuyerReservation(int id) {
    return _patch('/buyer/reservations/$id');
  }

  Future<List<ReservationRecord>> farmerListingReservations(int listingId) {
    return _list(
      '/farmer/listings/$listingId/reservations',
      parse: ReservationRecord.fromJson,
    );
  }

  Future<void> cancelFarmerReservation(
    int listingId,
    int reservationId, {
    String? note,
  }) {
    return _patch('/farmer/listings/$listingId/reservations/$reservationId', {
      if (note != null && note.isNotEmpty) 'note': note,
    });
  }

  Future<void> openListingNow(int listingId) async {
    await _post('/farmer/listings/$listingId/open', {});
  }

  Future<List<OrderRecord>> buyerOrders() async {
    final pages = await _listPages(
      '/buyer/orders',
      parse: OrderRecord.fromJson,
    );
    return pages.items;
  }

  Future<OrderRecord> buyerOrder(int id) async {
    final response = await _get('/buyer/orders/$id');
    return OrderRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<OrderMessage>> orderMessages(int orderId, {int? afterId}) {
    return _list(
      '/orders/$orderId/messages',
      query: {'after_id': ?afterId},
      parse: OrderMessage.fromJson,
    );
  }

  Future<OrderMessage> sendOrderMessage(
    int orderId, {
    required String body,
  }) async {
    final response = await _post('/orders/$orderId/messages', {'body': body});
    return OrderMessage.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<StallChat>> stallChats() {
    return _list('/stall-chats', parse: StallChat.fromJson);
  }

  Future<void> removeStallChat(int conversationId) =>
      _delete('/stall-chats/$conversationId');

  Future<StallChat> openStallChat(int sellerId) async {
    final response = await _post('/stall-chats', {
      'farmer_seller_id': sellerId,
    });
    return StallChat.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<OrderMessage>> stallMessages(int conversationId, {int? afterId}) {
    return _list(
      '/stall-chats/$conversationId/messages',
      query: {'after_id': ?afterId},
      parse: OrderMessage.fromJson,
    );
  }

  Future<int?> orderStallConversationId(int orderId) async {
    final response = await _get('/orders/$orderId/messages');
    final value = response['stall_conversation_id'];
    if (value == null) {
      return null;
    }
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse('$value');
  }

  Future<List<int>> downloadAuthorized(String url) async {
    try {
      final response = await _dio.get<List<int>>(
        url,
        options: Options(responseType: ResponseType.bytes),
      );
      return response.data ?? const [];
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<OrderMessage> sendStallMessage(
    int conversationId, {
    required String body,
    int? orderId,
    int? listingId,
    String? attachmentPath,
  }) async {
    if (attachmentPath == null || attachmentPath.isEmpty) {
      final response = await _post('/stall-chats/$conversationId/messages', {
        'body': body,
        'order_id': ?orderId,
        'listing_id': ?listingId,
      });
      return OrderMessage.fromJson(_asMap(response['data'] ?? response));
    }

    try {
      final response = await _dio.post(
        '/stall-chats/$conversationId/messages',
        data: FormData.fromMap({
          if (body.trim().isNotEmpty) 'body': body.trim(),
          'order_id': ?orderId,
          'listing_id': ?listingId,
          'attachment': await MultipartFile.fromFile(attachmentPath),
        }),
      );
      final data = _asMap(response.data);
      return OrderMessage.fromJson(_asMap(data['data'] ?? data));
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<List<FaqSuggestion>> faqSuggestions() async {
    final response = await _get('/faq');
    final data = _asMap(response['data'] ?? response);
    return ((data['suggestions'] as List?) ?? const [])
        .whereType<Map>()
        .map((item) => FaqSuggestion.fromJson(Map<String, dynamic>.from(item)))
        .toList();
  }

  Future<FaqAnswer> askFaq(String question) async {
    final response = await _post('/faq/ask', {'question': question});
    return FaqAnswer.fromJson(_asMap(response['data'] ?? response));
  }

  Future<Map<String, dynamic>> authorizeBroadcast({
    required String socketId,
    required String channelName,
  }) {
    return _postAbsolute(ApiConfig.broadcastingAuthUrl, {
      'socket_id': socketId,
      'channel_name': channelName,
    });
  }

  Future<List<CartLine>> cartItems() {
    return _list(CartRequests.cartPath, parse: CartLine.fromJson);
  }

  Future<CartLine> addCartItem({
    required int listingId,
    required String quantity,
  }) async {
    final response = await _post(
      CartRequests.cartPath,
      CartRequests.addItem(listingId: listingId, quantity: quantity),
    );
    return CartLine.fromJson(_asMap(response['data'] ?? response));
  }

  Future<CartLine> updateCartItem(int id, {required String quantity}) async {
    final response = await _patchJson(
      CartRequests.cartItemPath(id),
      CartRequests.updateItem(quantity: quantity),
    );
    return CartLine.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> removeCartItem(int id) => _delete(CartRequests.cartItemPath(id));

  Future<List<OrderRecord>> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
    List<Map<String, dynamic>> payments = const [],
  }) async {
    final response = await _post(
      CartRequests.checkoutPath,
      CartRequests.checkout(
        fulfillmentPreference: fulfillmentPreference,
        fulfillmentNote: fulfillmentNote,
        payments: payments,
      ),
    );
    return _parseList(response, OrderRecord.fromJson);
  }

  Future<List<OrderRecord>> farmerOrders({String? status}) async {
    final pages = await _listPages(
      '/farmer/orders',
      query: {if (status != null && status.isNotEmpty) 'status': status},
      parse: OrderRecord.fromJson,
    );
    return pages.items;
  }

  Future<OrderRecord> farmerOrder(int id) async {
    final response = await _get('/farmer/orders/$id');
    return OrderRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<OrderRecord> confirmOrder(int id) =>
      _farmerOrderAction('/farmer/orders/$id/confirm');

  Future<OrderRecord> markOrderReady(int id) =>
      _farmerOrderAction('/farmer/orders/$id/ready');

  Future<OrderRecord> completeOrder(int id, {required String amountReceived}) {
    return _farmerOrderAction('/farmer/orders/$id/complete', {
      'amount_received': amountReceived,
    });
  }

  Future<OrderRecord> cancelOrder(
    int id, {
    required String reason,
    String? note,
  }) {
    return _farmerOrderAction('/farmer/orders/$id/cancel', {
      'reason': reason,
      if (note != null && note.isNotEmpty) 'note': note,
    });
  }

  Future<OrderRecord> recordWalkInSale({
    required int listingId,
    required String quantity,
    required String amountReceived,
    String? buyerName,
    String? note,
  }) async {
    final response = await _post('/farmer/walk-in-sales', {
      'listing_id': listingId,
      'quantity': quantity,
      'amount_received': amountReceived,
      if (buyerName != null && buyerName.isNotEmpty) 'buyer_name': buyerName,
      if (note != null && note.isNotEmpty) 'note': note,
    });
    return OrderRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<OrderRecord> _farmerOrderAction(
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    final response = await _patchJson(path, body);
    return OrderRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<ShopProfile>> buyerShops({
    String? sort,
    double? nearLat,
    double? nearLng,
  }) {
    return _list(
      '/buyer/shops',
      query: {
        if (sort != null && sort.isNotEmpty) 'sort': sort,
        if (nearLat != null && nearLng != null) 'near_lat': nearLat,
        if (nearLat != null && nearLng != null) 'near_lng': nearLng,
      },
      parse: ShopProfile.fromJson,
    );
  }

  Future<ShopProfile> buyerShop(int sellerId) async {
    final response = await _get('/buyer/shops/$sellerId');
    return ShopProfile.fromJson(_asMap(response['data'] ?? response));
  }

  Future<PagedShopReviews> shopReviews(int sellerId, {int page = 1}) {
    return _pagedShopReviews('/buyer/shops/$sellerId/reviews', page: page);
  }

  Future<PagedShopReviews> farmerShopReviews({int page = 1}) {
    return _pagedShopReviews('/farmer/shop/reviews', page: page);
  }

  Future<PagedShopReviews> _pagedShopReviews(
    String path, {
    int page = 1,
  }) async {
    final response = await _get(path, query: {'page': page});
    final meta = _asMap(response['meta']);
    return PagedShopReviews(
      reviews: ((response['data'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => ShopReview.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      currentPage: _asInt(meta['current_page'], page),
      lastPage: _asInt(meta['last_page'], 1),
      averageRating: response['average_rating']?.toString(),
      reviewsCount: _asInt(response['reviews_count']),
    );
  }

  Future<ShopProfile> farmerShop() async {
    final response = await _get('/farmer/shop');
    return ShopProfile.fromJson(_asMap(response['data'] ?? response));
  }

  Future<FarmProfile> farm(int farmId) async {
    final response = await _get('/farms/$farmId');
    return FarmProfile.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<FarmAnnouncement>> farmerAnnouncements() {
    return _list('/farmer/announcements', parse: FarmAnnouncement.fromJson);
  }

  Future<PagedBuyerAnnouncements> buyerAnnouncements({
    bool following = false,
    int? farmId,
    int page = 1,
  }) async {
    final response = await _get(
      '/buyer/announcements',
      query: buyerAnnouncementQuery(
        following: following,
        farmId: farmId,
        page: page,
      ),
    );
    final meta = _asMap(response['meta']);
    return PagedBuyerAnnouncements(
      items: ((response['data'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                BuyerFarmAnnouncement.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      currentPage: _asInt(meta['current_page'], page),
      lastPage: _asInt(meta['last_page'], 1),
    );
  }

  Future<FarmerAnalytics> farmerAnalytics({String period = 'week'}) async {
    final response = await _get('/farmer/analytics', query: {'period': period});
    return FarmerAnalytics.fromJson(_asMap(response['data'] ?? response));
  }

  Future<ShopProfile> updateFarmerShop(Map<String, dynamic> body) async {
    try {
      final response = await _dio.patch('/farmer/shop', data: body);
      return ShopProfile.fromJson(
        _asMap(_asMap(response.data)['data'] ?? response.data),
      );
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<List<AppNotification>> notifications() {
    return _list('/notifications', parse: AppNotification.fromJson);
  }

  Future<int> unreadNotificationCount() async {
    final response = await _get('/notifications/unread-count');
    final data = _asMap(response['data'] ?? response);
    final count = data['unread_count'];
    if (count is int) {
      return count;
    }
    if (count is num) {
      return count.toInt();
    }
    return int.tryParse('$count') ?? 0;
  }

  Future<void> markNotificationRead(int id) =>
      _patch('/notifications/$id/read');

  Future<void> markAllNotificationsRead() =>
      _post('/notifications/read-all', {});

  Future<String?> resendVerification() async {
    final response = await _post('/auth/email/verification-notification', {});
    return response['verification_code'] as String?;
  }

  Future<void> verifyEmail(String code) =>
      _post('/auth/email/verify', {'code': code});

  Future<UserAccount> uploadAvatar(String imagePath) async {
    try {
      final response = await _dio.post(
        '/auth/user/avatar',
        data: FormData.fromMap({
          'image': await MultipartFile.fromFile(imagePath),
        }),
      );
      return UserAccount.fromJson(_asMap(_asMap(response.data)['data']));
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<UserAccount> deleteAvatar() async {
    try {
      final response = await _dio.delete('/auth/user/avatar');
      return UserAccount.fromJson(_asMap(_asMap(response.data)['data']));
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<ShopProfile> uploadShopCover(String imagePath) async {
    try {
      final response = await _dio.post(
        '/farmer/shop/cover',
        data: FormData.fromMap({
          'image': await MultipartFile.fromFile(imagePath),
        }),
      );
      return ShopProfile.fromJson(_asMap(_asMap(response.data)['data']));
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<ShopProfile> deleteShopCover() async {
    try {
      final response = await _dio.delete('/farmer/shop/cover');
      return ShopProfile.fromJson(_asMap(_asMap(response.data)['data']));
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) => _post('/auth/password', {
    'current_password': currentPassword,
    'password': password,
    'password_confirmation': passwordConfirmation,
  });

  Future<Map<String, dynamic>> _sendListing(
    String path,
    Map<String, dynamic> body, {
    String? imagePath,
  }) async {
    if (imagePath == null || imagePath.isEmpty) {
      return _post(path, body);
    }
    try {
      final map = <String, dynamic>{
        ...listingMultipartFields(body),
        'image': await MultipartFile.fromFile(imagePath),
      };
      final response = await _dio.post(path, data: FormData.fromMap(map));
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<Map<String, dynamic>> _get(
    String path, {
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<Map<String, dynamic>> _post(
    String path,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.post(path, data: body);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<Map<String, dynamic>> _postAbsolute(
    String url,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.postUri(Uri.parse(url), data: body);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<Map<String, dynamic>> _put(
    String path,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.put(path, data: body);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw _apiException(error);
    }
  }

  Future<void> _patch(String path, [Map<String, dynamic>? body]) async {
    await _patchJson(path, body);
  }

  Future<Map<String, dynamic>> _patchJson(
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    try {
      final response = await _dio.patch(path, data: body);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<void> _delete(String path) async {
    try {
      await _dio.delete(path);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<List<T>> _list<T>(
    String path, {
    required T Function(Map<String, dynamic>) parse,
    Map<String, dynamic>? query,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: query);
      return _parseList(response.data, parse);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<PagedItems<T>> _listPages<T>(
    String path, {
    required T Function(Map<String, dynamic>) parse,
    Map<String, dynamic>? query,
  }) async {
    const maxPages = 20;
    try {
      final items = <T>[];
      var page = 1;
      var lastPage = 1;
      do {
        final response = await _dio.get(
          path,
          queryParameters: {...?query, 'page': page},
        );
        items.addAll(_parseList(response.data, parse));
        final body = response.data;
        final meta = body is Map ? _asMap(body['meta']) : <String, dynamic>{};
        lastPage = _asInt(meta['last_page'], 1);
        page++;
      } while (page <= lastPage && page <= maxPages);
      return PagedItems(items: items, complete: lastPage <= maxPages);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  List<T> _parseList<T>(dynamic body, T Function(Map<String, dynamic>) parse) {
    final raw = body is Map && body['data'] is List
        ? body['data'] as List
        : body is List
        ? body
        : const [];
    return raw
        .whereType<Map>()
        .map((item) => parse(Map<String, dynamic>.from(item)))
        .toList();
  }

  Map<String, dynamic> _asMap(dynamic value) {
    if (value is Map<String, dynamic>) {
      return value;
    }
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }
    return <String, dynamic>{};
  }

  int _asInt(dynamic value, [int fallback = 0]) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse('$value') ?? fallback;
  }

  ApiException _apiException(DioException error) {
    final data = error.response?.data;
    return ApiException(
      _messageFrom(error),
      statusCode: error.response?.statusCode,
      body: data is Map ? _asMap(data) : null,
    );
  }

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        // Prefer field-specific reasons (e.g. open orders blocking deletion).
        final status = errors['status'];
        if (status is List && status.isNotEmpty) {
          return status.first.toString();
        }
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return first.first.toString();
        }
        if (first is String && first.isNotEmpty) {
          return first;
        }
      }
      final message = data['message'];
      if (message is String && message.isNotEmpty) {
        return message;
      }
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.sendTimeout ||
        error.response == null) {
      debugPrint(
        'AniHow API unreachable (${error.type.name}) at ${ApiConfig.baseUrl}: ${error.message}',
      );
      return 'Cannot connect. Check your internet connection and try again.';
    }
    return error.message ?? 'Request failed.';
  }
}
