import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/tawad_requests.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';

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

  static final _peso = FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'));

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

  Future<void> _save() async {
    final s = AppStrings.read(context);
    final amount = _amount.text.trim();
    if (amount.isEmpty || (double.tryParse(amount) ?? 0) <= 0) {
      setState(() => _error = s.enterPesoOff);
      return;
    }
    if (_type == TawadRequests.minQuantity) {
      final minimum = _minQuantity.text.trim();
      if (minimum.isEmpty || (double.tryParse(minimum) ?? 0) <= 0) {
        setState(() => _error = s.enterMinQty);
        return;
      }
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await context.read<AuthController>().api.saveTawad(
            listingId: widget.listing.id,
            type: _type,
            discountAmount: amount,
            minQuantity: _type == TawadRequests.minQuantity ? _minQuantity.text.trim() : null,
          );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
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
    final crop = widget.listing.category;
    final s = AppStrings.of(context);
    final language = context.watch<PreferencesController>().language;
    return Scaffold(
      appBar: AppBar(title: Text(widget.listing.tawad == null ? s.setTawad : s.replaceTawad)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowHintCard(
            icon: Icons.sell_outlined,
            title: s.tawadHint,
            body: s.tawadKeepPrice,
            tone: AniHowHintTone.cash,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          AniHowFormCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(widget.listing.title, style: Theme.of(context).textTheme.titleMedium),
                Text('${s.listed} ${AniHowMoney.peso(widget.listing.pricePerUnit)}'),
                if ((crop?.sellerMaxDiscount?.isNotEmpty ?? false) ||
                    (crop?.sellerFloorPrice?.isNotEmpty ?? false)) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  if (crop?.sellerMaxDiscount?.isNotEmpty ?? false)
                    Text(s.maxTawadFor(crop!.labelFor(language), AniHowMoney.peso(crop.sellerMaxDiscount))),
                  if (crop?.sellerFloorPrice?.isNotEmpty ?? false)
                    Text(s.unitFloor(AniHowMoney.peso(crop!.sellerFloorPrice))),
                ],
              ],
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          Text(s.ruleType, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AniHowSpace.cardGap),
          Row(
            children: [
              Expanded(
                child: AniHowChoiceTile(
                  icon: Icons.payments_outlined,
                  label: s.tawadFlat,
                  selected: _type == TawadRequests.flat,
                  onTap: () => setState(() => _type = TawadRequests.flat),
                ),
              ),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: AniHowChoiceTile(
                  icon: Icons.stacked_bar_chart,
                  label: s.tawadMinQty,
                  selected: _type == TawadRequests.minQuantity,
                  onTap: () => setState(() => _type = TawadRequests.minQuantity),
                ),
              ),
            ],
          ),
          const SizedBox(height: AniHowSpace.section),
          AniHowFormCard(
            child: Column(
              children: [
                AniHowField(
                  label: s.pesoOff,
                  child: TextField(
                    controller: _amount,
                    keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    inputFormatters: [_peso],
                    decoration: const InputDecoration(prefixText: '₱ ', hintText: '0.00'),
                  ),
                ),
                if (_type == TawadRequests.minQuantity) ...[
                  const SizedBox(height: AniHowSpace.fieldGap),
                  AniHowField(
                    label: s.minQuantity,
                    child: TextField(
                      controller: _minQuantity,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      inputFormatters: [_peso],
                      decoration: InputDecoration(
                        hintText: 'e.g. 5',
                        suffixText: widget.listing.unitLabel ?? widget.listing.unit,
                      ),
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (_error != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              _error!,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: AniHowSpace.section),
          AniHowHintCard(
            icon: Icons.info_outline,
            title: s.tawadReplaceNote,
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: s.saveTawad, onPressed: _save, busy: _busy),
        ],
      ),
    );
  }
}
