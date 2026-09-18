import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class CropCareAuthorChip extends StatelessWidget {
  const CropCareAuthorChip({super.key, required this.article});

  final CropCareArticle article;

  @override
  Widget build(BuildContext context) {
    if (article.isOfficial) {
      return Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: AniHowColors.brand.withValues(alpha: 0.16),
          borderRadius: BorderRadius.circular(999),
        ),
        child: Text(
          'Official',
          style: TextStyle(
            color: AniHowColors.brand,
            fontWeight: FontWeight.w700,
            fontSize: AniHowSpace.label,
          ),
        ),
      );
    }

    return Text(
      article.authorLabel,
      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.65),
            fontSize: AniHowSpace.meta,
            fontWeight: FontWeight.w600,
          ),
    );
  }
}
