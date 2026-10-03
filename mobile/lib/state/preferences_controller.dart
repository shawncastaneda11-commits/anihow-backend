import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../support/crop_language.dart';

class PreferencesController extends ChangeNotifier {
  static const _notificationsKey = 'anihow_notifications_enabled';
  static const _languageKey = 'anihow_crop_language';

  bool notificationsEnabled = true;
  CropLanguage language = CropLanguage.english;

  static CropLanguage languageFromStored(String? value) {
    return switch (value) {
      'filipino' || 'bilingual' => CropLanguage.filipino,
      _ => CropLanguage.english,
    };
  }

  Future<void> load() async {
    final prefs = await SharedPreferences.getInstance();
    notificationsEnabled = prefs.getBool(_notificationsKey) ?? true;
    language = languageFromStored(prefs.getString(_languageKey));
    notifyListeners();
  }

  Future<void> setNotificationsEnabled(bool enabled) async {
    notificationsEnabled = enabled;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_notificationsKey, enabled);
  }

  Future<void> setLanguage(CropLanguage next) async {
    language = next;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_languageKey, next.name);
  }
}
