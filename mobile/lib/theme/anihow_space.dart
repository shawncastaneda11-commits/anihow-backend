import 'package:flutter/material.dart';

/// Locked spacing and type scale for every AniHow screen.
class AniHowSpace {
  static const double screen = 16;
  static const double cardGap = 12;
  static const double section = 20;
  static const double cardPad = 12;
  static const double radius = 12;
  static const double labelGap = 4;
  static const double fieldGap = 14;

  static const EdgeInsets screenPadding = EdgeInsets.all(screen);
  static const EdgeInsets cardPadding = EdgeInsets.all(cardPad);

  static const double header = 20;
  static const double headline = 20;
  static const double title = 16;
  static const double name = 16;
  static const double body = 14;
  static const double meta = 14;
  static const double label = 12;
  static const double tab = 15;
  static const double nav = 13;
  static const double tabHeight = 60;
  static const double thumb = 64;
  static const double avatar = 24;
  static const double switchScale = 1.12;
  static const double articleHeight = 1.6;
}

class AniHowMoney {
  static String peso(Object? value) {
    final amount = value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
    return '₱${amount.toStringAsFixed(2)}';
  }

  static String rating(Object? value) {
    final amount = value is num ? value.toDouble() : double.tryParse('$value');
    if (amount == null) {
      return '';
    }
    return amount.toStringAsFixed(1);
  }
}
