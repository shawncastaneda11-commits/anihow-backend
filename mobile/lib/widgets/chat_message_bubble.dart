import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../screens/buyer/listing_detail_screen.dart';
import '../support/relative_time.dart';
import '../theme/anihow_space.dart';

class ChatMessageBubble extends StatelessWidget {
  const ChatMessageBubble({
    super.key,
    required this.message,
    required this.mine,
  });

  final OrderMessage message;
  final bool mine;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
      child: Container(
        margin: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
        padding: AniHowSpace.cardPadding,
        constraints: BoxConstraints(
          maxWidth: MediaQuery.sizeOf(context).width * 0.8,
        ),
        decoration: BoxDecoration(
          color: mine
              ? Theme.of(context).colorScheme.primaryContainer
              : Theme.of(context).colorScheme.surfaceContainerHighest,
          borderRadius: BorderRadius.circular(AniHowSpace.radius),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              mine ? s.you : message.authorName,
              style: Theme.of(context).textTheme.labelMedium,
            ),
            if (message.orderId != null) ...[
              const SizedBox(height: 4),
              Text(
                s.orderTag(message.orderId!),
                style: Theme.of(context).textTheme.labelMedium,
              ),
            ],
            if (message.hasProductCard) ...[
              const SizedBox(height: 8),
              _ProductCard(message: message),
            ],
            const SizedBox(height: 4),
            Text(message.body),
            if (message.createdAt != null) ...[
              const SizedBox(height: 4),
              Text(
                relativeTime(message.createdAt),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ProductCard extends StatelessWidget {
  const _ProductCard({required this.message});

  final OrderMessage message;

  @override
  Widget build(BuildContext context) {
    final linked = message.listingId != null;
    final card = ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 48),
      child: Row(
        children: [
          _Thumbnail(message: message),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(message.listingTitle ?? ''),
                Text(
                  [message.listingPrice, message.listingUnit]
                      .whereType<String>()
                      .where((part) => part.isNotEmpty)
                      .join(' / '),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );

    return KeyedSubtree(
      key: ValueKey('listing-card-${message.id}'),
      child: linked
          ? InkWell(
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) =>
                        ListingDetailScreen(listingId: message.listingId!),
                  ),
                );
              },
              child: card,
            )
          : card,
    );
  }
}

class _Thumbnail extends StatelessWidget {
  const _Thumbnail({required this.message});

  final OrderMessage message;

  @override
  Widget build(BuildContext context) {
    final url = message.listingThumbnailUrl;
    if (url == null || url.isEmpty) {
      return _placeholder(message.id);
    }

    return Image.network(
      url,
      width: 48,
      height: 48,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => _placeholder(message.id),
    );
  }

  Widget _placeholder(int id) {
    return SizedBox(
      key: ValueKey('listing-placeholder-$id'),
      width: 48,
      height: 48,
      child: const Icon(Icons.image_outlined),
    );
  }
}
