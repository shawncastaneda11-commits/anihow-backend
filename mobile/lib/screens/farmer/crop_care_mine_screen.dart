import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/primary_button.dart';
import 'crop_care_detail_screen.dart';
import 'crop_care_form_screen.dart';

class CropCareMineScreen extends StatefulWidget {
  const CropCareMineScreen({super.key});

  @override
  State<CropCareMineScreen> createState() => _CropCareMineScreenState();
}

class _CropCareMineScreenState extends State<CropCareMineScreen> {
  late Future<List<CropCareArticle>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.cropCareMine();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.cropCareMine();
    setState(() {
      _future = future;
    });
    await future;
  }

  Future<void> _openForm([CropCareArticle? article]) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => CropCareFormScreen(article: article)),
    );
    if (saved == true && mounted) {
      await _reload();
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
        await _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('My guides')),
      floatingActionButton: FloatingActionButton(
        onPressed: () => _openForm(),
        tooltip: 'Add guide',
        child: const Icon(Icons.add),
      ),
      body: FutureBuilder<List<CropCareArticle>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final guides = snapshot.data ?? const [];
          if (guides.isEmpty) {
            return RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: AniHowSpace.screenPadding,
                children: [
                  const SizedBox(height: 80),
                  Icon(
                    Icons.edit_note_outlined,
                    size: 48,
                    color: AniHowColors.brand.withValues(alpha: 0.7),
                  ),
                  const SizedBox(height: AniHowSpace.cardGap),
                  Text(
                    "You haven't added a guide yet",
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                  const SizedBox(height: AniHowSpace.section),
                  PrimaryButton(label: 'Add guide', onPressed: () => _openForm()),
                ],
              ),
            );
          }

          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              padding: const EdgeInsets.fromLTRB(
                AniHowSpace.screen,
                AniHowSpace.screen,
                AniHowSpace.screen,
                88,
              ),
              itemCount: guides.length,
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                final guide = guides[index];
                return Card(
                  child: ListTile(
                    title: Text(
                      guide.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    subtitle: Text(
                      guide.summary,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          tooltip: 'Edit',
                          onPressed: () => _openForm(guide),
                          icon: const Icon(Icons.edit_outlined),
                        ),
                        IconButton(
                          tooltip: 'Delete',
                          onPressed: () => _delete(guide),
                          icon: const Icon(Icons.delete_outline),
                        ),
                      ],
                    ),
                    onTap: () async {
                      await Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => CropCareDetailScreen(
                            articleId: guide.id,
                            category: guide.category,
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
