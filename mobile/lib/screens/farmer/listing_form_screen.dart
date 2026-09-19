import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/dashed_photo_box.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../state/auth_controller.dart';

class ListingFormScreen extends StatefulWidget {
  const ListingFormScreen({super.key, this.listing});

  final ListingItem? listing;

  @override
  State<ListingFormScreen> createState() => _ListingFormScreenState();
}

class _ListingFormScreenState extends State<ListingFormScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _quantity = TextEditingController();
  final _description = TextEditingController();
  String _unit = 'kg';
  int? _categoryId;
  String? _imagePath;
  bool _busy = false;
  late Future<List<CategoryItem>> _categories;

  static const units = ['kg', 'g', 'piece', 'bundle', 'sack', 'tray', 'liter'];

  @override
  void initState() {
    super.initState();
    final listing = widget.listing;
    if (listing != null) {
      _name.text = listing.name;
      _price.text = listing.pricePerUnit;
      _quantity.text = listing.quantityAvailable;
      _description.text = listing.description ?? '';
      _unit = listing.unit ?? 'kg';
      _categoryId = listing.category?.id;
    }
    _categories = context.read<AuthController>().api.categories();
  }

  @override
  void dispose() {
    _name.dispose();
    _price.dispose();
    _quantity.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto() async {
    final file = await ImagePicker().pickImage(source: ImageSource.gallery, imageQuality: 85);
    if (file != null && mounted) {
      setState(() => _imagePath = file.path);
    }
  }

  Future<void> _save() async {
    if (_categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a category.')),
      );
      return;
    }
    setState(() => _busy = true);
    final api = context.read<AuthController>().api;
    final body = {
      'name': _name.text.trim(),
      'category_id': _categoryId,
      'unit': _unit,
      'price_per_unit': _price.text.trim(),
      'quantity_available': _quantity.text.trim(),
      'description': _description.text.trim(),
    };
    try {
      if (widget.listing == null) {
        await api.createListing(body, imagePath: _imagePath);
      } else {
        await api.updateListing(widget.listing!.id, body, imagePath: _imagePath);
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

  Future<void> _deleteListing() async {
    final listing = widget.listing;
    if (listing == null || _busy) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this listing?'),
        content: Text(listing.name),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.deleteListing(listing.id);
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

  static String _unitLabel(String unit) {
    if (unit.isEmpty) {
      return unit;
    }
    return unit[0].toUpperCase() + unit.substring(1);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.listing == null ? 'New listing' : 'Edit listing'),
        actions: [
          if (widget.listing != null)
            IconButton(
              tooltip: 'Delete listing',
              onPressed: _busy ? null : _deleteListing,
              icon: const Icon(Icons.delete_outline),
            ),
        ],
      ),
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
                    DashedPhotoBox(
                      filePath: _imagePath,
                      networkUrl: widget.listing?.imageUrl,
                      onTap: _pickPhoto,
                    ),
                    const SizedBox(height: AniHowSpace.section),
                    AniHowField(
                      label: 'Name',
                      child: TextField(
                        controller: _name,
                        textCapitalization: TextCapitalization.words,
                        decoration: const InputDecoration(hintText: 'Name this produce'),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: AniHowField(
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
                        ),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: AniHowField(
                            label: 'Unit',
                            child: DropdownButtonFormField<String>(
                              initialValue: _unit,
                              items: units
                                  .map(
                                    (unit) => DropdownMenuItem(
                                      value: unit,
                                      child: Text(_unitLabel(unit)),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => setState(() => _unit = value ?? 'kg'),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: AniHowField(
                            label: 'Price',
                            child: TextField(
                              controller: _price,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                              decoration: const InputDecoration(
                                prefixText: '₱ ',
                                hintText: '0.00',
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: AniHowField(
                            label: 'Quantity',
                            child: TextField(
                              controller: _quantity,
                              keyboardType: const TextInputType.numberWithOptions(decimal: true),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    AniHowField(
                      label: 'Description',
                      child: TextField(
                        controller: _description,
                        maxLines: 4,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(hintText: 'Add a short note'),
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
                  child: PrimaryButton(label: 'Save listing', busy: _busy, onPressed: _save),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
