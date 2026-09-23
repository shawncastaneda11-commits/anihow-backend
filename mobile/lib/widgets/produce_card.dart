import 'package:flutter/material.dart';

import 'package:provider/provider.dart';

import '../models/models.dart';
import '../state/preferences_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'category_color.dart';
import 'status_pill.dart';

class ProduceCard extends StatelessWidget {
  const ProduceCard({
    super.key,
    required this.listing,
    this.onTap,
    this.onSellerTap,
    this.trailing,
    this.showSeller = true,
    this.showStock = false,
    this.placeholderColor,
  });

  final ListingItem listing;
  final VoidCallback? onTap;
  final VoidCallback? onSellerTap;
  final Widget? trailing;
  final bool showSeller;
  final bool showStock;
  final Color? placeholderColor;

  @override
  Widget build(BuildContext context) {
    final accent = placeholderColor ??
        (showStock
            ? AniHowColors.stockPlaceholder(
                isLowStock: listing.isLowStock,
                isInStock: listing.isInStock,
              )
            : CategoryColor.of(listing.category, listingName: listing.name));
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final sellerLabel = listing.sellerName ?? listing.category?.labelFor(language) ?? 'Farm stall';
    final cropLabel = listing.category?.labelFor(language);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: Row(
        children: [
          Expanded(
            child: InkWell(
              onTap: onTap,
              child: Padding(
                padding: AniHowSpace.cardPadding,
                child: Row(
                  children: [
                    _Thumbnail(listing: listing, accent: accent),
                    const SizedBox(width: AniHowSpace.cardGap),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(listing.name, style: theme.textTheme.titleMedium),
                          if (cropLabel != null && cropLabel.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              cropLabel,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                              ),
                            ),
                          ],
                          if (showSeller) ...[
                            const SizedBox(height: 2),
                            GestureDetector(
                              onTap: onSellerTap,
                              behavior: HitTestBehavior.opaque,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(vertical: 4),
                                child: Text(
                                  sellerLabel,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: onSellerTap == null
                                        ? theme.colorScheme.onSurface.withValues(alpha: 0.7)
                                        : AniHowColors.deepGreen,
                                    fontWeight: onSellerTap == null ? FontWeight.w500 : FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: AniHowSpace.labelGap),
                          Text(
                            '${AniHowMoney.peso(listing.pricePerUnit)} / ${listing.unit ?? ''}',
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: AniHowColors.brand,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (listing.tawad != null) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            Text(listing.tawad!.summary, style: theme.textTheme.bodyMedium),
                          ],
                          if (listing.hasRating) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            RatingLabel(rating: listing.averageRating!),
                          ],
                          if (showStock) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            StatusPill.forListing(listing),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          if (trailing != null)
            Padding(
              padding: const EdgeInsets.only(right: AniHowSpace.cardPad),
              child: trailing,
            ),
        ],
      ),
    );
  }
}

class RatingLabel extends StatelessWidget {
  const RatingLabel({
    super.key,
    required this.rating,
    this.count,
    this.size = 16,
  });

  final String rating;
  final int? count;
  final double size;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final label = AniHowMoney.rating(rating);
    final reviews = count;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(Icons.star_rounded, size: size, color: AniHowColors.pending),
        const SizedBox(width: 2),
        Text(label, style: theme.textTheme.labelLarge),
        if (reviews != null) ...[
          const SizedBox(width: 4),
          Text(
            reviews == 1 ? '(1 review)' : '($reviews reviews)',
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
            ),
          ),
        ],
      ],
    );
  }
}

class _Thumbnail extends StatelessWidget {
  const _Thumbnail({required this.listing, required this.accent});

  final ListingItem listing;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final letter = listing.name.isNotEmpty ? listing.name[0].toUpperCase() : '?';
    return ClipRRect(
      borderRadius: BorderRadius.circular(AniHowSpace.radius),
      child: SizedBox(
        width: AniHowSpace.thumb,
        height: AniHowSpace.thumb,
        child: listing.imageUrl != null && listing.imageUrl!.isNotEmpty
            ? Image.network(
                listing.imageUrl!,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _Fallback(letter: letter, accent: accent),
              )
            : _Fallback(letter: letter, accent: accent),
      ),
    );
  }
}

class _Fallback extends StatelessWidget {
  const _Fallback({required this.letter, required this.accent});

  final String letter;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: accent,
      child: Center(
        child: Text(
          letter,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onPrimary,
            fontSize: AniHowSpace.name,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}
