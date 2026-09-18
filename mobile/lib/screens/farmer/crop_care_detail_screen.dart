import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/category_color.dart';
import '../../widgets/crop_care_author_chip.dart';
import 'crop_care_form_screen.dart';

class CropCareDetailScreen extends StatefulWidget {
  const CropCareDetailScreen({
    super.key,
    required this.articleId,
    this.category,
  });

  final int articleId;
  final CategoryItem? category;

  @override
  State<CropCareDetailScreen> createState() => _CropCareDetailScreenState();
}

class _CropCareDetailScreenState extends State<CropCareDetailScreen> {
  late Future<CropCareArticle> _future;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _future = context.read<AuthController>().api.cropCareShow(widget.articleId);
  }

  Future<void> _edit(CropCareArticle article) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => CropCareFormScreen(article: article)),
    );
    if (saved == true && mounted) {
      setState(() {
        _reload();
      });
    }
  }

  Future<void> _delete(CropCareArticle article) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this guide?'),
        content: Text(article.title),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }
    try {
      await context.read<AuthController>().api.deleteCropCare(article.id);
      if (mounted) {
        Navigator.of(context).pop();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  List<String> _paragraphs(String body) {
    final trimmed = body.trim();
    if (trimmed.isEmpty) {
      return const [];
    }
    final blocks = trimmed
        .split(RegExp(r'\n\s*\n'))
        .map((block) => block.replaceAll(RegExp(r'\s+'), ' ').trim())
        .where((block) => block.isNotEmpty)
        .toList();
    return blocks.isEmpty ? [trimmed] : blocks;
  }

  bool _isCallout(String text) {
    final lower = text.toLowerCase();
    return lower.startsWith('tip:') || lower.startsWith('note:');
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<CropCareArticle>(
      future: _future,
      builder: (context, snapshot) {
        final article = snapshot.data;
        return Scaffold(
          appBar: AppBar(
            title: const Text('Crop care'),
            actions: [
              if (article != null && article.canEdit) ...[
                IconButton(
                  tooltip: 'Edit',
                  onPressed: () => _edit(article),
                  icon: const Icon(Icons.edit_outlined),
                ),
                IconButton(
                  tooltip: 'Delete',
                  onPressed: () => _delete(article),
                  icon: const Icon(Icons.delete_outline),
                ),
              ],
            ],
          ),
          body: _detailBody(context, snapshot),
        );
      },
    );
  }

  Widget _detailBody(BuildContext context, AsyncSnapshot<CropCareArticle> snapshot) {
    if (snapshot.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snapshot.hasError) {
      return Center(child: Text('${snapshot.error}'));
    }
    final article = snapshot.data;
    if (article == null) {
      return const Center(child: Text('Tip not found.'));
    }

    final theme = Theme.of(context);
    final category = article.category ?? widget.category;
    final accent = CategoryColor.of(category);
    final paragraphs = _paragraphs(article.body);
    final categoryLabel = category?.name ?? 'General';

    return ListView(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: AniHowSpace.screen),
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.16),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                categoryLabel,
                style: TextStyle(
                  color: accent,
                  fontWeight: FontWeight.w700,
                  fontSize: AniHowSpace.label,
                ),
              ),
            ),
            CropCareAuthorChip(article: article),
          ],
        ),
        const SizedBox(height: AniHowSpace.cardGap),
        Text(article.title, style: theme.textTheme.headlineSmall),
        const SizedBox(height: AniHowSpace.section),
        for (var i = 0; i < paragraphs.length; i++) ...[
          if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
          if (_isCallout(paragraphs[i]))
            _TipBox(text: paragraphs[i])
          else
            Text(
              paragraphs[i],
              style: i == 0
                  ? theme.textTheme.bodyLarge?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: AniHowSpace.articleHeight,
                    )
                  : theme.textTheme.bodyLarge?.copyWith(height: AniHowSpace.articleHeight),
            ),
        ],
      ],
    );
  }
}

class _TipBox extends StatelessWidget {
  const _TipBox({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: AniHowSpace.cardPadding,
      decoration: BoxDecoration(
        color: AniHowColors.brand.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(AniHowSpace.radius),
      ),
      child: Text(
        text,
        style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: AniHowSpace.articleHeight),
      ),
    );
  }
}
