import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';
import 'profile_avatar_button.dart';

/// The cover banner with the shop photo overlapping its bottom edge. Without
/// a cover it stays brand green, with a button to add one.
class ShopHero extends StatelessWidget {
  const ShopHero({super.key, required this.shop, this.onCover});

  final ShopProfile shop;

  /// The seller's own view only. Buyers see the cover without the button.
  final VoidCallback? onCover;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final cover = shop.coverUrl?.trim();
    final hasCover = cover != null && cover.isNotEmpty;
    final background = Theme.of(context).scaffoldBackgroundColor;
    return SizedBox(
      height: 168,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            bottom: 40,
            child: hasCover
                ? Image.network(
                    cover,
                    key: const ValueKey('shop-cover-photo'),
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const ShopGreenCover(),
                  )
                : const ShopGreenCover(),
          ),
          if (onCover != null)
          Positioned(
            top: 10,
            right: 10,
            child: Material(
              color: Colors.black.withValues(alpha: 0.38),
              shape: const StadiumBorder(),
              child: InkWell(
                key: const ValueKey('shop-cover-button'),
                customBorder: const StadiumBorder(),
                onTap: onCover,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.photo_camera_outlined, size: 16, color: Colors.white),
                      const SizedBox(width: 6),
                      Text(
                        hasCover ? s.changeCover : s.addCover,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: AniHowSpace.screen,
            bottom: 0,
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: background, width: 4),
              ),
              child: AniHowAvatar(name: shop.shopName, imageUrl: shop.avatarUrl, radius: 36),
            ),
          ),
        ],
      ),
    );
  }
}

class ShopGreenCover extends StatelessWidget {
  const ShopGreenCover({super.key});

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF2C6B4B), Color(0xFF1A4733), Color(0xFF173B2B)],
        ),
      ),
    );
  }
}

/// Listings, rating and reviews in one row; the reviews cell opens them.
class ShopStats extends StatelessWidget {
  const ShopStats({
    super.key,
    required this.listings,
    required this.rating,
    required this.reviews,
    required this.onReviews,
  });

  final int? listings;
  final String? rating;
  final int reviews;
  final VoidCallback onReviews;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    Widget cell(String value, String label, {String? link}) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
        child: Column(
          children: [
            Text(value, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            Text(label, style: theme.textTheme.labelMedium),
            if (link != null)
              Text(
                link,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: readableAccent(context),
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      );
    }

    final divider = VerticalDivider(width: 1, thickness: 1, color: theme.dividerColor);
    return Card(
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(child: cell(listings == null ? '–' : '$listings', s.listings)),
            divider,
            Expanded(
              child: cell(rating == null ? '–' : '${AniHowMoney.rating(rating)}★', s.rating),
            ),
            divider,
            Expanded(
              child: InkWell(
                key: const ValueKey('farmer-shop-reviews'),
                onTap: onReviews,
                child: cell('$reviews', s.reviews, link: '${s.seeAll} ›'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class ShopAbout extends StatelessWidget {
  const ShopAbout({super.key, required this.shop, this.onCall});

  final ShopProfile shop;

  /// Set on the seller's own profile. Buyers never see the contact number.
  final ValueChanged<String>? onCall;

  static String? _text(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  /// Only what the seller has filled in; the checklist covers the rest.
  static bool hasAny(ShopProfile shop, {bool showContact = true}) =>
      _text(shop.bio) != null ||
      _text(shop.location) != null ||
      (showContact && _text(shop.contact) != null);

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final bio = _text(shop.bio);
    final location = _text(shop.location);
    final contact = onCall == null ? null : _text(shop.contact);
    Widget row(IconData icon, Widget child) => Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: theme.colorScheme.onSurface.withValues(alpha: 0.7)),
          const SizedBox(width: 10),
          Expanded(child: child),
        ],
      ),
    );
    return Card(
      key: const ValueKey('shop-about'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.aboutShop, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            if (bio != null) row(Icons.eco_outlined, Text(bio)),
            if (location != null) row(Icons.location_on_outlined, Text(location)),
            if (contact != null)
              row(
                Icons.phone_outlined,
                InkWell(
                  onTap: () => onCall!(contact),
                  child: Text(
                    contact,
                    style: TextStyle(color: readableAccent(context), fontWeight: FontWeight.w600),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Whether online payment is on, at a glance. Manage opens the shop editor,
/// where the QR codes and the time limit live.
class ShopPaymentsCard extends StatelessWidget {
  const ShopPaymentsCard({super.key, required this.shop, this.onManage});

  final ShopProfile shop;

  /// The seller's own view. Without it the card speaks to the buyer and adds
  /// the pickup note.
  final VoidCallback? onManage;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final forSeller = onManage != null;
    final on = shop.acceptsOnlinePayment && (shop.paymentQrs.isNotEmpty || !forSeller);
    return Card(
      key: const ValueKey('shop-payments'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    s.payments,
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                if (onManage != null)
                  TextButton(
                    key: const ValueKey('shop-payments-manage'),
                    onPressed: onManage,
                    child: Text('${s.manageLink} ›'),
                  ),
              ],
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Icon(
                    Icons.circle,
                    size: 10,
                    color: on ? readableAccent(context) : AniHowColors.cancelled,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        forSeller
                            ? (on ? s.onlinePaymentIsOn : s.onlinePaymentIsOff)
                            : (on ? s.shopTakesOnlinePayment : s.shopCashOnly),
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        forSeller
                            ? (on
                                  ? s.onlinePaymentDetail(shop.paymentQrs.length, shop.paymentTimeLimitHours)
                                  : s.onlinePaymentOffDetail)
                            : (on ? s.shopOnlinePaymentDetail(shop.paymentTimeLimitHours) : s.shopCashOnlyDetail),
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (!forSeller) ...[
              const SizedBox(height: 10),
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.storefront_outlined,
                    size: 18,
                    color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(s.pickupOnly, style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text(
                          s.callToPickup,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class ShopListingsEmpty extends StatelessWidget {
  const ShopListingsEmpty({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AniHowSpace.section),
      child: Column(
        children: [
          Icon(
            Icons.inventory_2_outlined,
            size: 40,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          Text(
            AppStrings.of(context).noActiveListings,
            textAlign: TextAlign.center,
            style: const TextStyle(fontSize: AniHowSpace.body),
          ),
        ],
      ),
    );
  }
}
