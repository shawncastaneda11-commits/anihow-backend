import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
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
  bool _busy = false;
  late Future<List<CategoryItem>> _categories;

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

  Future<void> _save() async {
    if (_title.text.trim().isEmpty || _body.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add a title and body.')),
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
        );
      } else {
        await api.updateCropCare(
          id: widget.article!.id,
          title: _title.text.trim(),
          body: _body.text.trim(),
          categoryId: _categoryId!,
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

  @override
  Widget build(BuildContext context) {
    final editing = widget.article != null;

    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit guide' : 'Add guide')),
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
                child: ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    AniHowField(
                      label: 'Title',
                      child: TextField(controller: _title),
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    AniHowField(
                      label: 'Category',
                      child: DropdownButtonFormField<int>(
                        initialValue: _categoryId,
                        items: categories
                            .map(
                              (category) => DropdownMenuItem(
                                value: category.id,
                                child: Text(category.name),
                              ),
                            )
                            .toList(),
                        onChanged: (value) => setState(() => _categoryId = value),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    AniHowField(
                      label: 'Body',
                      child: TextField(
                        controller: _body,
                        maxLines: 10,
                        minLines: 6,
                      ),
                    ),
                  ],
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
                    label: editing ? 'Save guide' : 'Publish guide',
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
