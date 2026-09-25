import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';

class CropCareDetailScreen extends StatefulWidget {
  const CropCareDetailScreen({
    super.key,
    required this.articleId,
    this.preview,
  });

  final int articleId;
  final CropCareArticle? preview;

  @override
  State<CropCareDetailScreen> createState() => _CropCareDetailScreenState();
}

class _CropCareDetailScreenState extends State<CropCareDetailScreen> {
  late Future<CropCareArticle> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.cropCareShow(widget.articleId);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<CropCareArticle>(
      future: _future,
      builder: (context, snapshot) {
        final article = snapshot.data ?? widget.preview;
        return Scaffold(
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
          appBar: AppBar(title: Text(article?.title ?? 'Care guide')),
          body: _body(context, snapshot, article),
        );
      },
    );
  }

  Widget _body(
    BuildContext context,
    AsyncSnapshot<CropCareArticle> snapshot,
    CropCareArticle? article,
  ) {
    if (article == null && snapshot.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snapshot.hasError && article == null) {
      return Center(child: Text('${snapshot.error}'));
    }
    if (article == null) {
      return const Center(child: Text('Guide not found.'));
    }

    final language = context.watch<PreferencesController>().language;
    final cropNames = article.cropTypes.map((crop) => crop.labelFor(language)).join(', ');
    final theme = Theme.of(context);
    final cardColor = theme.cardTheme.color;

    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          color: cardColor,
          elevation: 0,
          clipBehavior: Clip.antiAlias,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          child: _FeaturedMedia(imageUrl: article.imageUrl),
        ),
        const SizedBox(height: AniHowSpace.cardGap),
        Card(
          color: cardColor,
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (article.categoryLabel.isNotEmpty)
                  Text(
                    article.categoryLabel,
                    style: const TextStyle(
                      color: AniHowColors.brand,
                      fontWeight: FontWeight.w700,
                      fontSize: AniHowSpace.meta,
                    ),
                  ),
                const SizedBox(height: AniHowSpace.labelGap),
                Text(article.title, style: Theme.of(context).textTheme.titleMedium),
                if (article.authorLabel.isNotEmpty) ...[
                  const SizedBox(height: AniHowSpace.labelGap),
                  Text(article.authorLabel, style: Theme.of(context).textTheme.bodyMedium),
                ],
                if (article.farmName != null && article.farmName!.isNotEmpty)
                  Text(article.farmName!, style: Theme.of(context).textTheme.bodyMedium),
                if (cropNames.isNotEmpty)
                  Text(cropNames, style: Theme.of(context).textTheme.bodyMedium),
                if (article.publishedAt != null)
                  Text(
                    relativeTime(article.publishedAt),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                if (article.summary.isNotEmpty) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  Text(article.summary, style: Theme.of(context).textTheme.bodyMedium),
                ],
                const SizedBox(height: AniHowSpace.section),
                Divider(height: 1, color: theme.dividerColor),
                const SizedBox(height: AniHowSpace.section),
                Text(
                  article.body.isEmpty ? 'No instructions yet.' : article.body,
                  style: theme.textTheme.bodyLarge?.copyWith(
                        height: AniHowSpace.articleHeight,
                        color: article.body.isEmpty
                            ? theme.colorScheme.onSurface.withValues(alpha: 0.7)
                            : theme.colorScheme.onSurface,
                      ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _FeaturedMedia extends StatelessWidget {
  const _FeaturedMedia({this.imageUrl});

  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 188,
      width: double.infinity,
      child: imageUrl != null && imageUrl!.isNotEmpty
          ? Image.network(imageUrl!, fit: BoxFit.cover)
          : const ColoredBox(
              color: AniHowColors.photoPlaceholder,
              child: Center(
                child: Icon(
                  Icons.photo_camera_outlined,
                  size: 40,
                  color: AniHowColors.brand,
                ),
              ),
            ),
    );
  }
}
