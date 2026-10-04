import 'package:flutter/foundation.dart';

import '../models/models.dart';
import '../services/api_client.dart';

class AuthController extends ChangeNotifier {
  AuthController({ApiClient? api}) {
    if (api != null) {
      this.api = api;
      return;
    }

    this.api = ApiClient(
      onUnauthorized: () {
        user = null;
        sessionEnded = true;
        notifyListeners();
        this.api.clearToken();
      },
    );
  }

  late final ApiClient api;
  UserAccount? user;
  bool restoring = true;
  String? error;
  bool sessionEnded = false;
  bool pendingEmailVerification = false;
  String? pendingVerificationCode;

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

  Future<bool> login(
    String email,
    String password, {
    bool remember = true,
  }) async {
    error = null;
    sessionEnded = false;
    notifyListeners();
    try {
      final result = await api.login(
        email: email,
        password: password,
        remember: remember,
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
      pendingEmailVerification = true;
      pendingVerificationCode = result.verificationCode;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      error = e.message;
      notifyListeners();
      return false;
    }
  }

  void clearPendingEmailVerification() {
    pendingEmailVerification = false;
  }

  void rememberVerificationCode(String? code) {
    pendingVerificationCode = code;
    notifyListeners();
  }

  Future<void> refreshUser() async {
    user = await api.currentUser();
    notifyListeners();
  }

  void applyAccount(UserAccount next) {
    user = next;
    notifyListeners();
  }

  Future<void> logout() async {
    await api.logout();
    user = null;
    pendingEmailVerification = false;
    pendingVerificationCode = null;
    notifyListeners();
  }
}
