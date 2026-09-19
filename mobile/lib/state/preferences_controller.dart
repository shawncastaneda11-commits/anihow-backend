import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

class PreferencesController extends ChangeNotifier {
  static const _notificationsKey = 'anihow_notifications_enabled';

  bool notificationsEnabled = true;

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    notificationsEnabled = prefs.getBool(_notificationsKey) ?? true;
    notifyListeners();
  }

  Future<void> setNotificationsEnabled(bool enabled) async {
    notificationsEnabled = enabled;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_notificationsKey, enabled);
  }
}
