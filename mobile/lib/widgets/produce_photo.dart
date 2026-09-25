import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'category_color.dart';

/// Cover photo, or a crop-colored illustration when the listing has none.
class ProducePhoto extends StatelessWidget {
  const ProducePhoto({
    super.key,
    required this.listing,
    this.borderRadius,
    this.iconSize = 44,
    this.preferThumbnail = true,
  });

  final ListingItem listing;
  final BorderRadius? borderRadius;
  final double iconSize;
  final bool preferThumbnail;

  @override
  Widget build(BuildContext context) {
    final accent = CategoryColor.of(listing.category, listingName: listing.name);
    final radius = borderRadius ?? BorderRadius.zero;
    final url = preferThumbnail
        ? (listing.thumbnailUrl ?? listing.imageUrl)
        : listing.imageUrl;
    final photo = url != null && url.isNotEmpty
        ? Image.network(
            url,
            fit: BoxFit.cover,
            width: double.infinity,
            height: double.infinity,
            filterQuality: FilterQuality.high,
            errorBuilder: (_, _, _) => _Fallback(
              listing: listing,
              accent: accent,
              iconSize: iconSize,
            ),
          )
        : _Fallback(
            listing: listing,
            accent: accent,
            iconSize: iconSize,
          );

    return ClipRRect(
      borderRadius: radius,
      child: Stack(
        fit: StackFit.expand,
        children: [
          photo,
          if (_hasActiveTawad)
            Positioned(
              left: 8,
              bottom: 8,
              right: 8,
              child: Align(
                alignment: Alignment.bottomLeft,
                child: _TawadBadge(rule: listing.tawad!),
              ),
            ),
        ],
      ),
    );
  }

  bool get _hasActiveTawad {
    final rule = listing.tawad;
    return rule != null && rule.isActive;
  }
}

class _TawadBadge extends StatelessWidget {
  const _TawadBadge({required this.rule});

  final TawadRule rule;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.maybeOf(context);
    return DecoratedBox(
      key: const ValueKey('produce-tawad-badge'),
      decoration: BoxDecoration(
        color: AniHowColors.brand.withValues(alpha: 0.92),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
        child: Text(
          s.tawadMinus(AniHowMoney.peso(rule.discountAmount)),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w700,
            fontSize: AniHowSpace.meta,
          ),
        ),
      ),
    );
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({
    required this.listing,
    required this.accent,
    required this.iconSize,
  });

  final ListingItem listing;
  final Color accent;
  final double iconSize;

  @override
  Widget build(BuildContext context) {
    final hsl = HSLColor.fromColor(accent);
    final deep = hsl.withLightness((hsl.lightness * 0.55).clamp(0.12, 1.0)).toColor();
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [accent, deep],
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Positioned(
            right: -18,
            bottom: -22,
            child: Icon(
              CategoryColor.iconOf(listing.category, listingName: listing.name),
              size: iconSize * 2.4,
              color: Colors.white.withValues(alpha: 0.12),
            ),
          ),
          Center(
            child: Icon(
              CategoryColor.iconOf(listing.category, listingName: listing.name),
              size: iconSize,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }
}
