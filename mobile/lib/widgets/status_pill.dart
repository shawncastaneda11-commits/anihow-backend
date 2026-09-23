import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class StatusPill extends StatelessWidget {
  const StatusPill({
    super.key,
    required this.label,
    required this.color,
    this.background,
  });

  final String label;
  final Color color;
  final Color? background;

  factory StatusPill.order(String status, {String? label, AppStrings? strings}) {
    final normalized = status.toLowerCase();
    final mapped = switch (normalized) {
      'placed' => (AniHowColors.pending, label ?? strings?.placed ?? 'Placed'),
      'confirmed' => (AniHowColors.sage, label ?? strings?.confirmed ?? 'Confirmed'),
      'ready' => (AniHowColors.ready, label ?? strings?.ready ?? 'Ready'),
      'completed' => (AniHowColors.completed, label ?? strings?.completed ?? 'Completed'),
      'cancelled' => (AniHowColors.cancelled, label ?? strings?.cancelled ?? 'Cancelled'),
      _ => (AniHowColors.cancelled, label ?? status),
    };
    return StatusPill(label: mapped.$2, color: mapped.$1);
  }

  factory StatusPill.lowStock({AppStrings? strings}) {
    return StatusPill(
      label: strings?.lowStock ?? 'Low stock',
      color: AniHowColors.lowStock,
      background: AniHowColors.lowStockBg,
    );
  }

  factory StatusPill.inStock({AppStrings? strings}) {
    return StatusPill(
      label: strings?.inStock ?? 'In stock',
      color: AniHowColors.inStock,
      background: AniHowColors.inStockBg,
    );
  }

  factory StatusPill.forListing(ListingItem listing) {
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    if (quantity <= 0) {
      return const StatusPill(label: 'Out', color: AniHowColors.cancelled);
    }
    return listing.isLowStock ? StatusPill.lowStock() : StatusPill.inStock();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background ?? color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: _labelColor(context),
          fontWeight: FontWeight.w700,
          fontSize: AniHowSpace.meta,
        ),
      ),
    );
  }

  /// Darker (light) or lighter (dark) shade of [color], same hue, so text on
  /// the 16% tint clears 4.5:1. Solid stock chips keep a dark shade in both
  /// modes because their backgrounds stay light.
  Color _labelColor(BuildContext context) {
    final hsl = HSLColor.fromColor(color);
    if (background != null) {
      if (color == AniHowColors.lowStock) {
        return hsl.withLightness(0.462).toColor();
      }
      return color;
    }

    final dark = Theme.of(context).brightness == Brightness.dark;
    // Lightness chosen so text on the 16% tint over card (≥4.5:1 light,
    // readable dark). Same hue; only lightness changes.
    final lightness = switch (color) {
      AniHowColors.pending => dark ? 0.480 : 0.325,
      AniHowColors.sage => dark ? 0.540 : 0.355,
      AniHowColors.ready => dark ? 0.455 : 0.305,
      AniHowColors.completed => dark ? 0.630 : 0.415,
      AniHowColors.cancelled => dark ? 0.610 : 0.405,
      _ => dark ? (hsl.lightness + 0.12).clamp(0.2, 0.85) : (hsl.lightness - 0.12).clamp(0.2, 0.85),
    };
    return hsl.withLightness(lightness).toColor();
  }
}
