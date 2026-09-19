import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/category_color.dart';
import 'crop_care_category_screen.dart';
import 'crop_care_form_screen.dart';
import 'crop_care_mine_screen.dart';

class CropCareScreen extends StatefulWidget {
  const CropCareScreen({super.key});

  @override
  State<CropCareScreen> createState() => _CropCareScreenState();
}

class _CropCareScreenState extends State<CropCareScreen> {
  late Future<List<CropCareCategory>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.cropCareCategories();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.cropCareCategories();
    setState(() {
      _future = future;
    });
    await future;
  }

  Future<void> _openForm() async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => const CropCareFormScreen()),
    );
    if (saved == true && mounted) {
      await _reload();
    }
  }

  Future<void> _openMine() async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const CropCareMineScreen()),
    );
    if (mounted) {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      floatingActionButton: FloatingActionButton(
        onPressed: _openForm,
        tooltip: 'Add Care Guide',
        child: const Icon(Icons.add),
      ),
      body: FutureBuilder<List<CropCareCategory>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final categories = snapshot.data ?? const [];

          return RefreshIndicator(
            onRefresh: _reload,
            child: CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPadding(
                  padding: AniHowSpace.screenPadding,
                  sliver: SliverToBoxAdapter(
                    child: Card(
                      child: ListTile(
                        leading: CircleAvatar(
                          radius: AniHowSpace.avatar,
                          backgroundColor: AniHowColors.brand.withValues(alpha: 0.16),
                          foregroundColor: AniHowColors.brand,
                          child: const Icon(Icons.edit_note),
                        ),
                        title: const Text('My Care Guides'),
                        subtitle: const Text('Write, edit, or delete your tips'),
                        trailing: const Icon(Icons.chevron_right),
                        onTap: _openMine,
                      ),
                    ),
                  ),
                ),
                if (categories.isEmpty)
                  const SliverFillRemaining(
                    hasScrollBody: false,
                    child: _CropCareEmpty(
                      icon: Icons.menu_book_outlined,
                      message: 'No crop-care tips yet',
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(
                      AniHowSpace.screen,
                      0,
                      AniHowSpace.screen,
                      88,
                    ),
                    sliver: SliverGrid(
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        mainAxisSpacing: AniHowSpace.cardGap,
                        crossAxisSpacing: AniHowSpace.cardGap,
                        childAspectRatio: 1.05,
                      ),
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          final category = categories[index];
                          final chip = category.asCategory;
                          final accent = CategoryColor.of(chip);

                          return Card(
                            clipBehavior: Clip.antiAlias,
                            child: InkWell(
                              onTap: () async {
                                await Navigator.of(context).push(
                                  MaterialPageRoute<void>(
                                    builder: (_) => CropCareCategoryScreen(category: category),
                                  ),
                                );
                                if (mounted) {
                                  await _reload();
                                }
                              },
                              child: Padding(
                                padding: AniHowSpace.cardPadding,
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    CircleAvatar(
                                      radius: AniHowSpace.avatar,
                                      backgroundColor: accent.withValues(alpha: 0.16),
                                      foregroundColor: accent,
                                      child: Icon(CategoryColor.iconOf(chip)),
                                    ),
                                    const Spacer(),
                                    Text(
                                      category.name,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: Theme.of(context).textTheme.titleMedium,
                                    ),
                                    const SizedBox(height: AniHowSpace.labelGap),
                                    Text(
                                      category.tipsCount == 1
                                          ? '1 tip'
                                          : '${category.tipsCount} tips',
                                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .onSurface
                                                .withValues(alpha: 0.65),
                                            fontSize: AniHowSpace.meta,
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          );
                        },
                        childCount: categories.length,
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _CropCareEmpty extends StatelessWidget {
  const _CropCareEmpty({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        Icon(icon, size: 48, color: AniHowColors.brand.withValues(alpha: 0.7)),
        const SizedBox(height: AniHowSpace.cardGap),
        Text(message, textAlign: TextAlign.center, style: Theme.of(context).textTheme.bodyLarge),
      ],
    );
  }
}
