import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/dashed_photo_box.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/promo_badge.dart';
import '../../push/push_permission.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/order_quantity.dart';
import 'cancel_reservations_dialog.dart';
import 'listing_delete.dart';
import 'harvest_form.dart';
import 'stock_history_screen.dart';
import 'stock_sheets.dart';
import 'tawad_form_screen.dart';
import 'walk_in_sale_screen.dart';

class ListingFormScreen extends StatefulWidget {
  const ListingFormScreen({super.key, this.listing, this.cropTypes});

  final ListingItem? listing;

  /// When set, the form does not call the network for crop types.
  final Future<List<CategoryItem>>? cropTypes;

  @override
  State<ListingFormScreen> createState() => _ListingFormScreenState();
}

class _ListingFormScreenState extends State<ListingFormScreen> {
  final _name = TextEditingController();
  final _price = TextEditingController();
  final _quantity = TextEditingController();
  final _harvestedQty = TextEditingController();
  final _rejected = TextEditingController(text: '0');
  final _rejectionNote = TextEditingController();
  final _productionCost = TextEditingController();
  final _costs = {
    for (final category in costCategories) category: TextEditingController(),
  };
  String? _rejectionReason;
  bool _breakdown = false;
  String? _formError;
  bool _showReasonError = false;
  DateTime _harvestDate = DateTime.now();
  final _minOrder = TextEditingController(text: '1');
  final _orderStep = TextEditingController(text: '1');
  final _description = TextEditingController();
  int? _cropTypeId;
  String? _unit;
  String? _imagePath;
  DateTime? _availableFrom;
  DateTime? _availableUntil;
  DateTime? _harvestedOn;
  String _growingMethod = '';
  bool _isActive = true;
  bool _discountOpen = false;
  bool _busy = false;
  final _photoKey = GlobalKey();
  final _detailsKey = GlobalKey();
  final _priceKey = GlobalKey();
  final _fourthKey = GlobalKey();
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
      _minOrder.text = formatOrderAmount(listing.minOrderQuantity);
      _orderStep.text = formatOrderAmount(listing.orderStep);
      _description.text = listing.description ?? '';
      _cropTypeId = listing.category?.id;
      _unit = listing.unit;
      _availableFrom = listing.availableFrom?.toLocal();
      _availableUntil = listing.availableUntil?.toLocal();
      _harvestedOn = listing.harvestedOn?.toLocal();
      _growingMethod = listing.growingMethod ?? '';
      _isActive = listing.isActive;
      _discountOpen = listing.tawad?.isActive == true;
    }
    _cropTypes =
        widget.cropTypes ?? context.read<AuthController>().api.cropTypes();
    for (final controller in [_name, _price, _quantity, _harvestedQty]) {
      controller.addListener(_refreshSteps);
    }
  }

  void _refreshSteps() {
    if (mounted) {
      setState(() {});
    }
  }

  bool get _photoDone {
    final picked = _imagePath != null && _imagePath!.isNotEmpty;
    final existing =
        widget.listing?.imageUrl != null &&
        widget.listing!.imageUrl!.isNotEmpty;
    return picked || existing;
  }

  bool get _detailsDone => _name.text.trim().isNotEmpty && _cropTypeId != null;

  bool get _priceDone {
    final price = double.tryParse(_price.text.trim()) ?? 0;
    return _unit != null && _unit!.isNotEmpty && price > 0;
  }

  bool get _fourthDone {
    if (widget.listing != null) {
      return true;
    }
    if (_isUpcoming) {
      return (double.tryParse(_quantity.text.trim()) ?? 0) > 0;
    }
    return (double.tryParse(_harvestedQty.text.trim()) ?? 0) > 0;
  }

  String _fourthLabel(AppStrings s) {
    if (widget.listing != null) {
      return s.stepStock;
    }
    if (_isUpcoming) {
      return s.stepExpected;
    }
    return s.harvestSection;
  }

  String _saveHint(AppStrings s) {
    final missing = <String>[
      if (!_photoDone) s.stepPhoto.toLowerCase(),
      if (!_detailsDone) s.stepDetails.toLowerCase(),
      if (!_priceDone) s.stepPrice.toLowerCase(),
      if (!_fourthDone) _fourthLabel(s).toLowerCase(),
    ];
    if (missing.isEmpty) {
      return s.everythingFilledIn;
    }
    return s.notFilledYet(missing.join(', '));
  }

  void _scrollTo(GlobalKey key) {
    final target = key.currentContext;
    if (target == null) {
      return;
    }
    Scrollable.ensureVisible(
      target,
      duration: const Duration(milliseconds: 250),
      alignment: 0.05,
    );
  }

  @override
  void dispose() {
    for (final controller in [_name, _price, _quantity, _harvestedQty]) {
      controller.removeListener(_refreshSteps);
    }
    _name.dispose();
    _price.dispose();
    _quantity.dispose();
    _harvestedQty.dispose();
    _rejected.dispose();
    _rejectionNote.dispose();
    _productionCost.dispose();
    for (final controller in _costs.values) {
      controller.dispose();
    }
    _minOrder.dispose();
    _orderStep.dispose();
    _description.dispose();
    super.dispose();
  }

  Future<void> _pickDate({
    required DateTime? current,
    required ValueChanged<DateTime> onPicked,
    DateTime? firstDate,
    DateTime? lastDate,
  }) async {
    final picked = await showDatePicker(
      context: context,
      initialDate: current ?? DateTime.now(),
      firstDate: firstDate ?? DateTime(2020),
      lastDate: lastDate ?? DateTime.now().add(const Duration(days: 365 * 3)),
    );
    if (picked != null) {
      onPicked(picked);
    }
  }

  String? _dayStart(DateTime? date) {
    if (date == null) {
      return null;
    }
    return DateTime(date.year, date.month, date.day).toIso8601String();
  }

  String? _dayEnd(DateTime? date) {
    if (date == null) {
      return null;
    }
    return DateTime(
      date.year,
      date.month,
      date.day,
      23,
      59,
      59,
    ).toIso8601String();
  }

  String? _dayOnly(DateTime? date) {
    if (date == null) {
      return null;
    }
    final month = date.month.toString().padLeft(2, '0');
    final day = date.day.toString().padLeft(2, '0');
    return '${date.year}-$month-$day';
  }

  Future<void> _pickPhoto() async {
    final file = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      imageQuality: 85,
    );
    if (file != null && mounted) {
      setState(() => _imagePath = file.path);
    }
  }

  Future<void> _save({bool confirmed = false}) async {
    if (_harvestReasonMissing()) {
      setState(() => _showReasonError = true);
      return;
    }
    if (_cropTypeId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).chooseCrop)),
      );
      return;
    }
    final rule = _orderRuleMessage();
    if (rule != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(rule)));
      return;
    }
    final listing = widget.listing;
    final turningOff = listing != null && listing.isActive && !_isActive;
    if (!confirmed &&
        turningOff &&
        (listing.activeReservationsCount ?? 0) > 0) {
      final accepted = await confirmCancelReservations(
        context,
        count: listing.activeReservationsCount ?? 0,
        quantity: formatReservedQuantity(listing.reservedQuantity),
        unit: listing.unit ?? '',
        deleting: false,
      );
      if (!accepted || !mounted) {
        return;
      }
      confirmed = true;
    }
    setState(() => _busy = true);
    final api = context.read<AuthController>().api;
    final farmCertified =
        context.read<AuthController>().user?.farmIsOrganicCertified == true;
    final keepStoredClaim =
        !farmCertified && _growingMethod == 'certified_organic';
    final editing = listing != null;
    final upcoming = _isUpcoming;
    final body = {
      'title': _name.text.trim(),
      'crop_type_id': _cropTypeId,
      'unit': _unit,
      'price_per_unit': _price.text.trim(),
      if (editing && listing.needsActualHarvest)
        'quantity_available': _quantity.text.trim(),
      if (!editing && upcoming) 'quantity_available': _quantity.text.trim(),
      if (!editing && !upcoming) ..._harvestBody(),
      'min_order_quantity': _minOrder.text.trim(),
      'order_step': _orderStep.text.trim(),
      'description': _description.text.trim(),
      'available_from': _dayStart(_availableFrom),
      'available_until': _dayEnd(_availableUntil),
      if (editing) 'harvested_on': _dayOnly(_harvestedOn),
      if (!keepStoredClaim)
        'growing_method': _growingMethod.isEmpty ? null : _growingMethod,
      if (listing != null && !listing.isTakenDown) 'is_active': _isActive,
    };
    try {
      if (listing == null) {
        await api.createListing(body, imagePath: _imagePath);
      } else {
        await api.updateListing(
          listing.id,
          body,
          imagePath: _imagePath,
          confirmCancelReservations: confirmed,
        );
      }
      if (mounted) {
        await offerPushPermission(context);
      }
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      final conflict = ReservationConflict.fromException(error);
      if (conflict != null && !confirmed && mounted) {
        setState(() => _busy = false);
        final accepted = await confirmCancelReservations(
          context,
          count: conflict.count,
          quantity: conflict.quantity,
          unit: listing?.unit ?? '',
          deleting: false,
        );
        if (accepted && mounted) {
          await _save(confirmed: true);
        }
        return;
      }
      if (mounted) {
        setState(() => _formError = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  bool _harvestReasonMissing() {
    if (widget.listing != null || _isUpcoming) {
      return false;
    }
    final rejected = double.tryParse(_rejected.text.trim()) ?? 0;
    return rejected > 0 &&
        (_rejectionReason == null || _rejectionReason!.isEmpty);
  }

  bool get _isUpcoming {
    final from = _availableFrom;
    if (from == null) {
      return false;
    }
    final start = DateTime(from.year, from.month, from.day);
    final today = DateTime.now();
    return start.isAfter(DateTime(today.year, today.month, today.day));
  }

  Map<String, dynamic> _harvestBody() {
    final rejected = double.tryParse(_rejected.text.trim()) ?? 0;
    final breakdown = <String, String>{};
    if (_breakdown) {
      for (final entry in _costs.entries) {
        if (entry.value.text.trim().isNotEmpty) {
          breakdown[entry.key] = entry.value.text.trim();
        }
      }
    }
    return {
      'harvested_on': _dayOnly(_harvestDate),
      'quantity_harvested': _harvestedQty.text.trim(),
      'quantity_rejected': _rejected.text.trim().isEmpty
          ? '0'
          : _rejected.text.trim(),
      if (rejected > 0 && _rejectionReason != null)
        'rejection_reason': _rejectionReason,
      if (rejected > 0 && _rejectionReason == 'other')
        'rejection_note': _rejectionNote.text.trim(),
      if (!_breakdown && _productionCost.text.trim().isNotEmpty)
        'production_cost': _productionCost.text.trim(),
      if (_breakdown && breakdown.isNotEmpty) 'cost_breakdown': breakdown,
    };
  }

  void _syncCost() {
    if (!_breakdown) {
      return;
    }
    if (!breakdownHasAmount(_costs)) {
      _productionCost.text = '';
      return;
    }
    _productionCost.text = breakdownTotal(_costs).toStringAsFixed(2);
  }

  Future<void> _deleteListing() async {
    final listing = _listing;
    if (listing == null || _busy) {
      return;
    }
    final deleted = await deleteListingFlow(
      context,
      listing,
      onBusy: (busy) {
        if (mounted) {
          setState(() => _busy = busy);
        }
      },
    );
    if (deleted && mounted) {
      Navigator.of(context).pop(true);
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

  String? _resolvedUnit(CategoryItem? crop) {
    if (crop == null) {
      return null;
    }
    final allowed = crop.allowedUnits.map((unit) => unit.value).toList();
    if (_unit != null && (allowed.isEmpty || allowed.contains(_unit))) {
      return _unit;
    }
    if (crop.unit != null && (allowed.isEmpty || allowed.contains(crop.unit))) {
      return crop.unit;
    }
    return allowed.isEmpty ? crop.unit : allowed.first;
  }

  String? _orderRuleMessage() {
    final s = AppStrings.read(context);
    final code = orderRuleCode(
      unit: _unit,
      minText: _minOrder.text,
      stepText: _orderStep.text,
    );
    if (code == null) {
      return null;
    }
    final name = s.filipino ? s.unitName(_unit ?? '') : wholeSaleName(_unit);
    return switch (code) {
      'whole-step' => s.soldWholeStep(name),
      'whole-min' => s.soldWholeMinimum(name),
      'min-step' => s.minimumAtLeastStep,
      'decimals' => s.orderAmountDecimals,
      'max' => s.orderAmountMax,
      _ => s.orderAmountPositive,
    };
  }

  Widget _orderRuleFields(AppStrings s) {
    final unit = _unit ?? '';
    final whole = sellsWhole(unit);
    final min = double.tryParse(_minOrder.text.trim());
    final step = double.tryParse(_orderStep.text.trim());
    final keyboard = whole
        ? TextInputType.number
        : const TextInputType.numberWithOptions(decimal: true);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: AniHowField(
                label: '${s.minimumOrder} ($unit)',
                child: TextField(
                  key: const ValueKey('listing-min-order'),
                  controller: _minOrder,
                  keyboardType: keyboard,
                  onChanged: (_) => setState(() {}),
                ),
              ),
            ),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: AniHowField(
                label: '${s.orderStep} ($unit)',
                child: TextField(
                  key: const ValueKey('listing-order-step'),
                  controller: _orderStep,
                  keyboardType: keyboard,
                  onChanged: (_) => setState(() {}),
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: [
            for (final choice in stepChoices(unit))
              SizedBox(
                height: 48,
                child: ActionChip(
                  key: ValueKey('order-step-chip-${formatOrderAmount(choice)}'),
                  label: Text(formatOrderAmount(choice)),
                  onPressed: () {
                    setState(() {
                      _orderStep.text = formatOrderAmount(choice);
                    });
                  },
                ),
              ),
          ],
        ),
        if (min != null && step != null && min > 0 && step > 0) ...[
          const SizedBox(height: 8),
          Text(
            key: const ValueKey('listing-order-preview'),
            s.buyersCanOrder(previewAmounts(min, step), unit),
          ),
        ],
      ],
    );
  }

  List<DropdownMenuItem<String>> _unitItems(CategoryItem crop, AppStrings s) {
    final allowed = crop.allowedUnits.isEmpty
        ? [AllowedListingUnit(value: crop.unit ?? 'kg', family: '')]
        : crop.allowedUnits;
    final families = <String>[];
    for (final unit in allowed) {
      if (!families.contains(unit.family)) {
        families.add(unit.family);
      }
    }
    final items = <DropdownMenuItem<String>>[];
    for (final family in families) {
      if (family.isNotEmpty) {
        items.add(
          DropdownMenuItem(
            enabled: false,
            value: '#$family',
            child: Text(
              s.unitFamily(family),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        );
      }
      for (final unit in allowed.where((row) => row.family == family)) {
        items.add(
          DropdownMenuItem(
            value: unit.value,
            child: SizedBox(
              height: 48,
              child: Align(
                alignment: Alignment.centerLeft,
                child: Text(s.unitName(unit.value)),
              ),
            ),
          ),
        );
      }
    }
    return items;
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
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(s.back),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(s.endTawad),
            ),
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
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
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
      final fresh = await context.read<AuthController>().api.farmerListing(
        listing.id,
      );
      if (mounted) {
        setState(() => _listing = fresh);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  bool _discountPaused(BuildContext context) {
    final listing = _listing;
    if (listing == null) {
      return false;
    }
    final features =
        context.watch<AuthController>().user?.farmFeatures ??
        const FarmFeatures();
    return listing.tawadPaused || (listing.tawad != null && !features.tawad);
  }

  bool _showDiscount(BuildContext context) {
    final features =
        context.watch<AuthController>().user?.farmFeatures ??
        const FarmFeatures();
    return features.tawad ||
        _listing?.tawad != null ||
        _listing?.tawadPaused == true;
  }

  bool _walkInAllowed(BuildContext context) {
    final user = context.watch<AuthController>().user;
    return (user?.canRecordWalkInSales ?? false) &&
        (user?.farmFeatures.walkIn ?? true);
  }

  Future<void> _openWalkIn() async {
    final listing = _listing;
    if (listing == null) {
      return;
    }
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => WalkInSaleScreen(listingId: listing.id),
      ),
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
              tooltip: s.stockHistory,
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => StockHistoryScreen(
                      listingId: widget.listing!.id,
                      listingTitle: widget.listing!.title,
                    ),
                  ),
                );
              },
              icon: const Icon(Icons.history),
            ),
          if (widget.listing != null)
            IconButton(
              tooltip: s.deleteListing,
              onPressed: _busy ? null : _deleteListing,
              icon: const Icon(Icons.delete_outline),
            ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(44),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 6),
            child: _FormStepBar(
              steps: [
                (s.stepPhoto, _photoDone, _photoKey),
                (s.stepDetails, _detailsDone, _detailsKey),
                (s.stepPrice, _priceDone, _priceKey),
                (_fourthLabel(s), _fourthDone, _fourthKey),
              ],
              onTap: _scrollTo,
            ),
          ),
        ),
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
          final floor = selectedCrop?.sellerFloorPrice;
          return Column(
            children: [
              Expanded(
                child: ListView(
                  scrollCacheExtent: const ScrollCacheExtent.pixels(8000),
                  padding: AniHowSpace.screenPadding,
                  children: [
                    KeyedSubtree(
                      key: _photoKey,
                      child: DashedPhotoBox(
                        filePath: _imagePath,
                        networkUrl: widget.listing?.imageUrl,
                        onTap: _pickPhoto,
                        emptyLabel: s.addPhoto,
                        hint: s.listingPhotoHint,
                        changeLabel: s.changePhoto,
                        height: 190,
                      ),
                    ),
                    if (_listing?.isTakenDown == true) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      AniHowHintCard(
                        icon: Icons.visibility_off_outlined,
                        title: s.takenDown,
                        body:
                            _listing!.takedownReason ?? s.listingTakenDownHint,
                        tone: AniHowHintTone.cash,
                      ),
                    ],
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowFormCard(
                      child: KeyedSubtree(
                        key: _detailsKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _NumberedHeading(
                              number: '1',
                              title: s.whatAreYouSelling,
                            ),
                            const SizedBox(height: AniHowSpace.fieldGap),
                            AniHowField(
                              label: s.titleLabel,
                              child: TextField(
                                controller: _name,
                                textCapitalization: TextCapitalization.words,
                                decoration: InputDecoration(
                                  hintText: s.nameProduce,
                                ),
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
                                          cropType.labelFor(
                                            context
                                                .watch<PreferencesController>()
                                                .language,
                                          ),
                                        ),
                                      ),
                                    )
                                    .toList(),
                                onChanged: (value) => setState(() {
                                  _cropTypeId = value;
                                  _unit = _resolvedUnit(
                                    _selectedCrop(cropTypes),
                                  );
                                }),
                              ),
                            ),
                            const SizedBox(height: AniHowSpace.fieldGap),
                            AniHowField(
                              label: s.howWasItGrown,
                              child: _GrowingMethodChips(
                                value:
                                    context
                                                .watch<AuthController>()
                                                .user
                                                ?.farmIsOrganicCertified ==
                                            true ||
                                        _growingMethod != 'certified_organic'
                                    ? _growingMethod
                                    : '',
                                certified:
                                    context
                                        .watch<AuthController>()
                                        .user
                                        ?.farmIsOrganicCertified ==
                                    true,
                                notStated: s.notStated,
                                naturallyGrown: s.naturallyGrown,
                                certifiedOrganic: s.certifiedOrganic,
                                onChanged: (value) =>
                                    setState(() => _growingMethod = value),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowFormCard(
                      child: KeyedSubtree(
                        key: _priceKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _NumberedHeading(number: '2', title: s.stepPrice),
                            const SizedBox(height: AniHowSpace.fieldGap),
                            Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                if (selectedCrop != null)
                                  Expanded(
                                    child: AniHowField(
                                      label: s.soldBy,
                                      child: DropdownButtonFormField<String>(
                                        key: ValueKey(
                                          'listing-unit-${selectedCrop.id}-${_unit ?? ''}',
                                        ),
                                        initialValue: _resolvedUnit(
                                          selectedCrop,
                                        ),
                                        isExpanded: true,
                                        items: _unitItems(selectedCrop, s),
                                        onChanged: (value) {
                                          if (value == null ||
                                              value.startsWith('#')) {
                                            return;
                                          }
                                          setState(() {
                                            _unit = value;
                                            if (sellsWhole(value)) {
                                              final min =
                                                  double.tryParse(
                                                    _minOrder.text,
                                                  ) ??
                                                  1;
                                              if (orderHundredths(min) % 100 !=
                                                  0) {
                                                _minOrder.text = '1';
                                              }
                                              final step =
                                                  double.tryParse(
                                                    _orderStep.text,
                                                  ) ??
                                                  1;
                                              if (orderHundredths(step) % 100 !=
                                                  0) {
                                                _orderStep.text = '1';
                                              }
                                            }
                                          });
                                        },
                                      ),
                                    ),
                                  ),
                                if (selectedCrop != null)
                                  const SizedBox(width: AniHowSpace.cardGap),
                                Expanded(
                                  child: AniHowField(
                                    label: s.price,
                                    child: TextField(
                                      controller: _price,
                                      keyboardType:
                                          const TextInputType.numberWithOptions(
                                            decimal: true,
                                          ),
                                      decoration: InputDecoration(
                                        prefixText: '₱ ',
                                        suffixText: _unit == null
                                            ? null
                                            : '/ $_unit',
                                        hintText: '0.00',
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                            if (_unit != null) ...[
                              const SizedBox(height: AniHowSpace.fieldGap),
                              _orderRuleFields(s),
                            ],
                            if (floor != null && floor.isNotEmpty) ...[
                              const SizedBox(height: AniHowSpace.cardGap),
                              Row(
                                children: [
                                  Icon(
                                    Icons.info_outline,
                                    size: 16,
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurface
                                        .withValues(alpha: 0.62),
                                  ),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      s.floorPriceFor(
                                        selectedCrop!.labelFor(
                                          context
                                              .watch<PreferencesController>()
                                              .language,
                                        ),
                                        AniHowMoney.peso(floor),
                                      ),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodyMedium
                                          ?.copyWith(
                                            color: Theme.of(context)
                                                .colorScheme
                                                .onSurface
                                                .withValues(alpha: 0.72),
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowFormCard(
                      child: KeyedSubtree(
                        key: _fourthKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (widget.listing == null && !_isUpcoming) ...[
                              _NumberedHeading(
                                number: '3',
                                title: s.harvestSection,
                              ),
                              const SizedBox(height: AniHowSpace.fieldGap),
                              HarvestFields(
                                valueAdded: selectedCrop?.isValueAdded == true,
                                unit: _unit ?? selectedCrop?.unit ?? '',
                                harvestedOnLabel: s.shortDate(_harvestDate),
                                harvested: _harvestedQty,
                                rejected: _rejected,
                                reason: _rejectionReason,
                                note: _rejectionNote,
                                cost: _productionCost,
                                costs: _costs,
                                breakdownOpen: _breakdown,
                                showReasonError: _showReasonError,
                                onChanged: () => setState(_syncCost),
                                onPickDate: () => _pickDate(
                                  current: _harvestDate,
                                  firstDate: DateTime.now().subtract(
                                    const Duration(days: 365),
                                  ),
                                  lastDate: DateTime.now(),
                                  onPicked: (date) =>
                                      setState(() => _harvestDate = date),
                                ),
                                onReason: (value) =>
                                    setState(() => _rejectionReason = value),
                                onToggleBreakdown: () => setState(() {
                                  _breakdown = !_breakdown;
                                  _syncCost();
                                }),
                              ),
                            ],
                            if (widget.listing == null && _isUpcoming) ...[
                              _NumberedHeading(
                                number: '3',
                                title: s.expectedHarvest,
                              ),
                              const SizedBox(height: AniHowSpace.fieldGap),
                              AniHowField(
                                label: s.expectedQuantity,
                                child: TextField(
                                  key: const ValueKey('listing-quantity'),
                                  controller: _quantity,
                                  keyboardType:
                                      const TextInputType.numberWithOptions(
                                        decimal: true,
                                      ),
                                ),
                              ),
                              Padding(
                                padding: const EdgeInsets.only(top: 8),
                                child: Text(s.expectedQuantityHint),
                              ),
                            ],
                            if (widget.listing != null) ...[
                              _NumberedHeading(
                                number: '3',
                                title: s.stockSection,
                              ),
                              const SizedBox(height: AniHowSpace.fieldGap),
                              AniHowField(
                                label: s.quantity,
                                child: TextField(
                                  key: const ValueKey('listing-quantity'),
                                  controller: _quantity,
                                  readOnly: !widget.listing!.needsActualHarvest,
                                  keyboardType:
                                      const TextInputType.numberWithOptions(
                                        decimal: true,
                                      ),
                                ),
                              ),
                              if (widget.listing!.needsActualHarvest)
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: TextButton(
                                    key: const ValueKey(
                                      'record-actual-harvest',
                                    ),
                                    onPressed: () => showAddStockSheet(
                                      context,
                                      widget.listing!,
                                      actual: true,
                                    ),
                                    child: Text(s.recordActualHarvest),
                                  ),
                                ),
                              if (!widget.listing!.needsActualHarvest)
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        key: const ValueKey('add-stock'),
                                        onPressed: () => showAddStockSheet(
                                          context,
                                          widget.listing!,
                                        ),
                                        child: Text(s.addStock),
                                      ),
                                    ),
                                    const SizedBox(width: AniHowSpace.cardGap),
                                    Expanded(
                                      child: OutlinedButton(
                                        key: const ValueKey('remove-stock'),
                                        onPressed: () async {
                                          final changed =
                                              await showRemoveStockSheet(
                                                context,
                                                widget.listing!,
                                              );
                                          if (changed && mounted) {
                                            await _reloadListing();
                                          }
                                        },
                                        child: Text(s.removeStock),
                                      ),
                                    ),
                                  ],
                                ),
                              const SizedBox(height: AniHowSpace.fieldGap),
                              _DateField(
                                label: s.harvestedOnLabel,
                                value: _harvestedOn,
                                clearLabel: s.clearDate,
                                emptyLabel: s.dateNotSet,
                                formatted: _harvestedOn == null
                                    ? null
                                    : s.shortDate(_harvestedOn!),
                                onPick: () => _pickDate(
                                  current: _harvestedOn,
                                  lastDate: DateTime.now(),
                                  onPicked: (date) =>
                                      setState(() => _harvestedOn = date),
                                ),
                                onClear: () =>
                                    setState(() => _harvestedOn = null),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowFormCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _NumberedHeading(
                            number: '4',
                            title: s.whenCanBuyersOrder,
                          ),
                          const SizedBox(height: AniHowSpace.fieldGap),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: _DateField(
                                  label: s.availableFromLabel,
                                  value: _availableFrom,
                                  clearLabel: s.clearDate,
                                  emptyLabel: s.dateNotSet,
                                  formatted: _availableFrom == null
                                      ? null
                                      : s.shortDate(_availableFrom!),
                                  onPick: () => _pickDate(
                                    current: _availableFrom,
                                    onPicked: (date) =>
                                        setState(() => _availableFrom = date),
                                  ),
                                  onClear: () =>
                                      setState(() => _availableFrom = null),
                                ),
                              ),
                              const SizedBox(width: AniHowSpace.cardGap),
                              Expanded(
                                child: _DateField(
                                  label: s.availableUntilLabel,
                                  value: _availableUntil,
                                  clearLabel: s.clearDate,
                                  emptyLabel: s.dateNotSet,
                                  formatted: _availableUntil == null
                                      ? null
                                      : s.shortDate(_availableUntil!),
                                  onPick: () => _pickDate(
                                    current: _availableUntil,
                                    onPicked: (date) =>
                                        setState(() => _availableUntil = date),
                                  ),
                                  onClear: () =>
                                      setState(() => _availableUntil = null),
                                ),
                              ),
                            ],
                          ),
                          if (_listing != null && !_listing!.isTakenDown) ...[
                            const Divider(height: 28),
                            SwitchListTile(
                              key: const Key('listing-active-switch'),
                              contentPadding: EdgeInsets.zero,
                              title: Text(s.showInTheMarketTitle),
                              value: _isActive,
                              onChanged: _busy
                                  ? null
                                  : (value) =>
                                        setState(() => _isActive = value),
                            ),
                            Text(
                              _isActive
                                  ? s.buyersCanOrderOnceSaved
                                  : s.savedAsHidden,
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: Theme.of(context)
                                        .colorScheme
                                        .onSurface
                                        .withValues(alpha: 0.72),
                                  ),
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowFormCard(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _NumberedHeading(
                            number: '5',
                            title: s.description,
                            trailing: s.optionalLabel,
                          ),
                          const SizedBox(height: AniHowSpace.fieldGap),
                          TextField(
                            controller: _description,
                            maxLines: 4,
                            textCapitalization: TextCapitalization.sentences,
                            decoration: InputDecoration(hintText: s.shortNote),
                          ),
                        ],
                      ),
                    ),
                    if (_listing != null && _showDiscount(context)) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      Card(
                        child: Theme(
                          data: Theme.of(context)
                              .copyWith(dividerColor: Colors.transparent),
                          child: ExpansionTile(
                            key: const ValueKey('discount-section'),
                            initiallyExpanded: _discountOpen,
                            onExpansionChanged: (open) =>
                                setState(() => _discountOpen = open),
                            leading: DecoratedBox(
                              decoration: BoxDecoration(
                                color:
                                    Theme.of(context).brightness ==
                                        Brightness.dark
                                    ? const Color(0xFF5A431C)
                                    : const Color(0xFFFBF3DC),
                                shape: BoxShape.circle,
                              ),
                              child: SizedBox(
                                width: 36,
                                height: 36,
                                child: Icon(
                                  Icons.local_offer_outlined,
                                  size: 18,
                                  color:
                                      Theme.of(context).brightness ==
                                          Brightness.dark
                                      ? const Color(0xFFF6D48A)
                                      : const Color(0xFF7A4E0C),
                                ),
                              ),
                            ),
                            tilePadding: const EdgeInsets.symmetric(
                              horizontal: 16,
                            ),
                            childrenPadding: const EdgeInsets.fromLTRB(
                              16,
                              0,
                              16,
                              16,
                            ),
                            title: Text(
                              _discountPaused(context)
                                  ? s.discountPaused
                                  : s.discountOptionalTitle,
                            ),
                            subtitle: Text(s.discountOptionalHelp),
                            children: [
                              if (_discountPaused(context) &&
                                  _discountOpen) ...[
                                Text(
                                  s.discountPaused,
                                  key: const ValueKey('discount-paused'),
                                ),
                                const SizedBox(height: AniHowSpace.cardGap),
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: OutlinedButton(
                                    style: OutlinedButton.styleFrom(
                                      minimumSize: const Size(48, 48),
                                    ),
                                    onPressed: _busy ? null : _endTawad,
                                    child: Text(s.endDiscount),
                                  ),
                                ),
                              ] else if (_discountOpen &&
                                  sellerDiscountSummary(
                                        s,
                                        _listing!.tawad,
                                        unit: _listing!.unit,
                                      ) !=
                                      null) ...[
                                Text(
                                  sellerDiscountSummary(
                                    s,
                                    _listing!.tawad,
                                    unit: _listing!.unit,
                                  )!,
                                  key: const ValueKey('discount-summary'),
                                ),
                                const SizedBox(height: AniHowSpace.cardGap),
                                Row(
                                  children: [
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          minimumSize: const Size(48, 48),
                                        ),
                                        onPressed: _busy ? null : _openTawad,
                                        child: Text(s.editDiscount),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    Expanded(
                                      child: OutlinedButton(
                                        style: OutlinedButton.styleFrom(
                                          minimumSize: const Size(48, 48),
                                        ),
                                        onPressed: _busy ? null : _endTawad,
                                        child: Text(s.endDiscount),
                                      ),
                                    ),
                                  ],
                                ),
                              ] else if (_discountOpen) ...[
                                Align(
                                  alignment: Alignment.centerLeft,
                                  child: TextButton(
                                    style: TextButton.styleFrom(
                                      minimumSize: const Size(48, 48),
                                    ),
                                    onPressed: _busy ? null : _openTawad,
                                    child: Text(s.setTawad),
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                      ),
                    ],
                    if (_listing != null &&
                        !_listing!.isTakenDown &&
                        _walkInAllowed(context)) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      OutlinedButton.icon(
                        onPressed: _busy ? null : _openWalkIn,
                        icon: const Icon(Icons.point_of_sale_outlined),
                        label: Text(s.recordWalkIn),
                      ),
                    ],
                  ],
                ),
              ),
              Material(
                color: Theme.of(context).cardColor,
                elevation: 8,
                shadowColor: Colors.black26,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    border: Border(
                      top: BorderSide(color: Theme.of(context).dividerColor),
                    ),
                  ),
                  child: SafeArea(
                    top: false,
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AniHowSpace.screen,
                        8,
                        AniHowSpace.screen,
                        AniHowSpace.screen,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          if (_formError != null)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Text(
                                _formError!,
                                key: const ValueKey('listing-form-error'),
                                style: TextStyle(
                                  color: Theme.of(context).colorScheme.error,
                                ),
                              ),
                            ),
                          PrimaryButton(
                            label: s.saveListing,
                            busy: _busy,
                            onPressed: _save,
                          ),
                          const SizedBox(height: 6),
                          Text(
                            _saveHint(s),
                            key: const ValueKey('form-save-hint'),
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  fontSize: 12,
                                  color: Theme.of(context).colorScheme.onSurface
                                      .withValues(alpha: 0.72),
                                ),
                          ),
                        ],
                      ),
                    ),
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

class _DateField extends StatelessWidget {
  const _DateField({
    required this.label,
    required this.value,
    required this.clearLabel,
    required this.emptyLabel,
    required this.formatted,
    required this.onPick,
    required this.onClear,
  });

  final String label;
  final DateTime? value;
  final String clearLabel;
  final String emptyLabel;
  final String? formatted;
  final VoidCallback onPick;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) {
    return AniHowField(
      label: label,
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton(
              style: OutlinedButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
                alignment: Alignment.centerLeft,
              ),
              onPressed: onPick,
              child: Row(
                children: [
                  const Icon(Icons.calendar_today_outlined, size: 16),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      formatted ?? emptyLabel,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (value != null)
            IconButton(
              tooltip: clearLabel,
              onPressed: onClear,
              icon: const Icon(Icons.close),
            ),
        ],
      ),
    );
  }
}

class _NumberedHeading extends StatelessWidget {
  const _NumberedHeading({
    required this.number,
    required this.title,
    this.trailing,
  });

  final String number;
  final String title;
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      children: [
        DecoratedBox(
          decoration: BoxDecoration(
            color: accentTint(context),
            shape: BoxShape.circle,
          ),
          child: SizedBox(
            width: 28,
            height: 28,
            child: Center(
              child: Text(
                number,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: readableAccent(context),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            title,
            style: theme.textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        if (trailing != null)
          Text(
            trailing!,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.62),
            ),
          ),
      ],
    );
  }
}

class _GrowingMethodChips extends StatelessWidget {
  const _GrowingMethodChips({
    required this.value,
    required this.certified,
    required this.notStated,
    required this.naturallyGrown,
    required this.certifiedOrganic,
    required this.onChanged,
  });

  final String value;
  final bool certified;
  final String notStated;
  final String naturallyGrown;
  final String certifiedOrganic;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final options = <(String, String)>[
      ('', notStated),
      ('naturally_grown', naturallyGrown),
      if (certified) ('certified_organic', certifiedOrganic),
    ];
    return Wrap(
      key: const ValueKey('listing-growing-method'),
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final option in options)
          ChoiceChip(
            label: Text(option.$2),
            selected: value == option.$1,
            selectedColor: const Color(0xFF1F5A3E),
            backgroundColor: dark
                ? const Color(0xFF2A3330)
                : const Color(0xFFF1EFE8),
            labelStyle: TextStyle(
              color: value == option.$1
                  ? Colors.white
                  : Theme.of(context).colorScheme.onSurface,
              fontWeight: FontWeight.w600,
            ),
            onSelected: (_) => onChanged(option.$1),
          ),
      ],
    );
  }
}

class _FormStepBar extends StatelessWidget {
  const _FormStepBar({required this.steps, required this.onTap});

  final List<(String, bool, GlobalKey)> steps;
  final ValueChanged<GlobalKey> onTap;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 36,
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: [
            for (var index = 0; index < steps.length; index++) ...[
              if (index > 0) const SizedBox(width: 8),
              _stepChip(index),
            ],
          ],
        ),
      ),
    );
  }

  Widget _stepChip(int index) {
    final (label, done, key) = steps[index];
    final background = done
        ? const Color(0xFFE5F4EB)
        : Colors.white.withValues(alpha: 0.16);
    final foreground = done ? const Color(0xFF145C38) : Colors.white;
    return Material(
      color: background,
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        key: ValueKey('form-step-$index'),
        borderRadius: BorderRadius.circular(999),
        onTap: () => onTap(key),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 10),
          child: Row(
            children: [
              if (done)
                Icon(Icons.check, size: 14, color: foreground)
              else
                Text(
                  '${index + 1}',
                  style: TextStyle(
                    color: foreground,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              const SizedBox(width: 4),
              Text(
                label,
                style: TextStyle(
                  color: foreground,
                  fontWeight: FontWeight.w700,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
