import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../screens/buyer/announcements_feed_screen.dart';
import '../theme/anihow_space.dart';

class FarmUpdatesStrip extends StatelessWidget {
  const FarmUpdatesStrip({super.key, required this.items});

  final List<BuyerFarmAnnouncement> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    final shown = items.take(3).toList();
    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            AniHowSpace.cardGap,
            AniHowSpace.screen,
            0,
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  s.updatesFromFarms,
                  style: theme.textTheme.titleMedium,
                ),
              ),
              TextButton(
                key: const ValueKey('farm-updates-see-all'),
                style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const AnnouncementsFeedScreen(),
                    ),
                  );
                },
                child: Text(s.seeAll),
              ),
            ],
          ),
        ),
        SizedBox(
          height: 148,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
            itemCount: shown.length,
            separatorBuilder: (_, _) =>
                const SizedBox(width: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final post = shown[index];
              return SizedBox(
                width: 220,
                child: Card(
                  key: ValueKey('farm-update-${post.id}'),
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          post.farmName,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelLarge,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          post.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.titleSmall,
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
