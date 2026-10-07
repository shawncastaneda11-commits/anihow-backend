import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_theme.dart';

class CategoryColor {
  static Color of(CategoryItem? category, {String? listingName}) {
    final haystack = [
      category?.slug ?? '',
      category?.name ?? '',
      listingName ?? '',
    ].join(' ').toLowerCase();

    if (haystack.contains('eggplant') || haystack.contains('talong') || haystack.contains('bean')) {
      return AniHowColors.eggplant;
    }
    if (haystack.contains('fruit')) {
      return AniHowColors.fruit;
    }
    if (haystack.contains('root') || haystack.contains('grain')) {
      return AniHowColors.root;
    }
    if (haystack.contains('veget') || haystack.contains('herb') || haystack.contains('leaf')) {
      return AniHowColors.leafy;
    }
    return AniHowColors.sage;
  }

  static IconData iconOf(CategoryItem? category, {String? listingName}) {
    final haystack = [
      category?.slug ?? '',
      category?.name ?? '',
      listingName ?? '',
    ].join(' ').toLowerCase();

    if (haystack.contains('tomato') || haystack.contains('kamatis')) {
      return Icons.eco;
    }
    if (haystack.contains('mango') || haystack.contains('mangga')) {
      return Icons.spa;
    }
    if (haystack.contains('banana') || haystack.contains('saging')) {
      return Icons.spa;
    }

    if (haystack.contains('fruit')) {
      return Icons.spa;
    }
    if (haystack.contains('root') || haystack.contains('grain')) {
      return Icons.grass;
    }
    if (haystack.contains('bean')) {
      return Icons.grain;
    }
    if (haystack.contains('veget') || haystack.contains('herb') || haystack.contains('leaf')) {
      return Icons.eco;
    }
    return Icons.auto_stories;
  }
}
