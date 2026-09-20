import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/care_guide_card.dart';
import 'crop_care_detail_screen.dart';

class CropCareScreen extends StatefulWidget {
  const CropCareScreen({super.key});

  @override
  State<CropCareScreen> createState() => _CropCareScreenState();
}

class _CropCareScreenState extends State<CropCareScreen> {
  String? _category;
  late Future<List<CropCareArticle>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<CropCareArticle>> _load() {
    return context.read<AuthController>().api.cropCare(category: _category);
  }

  Future<void> _reload() async {
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  void _selectCategory(String? category) {
    setState(() => _category = category);
    _reload();
  }

  Future<void> _open(CropCareArticle article) {
    return Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CropCareDetailScreen(articleId: article.id, preview: article),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        SizedBox(
          height: 48,
          child: ListView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 8),
                child: FilterChip(
                  label: const Text('All'),
                  selected: _category == null,
                  onSelected: (_) => _selectCategory(null),
                ),
              ),
              ...CropCareCategory.filters.map(
                (filter) => Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: FilterChip(
                    label: Text(filter.label),
                    selected: _category == filter.value,
                    onSelected: (_) => _selectCategory(filter.value),
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: FutureBuilder<List<CropCareArticle>>(
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
                    children: const [
                      SizedBox(height: 80),
                      Icon(Icons.menu_book_outlined, size: 48, color: AniHowColors.brand),
                      SizedBox(height: AniHowSpace.cardGap),
                      Text('No crop-care articles yet.', textAlign: TextAlign.center),
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
                      onViewDetails: () => _open(article),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
