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
    this.icon,
    this.maxLines = 2,
  });

  final String label;
  final Color color;
  final Color? background;
  final IconData? icon;
  final int maxLines;

  factory StatusPill.order(
    String status, {
    String? label,
    AppStrings? strings,
    String? fulfillmentPreference,
  }) {
    final normalized = status.toLowerCase();
    final readyLabel = fulfillmentPreference == 'seller_delivers'
        ? strings?.outForDelivery ?? 'Out for delivery'
        : strings?.readyForPickup ?? 'Ready for pickup';
    final mapped = switch (normalized) {
      'placed' => (
        AniHowColors.pending,
        label ?? strings?.stepPending ?? 'Pending',
      ),
      'confirmed' => (
        AniHowColors.confirmedBlue,
        label ?? strings?.stepConfirmed ?? 'Confirmed',
      ),
      'ready' => (AniHowColors.readyTeal, label ?? readyLabel),
      'completed' => (
        AniHowColors.completeGreen,
        label ?? strings?.orderComplete ?? 'Order complete',
      ),
      'cancelled' => (
        AniHowColors.cancelledRed,
        label ?? strings?.cancelled ?? 'Cancelled',
      ),
      _ => (AniHowColors.cancelledRed, label ?? status),
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

  factory StatusPill.takenDown({AppStrings? strings}) {
    return StatusPill(
      label: strings?.takenDown ?? 'Taken down',
      color: AniHowColors.cancelled,
    );
  }

  factory StatusPill.forListing(ListingItem listing, {AppStrings? strings}) {
    if (listing.isTakenDown) {
      return StatusPill.takenDown(strings: strings);
    }
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    if (quantity <= 0) {
      return const StatusPill(label: 'Out', color: AniHowColors.cancelled);
    }
    return listing.isLowStock
        ? StatusPill.lowStock(strings: strings)
        : StatusPill.inStock(strings: strings);
  }

  @override
  Widget build(BuildContext context) {
    final labelColor = _labelColor(context);
    final text = Text(
      label,
      maxLines: maxLines,
      softWrap: maxLines > 1,
      overflow: TextOverflow.ellipsis,
      style: TextStyle(
        color: labelColor,
        fontWeight: FontWeight.w700,
        fontSize: AniHowSpace.meta,
      ),
    );
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: background ?? color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: icon == null
          ? text
          : Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(icon, size: 14, color: labelColor),
                const SizedBox(width: 4),
                Flexible(child: text),
              ],
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
      AniHowColors.pending => dark ? 0.780 : 0.325,
      AniHowColors.sage => dark ? 0.540 : 0.355,
      AniHowColors.ready => dark ? 0.455 : 0.305,
      AniHowColors.completed => dark ? 0.630 : 0.415,
      AniHowColors.cancelled => dark ? 0.610 : 0.405,
      AniHowColors.confirmedBlue => dark ? 0.800 : 0.280,
      AniHowColors.readyTeal => dark ? 0.800 : 0.220,
      AniHowColors.completeGreen => dark ? 0.800 : 0.220,
      AniHowColors.cancelledRed => dark ? 0.820 : 0.300,
      _ =>
        dark
            ? (hsl.lightness + 0.12).clamp(0.2, 0.85)
            : (hsl.lightness - 0.12).clamp(0.2, 0.85),
    };
    return hsl.withLightness(lightness).toColor();
  }
}
