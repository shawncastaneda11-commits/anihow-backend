import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_space.dart';
import 'category_color.dart';
import 'crop_care_author_chip.dart';

class CareGuideCard extends StatelessWidget {
  const CareGuideCard({
    super.key,
    required this.article,
    required this.onViewDetails,
    this.onEdit,
    this.onDelete,
  });

  final CropCareArticle article;
  final VoidCallback onViewDetails;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;

  static const double _radius = 16;

  @override
  Widget build(BuildContext context) {
        final accent = CategoryColor.of(article.categoryChip, listingName: article.title);

    return Card(
      color: Theme.of(context).cardTheme.color,
      elevation: 0,
      shadowColor: Colors.transparent,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(_radius),
      ),
      child: ListTile(
        onTap: onViewDetails,
        onLongPress: onEdit == null && onDelete == null ? null : () => _showActions(context),
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        leading: CircleAvatar(
          radius: AniHowSpace.avatar,
          backgroundColor: accent,
          foregroundColor: Theme.of(context).colorScheme.onPrimary,
          child: const Icon(Icons.eco_outlined),
        ),
        title: Text(
          article.title,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.titleMedium,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              article.summary,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontSize: AniHowSpace.meta,
                  ),
            ),
            const SizedBox(height: 2),
            CropCareAuthorChip(article: article),
          ],
        ),
        isThreeLine: true,
        trailing: const Icon(Icons.chevron_right),
      ),
    );
  }

  Future<void> _showActions(BuildContext context) async {
    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (onEdit != null)
              ListTile(
                leading: const Icon(Icons.edit_outlined),
                title: const Text('Edit'),
                onTap: () => Navigator.pop(context, 'edit'),
              ),
            if (onDelete != null)
              ListTile(
                leading: const Icon(Icons.delete_outline),
                title: const Text('Delete'),
                onTap: () => Navigator.pop(context, 'delete'),
              ),
          ],
        ),
      ),
    );
    if (choice == 'edit') {
      onEdit?.call();
    } else if (choice == 'delete') {
      onDelete?.call();
    }
  }
}
