import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/care_guide_card.dart';
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
    _future = _load();
  }

  Future<List<CropCareArticle>> _load() {
    return context.read<AuthController>().api.cropCare(category: widget.category.value);
  }

  Future<void> _reload() async {
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(widget.category.label)),
      body: FutureBuilder<List<CropCareArticle>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final articles = snapshot.data ?? const [];
          if (articles.isEmpty) {
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: AniHowSpace.screenPadding,
                children: [
                  const SizedBox(height: 80),
                  const Icon(Icons.menu_book_outlined, size: 48, color: AniHowColors.brand),
                  const SizedBox(height: AniHowSpace.cardGap),
                  Text(
                    'No ${widget.category.label.toLowerCase()} articles yet.',
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            );
          }
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              padding: AniHowSpace.screenPadding,
              itemCount: articles.length,
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                final article = articles[index];
                return CareGuideCard(
                  article: article,
                  index: index,
                  onViewDetails: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => CropCareDetailScreen(
                          articleId: article.id,
                          preview: article,
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          );
        },
      ),
    );
  }
}
