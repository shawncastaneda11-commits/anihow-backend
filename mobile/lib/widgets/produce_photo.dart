import 'package:flutter/material.dart';

import '../models/models.dart';
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
        ? LayoutBuilder(
            builder: (context, constraints) {
              final dpr = MediaQuery.devicePixelRatioOf(context);
              final cacheWidth = constraints.maxWidth.isFinite
                  ? (constraints.maxWidth * dpr).round()
                  : null;
              return Image.network(
                url,
                fit: BoxFit.cover,
                width: double.infinity,
                height: double.infinity,
                cacheWidth: cacheWidth == null || cacheWidth <= 0 ? null : cacheWidth,
                filterQuality: FilterQuality.medium,
                errorBuilder: (_, _, _) => _Fallback(
                  listing: listing,
                  accent: accent,
                  iconSize: iconSize,
                ),
              );
            },
          )
        : _Fallback(
            listing: listing,
            accent: accent,
            iconSize: iconSize,
          );

    return ClipRRect(borderRadius: radius, child: photo);
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
