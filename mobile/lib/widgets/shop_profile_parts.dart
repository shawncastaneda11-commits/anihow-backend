import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_space.dart';
import 'produce_card.dart';
import 'profile_avatar_button.dart';

class ShopIdentityHeader extends StatelessWidget {
  const ShopIdentityHeader({super.key, required this.shop});

  final ShopProfile shop;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final location = shop.location?.trim();

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        AniHowAvatar(name: shop.shopName, radius: 28),
        const SizedBox(width: AniHowSpace.cardGap),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                shop.shopName,
                style: const TextStyle(fontSize: AniHowSpace.title, fontWeight: FontWeight.w800),
              ),
              if (location != null && location.isNotEmpty) ...[
                const SizedBox(height: 2),
                Row(
                  children: [
                    Icon(
                      Icons.location_on_outlined,
                      size: 16,
                      color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                    ),
                    const SizedBox(width: 4),
                    Expanded(
                      child: Text(location, style: theme.textTheme.bodyMedium),
                    ),
                  ],
                ),
              ],
              if (shop.hasRating) ...[
                const SizedBox(height: AniHowSpace.labelGap),
                RatingLabel(rating: shop.averageRating!, count: shop.reviewsCount),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class ShopStatRow extends StatelessWidget {
  const ShopStatRow({
    super.key,
    this.listings,
    this.sales,
    this.rating,
  });

  final int? listings;
  final int? sales;
  final String? rating;

  @override
  Widget build(BuildContext context) {
    final cells = <_StatCell>[
      if (listings != null) _StatCell(value: '$listings', label: 'Listings'),
      if (sales != null) _StatCell(value: '$sales', label: 'Sales'),
      if (rating != null && rating!.isNotEmpty)
        _StatCell(value: AniHowMoney.rating(rating), label: 'Rating'),
    ];
    if (cells.isEmpty) {
      return const SizedBox.shrink();
    }

    return Card(
      child: IntrinsicHeight(
        child: Row(
          children: [
            for (var index = 0; index < cells.length; index++) ...[
              if (index > 0)
                VerticalDivider(
                  width: 1,
                  thickness: 1,
                  color: Theme.of(context).dividerColor,
                ),
              Expanded(child: cells[index]),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AniHowSpace.cardPad, horizontal: 8),
      child: Column(
        children: [
          Text(
            value,
            style: const TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelSmall,
          ),
        ],
      ),
    );
  }
}

class ShopAboutCard extends StatelessWidget {
  const ShopAboutCard({
    super.key,
    required this.shop,
    this.onCall,
  });

  final ShopProfile shop;
  final ValueChanged<String>? onCall;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final bio = shop.bio?.trim();
    final location = shop.location?.trim();
    final contact = shop.contact?.trim();
    final canCall = contact != null && contact.isNotEmpty && onCall != null;

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              (bio != null && bio.isNotEmpty) ? bio : 'No bio yet',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodyMedium,
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            _IconRow(
              icon: Icons.location_on_outlined,
              text: (location != null && location.isNotEmpty) ? location : 'No location yet',
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            _IconRow(
              icon: Icons.phone_outlined,
              text: (contact != null && contact.isNotEmpty) ? contact : 'No contact yet',
              onTap: canCall ? () => onCall!(contact) : null,
            ),
          ],
        ),
      ),
    );
  }
}

class _IconRow extends StatelessWidget {
  const _IconRow({
    required this.icon,
    required this.text,
    this.onTap,
  });

  final IconData icon;
  final String text;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final row = Row(
      children: [
        Icon(icon, size: 16, color: theme.colorScheme.onSurface.withValues(alpha: 0.7)),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: theme.textTheme.bodyMedium?.copyWith(
              color: onTap == null ? null : theme.colorScheme.primary,
              fontWeight: onTap == null ? FontWeight.w400 : FontWeight.w700,
            ),
          ),
        ),
      ],
    );

    if (onTap == null) {
      return row;
    }
    return InkWell(onTap: onTap, child: row);
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
          Icon(Icons.inventory_2_outlined, size: 40, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: AniHowSpace.cardGap),
          const Text(
            'No active listings',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: AniHowSpace.body),
          ),
        ],
      ),
    );
  }
}
