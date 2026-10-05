import 'dart:math' as math;

import 'package:flutter/material.dart';

import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../state/preferences_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'growing_badge.dart';
import 'produce_photo.dart';
import 'promo_badge.dart';
import 'status_pill.dart';

enum ProduceCardStyle { row, poster }

class ReservedHarvestLabel extends StatelessWidget {
  const ReservedHarvestLabel({super.key, required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    if (!listing.isUpcoming || listing.reservedQuantity == null) {
      return const SizedBox.shrink();
    }
    final quantity = listing.reservedQuantity ?? 0;
    final count = listing.activeReservationsCount ?? 0;
    if (quantity <= 0 && count <= 0) {
      return const SizedBox.shrink();
    }
    final shown = quantity == quantity.roundToDouble()
        ? quantity.toStringAsFixed(0)
        : quantity.toString();
    return Text(
      AppStrings.of(context).reservedHarvest(shown, listing.unit ?? '', count),
      key: const ValueKey('reserved-harvest'),
    );
  }
}

class ProduceCard extends StatelessWidget {
  const ProduceCard({
    super.key,
    required this.listing,
    this.onTap,
    this.onSellerTap,
    this.trailing,
    this.showSeller = true,
    this.showStock = false,
    this.showPromo = true,
    this.placeholderColor,
    this.style = ProduceCardStyle.row,
  });

  final ListingItem listing;
  final VoidCallback? onTap;
  final VoidCallback? onSellerTap;
  final Widget? trailing;
  final bool showSeller;
  final bool showStock;
  final bool showPromo;
  final Color? placeholderColor;
  final ProduceCardStyle style;

  @override
  Widget build(BuildContext context) {
    return style == ProduceCardStyle.poster ? _poster(context) : _row(context);
  }

  Widget _poster(BuildContext context) {
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final sellerLabel =
        listing.sellerName ??
        listing.category?.labelFor(language) ??
        AppStrings.maybeOf(context).farmStall;
    final cropLabel = listing.category?.labelFor(language);
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
    );

    final promo = promoBadgeLabel(
      AppStrings.of(context),
      listing.tawad,
      unit: listing.unit,
    );
    final photo = Stack(
      fit: StackFit.expand,
      children: [
        ProducePhoto(listing: listing, iconSize: 40),
        if (showPromo && promo != null)
          Positioned(
            left: 8,
            right: 8,
            bottom: 8,
            child: PromoBadge(rule: listing.tawad, unit: listing.unit),
          ),
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
            listing.priceLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.labelLarge?.copyWith(
              color: _priceColor(theme),
              fontWeight: FontWeight.w700,
            ),
          ),
          ..._availabilityPill(context),
          ..._buyerNotes(context),
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
              final idealPhotoHeight = constraints.maxWidth * 3 / 4;
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Flexible(
                    fit: FlexFit.loose,
                    child: LayoutBuilder(
                      builder: (context, photoConstraints) {
                        final height = photoConstraints.maxHeight.isFinite
                            ? math.min(
                                idealPhotoHeight,
                                photoConstraints.maxHeight,
                              )
                            : idealPhotoHeight;
                        return SizedBox(
                          height: height,
                          width: double.infinity,
                          child: photo,
                        );
                      },
                    ),
                  ),
                  details,
                ],
              );
            }
            return Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AspectRatio(aspectRatio: 4 / 3, child: photo),
                details,
              ],
            );
          },
        ),
      ),
    );
  }

  Color _priceColor(ThemeData theme) {
    if (theme.brightness == Brightness.dark) {
      return AniHowColors.sage;
    }
    return AniHowColors.brand;
  }

  List<Widget> _availabilityPill(BuildContext context) {
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    if (listing.isTakenDown || quantity <= 0) {
      return const [];
    }
    final s = AppStrings.of(context);
    final upcoming = listing.isUpcoming;
    final from = listing.availableFrom;
    final label = upcoming
        ? (from == null
              ? s.reserveOnly
              : s.reserveFrom(s.shortDate(from.toLocal())))
        : s.availableNow;
    return [
      const SizedBox(height: 4),
      Align(
        alignment: Alignment.centerLeft,
        child: StatusPill(
          key: ValueKey('availability-pill-${listing.id}'),
          label: label,
          color: upcoming ? AniHowColors.pending : AniHowColors.inStock,
          background: upcoming ? null : AniHowColors.inStockBg,
          icon: upcoming ? Icons.event : Icons.shopping_basket_outlined,
          maxLines: 1,
        ),
      ),
    ];
  }

  List<Widget> _buyerNotes(BuildContext context) {
    final notes = <Widget>[];
    final distance = listing.distanceKm;
    if (distance != null) {
      notes.add(
        Padding(
          padding: const EdgeInsets.only(top: 4),
          child: Text(
            AppStrings.of(context).kilometersAway(distance),
            key: ValueKey('distance-${listing.id}'),
          ),
        ),
      );
    }
    if (!showSeller) {
      return notes;
    }
    final s = AppStrings.of(context);
    if (listing.organicBadge != null) {
      notes.add(GrowingBadge(badge: listing.organicBadge));
    }
    if (listing.harvestedOn != null) {
      notes.add(
        Padding(
          padding: const EdgeInsets.only(top: 2),
          child: Text(
            s.harvestedLine(s.shortDate(listing.harvestedOn!.toLocal())),
            key: const ValueKey('harvest-line'),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
      );
    }
    return notes;
  }

  Widget _row(BuildContext context) {
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final sellerLabel =
        listing.sellerName ??
        listing.category?.labelFor(language) ??
        AppStrings.maybeOf(context).farmStall;
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
                          Text(
                            listing.name,
                            style: theme.textTheme.titleMedium,
                          ),
                          if (cropLabel != null && cropLabel.isNotEmpty) ...[
                            const SizedBox(height: 2),
                            Text(
                              cropLabel,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: theme.colorScheme.onSurface.withValues(
                                  alpha: 0.7,
                                ),
                              ),
                            ),
                          ],
                          if (showSeller) ...[
                            const SizedBox(height: 2),
                            GestureDetector(
                              onTap: onSellerTap,
                              behavior: HitTestBehavior.opaque,
                              child: Padding(
                                padding: const EdgeInsets.symmetric(
                                  vertical: 4,
                                ),
                                child: Text(
                                  sellerLabel,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: onSellerTap == null
                                        ? theme.colorScheme.onSurface
                                              .withValues(alpha: 0.7)
                                        : AniHowColors.deepGreen,
                                    fontWeight: onSellerTap == null
                                        ? FontWeight.w500
                                        : FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: AniHowSpace.labelGap),
                          Text(
                            listing.priceLabel,
                            style: theme.textTheme.labelLarge?.copyWith(
                              color: _priceColor(theme),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          ..._availabilityPill(context),
                          ..._buyerNotes(context),
                          if (showPromo &&
                              promoBadgeLabel(
                                    AppStrings.of(context),
                                    listing.tawad,
                                    unit: listing.unit,
                                  ) !=
                                  null) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            PromoBadge(rule: listing.tawad, unit: listing.unit),
                          ],
                          if (listing.hasRating) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            RatingLabel(rating: listing.averageRating!),
                          ],
                          if (showStock) ...[
                            const SizedBox(height: AniHowSpace.labelGap),
                            StatusPill.forListing(
                              listing,
                              strings: AppStrings.of(context),
                            ),
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
