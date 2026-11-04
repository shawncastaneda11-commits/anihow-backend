import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/tawad_requests.dart';
import '../../state/auth_controller.dart';
import '../../support/tawad_preview.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/sale_parts.dart';

/// Sets or replaces a listing's tawad.
///
/// One card shows the listing and its limits, already in the listing's own
/// unit. The seller picks who gets the tawad, types the peso off, and the
/// "What the buyer pays" box says at once whether it fits. Save stays off
/// until it does. The server still checks on save.
class TawadFormScreen extends StatefulWidget {
  const TawadFormScreen({super.key, required this.listing});

  final ListingItem listing;

  @override
  State<TawadFormScreen> createState() => _TawadFormScreenState();
}

class _TawadFormScreenState extends State<TawadFormScreen> {
  late String _type;
  final _amount = TextEditingController();
  final _minQuantity = TextEditingController();
  bool _busy = false;
  String? _error;

  static final _decimal = FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'));

  bool get _bulk => _type == TawadRequests.minQuantity;

  @override
  void initState() {
    super.initState();
    final existing = widget.listing.tawad;
    _type = existing?.type == TawadRequests.minQuantity
        ? TawadRequests.minQuantity
        : TawadRequests.flat;
    if (existing != null) {
      _amount.text = existing.discountAmount;
      _minQuantity.text = existing.minQuantity ?? '';
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _minQuantity.dispose();
    super.dispose();
  }

  TawadPreview get _preview => TawadPreview.of(
    widget.listing,
    bulk: _bulk,
    amountText: _amount.text,
    minQuantityText: _minQuantity.text,
  );

  Future<void> _save() async {
    if (!_preview.isOk) {
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthController>().api.saveTawad(
        listingId: widget.listing.id,
        type: _type,
        discountAmount: _amount.text.trim(),
        minQuantity: _bulk ? _minQuantity.text.trim() : null,
      );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
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
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final preview = _preview;
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.listing.tawad == null ? s.setTawad : s.replaceTawad),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                _ListingLimits(listing: widget.listing),
                const SizedBox(height: AniHowSpace.section),
                Text(s.whoGetsTawad, style: theme.textTheme.titleSmall),
                const SizedBox(height: 8),
                SegmentedButton<String>(
                  key: const ValueKey('tawad-type'),
                  showSelectedIcon: false,
                  segments: [
                    ButtonSegment(
                      value: TawadRequests.flat,
                      label: Text(s.tawadEveryOrder),
                    ),
                    ButtonSegment(
                      value: TawadRequests.minQuantity,
                      label: Text(s.tawadBulkOrders),
                    ),
                  ],
                  selected: {_type},
                  onSelectionChanged: (value) =>
                      setState(() => _type = value.first),
                ),
                const SizedBox(height: 8),
                Text(
                  _bulk ? s.tawadBulkHelp : s.tawadEveryHelp,
                  style: TextStyle(color: muted, height: 1.4),
                ),
                const SizedBox(height: AniHowSpace.section),
                AniHowFormCard(
                  child: Column(
                    children: [
                      AniHowField(
                        label: s.pesoOffShort,
                        child: TextField(
                          key: const ValueKey('tawad-amount'),
                          controller: _amount,
                          onChanged: (_) => setState(() {}),
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
                      if (_bulk) ...[
                        const SizedBox(height: AniHowSpace.fieldGap),
                        AniHowField(
                          label: s.whenTheyBuyAtLeast,
                          child: TextField(
                            key: const ValueKey('tawad-min-quantity'),
                            controller: _minQuantity,
                            onChanged: (_) => setState(() {}),
                            keyboardType:
                                const TextInputType.numberWithOptions(
                                  decimal: true,
                                ),
                            inputFormatters: [_decimal],
                            decoration: InputDecoration(
                              hintText: 'e.g. 5',
                              suffixText: s.unitWord(widget.listing.unit, 2),
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                _BuyerPays(listing: widget.listing, preview: preview, bulk: _bulk),
                if (_error != null) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  SaleNote(text: _error!, tone: SaleNoteTone.problem),
                ],
                const SizedBox(height: AniHowSpace.cardGap),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  child: Text(
                    s.tawadSaveFootnote,
                    style: TextStyle(color: muted, fontSize: 13, height: 1.4),
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
                key: const ValueKey('save-tawad'),
                label: s.saveTawad,
                onPressed: preview.isOk ? _save : null,
                busy: _busy,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

/// The listing, its price, and its two limits side by side.
class _ListingLimits extends StatelessWidget {
  const _ListingLimits({required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final guard = listing.priceGuard;
    final crop = listing.category;
    final unit = guard?.unit ?? listing.unit ?? crop?.unit;
    final perUnit = s.unitWord(unit, 1);

    // Values the server sent in this listing's unit; an older server sends
    // only the crop's own values.
    final floor = guard != null
        ? guard.floorPerUnit
        : double.tryParse(crop?.sellerFloorPrice ?? '');
    final max = guard != null
        ? guard.maxTawadPerUnit
        : double.tryParse(crop?.sellerMaxDiscount ?? '');
    final showLimits = guard != null || floor != null || max != null;

    return AniHowFormCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              ListingThumb(listing: listing),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(listing.title, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 2),
                    Text(
                      '${AniHowMoney.pesoUnit(listing.pricePerUnit)} / ${s.unitWord(listing.unit, 1)}',
                      style: TextStyle(color: muted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (showLimits) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Row(
              children: [
                Expanded(
                  child: _LimitBox(
                    label: s.lowestPrice,
                    value: floor == null ? '—' : AniHowMoney.pesoUnit(floor),
                    unit: floor == null ? null : perUnit,
                    valueKey: const ValueKey('tawad-floor-per-unit'),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: _LimitBox(
                    label: s.maxTawadShort,
                    value: max == null ? s.noLimit : AniHowMoney.pesoUnit(max),
                    unit: max == null ? null : perUnit,
                    valueKey: const ValueKey('tawad-max-per-unit'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _LimitBox extends StatelessWidget {
  const _LimitBox({
    required this.label,
    required this.value,
    required this.unit,
    required this.valueKey,
  });

  final String label;
  final String value;
  final String? unit;
  final Key valueKey;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              color: muted,
              fontSize: 12,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 2),
          Text.rich(
            key: valueKey,
            TextSpan(
              text: value,
              style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700),
              children: [
                if (unit != null)
                  TextSpan(
                    text: ' / $unit',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w400,
                      color: muted,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The example order with the tawad applied, and whether it fits.
class _BuyerPays extends StatelessWidget {
  const _BuyerPays({
    required this.listing,
    required this.preview,
    required this.bulk,
  });

  final ListingItem listing;
  final TawadPreview preview;
  final bool bulk;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final guard = listing.priceGuard;
    final unit = s.unitWord(guard?.unit ?? listing.unit, 1);
    final quantity = preview.quantity;
    final quantityText =
        '${_trim(quantity)} ${s.unitWord(listing.unit, quantity)}';

    final (String note, SaleNoteTone tone) = switch (preview.status) {
      TawadPreviewStatus.empty => (
        bulk ? s.tawadTypeAmountAndMin : s.tawadTypeAmount,
        SaleNoteTone.neutral,
      ),
      TawadPreviewStatus.ok => (
        s.tawadWithinLimits(AniHowMoney.pesoUnit(preview.perUnit), unit),
        SaleNoteTone.good,
      ),
      TawadPreviewStatus.over => (
        s.tawadOverMax(
          AniHowMoney.pesoUnit(preview.perUnit),
          AniHowMoney.pesoUnit(guard?.maxTawadPerUnit),
          unit,
        ),
        SaleNoteTone.problem,
      ),
      TawadPreviewStatus.belowFloor => (
        s.tawadBelowLowest(
          AniHowMoney.pesoUnit(preview.unitPrice - preview.perUnit),
          AniHowMoney.pesoUnit(guard?.floorPerUnit),
          unit,
        ),
        SaleNoteTone.problem,
      ),
      TawadPreviewStatus.turnedOff => (s.tawadTurnedOff, SaleNoteTone.problem),
      TawadPreviewStatus.unit => (s.tawadUnitNotAllowed, SaleNoteTone.problem),
    };

    return AniHowFormCard(
      child: Semantics(
        liveRegion: true,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.whatBuyerPays.toUpperCase(),
              style: TextStyle(
                color: muted,
                fontSize: 12,
                fontWeight: FontWeight.w600,
                letterSpacing: 0.3,
              ),
            ),
            const SizedBox(height: 10),
            Row(
              crossAxisAlignment: CrossAxisAlignment.baseline,
              textBaseline: TextBaseline.alphabetic,
              children: [
                Expanded(child: Text(quantityText)),
                if (preview.isOk && preview.total < preview.listed) ...[
                  Text(
                    AniHowMoney.peso(preview.listed),
                    style: TextStyle(
                      color: muted,
                      decoration: TextDecoration.lineThrough,
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
                Text(
                  AniHowMoney.peso(preview.total),
                  key: const ValueKey('tawad-preview-total'),
                  style: TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w700,
                    color: readableAccent(context),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            SaleNote(
              key: const ValueKey('tawad-preview-note'),
              text: note,
              tone: tone,
            ),
          ],
        ),
      ),
    );
  }

  static String _trim(double value) => value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toString();
}
