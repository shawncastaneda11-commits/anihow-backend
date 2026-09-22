import 'package:flutter/material.dart';

import 'anihow_space.dart';

class AniHowColors {
  static const Color brand = Color(0xFF1F5A3E);
  static const Color deepGreen = Color(0xFF1F5A3E);
  static const Color sage = Color(0xFF58A67D);
  static const Color leafy = Color(0xFF639922);
  static const Color fruit = Color(0xFFD85A30);
  static const Color root = Color(0xFFBA7517);
  static const Color eggplant = Color(0xFF7F77DD);
  static const Color cream = Color(0xFFF8F6F0);
  static const Color card = Color(0xFFFFFFFF);
  static const Color hairline = Color(0xFFE8E4DA);
  static const Color cardBorder = Color(0xFFE5E7EB);
  static const Color photoPlaceholder = Color(0xFFE2E8F0);
  static const Color text = Color(0xFF1E2421);
  static const Color muted = Color(0xFF6C757D);
  static const Color navBar = Color(0xFFFDFCFA);
  static const Color navActive = Color(0xFFD7EADF);
  static const Color navInactive = Color(0xFF6F7872);
  static const Color badge = Color(0xFFE63946);
  static const Color avatarOnBrand = Color(0xFF3A7A58);
  static const Color switchOn = Color(0xFF2E8B57);
  static const Color switchOff = Color(0xFFD6D1C7);
  static const Color inStock = Color(0xFF1C5635);
  static const Color inStockBg = Color(0xFFD9F5DF);
  static const Color inStockAvatar = Color(0xFF7CD96C);
  static const Color lowStock = Color(0xFFB94A3E);
  static const Color lowStockBg = Color(0xFFFCE3DE);
  static const Color lowStockAvatar = Color(0xFFE88A83);
  static const Color pending = Color(0xFFBA7517);
  static const Color ready = Color(0xFF2E8B57);
  static const Color completed = Color(0xFF378ADD);
  static const Color cancelled = Color(0xFF888780);

  static const Color darkBackground = Color(0xFF121A17);
  static const Color darkCard = Color(0xFF1C2622);
  static const Color darkHairline = Color(0xFF2E3A34);
  static const Color darkText = Color(0xFFF3F0E8);

  static Color stockPlaceholder({required bool isLowStock, required bool isInStock}) {
    if (isLowStock) {
      return lowStockAvatar;
    }
    if (isInStock) {
      return inStockAvatar;
    }
    return switchOff;
  }
}

class AniHowTheme {
  static const String fontFamily = 'PlusJakartaSans';
  static const double cardRadius = 16;
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
    final dark = brightness == Brightness.dark;
    // Dark accent: sage reads ≥4.5:1 on dark surfaces; brand does not.
    final accent = dark ? AniHowColors.sage : AniHowColors.brand;

    return ThemeData(
      useMaterial3: true,
      fontFamily: fontFamily,
      colorScheme: scheme,
      scaffoldBackgroundColor: background,
      canvasColor: background,
      textTheme: _textTheme(text),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(foregroundColor: accent),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: AniHowColors.brand,
        foregroundColor: scheme.onPrimary,
        elevation: 0,
        centerTitle: true,
        titleTextStyle: TextStyle(
          fontFamily: fontFamily,
          fontSize: AniHowSpace.header,
          fontWeight: FontWeight.w500,
          color: scheme.onPrimary,
        ),
      ),
      cardTheme: CardThemeData(
        color: card,
        elevation: 1.5,
        shadowColor: AniHowColors.text.withValues(alpha: 0.10),
        surfaceTintColor: Colors.transparent,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: radius),
      ),
      dividerColor: hairline,
      badgeTheme: const BadgeThemeData(
        backgroundColor: AniHowColors.badge,
        textColor: Colors.white,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: card,
        selectedColor: AniHowColors.brand.withValues(alpha: 0.16),
        side: BorderSide(color: hairline),
        labelStyle: TextStyle(
          fontFamily: fontFamily,
          color: text,
          fontWeight: FontWeight.w600,
          fontSize: AniHowSpace.label,
        ),
        shape: const StadiumBorder(),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: card,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(controlRadius),
          borderSide: BorderSide(color: hairline),
        ),
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
          foregroundColor: scheme.onPrimary,
          minimumSize: const Size.fromHeight(52),
          shape: const StadiumBorder(),
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: AniHowSpace.name,
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AniHowColors.brand,
          side: const BorderSide(color: AniHowColors.brand),
          minimumSize: const Size.fromHeight(52),
          shape: const StadiumBorder(),
          textStyle: const TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            fontSize: AniHowSpace.name,
          ),
        ),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: AniHowColors.brand,
        foregroundColor: scheme.onPrimary,
      ),
      switchTheme: SwitchThemeData(
        materialTapTargetSize: MaterialTapTargetSize.padded,
        thumbColor: const WidgetStatePropertyAll(Colors.white),
        trackColor: WidgetStateProperty.resolveWith(
          (states) => states.contains(WidgetState.selected)
              ? AniHowColors.switchOn
              : AniHowColors.switchOff,
        ),
        trackOutlineColor: const WidgetStatePropertyAll(Colors.transparent),
      ),
      tabBarTheme: TabBarThemeData(
        labelColor: AniHowColors.brand,
        unselectedLabelColor: AniHowColors.muted,
        indicatorColor: AniHowColors.brand,
        indicatorSize: TabBarIndicatorSize.tab,
        dividerColor: hairline,
        labelPadding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
        labelStyle: const TextStyle(
          fontFamily: fontFamily,
          fontSize: AniHowSpace.tab,
          fontWeight: FontWeight.w700,
        ),
        unselectedLabelStyle: const TextStyle(
          fontFamily: fontFamily,
          fontSize: AniHowSpace.tab,
          fontWeight: FontWeight.w600,
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: dark ? AniHowColors.darkCard : AniHowColors.navBar,
        elevation: 0,
        shadowColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AniHowColors.navActive,
        overlayColor: const WidgetStatePropertyAll(Colors.transparent),
        iconTheme: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return IconThemeData(
            color: selected ? accent : AniHowColors.navInactive,
          );
        }),
        labelTextStyle: WidgetStateProperty.resolveWith((states) {
          final selected = states.contains(WidgetState.selected);
          return TextStyle(
            fontFamily: fontFamily,
            fontWeight: FontWeight.w600,
            color: selected ? accent : AniHowColors.navInactive,
            fontSize: AniHowSpace.nav,
          );
        }),
      ),
    );
  }

  static TextTheme _textTheme(Color text) {
    return TextTheme(
      headlineLarge: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.headline, fontWeight: FontWeight.w700, color: text, height: 1.25),
      headlineMedium: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.headline, fontWeight: FontWeight.w700, color: text, height: 1.25),
      headlineSmall: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.headline, fontWeight: FontWeight.w700, color: text, height: 1.25),
      titleLarge: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.title, fontWeight: FontWeight.w600, color: text),
      titleMedium: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.name, fontWeight: FontWeight.w600, color: text),
      bodyLarge: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.body, fontWeight: FontWeight.w400, color: text, height: AniHowSpace.articleHeight),
      bodyMedium: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.body, fontWeight: FontWeight.w400, color: text, height: 1.4),
      labelLarge: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.meta, fontWeight: FontWeight.w700, color: text),
      labelSmall: TextStyle(fontFamily: fontFamily, fontSize: AniHowSpace.label, fontWeight: FontWeight.w600, color: text.withValues(alpha: 0.7)),
    );
  }
}
