import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class CropCareAuthorChip extends StatelessWidget {
  const CropCareAuthorChip({super.key, required this.article});

  final CropCareArticle article;

  @override
  Widget build(BuildContext context) {
    return Text(
      article.authorLabel,
      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
            color: AniHowColors.muted,
            fontSize: AniHowSpace.meta,
            fontWeight: FontWeight.w600,
          ),
    );
  }
}
