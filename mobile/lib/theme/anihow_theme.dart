import 'package:flutter/material.dart';

import 'anihow_space.dart';

class AniHowColors {
  static const Color brand = Color(0xFF1D9E75);
  static const Color deepGreen = Color(0xFF0F6E56);
  static const Color leafy = Color(0xFF639922);
  static const Color fruit = Color(0xFFD85A30);
  static const Color root = Color(0xFFBA7517);
  static const Color eggplant = Color(0xFF7F77DD);
  static const Color cream = Color(0xFFFAF7F0);
  static const Color card = Color(0xFFFFFFFF);
  static const Color hairline = Color(0xFFECE8DF);
  static const Color text = Color(0xFF24312B);
  static const Color pending = Color(0xFFBA7517);
  static const Color ready = Color(0xFF1D9E75);
  static const Color completed = Color(0xFF378ADD);
  static const Color cancelled = Color(0xFF888780);

  static const Color darkBackground = Color(0xFF121A17);
  static const Color darkCard = Color(0xFF1C2622);
  static const Color darkHairline = Color(0xFF2E3A34);
  static const Color darkText = Color(0xFFF3F0E8);
}

class AniHowTheme {
  static const double cardRadius = AniHowSpace.radius;
  static const double controlRadius = AniHowSpace.radius;
  static const EdgeInsets pagePadding = AniHowSpace.screenPadding;

  static ThemeData light() => _build(
        brightness: Brightness.light,
        background: AniHowColors.cream,
        card: AniHowColors.card,
        hairline: AniHowColors.hairline,
        text: AniHowColors.text,
      );

  static ThemeData dark() => _build(
        brightness: Brightness.dark,
        background: AniHowColors.darkBackground,
        card: AniHowColors.darkCard,
        hairline: AniHowColors.darkHairline,
        text: AniHowColors.darkText,
      );

  static ThemeData _build({
    required Brightness brightness,
    required Color background,
    required Color card,
    required Color hairline,
    required Color text,
  }) {
    final scheme = ColorScheme.fromSeed(
      seedColor: AniHowColors.brand,
      brightness: brightness,
      primary: AniHowColors.brand,
      onPrimary: Colors.white,
      secondary: AniHowColors.deepGreen,
      onSecondary: Colors.white,
      surface: background,
      onSurface: text,
    );

    final radius = BorderRadius.circular(cardRadius);

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: background,
      canvasColor: background,
      textTheme: _textTheme(text),
      appBarTheme: AppBarTheme(
        backgroundColor: AniHowColors.brand,
        foregroundColor: Colors.white,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: const TextStyle(
          fontSize: AniHowSpace.header,
          fontWeight: FontWeight.w500,
          color: Colors.white,
        ),
      ),
      cardTheme: CardThemeData(
        color: card,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: BorderSide(color: hairline),
        ),
      ),
      dividerColor: hairline,
      chipTheme: ChipThemeData(
        backgroundColor: card,
        selectedColor: AniHowColors.brand.withValues(alpha: 0.16),
        side: BorderSide(color: hairline),
        labelStyle: TextStyle(
          color: text,
          fontWeight: FontWeight.w600,
          fontSize: AniHowSpace.label,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: card,
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(controlRadius)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(controlRadius),
          borderSide: BorderSide(color: hairline),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(controlRadius),
          borderSide: const BorderSide(color: AniHowColors.brand, width: 1.5),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: AniHowColors.brand,
          foregroundColor: Colors.white,
          minimumSize: const Size.fromHeight(48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(controlRadius),
          ),
          textStyle: const TextStyle(fontWeight: FontWeight.w700, fontSize: AniHowSpace.name),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AniHowColors.brand,
        foregroundColor: Colors.white,
      ),
      switchTheme: SwitchThemeData(
        materialTapTargetSize: MaterialTapTargetSize.padded,
        thumbColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected) ? Colors.white : hairline,
        ),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AniHowColors.brand
              : hairline,
        ),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: AniHowColors.brand,
        unselectedLabelColor: text.withValues(alpha: 0.55),
        indicatorColor: AniHowColors.brand,
        indicatorSize: TabBarIndicatorSize.tab,
        dividerColor: hairline,
        labelPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        labelStyle: const TextStyle(
          fontSize: AniHowSpace.tab,
          fontWeight: FontWeight.w700,
        ),
        unselectedLabelStyle: const TextStyle(
          fontSize: AniHowSpace.tab,
          fontWeight: FontWeight.w600,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: card,
        indicatorColor: AniHowColors.brand.withValues(alpha: 0.16),
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(fontWeight: FontWeight.w600, color: text, fontSize: AniHowSpace.nav),
        ),
      ),
    );
  }

  static TextTheme _textTheme(Color text) {
    return TextTheme(
      headlineLarge: TextStyle(fontSize: AniHowSpace.headline, fontWeight: FontWeight.w800, color: text, height: 1.25),
      headlineMedium: TextStyle(fontSize: AniHowSpace.headline, fontWeight: FontWeight.w800, color: text, height: 1.25),
      headlineSmall: TextStyle(fontSize: AniHowSpace.headline, fontWeight: FontWeight.w800, color: text, height: 1.25),
      titleLarge: TextStyle(fontSize: AniHowSpace.title, fontWeight: FontWeight.w700, color: text),
      titleMedium: TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w700, color: text),
      bodyLarge: TextStyle(fontSize: AniHowSpace.body, fontWeight: FontWeight.w400, color: text, height: AniHowSpace.articleHeight),
      bodyMedium: TextStyle(fontSize: AniHowSpace.body, fontWeight: FontWeight.w400, color: text, height: 1.4),
      labelLarge: TextStyle(fontSize: AniHowSpace.meta, fontWeight: FontWeight.w700, color: text),
      labelSmall: TextStyle(fontSize: AniHowSpace.label, fontWeight: FontWeight.w600, color: text.withValues(alpha: 0.7)),
    );
  }
}
