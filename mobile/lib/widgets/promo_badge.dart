import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../support/order_quantity.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

/// Buyer-facing promo line for one active tawad rule.
///
/// A flat rule is a fixed peso amount taken once off the line, so the badge
/// says "₱5.00 off". A minimum-quantity rule uses the same peso amount once
/// the quantity is at least the threshold.
String? promoBadgeLabel(AppStrings strings, TawadRule? rule, {String? unit}) {
  if (rule == null || !rule.isActive) {
    return null;
  }
  final peso = AniHowMoney.peso(rule.discountAmount);
  if (rule.isMinQuantity) {
    final quantity = _quantity(rule.minQuantity);
    if (quantity == null) {
      return null;
    }
    return strings.promoMinOff(peso, quantity, unit ?? '');
  }
  if (rule.isFlat) {
    return strings.promoFlatOff(peso);
  }
  return null;
}

/// Seller summary of the same rule, in the listing's unit.
String? sellerDiscountSummary(
  AppStrings strings,
  TawadRule? rule, {
  String? unit,
}) {
  if (rule == null || !rule.isActive) {
    return null;
  }
  final peso = AniHowMoney.peso(rule.discountAmount);
  if (rule.isMinQuantity) {
    final quantity = _quantity(rule.minQuantity);
    if (quantity == null) {
      return null;
    }
    return strings.sellerDiscountMin(peso, quantity, unit ?? '');
  }
  if (rule.isFlat) {
    return strings.sellerDiscountFlat(peso);
  }
  return null;
}

String? _quantity(String? raw) {
  if (raw == null || raw.trim().isEmpty) {
    return null;
  }
  final parsed = double.tryParse(raw.trim());
  if (parsed == null) {
    return raw.trim();
  }
  return formatOrderAmount(parsed);
}

class PromoBadge extends StatelessWidget {
  const PromoBadge({
    super.key,
    required this.rule,
    this.unit,
    this.onPhoto = false,
  });

  final TawadRule? rule;
  final String? unit;

  /// Solid fill so the label stays readable on a produce photo.
  final bool onPhoto;

  @override
  Widget build(BuildContext context) {
    final label = promoBadgeLabel(AppStrings.of(context), rule, unit: unit);
    if (label == null) {
      return const SizedBox.shrink();
    }
    final dark = Theme.of(context).brightness == Brightness.dark;
    final background = onPhoto
        ? (dark ? const Color(0xFF2E7D55) : AniHowColors.brand)
        : (dark
              ? AniHowColors.sage.withValues(alpha: 0.22)
              : AniHowColors.brand.withValues(alpha: 0.12));
    final foreground = onPhoto
        ? Colors.white
        : (dark ? AniHowColors.sage : AniHowColors.brand);
    return Align(
      alignment: Alignment.centerLeft,
      child: DecoratedBox(
        key: const ValueKey('promo-badge'),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
          boxShadow: onPhoto
              ? const [
                  BoxShadow(
                    color: Color(0x40000000),
                    blurRadius: 4,
                    offset: Offset(0, 1),
                  ),
                ]
              : null,
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: Text(
            label,
            maxLines: onPhoto ? 1 : 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: foreground,
              fontWeight: FontWeight.w700,
              fontSize: 12,
              height: 1.2,
            ),
          ),
        ),
      ),
    );
  }
}
