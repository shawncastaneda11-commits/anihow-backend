import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_client.dart';

class AuthController extends ChangeNotifier {
  AuthController() {
    api = ApiClient(onUnauthorized: () {
      user = null;
      notifyListeners();
      api.clearToken();
    });
  }

  late final ApiClient api;
  UserAccount? user;
  bool restoring = true;
  String? error;

  Future<void> restoreSession() async {
    restoring = true;
    notifyListeners();
    try {
      final token = await api.readToken();
      if (token == null || token.isEmpty) {
        user = null;
        return;
      }
      user = await api.currentUser();
    } catch (_) {
      user = null;
      await api.clearToken();
    } finally {
      restoring = false;
      notifyListeners();
    }
  }

  Future<bool> login(String email, String password) async {
    error = null;
    notifyListeners();
    try {
      final result = await api.login(email: email, password: password);
      user = result.user;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    String? phone,
  }) async {
    error = null;
    notifyListeners();
    try {
      final result = await api.registerBuyer(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
        phone: phone,
      );
      user = result.user;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<void> refreshUser() async {
    user = await api.currentUser();
    notifyListeners();
  }

  Future<void> logout() async {
    await api.logout();
    user = null;
    notifyListeners();
  }
}
