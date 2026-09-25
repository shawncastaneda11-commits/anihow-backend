import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_client.dart';
import 'auth_controller.dart';

class CartController extends ChangeNotifier {
  CartController(this._auth) {
    _auth.addListener(_onAuth);
    if (!_auth.restoring) {
      reload();
    }
  }

  final AuthController _auth;
  CartSnapshot snapshot = const CartSnapshot();
  bool loading = false;
  Object? error;

  ApiClient get api => _auth.api;

  int get count => snapshot.items.length;

  bool get isEmpty => snapshot.items.isEmpty;

  @override
  void dispose() {
    _auth.removeListener(_onAuth);
    super.dispose();
  }

  void _onAuth() {
    if (_auth.restoring) {
      return;
    }
    if (_auth.user?.isBuyer != true) {
      snapshot = const CartSnapshot();
      error = null;
      loading = false;
      notifyListeners();
      return;
    }
    reload();
  }

  Future<void> reload() async {
    if (_auth.user?.isBuyer != true) {
      snapshot = const CartSnapshot();
      error = null;
      notifyListeners();
      return;
    }
    loading = snapshot.items.isEmpty;
    error = null;
    notifyListeners();
    try {
      final items = await _auth.api.cartItems();
      snapshot = CartSnapshot(
        items: items.where((item) => item.isPurchasable).toList(),
      );
      error = null;
    } on ApiException catch (caught) {
      error = caught;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> add({required int listingId, required String quantity}) async {
    await _auth.api.addCartItem(listingId: listingId, quantity: quantity);
    await reload();
  }

  Future<void> updateQuantity(int cartItemId, String quantity) async {
    await _auth.api.updateCartItem(cartItemId, quantity: quantity);
    await reload();
  }

  Future<void> remove(int cartItemId) async {
    await _auth.api.removeCartItem(cartItemId);
    await reload();
  }

  Future<List<OrderRecord>> checkout({
    required String fulfillmentPreference,
    String? fulfillmentNote,
  }) async {
    final orders = await _auth.api.checkout(
      fulfillmentPreference: fulfillmentPreference,
      fulfillmentNote: fulfillmentNote,
    );
    snapshot = const CartSnapshot();
    notifyListeners();
    return orders;
  }
}
