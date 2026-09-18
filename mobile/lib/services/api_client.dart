import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../config/api_config.dart';
import '../models/models.dart';

class ApiException implements Exception {
  ApiException(this.message);
  final String message;

  @override
  String toString() => message;
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
          final token = await _storage.read(key: _tokenKey);
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          if (options.data is FormData) {
            options.headers.remove('Content-Type');
          }
          handler.next(options);
        },
        onError: (error, handler) {
          if (error.response?.statusCode == 401) {
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

  Future<void> saveToken(String token) =>
      _storage.write(key: _tokenKey, value: token);

  Future<String?> readToken() => _storage.read(key: _tokenKey);

  Future<void> clearToken() => _storage.delete(key: _tokenKey);

  Future<({UserAccount user, String token})> login({
    required String email,
    required String password,
  }) async {
    final response = await _post('/auth/login', {
      'email': email,
      'password': password,
      'device_name': 'anihow-mobile',
    });
    final token = response['token'] as String;
    await saveToken(token);
    return (
      user: UserAccount.fromJson(_asMap(response['data'])),
      token: token,
    );
  }

  Future<({UserAccount user, String token})> registerBuyer({
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
    );
  }

  Future<UserAccount> currentUser() async {
    final response = await _get('/auth/user');
    return UserAccount.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> logout() async {
    try {
      await _post('/auth/logout', {});
    } catch (_) {
      // Token is cleared locally even if the API call fails.
    } finally {
      await clearToken();
    }
  }

  Future<List<ListingItem>> marketplace({String? search, int? categoryId, String? sort}) async {
    return _list(
      '/buyer/marketplace',
      query: {
        if (search != null && search.isNotEmpty) 'search': search,
        'category_id': ?categoryId,
        if (sort != null && sort.isNotEmpty) 'sort': sort,
      },
      parse: ListingItem.fromJson,
    );
  }

  Future<ListingItem> marketplaceShow(int id) async {
    final response = await _get('/buyer/marketplace/$id');
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<ReservationRecord> createReservation({
    required int listingId,
    required String quantity,
    String? notes,
  }) async {
    final response = await _post('/buyer/reservations', {
      'items': [
        {'listing_id': listingId, 'quantity': quantity},
      ],
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    return ReservationRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<ReservationRecord>> buyerReservations() {
    return _list('/buyer/reservations', parse: ReservationRecord.fromJson);
  }

  Future<ReservationRecord> buyerReservation(int id) async {
    final response = await _get('/buyer/reservations/$id');
    return ReservationRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> cancelBuyerReservation(int id) =>
      _patch('/buyer/reservations/$id/cancel');

  Future<void> submitReview({
    required int reservationId,
    required int rating,
    String? comment,
  }) async {
    await _post('/buyer/reviews', {
      'reservation_id': reservationId,
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

  Future<ListingItem> farmerListing(int id) async {
    final response = await _get('/farmer/listings/$id');
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<ListingItem>> farmerListings() {
    return _list('/farmer/listings', parse: ListingItem.fromJson);
  }

  Future<ListingItem> createListing(
    Map<String, dynamic> body, {
    String? imagePath,
  }) async {
    final response = await _sendListing('/farmer/listings', body, imagePath: imagePath);
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<ListingItem> updateListing(
    int id,
    Map<String, dynamic> body, {
    String? imagePath,
  }) async {
    final response = imagePath == null
        ? await _put('/farmer/listings/$id', body)
        : await _sendListing('/farmer/listings/$id', body, imagePath: imagePath);
    return ListingItem.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> toggleListingActive(int id) =>
      _patch('/farmer/listings/$id/active');

  Future<List<ReservationRecord>> farmerReservations() {
    return _list('/farmer/reservations', parse: ReservationRecord.fromJson);
  }

  Future<void> markReservationReady(int id) =>
      _patch('/farmer/reservations/$id/ready');

  Future<void> completeReservation(int id) =>
      _patch('/farmer/reservations/$id/complete');

  Future<SaleRecord> recordSale({
    required int listingId,
    required String quantity,
    String? notes,
  }) async {
    final response = await _post('/farmer/sales', {
      'items': [
        {'listing_id': listingId, 'quantity': quantity},
      ],
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
    return SaleRecord.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<SaleRecord>> farmerSales() {
    return _list('/farmer/sales', parse: SaleRecord.fromJson);
  }

  Future<List<CropCareCategory>> cropCareCategories() {
    return _list('/farmer/crop-care/categories', parse: CropCareCategory.fromJson);
  }

  Future<List<CropCareArticle>> cropCare({String? search, int? categoryId}) {
    return _list(
      '/farmer/crop-care',
      query: {
        if (search != null && search.isNotEmpty) 'search': search,
        'category_id': ?categoryId,
      },
      parse: CropCareArticle.fromJson,
    );
  }

  Future<CropCareArticle> cropCareShow(int id) async {
    final response = await _get('/farmer/crop-care/$id');
    return CropCareArticle.fromJson(_asMap(response['data'] ?? response));
  }

  Future<List<CropCareArticle>> cropCareMine() {
    return _list('/farmer/crop-care/mine', parse: CropCareArticle.fromJson);
  }

  Future<CropCareArticle> createCropCare({
    required String title,
    required String body,
    required int categoryId,
  }) async {
    final response = await _post('/farmer/crop-care', {
      'title': title,
      'body': body,
      'category_id': categoryId,
    });
    return CropCareArticle.fromJson(_asMap(response['data'] ?? response));
  }

  Future<CropCareArticle> updateCropCare({
    required int id,
    required String title,
    required String body,
    required int categoryId,
  }) async {
    final response = await _put('/farmer/crop-care/$id', {
      'title': title,
      'body': body,
      'category_id': categoryId,
    });
    return CropCareArticle.fromJson(_asMap(response['data'] ?? response));
  }

  Future<void> deleteCropCare(int id) => _delete('/farmer/crop-care/$id');

  Future<List<CategoryItem>> categories() {
    return _list('/categories', parse: CategoryItem.fromJson);
  }

  Future<List<ReservationRecord>> buyerOrders() {
    return _list('/buyer/orders', parse: ReservationRecord.fromJson);
  }

  Future<ShopProfile> buyerShop(int sellerId) async {
    final response = await _get('/buyer/shops/$sellerId');
    return ShopProfile.fromJson(_asMap(response['data'] ?? response));
  }

  Future<PagedShopReviews> shopReviews(int sellerId, {int page = 1}) async {
    final response = await _get('/buyer/shops/$sellerId/reviews', query: {'page': page});
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

  Future<ShopProfile> updateFarmerShop(Map<String, dynamic> body) async {
    try {
      final response = await _dio.patch('/farmer/shop', data: body);
      return ShopProfile.fromJson(_asMap(_asMap(response.data)['data'] ?? response.data));
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

  Future<void> markNotificationRead(int id) => _patch('/notifications/$id/read');

  Future<void> markAllNotificationsRead() => _post('/notifications/read-all', {});

  Future<void> resendVerification() => _post('/auth/email/verification-notification', {});

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
        for (final entry in body.entries)
          if (entry.value != null) entry.key: '${entry.value}',
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

  Future<Map<String, dynamic>> _put(
    String path,
    Map<String, dynamic> body,
  ) async {
    try {
      final response = await _dio.put(path, data: body);
      return _asMap(response.data);
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
  }

  Future<void> _patch(String path, [Map<String, dynamic>? body]) async {
    try {
      await _dio.patch(path, data: body);
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
      final body = response.data;
      final raw = body is Map && body['data'] is List
          ? body['data'] as List
          : body is List
              ? body
              : const [];
      return raw
          .whereType<Map>()
          .map((item) => parse(Map<String, dynamic>.from(item)))
          .toList();
    } on DioException catch (error) {
      throw ApiException(_messageFrom(error));
    }
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

  String _messageFrom(DioException error) {
    final data = error.response?.data;
    if (data is Map && data['message'] is String) {
      final errors = data['errors'];
      if (errors is Map && errors.isNotEmpty) {
        final first = errors.values.first;
        if (first is List && first.isNotEmpty) {
          return first.first.toString();
        }
      }
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError ||
        error.type == DioExceptionType.connectionTimeout) {
      return 'Cannot reach the AniHow API at ${ApiConfig.baseUrl}. '
          'Use 10.0.2.2 on the Android emulator and keep php artisan serve running.';
    }
    return error.message ?? 'Request failed.';
  }
}
