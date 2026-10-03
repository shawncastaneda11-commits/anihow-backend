import 'package:flutter/material.dart';

import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../state/preferences_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'produce_photo.dart';
import 'status_pill.dart';

enum ProduceCardStyle { row, poster }

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
    this.style = ProduceCardStyle.row,
  });

  final ListingItem listing;
  final VoidCallback? onTap;
  final VoidCallback? onSellerTap;
  final Widget? trailing;
  final bool showSeller;
  final bool showStock;
  final Color? placeholderColor;
  final ProduceCardStyle style;

  @override
  Widget build(BuildContext context) {
    return style == ProduceCardStyle.poster ? _poster(context) : _row(context);
  }

  Widget _poster(BuildContext context) {
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final sellerLabel = listing.sellerName ?? listing.category?.labelFor(language) ?? AppStrings.maybeOf(context).farmStall;
    final cropLabel = listing.category?.labelFor(language);
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
    );

    final photo = AspectRatio(
      aspectRatio: 4 / 3,
      child: Stack(
        fit: StackFit.expand,
        children: [
          ProducePhoto(listing: listing, iconSize: 40),
          if (trailing != null)
            Positioned(
              top: 4,
              right: 4,
              child: Material(
                color: Colors.black.withValues(alpha: 0.35),
                shape: const CircleBorder(),
                child: trailing,
              ),
            ),
        ],
      ),
    );

    final details = Padding(
      padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            listing.name,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.titleMedium,
          ),
          Text(
            [
              if (cropLabel != null && cropLabel.isNotEmpty) cropLabel,
              if (showSeller) sellerLabel,
            ].join(' · '),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: muted,
          ),
          const SizedBox(height: 4),
          Text(
            '${AniHowMoney.peso(listing.pricePerUnit)} / ${listing.unit ?? ''}',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelLarge?.copyWith(
              color: AniHowColors.brand,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: LayoutBuilder(
          builder: (context, constraints) {
            if (constraints.maxHeight.isFinite) {
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  photo,
                  Expanded(child: details),
                ],
              );
            }
            return Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [photo, details],
            );
          },
        ),
      ),
    );
  }

  Widget _row(BuildContext context) {
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final sellerLabel = listing.sellerName ?? listing.category?.labelFor(language) ?? AppStrings.maybeOf(context).farmStall;
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
                    SizedBox(
                      width: 88,
                      height: 88,
                      child: ProducePhoto(
                        listing: listing,
                        borderRadius: BorderRadius.circular(AniHowSpace.radius),
                        iconSize: 32,
                      ),
                    ),
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
                          if (listing.tawad != null && listing.tawad!.isActive) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            Text(
                              listing.tawad!.displaySummary(
                                offThisOrder: AppStrings.of(context).tawadOffThisOrder,
                                offAtMin: AppStrings.of(context).tawadOffAtMin,
                              ),
                              style: theme.textTheme.bodyMedium,
                            ),
                          ],
                          if (listing.hasRating) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            RatingLabel(rating: listing.averageRating!),
                          ],
                          if (showStock) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            StatusPill.forListing(listing, strings: AppStrings.of(context)),
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
            AppStrings.maybeOf(context).reviewsCount(reviews),
            style: theme.textTheme.bodyMedium?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
            ),
          ),
        ],
      ],
    );
  }
}
