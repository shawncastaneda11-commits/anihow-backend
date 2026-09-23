import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/dashed_photo_box.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import 'tawad_form_screen.dart';
import 'walk_in_sale_screen.dart';

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
  int? _cropTypeId;
  String? _imagePath;
  bool _busy = false;
  late Future<List<CategoryItem>> _cropTypes;
  ListingItem? _listing;

  @override
  void initState() {
    super.initState();
    final listing = widget.listing;
    _listing = listing;
    if (listing != null) {
      _name.text = listing.title;
      _price.text = listing.pricePerUnit;
      _quantity.text = listing.quantityAvailable;
      _description.text = listing.description ?? '';
      _cropTypeId = listing.category?.id;
    }
    _cropTypes = context.read<AuthController>().api.cropTypes();
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
    if (_cropTypeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).chooseCrop)),
      );
      return;
    }
    setState(() => _busy = true);
    final api = context.read<AuthController>().api;
    final body = {
      'title': _name.text.trim(),
      'crop_type_id': _cropTypeId,
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
    final listing = _listing;
    if (listing == null || _busy) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) {
        final s = AppStrings.of(context);
        return AlertDialog(
          title: Text(s.deleteListingAsk),
          content: Text(listing.title),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: Text(s.cancel)),
            TextButton(onPressed: () => Navigator.pop(context, true), child: Text(s.delete)),
          ],
        );
      },
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

  CategoryItem? _selectedCrop(List<CategoryItem> cropTypes) {
    for (final cropType in cropTypes) {
      if (cropType.id == _cropTypeId) {
        return cropType;
      }
    }
    return null;
  }

  String? _unitFor(List<CategoryItem> cropTypes) {
    final cropType = _selectedCrop(cropTypes);
    if (cropType != null) {
      return cropType.unitLabel ?? cropType.unit;
    }
    return widget.listing?.unitLabel ?? widget.listing?.unit;
  }

  Future<void> _openTawad() async {
    final listing = _listing;
    if (listing == null) {
      return;
    }
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => TawadFormScreen(listing: listing)),
    );
    if (changed == true && mounted) {
      await _reloadListing();
    }
  }

  Future<void> _endTawad() async {
    final listing = _listing;
    final rule = listing?.tawad;
    if (listing == null || rule == null || _busy) {
      return;
    }
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) {
        final s = AppStrings.of(context);
        return AlertDialog(
          title: Text(s.endTawadAsk),
          content: Text(s.tawadKeepPrice),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: Text(s.back)),
            TextButton(onPressed: () => Navigator.pop(context, true), child: Text(s.endTawad)),
          ],
        );
      },
    );
    if (confirmed != true || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.endTawad(
            listingId: listing.id,
            tawadRuleId: rule.id,
          );
      if (mounted) {
        await _reloadListing();
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

  Future<void> _reloadListing() async {
    final listing = _listing;
    if (listing == null) {
      return;
    }
    try {
      final fresh = await context.read<AuthController>().api.farmerListing(listing.id);
      if (mounted) {
        setState(() => _listing = fresh);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _openWalkIn() async {
    final listing = _listing;
    if (listing == null) {
      return;
    }
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => WalkInSaleScreen(listingId: listing.id)),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.listing == null ? s.newListing : s.editListing),
        actions: [
          if (widget.listing != null)
            IconButton(
              tooltip: s.deleteListing,
              onPressed: _busy ? null : _deleteListing,
              icon: const Icon(Icons.delete_outline),
            ),
        ],
      ),
      body: FutureBuilder<List<CategoryItem>>(
        future: _cropTypes,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final cropTypes = snapshot.data ?? const [];
          final selectedCrop = _selectedCrop(cropTypes);
          final unit = _unitFor(cropTypes);
          final floor = selectedCrop?.sellerFloorPrice;
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    AniHowFormCard(
                      child: DashedPhotoBox(
                        filePath: _imagePath,
                        networkUrl: widget.listing?.imageUrl,
                        onTap: _pickPhoto,
                        emptyLabel: s.addPhoto,
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowHintCard(
                      icon: Icons.photo_camera_outlined,
                      title: s.listingPhotoHint,
                      tone: AniHowHintTone.brand,
                    ),
                    const SizedBox(height: AniHowSpace.section),
                    AniHowFormCard(
                      title: s.listingDetails,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          AniHowField(
                            label: s.titleLabel,
                            child: TextField(
                              controller: _name,
                              textCapitalization: TextCapitalization.words,
                              decoration: InputDecoration(hintText: s.nameProduce),
                            ),
                          ),
                          const SizedBox(height: AniHowSpace.fieldGap),
                          AniHowField(
                            label: s.cropType,
                            child: DropdownButtonFormField<int>(
                              initialValue: _cropTypeId,
                              items: cropTypes
                                  .map(
                                    (cropType) => DropdownMenuItem(
                                      value: cropType.id,
                                      child: Text(
                                        cropType.labelFor(context.watch<PreferencesController>().language),
                                      ),
                                    ),
                                  )
                                  .toList(),
                              onChanged: (value) => setState(() => _cropTypeId = value),
                            ),
                          ),
                          if (unit != null && unit.isNotEmpty) ...[
                            const SizedBox(height: AniHowSpace.cardGap),
                            Text(s.unitLine(unit), style: Theme.of(context).textTheme.bodyMedium),
                          ],
                          const SizedBox(height: AniHowSpace.fieldGap),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: AniHowField(
                                  label: s.price,
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
                                  label: s.quantity,
                                  child: TextField(
                                    controller: _quantity,
                                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          if (floor != null && floor.isNotEmpty) ...[
                            const SizedBox(height: AniHowSpace.cardGap),
                            Text(
                              s.floorPriceFor(
                                selectedCrop!.labelFor(context.watch<PreferencesController>().language),
                                AniHowMoney.peso(floor),
                              ),
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
                                  ),
                            ),
                          ],
                          const SizedBox(height: AniHowSpace.fieldGap),
                          AniHowField(
                            label: s.description,
                            child: TextField(
                              controller: _description,
                              maxLines: 4,
                              textCapitalization: TextCapitalization.sentences,
                              decoration: InputDecoration(hintText: s.shortNote),
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (_listing != null) ...[
                      const SizedBox(height: AniHowSpace.section),
                      AniHowFormCard(
                        title: s.tawad,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (_listing!.tawad != null) ...[
                              Text(_listing!.tawad!.summary),
                              if (_listing!.tawad!.typeLabel != null) Text(_listing!.tawad!.typeLabel!),
                              const SizedBox(height: AniHowSpace.cardGap),
                              PrimaryButton(
                                label: s.replaceTawad,
                                onPressed: _busy ? null : _openTawad,
                              ),
                              const SizedBox(height: AniHowSpace.cardGap),
                              OutlinedButton(
                                onPressed: _busy ? null : _endTawad,
                                child: Text(s.endTawad),
                              ),
                            ] else ...[
                              Text(s.noTawad),
                              const SizedBox(height: AniHowSpace.cardGap),
                              PrimaryButton(label: s.setTawad, onPressed: _busy ? null : _openTawad),
                            ],
                          ],
                        ),
                      ),
                      if (!_listing!.isTakenDown &&
                          (context.watch<AuthController>().user?.canRecordWalkInSales ?? false)) ...[
                        const SizedBox(height: AniHowSpace.cardGap),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : _openWalkIn,
                          icon: const Icon(Icons.point_of_sale_outlined),
                          label: Text(s.recordWalkIn),
                        ),
                      ],
                    ],
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
                  child: PrimaryButton(label: s.saveListing, busy: _busy, onPressed: _save),
                ),
              ),
            ],
          );
        },
      ),
    );
  }
}
