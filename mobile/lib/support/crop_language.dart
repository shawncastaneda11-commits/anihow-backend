import 'package:flutter/material.dart';

/// App and crop-label language. English or Filipino only.
enum CropLanguage {
  english,
  filipino;

  String get settingsLabel => switch (this) {
        CropLanguage.english => 'English',
        CropLanguage.filipino => 'Filipino',
      };

  Locale get locale => switch (this) {
        CropLanguage.english => const Locale('en'),
        CropLanguage.filipino => const Locale('fil'),
      };
}
