import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';
import 'harvest_form.dart';
import 'walk_in_sale_screen.dart';

Future<bool> showAddStockSheet(
  BuildContext context,
  ListingItem listing, {
  bool actual = false,
}) {
  return showModalBottomSheet<bool>(
    context: context,
    isScrollControlled: true,
    builder: (context) => _HarvestSheet(listing: listing, actual: actual),
  ).then((saved) => saved == true);
}

Future<bool> showRemoveStockSheet(
  BuildContext context,
  ListingItem listing, {
  String? prefilledReason,
}) async {
  final outcome = await showModalBottomSheet<String>(
    context: context,
    isScrollControlled: true,
    builder: (context) =>
        _RemoveSheet(listing: listing, prefilledReason: prefilledReason),
  );
  if (!context.mounted) {
    return false;
  }
  if (outcome == 'walk_in') {
    final recorded = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => WalkInSaleScreen(listingId: listing.id),
      ),
    );
    return recorded == true;
  }
  return outcome == 'saved';
}

class _HarvestSheet extends StatefulWidget {
  const _HarvestSheet({required this.listing, required this.actual});

  final ListingItem listing;
  final bool actual;

  @override
  State<_HarvestSheet> createState() => _HarvestSheetState();
}

class _HarvestSheetState extends State<_HarvestSheet> {
  final _harvested = TextEditingController();
  final _rejected = TextEditingController(text: '0');
  final _note = TextEditingController();
  final _cost = TextEditingController();
  final _costs = {
    for (final category in costCategories) category: TextEditingController(),
  };
  DateTime? _harvestedOn = DateTime.now();
  String? _reason;
  bool _breakdown = false;
  bool _showReasonError = false;
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _harvested.dispose();
    _rejected.dispose();
    _note.dispose();
    _cost.dispose();
    for (final controller in _costs.values) {
      controller.dispose();
    }
    super.dispose();
  }

  bool get _valueAdded => widget.listing.category?.isValueAdded == true;

  Map<String, dynamic> _body() {
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
      'harvested_on': _day(_harvestedOn ?? DateTime.now()),
      'quantity_harvested': _harvested.text.trim(),
      'quantity_rejected': _rejected.text.trim().isEmpty
          ? '0'
          : _rejected.text.trim(),
      if (rejected > 0 && _reason != null) 'rejection_reason': _reason,
      if (rejected > 0 && _reason == 'other')
        'rejection_note': _note.text.trim(),
      if (!_breakdown && _cost.text.trim().isNotEmpty)
        'production_cost': _cost.text.trim(),
      if (_breakdown && breakdown.isNotEmpty) 'cost_breakdown': breakdown,
    };
  }

  Future<void> _save() async {
    final s = AppStrings.read(context);
    final rejected = double.tryParse(_rejected.text.trim()) ?? 0;
    if (rejected > 0 && (_reason == null || _reason!.isEmpty)) {
      setState(() => _showReasonError = true);
      return;
    }
    final good = goodQuantity(_harvested.text, _rejected.text);
    final reserved = widget.listing.reservedQuantity ?? 0;
    var confirm = false;
    if (widget.actual && good + 0.001 < reserved) {
      final accepted = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          key: const Key('actual-harvest-confirm'),
          title: Text(s.confirmShortfallTitle),
          content: Text(
            s.confirmShortfallBody(
              formatGoodQuantity(reserved),
              widget.listing.unit ?? '',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: Text(s.cancel),
            ),
            TextButton(
              key: const Key('confirm-actual-harvest'),
              onPressed: () => Navigator.of(context).pop(true),
              child: Text(s.confirmHarvest),
            ),
          ],
        ),
      );
      if (accepted != true || !mounted) {
        return;
      }
      confirm = true;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final api = context.read<AuthController>().api;
      if (widget.actual) {
        await api.recordActualHarvest(
          widget.listing.id,
          _body(),
          confirmCancelReservations: confirm,
        );
      } else {
        await api.addStock(widget.listing.id, _body());
      }
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              widget.actual ? s.recordActualHarvest : s.addStock,
              style: Theme.of(context).textTheme.titleLarge,
            ),
            if (widget.actual &&
                (widget.listing.reservedQuantity ?? 0) > 0) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              Text(
                s.confirmShortfallBody(
                  formatGoodQuantity(widget.listing.reservedQuantity ?? 0),
                  widget.listing.unit ?? '',
                ),
                key: const ValueKey('reserved-total'),
              ),
            ],
            const SizedBox(height: AniHowSpace.fieldGap),
            HarvestFields(
              valueAdded: _valueAdded,
              unit: widget.listing.unit ?? '',
              harvestedOnLabel: _harvestedOn == null
                  ? ''
                  : s.shortDate(_harvestedOn!),
              harvested: _harvested,
              rejected: _rejected,
              reason: _reason,
              note: _note,
              cost: _cost,
              costs: _costs,
              breakdownOpen: _breakdown,
              showReasonError: _showReasonError,
              onChanged: () => setState(_syncCost),
              onPickDate: _pickDate,
              onReason: (value) => setState(() => _reason = value),
              onToggleBreakdown: () => setState(() {
                _breakdown = !_breakdown;
                _syncCost();
              }),
            ),
            if (_error != null) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              Text(
                _error!,
                key: const ValueKey('stock-sheet-error'),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: AniHowSpace.fieldGap),
            PrimaryButton(
              label: widget.actual ? s.recordActualHarvest : s.addStock,
              busy: _busy,
              onPressed: _busy ? null : _save,
            ),
          ],
        ),
      ),
    );
  }

  void _syncCost() {
    if (!_breakdown) {
      return;
    }
    if (!breakdownHasAmount(_costs)) {
      _cost.text = '';
      return;
    }
    _cost.text = breakdownTotal(_costs).toStringAsFixed(2);
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _harvestedOn ?? DateTime.now(),
      firstDate: DateTime(2020),
      lastDate: DateTime.now(),
    );
    if (picked != null) {
      setState(() => _harvestedOn = picked);
    }
  }
}

class _RemoveSheet extends StatefulWidget {
  const _RemoveSheet({required this.listing, this.prefilledReason});

  final ListingItem listing;
  final String? prefilledReason;

  @override
  State<_RemoveSheet> createState() => _RemoveSheetState();
}

class _RemoveSheetState extends State<_RemoveSheet> {
  final _quantity = TextEditingController();
  final _note = TextEditingController();
  late String _reason = widget.prefilledReason ?? 'spoiled';
  bool _busy = false;
  String? _error;

  @override
  void dispose() {
    _quantity.dispose();
    _note.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final s = AppStrings.read(context);
    if (_reason == 'correction' && _note.text.trim().isEmpty) {
      setState(() => _error = s.rejectionNote);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthController>().api.removeStock(
        widget.listing.id,
        quantity: _quantity.text.trim(),
        reason: _reason,
        note: _note.text,
      );
      if (mounted) {
        Navigator.of(context).pop('saved');
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final unit = widget.listing.unit ?? '';
    final sellable =
        widget.listing.sellableQuantity ?? widget.listing.quantityAvailable;
    final held = formatGoodQuantity(widget.listing.heldQuantity ?? 0);
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(s.removeStock, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: AniHowSpace.cardGap),
          Text(
            s.removeStockLimit(sellable, held, unit),
            key: const ValueKey('remove-stock-limit'),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          TextField(
            key: const ValueKey('remove-quantity'),
            controller: _quantity,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: s.quantity),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          Wrap(
            spacing: 8,
            children: [
              for (final reason in removalReasons)
                ChoiceChip(
                  key: ValueKey('removal-$reason'),
                  label: Text(s.rejectionReasonLabel(reason)),
                  selected: _reason == reason,
                  onSelected: (_) => setState(() => _reason = reason),
                ),
            ],
          ),
          if (_reason == 'sold_outside') ...[
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowHintCard(
              key: const ValueKey('walk-in-nudge'),
              icon: Icons.point_of_sale_outlined,
              title: '',
              body: s.soldOutsideHint,
              footer: OutlinedButton(
                key: const ValueKey('record-walk-in-from-stock'),
                onPressed: () => Navigator.of(context).pop('walk_in'),
                child: Text(s.recordWalkInSale),
              ),
            ),
          ],
          if (_reason == 'correction') ...[
            const SizedBox(height: AniHowSpace.fieldGap),
            TextField(
              key: const ValueKey('removal-note'),
              controller: _note,
              decoration: InputDecoration(labelText: s.rejectionNote),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              _error!,
              key: const ValueKey('stock-sheet-error'),
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: AniHowSpace.fieldGap),
          PrimaryButton(
            label: s.removeStock,
            busy: _busy,
            onPressed: _busy ? null : _save,
          ),
        ],
      ),
    );
  }
}

String _day(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}
