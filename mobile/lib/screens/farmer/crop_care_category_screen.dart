import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/category_color.dart';
import '../../widgets/crop_care_author_chip.dart';
import 'crop_care_detail_screen.dart';

class CropCareCategoryScreen extends StatefulWidget {
  const CropCareCategoryScreen({super.key, required this.category});

  final CropCareCategory category;

  @override
  State<CropCareCategoryScreen> createState() => _CropCareCategoryScreenState();
}

class _CropCareCategoryScreenState extends State<CropCareCategoryScreen> {
  late Future<List<CropCareArticle>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.cropCare(categoryId: widget.category.id);
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.cropCare(categoryId: widget.category.id);
    setState(() {
      _future = future;
    });
    await future;
  }

  @override
  Widget build(BuildContext context) {
    final accent = CategoryColor.of(widget.category.asCategory);

    return Scaffold(
      appBar: AppBar(title: Text(widget.category.name)),
      body: FutureBuilder<List<CropCareArticle>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final tips = snapshot.data ?? const [];
          if (tips.isEmpty) {
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: AniHowSpace.screenPadding,
                children: [
                  const SizedBox(height: 80),
                  Icon(
                    Icons.menu_book_outlined,
                    size: 48,
                    color: AniHowColors.brand.withValues(alpha: 0.7),
                  ),
                  const SizedBox(height: AniHowSpace.cardGap),
                  Text(
                    'No tips in this category yet',
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              padding: AniHowSpace.screenPadding,
              itemCount: tips.length,
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                final tip = tips[index];
                return Card(
                  child: ListTile(
                    contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                    leading: CircleAvatar(
                      radius: AniHowSpace.avatar,
                      backgroundColor: accent,
                      foregroundColor: Colors.white,
                      child: Text(
                        '${index + 1}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: AniHowSpace.name,
                        ),
                      ),
                    ),
                    title: Text(
                      tip.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          tip.summary,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                fontSize: AniHowSpace.meta,
                              ),
                        ),
                        const SizedBox(height: 2),
                        CropCareAuthorChip(article: tip),
                      ],
                    ),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => CropCareDetailScreen(
                            articleId: tip.id,
                            category: widget.category.asCategory,
                          ),
                        ),
                      );
                      if (mounted) {
                        await _reload();
                      }
                    },
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
