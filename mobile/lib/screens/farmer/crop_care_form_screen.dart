import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/dashed_photo_box.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';

class CropCareFormScreen extends StatefulWidget {
  const CropCareFormScreen({super.key, this.article});

  final CropCareArticle? article;

  @override
  State<CropCareFormScreen> createState() => _CropCareFormScreenState();
}

class _CropCareFormScreenState extends State<CropCareFormScreen> {
  final _title = TextEditingController();
  final _body = TextEditingController();
  int? _categoryId;
  String? _imagePath;
  bool _busy = false;
  late Future<List<CategoryItem>> _categories;

  static const _titleHint = 'Name this guide';
  static const _instructionsHint = 'Write the steps, materials, and what to avoid.';

  @override
  void initState() {
    super.initState();
    final article = widget.article;
    if (article != null) {
      _title.text = article.title;
      _body.text = article.body;
      _categoryId = article.category?.id;
    }
    _categories = context.read<AuthController>().api.categories();
  }

  @override
  void dispose() {
    _title.dispose();
    _body.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto() async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file != null && mounted) {
      setState(() => _imagePath = file.path);
    }
  }

  Future<void> _save() async {
    if (_title.text.trim().isEmpty || _body.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add a title and care instructions.')),
      );
      return;
    }
    if (_categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a category.')),
      );
      return;
    }
    setState(() => _busy = true);
    final api = context.read<AuthController>().api;
    try {
      if (widget.article == null) {
        await api.createCropCare(
          title: _title.text.trim(),
          body: _body.text.trim(),
          categoryId: _categoryId!,
          imagePath: _imagePath,
        );
      } else {
        await api.updateCropCare(
          id: widget.article!.id,
          title: _title.text.trim(),
          body: _body.text.trim(),
          categoryId: _categoryId!,
          imagePath: _imagePath,
        );
      }
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  List<CategoryItem> _orderedCategories(List<CategoryItem> categories) {
    return [
      ...categories.where((category) => category.name.toLowerCase() == 'vegetables'),
      ...categories.where((category) => category.name.toLowerCase() != 'vegetables'),
    ];
  }

  @override
  Widget build(BuildContext context) {
    final editing = widget.article != null;

    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit Care Guide' : 'Add Care Guide')),
      body: FutureBuilder<List<CategoryItem>>(
        future: _categories,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final categories = snapshot.data ?? const [];
          return Column(
            children: [
              Expanded(
                child: Padding(
                  padding: AniHowSpace.screenPadding,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      DashedPhotoBox(
                        filePath: _imagePath,
                        networkUrl: widget.article?.imageUrl,
                        onTap: _pickPhoto,
                        emptyLabel: 'Upload crop photo / diagram (Optional)',
                        emptyIcon: Icons.photo_camera_outlined,
                        height: 112,
                      ),
                      const SizedBox(height: AniHowSpace.section),
                      AniHowField(
                        label: 'Guide Title',
                        child: TextField(
                          controller: _title,
                          decoration: const InputDecoration(hintText: _titleHint),
                        ),
                      ),
                      const SizedBox(height: AniHowSpace.fieldGap),
                      Text(
                        'Crop / Category Selector',
                        style: Theme.of(context).textTheme.labelSmall,
                      ),
                      const SizedBox(height: AniHowSpace.labelGap),
                      Wrap(
                        spacing: 8,
                        runSpacing: 4,
                        children: [
                          for (final category in _orderedCategories(categories))
                            ChoiceChip(
                              label: Text(category.name),
                              selected: _categoryId == category.id,
                              showCheckmark: true,
                              visualDensity: VisualDensity.compact,
                              materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                              padding: EdgeInsets.zero,
                              labelPadding: const EdgeInsets.symmetric(horizontal: 10),
                              selectedColor: AniHowColors.navActive,
                              backgroundColor: AniHowColors.card,
                              side: BorderSide(
                                color: _categoryId == category.id
                                    ? AniHowColors.navActive
                                    : AniHowColors.hairline,
                              ),
                              labelStyle: TextStyle(
                                color: _categoryId == category.id
                                    ? AniHowColors.brand
                                    : AniHowColors.muted,
                                fontWeight: FontWeight.w600,
                              ),
                              onSelected: (_) => setState(() => _categoryId = category.id),
                            ),
                        ],
                      ),
                      const SizedBox(height: AniHowSpace.section),
                      Text(
                        'Care Instructions',
                        style: Theme.of(context).textTheme.labelSmall,
                      ),
                      const SizedBox(height: AniHowSpace.labelGap),
                      Expanded(
                        child: TextField(
                          controller: _body,
                          expands: true,
                          maxLines: null,
                          minLines: null,
                          textAlignVertical: TextAlignVertical.top,
                          decoration: const InputDecoration(
                            hintText: _instructionsHint,
                            alignLabelWithHint: true,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(
                    AniHowSpace.screen,
                    8,
                    AniHowSpace.screen,
                    AniHowSpace.screen,
                  ),
                  child: PrimaryButton(
                    label: editing ? 'Save Care Guide' : 'Publish Care Guide',
                    busy: _busy,
                    onPressed: _save,
                  ),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
