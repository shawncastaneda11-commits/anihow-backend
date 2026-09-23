import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/walk_in_quote.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/primary_button.dart';

class WalkInSaleScreen extends StatefulWidget {
  const WalkInSaleScreen({super.key, this.listingId});

  final int? listingId;

  @override
  State<WalkInSaleScreen> createState() => _WalkInSaleScreenState();
}

class _WalkInSaleScreenState extends State<WalkInSaleScreen> {
  final _quantity = TextEditingController();
  final _amountReceived = TextEditingController();
  final _buyerName = TextEditingController();
  final _note = TextEditingController();
  List<ListingItem> _listings = const [];
  int? _listingId;
  bool _loading = true;
  bool _busy = false;
  Object? _error;

  static final _decimal = FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'));

  @override
  void initState() {
    super.initState();
    _quantity.addListener(_onQuantityChanged);
    _loadListings();
  }

  void _onQuantityChanged() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  void dispose() {
    _quantity.removeListener(_onQuantityChanged);
    _quantity.dispose();
    _amountReceived.dispose();
    _buyerName.dispose();
    _note.dispose();
    super.dispose();
  }

  ListingItem? get _selected {
    for (final listing in _listings) {
      if (listing.id == _listingId) {
        return listing;
      }
    }
    return null;
  }

  WalkInQuote? get _quote {
    final listing = _selected;
    if (listing == null) {
      return null;
    }
    return WalkInQuote.forListing(listing, _quantity.text);
  }

  Future<void> _loadListings() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.farmerListings();
      if (!mounted) {
        return;
      }
      final sellable = items.where((listing) => !listing.isTakenDown).toList();
      final requested = widget.listingId;
      final selected = sellable.any((listing) => listing.id == requested)
          ? requested
          : (sellable.isEmpty ? null : sellable.first.id);
      setState(() {
        _listings = sellable;
        _listingId = selected;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  Future<void> _save() async {
    final listingId = _listingId;
    if (listingId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a listing.')),
      );
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final api = context.read<AuthController>().api;
    setState(() => _busy = true);
    try {
      final order = await api.recordWalkInSale(
            listingId: listingId,
            quantity: _quantity.text.trim(),
            amountReceived: _amountReceived.text.trim(),
            buyerName: _buyerName.text.trim().isEmpty ? null : _buyerName.text.trim(),
            note: _note.text.trim().isEmpty ? null : _note.text.trim(),
          );
      if (!mounted) {
        return;
      }
      await showDialog<void>(
        context: context,
        builder: (dialogContext) {
          return AlertDialog(
            title: const Text('Walk-in sale recorded'),
            content: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                PriceBreakdown(
                  listed: order.listedTotal,
                  tawad: order.tawadDisplay,
                  total: order.total,
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                Text('Amount received ${AniHowMoney.peso(order.amountReceived)}'),
              ],
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Done'),
              ),
            ],
          );
        },
      );
      if (!mounted) {
        return;
      }
      navigator.pop(true);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Record walk-in sale')),
      body: _buildBody(),
    );
  }

  Widget _buildBody() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    if (_listings.isEmpty) {
      return const Center(child: Text('No listings available for a walk-in sale.'));
    }
    final selected = _selected;
    final unit = selected?.unitLabel ?? selected?.unit;
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              AniHowField(
                label: 'Listing',
                child: DropdownButtonFormField<int>(
                  initialValue: _listingId,
                  items: _listings
                      .map(
                        (listing) => DropdownMenuItem(
                          value: listing.id,
                          child: Text(listing.title),
                        ),
                      )
                      .toList(),
                  onChanged: (value) => setState(() => _listingId = value),
                ),
              ),
              if (selected != null) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                if (selected.category != null)
                  Text(selected.category!.labelFor(context.watch<PreferencesController>().language)),
                Text('Price per unit ${AniHowMoney.peso(selected.pricePerUnit)}'),
                Text(
                  unit == null || unit.isEmpty
                      ? 'Available ${selected.quantityAvailable}'
                      : 'Available ${selected.quantityAvailable} $unit',
                ),
              ],
              const SizedBox(height: AniHowSpace.fieldGap),
              AniHowField(
                label: 'Quantity',
                child: TextField(
                  controller: _quantity,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [_decimal],
                  decoration: InputDecoration(
                    hintText: '0',
                    suffixText: unit,
                  ),
                ),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              AniHowField(
                label: 'Amount received',
                child: TextField(
                  controller: _amountReceived,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                  inputFormatters: [_decimal],
                  decoration: const InputDecoration(
                    prefixText: '₱ ',
                    hintText: '0.00',
                  ),
                ),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              AniHowField(
                label: 'Guest name (optional)',
                child: TextField(
                  controller: _buyerName,
                  maxLength: 100,
                  textCapitalization: TextCapitalization.words,
                  decoration: const InputDecoration(hintText: 'For your reference only'),
                ),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              AniHowField(
                label: 'Note (optional)',
                child: TextField(
                  controller: _note,
                  maxLength: 1000,
                  maxLines: 3,
                  textCapitalization: TextCapitalization.sentences,
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
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                if (_quote != null) ...[
                  PriceBreakdown(
                    listed: _quote!.listed,
                    tawad: _quote!.tawad,
                    total: _quote!.total,
                  ),
                  const SizedBox(height: AniHowSpace.cardGap),
                ],
                PrimaryButton(label: 'Record sale', busy: _busy, onPressed: _save),
              ],
            ),
          ),
        ),
      ],
    );
  }
}
