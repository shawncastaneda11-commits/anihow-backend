import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/walk_in_quote.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/sale_parts.dart';

/// Records a cash sale made at the stall.
///
/// The seller picks the listing, sets the quantity with − and +, and sees
/// the listed amount, the tawad and the total as they go. The cash received
/// gives the change to hand back. Record stays off until the cash covers the
/// total. After saving, a receipt replaces the form.
class WalkInSaleScreen extends StatefulWidget {
  const WalkInSaleScreen({super.key, this.listingId});

  final int? listingId;

  @override
  State<WalkInSaleScreen> createState() => _WalkInSaleScreenState();
}

class _WalkInSaleScreenState extends State<WalkInSaleScreen> {
  final _quantity = TextEditingController();
  final _cash = TextEditingController();
  final _buyerName = TextEditingController();
  final _note = TextEditingController();
  List<ListingItem> _listings = const [];
  int? _listingId;
  bool _loading = true;
  bool _busy = false;
  bool _showDetails = false;
  Object? _error;
  _Recorded? _recorded;

  static final _decimal = FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'));
  static const _tolerance = 0.000001;

  @override
  void initState() {
    super.initState();
    _loadListings();
  }

  @override
  void dispose() {
    _quantity.dispose();
    _cash.dispose();
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

  static double _available(ListingItem listing) =>
      double.tryParse(listing.sellableQuantity ?? '') ??
      double.tryParse(listing.quantityAvailable) ??
      0;

  static double _step(ListingItem listing) =>
      listing.orderStep > 0 ? listing.orderStep : 1;

  static String _number(double value) {
    if (value == value.roundToDouble()) {
      return value.toStringAsFixed(0);
    }
    var text = value.toStringAsFixed(3);
    while (text.endsWith('0')) {
      text = text.substring(0, text.length - 1);
    }
    return text;
  }

  void _useListing(int? id) {
    final listing = _listings.where((item) => item.id == id).firstOrNull;
    setState(() {
      _listingId = id;
      if (listing != null) {
        final start = listing.minOrderQuantity > 0
            ? listing.minOrderQuantity
            : 1.0;
        _quantity.text = _number(start);
      }
    });
  }

  void _stepQuantity(int direction) {
    final listing = _selected;
    if (listing == null) {
      return;
    }
    final step = _step(listing);
    final current = double.tryParse(_quantity.text.trim()) ?? 0;
    final available = _available(listing);
    final upper = available > step ? available : step;
    final next = (current + direction * step).clamp(step, upper);
    setState(() => _quantity.text = _number(next.toDouble()));
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
      final requested = _listingId ?? widget.listingId;
      final selected = sellable.any((listing) => listing.id == requested)
          ? requested
          : (sellable.isEmpty ? null : sellable.first.id);
      setState(() {
        _listings = sellable;
        _loading = false;
        _error = null;
      });
      _useListing(selected);
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

  Future<void> _pickListing() async {
    final s = AppStrings.read(context);
    final picked = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (sheetContext) => SafeArea(
        child: ConstrainedBox(
          constraints: BoxConstraints(
            maxHeight: MediaQuery.of(sheetContext).size.height * 0.7,
          ),
          child: ListView(
            shrinkWrap: true,
            children: [
              for (final listing in _listings)
                ListTile(
                  leading: ListingThumb(listing: listing, size: 40),
                  title: Text(listing.title),
                  subtitle: Text(
                    '${AniHowMoney.pesoUnit(listing.pricePerUnit)} / ${s.unitFor(listing.unit, 1)}',
                  ),
                  selected: listing.id == _listingId,
                  onTap: () => Navigator.pop(sheetContext, listing.id),
                ),
            ],
          ),
        ),
      ),
    );
    if (picked != null && mounted) {
      _useListing(picked);
    }
  }

  Future<void> _save() async {
    final listing = _selected;
    if (listing == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).chooseListing)),
      );
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final api = context.read<AuthController>().api;
    final quantity = double.tryParse(_quantity.text.trim()) ?? 0;
    setState(() => _busy = true);
    try {
      final order = await api.recordWalkInSale(
        listingId: listing.id,
        quantity: _quantity.text.trim(),
        amountReceived: _cash.text.trim(),
        buyerName: _buyerName.text.trim().isEmpty
            ? null
            : _buyerName.text.trim(),
        note: _note.text.trim().isEmpty ? null : _note.text.trim(),
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _recorded = _Recorded(
          order: order,
          title: listing.title,
          unit: listing.unit,
          quantity: quantity,
          unitPrice: double.tryParse(listing.pricePerUnit) ?? 0,
          left: _available(listing) - quantity,
        );
      });
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

  void _recordAnother() {
    setState(() {
      _recorded = null;
      _cash.clear();
      _buyerName.clear();
      _note.clear();
      _showDetails = false;
    });
    _loadListings();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final recorded = _recorded;
    return PopScope(
      canPop: recorded == null,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          Navigator.of(context).pop(true);
        }
      },
      child: Scaffold(
        appBar: recorded == null
            ? AppBar(title: Text(s.walkInSale))
            : AppBar(automaticallyImplyLeading: false),
        body: recorded == null ? _buildForm(s) : _buildDone(s, recorded),
      ),
    );
  }

  Widget _buildForm(AppStrings s) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    final listing = _selected;
    if (_listings.isEmpty || listing == null) {
      return Center(child: Text(s.noWalkInListings));
    }

    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final quote = _quote;
    final quantity = double.tryParse(_quantity.text.trim()) ?? 0;
    final available = _available(listing);
    final validQuantity =
        quantity > 0 && quantity <= available + _tolerance && quote != null;
    final total = quote?.total ?? 0;
    final cash = double.tryParse(_cash.text.trim());
    final paid = validQuantity && cash != null && cash >= total - _tolerance;
    final short = validQuantity && cash != null && cash > 0 && !paid;
    final rule = listing.tawad;
    final ruleMinimum = double.tryParse(rule?.minQuantity ?? '');
    final unitOne = s.unitFor(listing.unit, 1);
    final tawadTag = rule == null || !rule.isActive
        ? null
        : rule.isMinQuantity && ruleMinimum != null
        ? s.tawadTagBulk(
            AniHowMoney.peso(rule.discountAmount),
            _number(ruleMinimum),
            s.unitFor(listing.unit, ruleMinimum),
          )
        : s.tawadTagEvery(AniHowMoney.peso(rule.discountAmount));
    final missing = rule != null &&
            rule.isActive &&
            rule.isMinQuantity &&
            ruleMinimum != null &&
            quantity > 0 &&
            quantity < ruleMinimum
        ? ruleMinimum - quantity
        : null;

    final String recordLabel;
    if (paid) {
      recordLabel = s.recordSaleAmount(AniHowMoney.peso(total));
    } else if (validQuantity) {
      recordLabel = s.enterCashToRecord;
    } else {
      recordLabel = s.checkQuantity;
    }

    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  key: const ValueKey('walk-in-listing'),
                  onTap: _listings.length > 1 ? _pickListing : null,
                  child: Padding(
                    padding: AniHowSpace.cardPadding,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            ListingThumb(listing: listing),
                            const SizedBox(width: 12),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    listing.title,
                                    style: theme.textTheme.titleMedium,
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    '${AniHowMoney.pesoUnit(listing.pricePerUnit)} / $unitOne · '
                                    '${s.quantityLeft(_number(available), s.unitFor(listing.unit, available))}',
                                    style: TextStyle(color: muted),
                                  ),
                                ],
                              ),
                            ),
                            if (_listings.length > 1)
                              Text(
                                s.changeListing,
                                style: TextStyle(
                                  color: readableAccent(context),
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                          ],
                        ),
                        if (tawadTag != null) ...[
                          const SizedBox(height: 10),
                          _TawadTag(text: tawadTag),
                        ],
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              AniHowFormCard(
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(s.quantity, style: theme.textTheme.titleSmall),
                          Text(
                            s.unitFor(listing.unit, 2),
                            style: TextStyle(color: muted, fontSize: 13),
                          ),
                        ],
                      ),
                    ),
                    IconButton.outlined(
                      key: const ValueKey('walk-in-less'),
                      tooltip: s.oneLess,
                      onPressed: () => _stepQuantity(-1),
                      icon: const Icon(Icons.remove),
                    ),
                    const SizedBox(width: 6),
                    SizedBox(
                      width: 72,
                      child: TextField(
                        key: const ValueKey('walk-in-quantity'),
                        controller: _quantity,
                        onChanged: (_) => setState(() {}),
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w700,
                        ),
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [_decimal],
                        decoration: const InputDecoration(
                          isDense: true,
                          hintText: '0',
                        ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    IconButton.filled(
                      key: const ValueKey('walk-in-more'),
                      tooltip: s.oneMore,
                      onPressed: () => _stepQuantity(1),
                      icon: const Icon(Icons.add),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              AniHowFormCard(
                child: Semantics(
                  liveRegion: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      SaleLine(
                        label:
                            '${_number(quantity)} × ${AniHowMoney.pesoUnit(listing.pricePerUnit)}',
                        value: AniHowMoney.peso(quote?.listed ?? 0),
                      ),
                      if ((quote?.tawad ?? 0) > 0)
                        SaleLine(
                          label: s.tawad,
                          value: '−${AniHowMoney.peso(quote!.tawad)}',
                          accent: true,
                        ),
                      if (missing != null) ...[
                        const SizedBox(height: 6),
                        SaleNote(
                          text: s.addMoreForTawad(
                            _number(missing),
                            s.unitFor(listing.unit, missing),
                            AniHowMoney.peso(rule!.discountAmount),
                          ),
                          tone: SaleNoteTone.nudge,
                        ),
                      ],
                      const Divider(height: 20),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.baseline,
                        textBaseline: TextBaseline.alphabetic,
                        children: [
                          Expanded(
                            child: Text(
                              s.total,
                              style: theme.textTheme.titleMedium,
                            ),
                          ),
                          Text(
                            AniHowMoney.peso(total),
                            key: const ValueKey('walk-in-total'),
                            style: const TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              AniHowFormCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    AniHowField(
                      label: s.cashReceived,
                      child: TextField(
                        key: const ValueKey('walk-in-cash'),
                        controller: _cash,
                        onChanged: (_) => setState(() {}),
                        style: const TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.w500,
                        ),
                        keyboardType: const TextInputType.numberWithOptions(
                          decimal: true,
                        ),
                        inputFormatters: [_decimal],
                        decoration: const InputDecoration(
                          prefixText: '₱ ',
                          hintText: '0.00',
                        ),
                      ),
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        for (final (index, option) in [
                          (s.exactAmount, total),
                          ('₱50', 50.0),
                          ('₱100', 100.0),
                          ('₱500', 500.0),
                        ].indexed) ...[
                          if (index > 0) const SizedBox(width: 6),
                          Expanded(
                            child: OutlinedButton(
                              key: ValueKey('walk-in-cash-$index'),
                              style: OutlinedButton.styleFrom(
                                padding: EdgeInsets.zero,
                                minimumSize: const Size(0, 44),
                              ),
                              onPressed: () => setState(
                                () => _cash.text = option.$2.toStringAsFixed(2),
                              ),
                              child: Text(option.$1),
                            ),
                          ),
                        ],
                      ],
                    ),
                    if (validQuantity && cash != null) ...[
                      const SizedBox(height: 10),
                      SaleNote(
                        key: const ValueKey('walk-in-change'),
                        text: short ? s.shortBy : s.changeToGive,
                        trailing: AniHowMoney.peso(
                          short ? total - cash : cash - total,
                        ),
                        tone: short ? SaleNoteTone.problem : SaleNoteTone.good,
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 4),
              TextButton(
                onPressed: () => setState(() => _showDetails = !_showDetails),
                style: TextButton.styleFrom(
                  minimumSize: const Size.fromHeight(44),
                  alignment: Alignment.centerLeft,
                ),
                child: Row(
                  children: [
                    Expanded(child: Text(s.buyerNameOrNote)),
                    Icon(_showDetails ? Icons.expand_less : Icons.expand_more),
                  ],
                ),
              ),
              if (_showDetails)
                AniHowFormCard(
                  child: Column(
                    children: [
                      AniHowField(
                        label: s.buyerNameLabel,
                        child: TextField(
                          controller: _buyerName,
                          maxLength: 100,
                          textCapitalization: TextCapitalization.words,
                          decoration: InputDecoration(hintText: s.guestNameHint),
                        ),
                      ),
                      const SizedBox(height: AniHowSpace.fieldGap),
                      AniHowField(
                        label: s.noteLabel,
                        child: TextField(
                          controller: _note,
                          maxLength: 1000,
                          maxLines: 2,
                          textCapitalization: TextCapitalization.sentences,
                        ),
                      ),
                    ],
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
                Text(
                  s.walkInCountCash,
                  textAlign: TextAlign.center,
                  style: TextStyle(color: muted, fontSize: 12),
                ),
                const SizedBox(height: 8),
                PrimaryButton(
                  key: const ValueKey('record-walk-in'),
                  label: recordLabel,
                  busy: _busy,
                  onPressed: paid ? _save : null,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildDone(AppStrings s, _Recorded recorded) {
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final order = recorded.order;
    final total = double.tryParse(order.total) ?? 0;
    final received = double.tryParse(order.amountReceived ?? '') ?? 0;
    final change = received - total;
    final tawad = double.tryParse(order.tawadDisplay) ?? 0;
    return Column(
      children: [
        Expanded(
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 24, 16, 16),
            children: [
              Center(
                child: Container(
                  width: 72,
                  height: 72,
                  decoration: BoxDecoration(
                    color: accentTint(context),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.check_rounded,
                    size: 38,
                    color: readableAccent(context),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                s.saleRecorded,
                textAlign: TextAlign.center,
                style: theme.textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                change > _tolerance
                    ? s.giveChange(AniHowMoney.peso(change))
                    : s.noChangeToGive,
                key: const ValueKey('walk-in-done-change'),
                textAlign: TextAlign.center,
                style: TextStyle(color: muted, fontSize: 15),
              ),
              const SizedBox(height: 20),
              AniHowFormCard(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            recorded.title,
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        Text(
                          '${_number(recorded.quantity)} ${s.unitFor(recorded.unit, recorded.quantity)}',
                          style: TextStyle(color: muted),
                        ),
                      ],
                    ),
                    const Divider(height: 20),
                    SaleLine(
                      label:
                          '${_number(recorded.quantity)} × ${AniHowMoney.pesoUnit(recorded.unitPrice)}',
                      value: AniHowMoney.peso(order.listedTotal),
                    ),
                    if (tawad > 0)
                      SaleLine(
                        label: s.tawad,
                        value: '−${AniHowMoney.peso(tawad)}',
                        accent: true,
                      ),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.baseline,
                      textBaseline: TextBaseline.alphabetic,
                      children: [
                        Expanded(
                          child: Text(
                            s.total,
                            style: theme.textTheme.titleMedium,
                          ),
                        ),
                        Text(
                          AniHowMoney.peso(total),
                          style: const TextStyle(
                            fontSize: 24,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    const Divider(height: 20),
                    SaleLine(
                      label: s.cashReceived,
                      value: AniHowMoney.peso(received),
                    ),
                    SaleLine(
                      label: s.changeRow,
                      value: AniHowMoney.peso(change > 0 ? change : 0),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
              Text(
                s.leftOnListing(
                  _number(recorded.left > 0 ? recorded.left : 0),
                  s.unitFor(recorded.unit, recorded.left),
                ),
                textAlign: TextAlign.center,
                style: TextStyle(color: muted, fontSize: 13),
              ),
            ],
          ),
        ),
        SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                PrimaryButton(
                  key: const ValueKey('walk-in-record-another'),
                  label: s.recordAnotherSale,
                  onPressed: _recordAnother,
                ),
                const SizedBox(height: 8),
                OutlinedButton(
                  key: const ValueKey('walk-in-done'),
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: () => Navigator.of(context).pop(true),
                  child: Text(s.done),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _Recorded {
  const _Recorded({
    required this.order,
    required this.title,
    required this.unit,
    required this.quantity,
    required this.unitPrice,
    required this.left,
  });

  final OrderRecord order;
  final String title;
  final String? unit;
  final double quantity;
  final double unitPrice;
  final double left;
}

class _TawadTag extends StatelessWidget {
  const _TawadTag({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final ink = dark ? const Color(0xFFF2D29B) : const Color(0xFF7A4A00);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: dark ? const Color(0xFF473A1E) : const Color(0xFFFFF4E0),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.sell_outlined, size: 14, color: ink),
          const SizedBox(width: 6),
          Flexible(
            child: Text(
              text,
              style: TextStyle(
                color: ink,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
