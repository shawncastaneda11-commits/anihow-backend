import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../support/relative_time.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'report_sheet.dart';

class ShopReviewTile extends StatelessWidget {
  const ShopReviewTile({
    super.key,
    required this.review,
    this.showReport,
  });

  final ShopReview review;
  final bool? showReport;

  @override
  Widget build(BuildContext context) {
    final time = relativeTime(review.createdAt);
    final reportVisible = showReport ?? !review.isOwn;
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    review.reviewerName,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                if (time.isNotEmpty)
                  Text(
                    time,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w400,
                          color: Theme.of(context).colorScheme.onSurface,
                        ),
                  ),
                if (reportVisible)
                  TextButton(
                    key: ValueKey('shop-review-report-${review.id}'),
                    style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                    onPressed: () => showReportSheet(
                      context,
                      targetType: 'review',
                      targetId: review.id,
                    ),
                    child: Text(AppStrings.of(context).report),
                  ),
              ],
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            Row(
              children: [
                for (var index = 1; index <= 5; index++)
                  Icon(
                    index <= review.rating ? Icons.star_rounded : Icons.star_outline_rounded,
                    size: 16,
                    color: AniHowColors.pending,
                  ),
              ],
            ),
            if (review.comment != null && review.comment!.isNotEmpty) ...[
              const SizedBox(height: AniHowSpace.labelGap),
              Text(review.comment!, style: Theme.of(context).textTheme.bodyMedium),
            ],
          ],
        ),
      ),
    );
  }
}
