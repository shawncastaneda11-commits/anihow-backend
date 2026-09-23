import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/tawad_requests.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';
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

  static const _types = [
    (value: TawadRequests.flat, label: 'Flat peso off per order'),
    (value: TawadRequests.minQuantity, label: 'Peso off at a minimum quantity'),
  ];

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
    final amount = _amount.text.trim();
    if (amount.isEmpty || (double.tryParse(amount) ?? 0) <= 0) {
      setState(() => _error = 'Enter a peso amount greater than zero.');
      return;
    }
    if (_type == TawadRequests.minQuantity) {
      final minimum = _minQuantity.text.trim();
      if (minimum.isEmpty || (double.tryParse(minimum) ?? 0) <= 0) {
        setState(() => _error = 'Enter the minimum quantity for this tawad.');
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
    return Scaffold(
      appBar: AppBar(title: Text(widget.listing.tawad == null ? 'Set tawad' : 'Replace tawad')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(widget.listing.title, style: Theme.of(context).textTheme.titleMedium),
          Text('Listed ${AniHowMoney.peso(widget.listing.pricePerUnit)}'),
          const SizedBox(height: AniHowSpace.cardGap),
          const Text('Tawad is a peso discount on the order.'),
          if (crop?.sellerMaxDiscount != null || crop?.sellerFloorPrice != null) ...[
            const SizedBox(height: AniHowSpace.labelGap),
            if (crop?.maxDiscount != null)
              Text('Maximum tawad for ${crop!.labelFor(context.watch<PreferencesController>().language)} is ${AniHowMoney.peso(crop.sellerMaxDiscount)}.'),
            if (crop?.floorPrice != null)
              Text('Unit price cannot fall below ${AniHowMoney.peso(crop!.sellerFloorPrice)}.'),
          ],
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: 'Rule type',
            child: RadioGroup<String>(
              groupValue: _type,
              onChanged: (value) {
                if (value != null) {
                  setState(() => _type = value);
                }
              },
              child: Column(
                children: [
                  for (final option in _types)
                    RadioListTile<String>(
                      title: Text(option.label),
                      value: option.value,
                      contentPadding: EdgeInsets.zero,
                      selected: _type == option.value,
                    ),
                ],
              ),
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Peso amount off',
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
              label: 'Minimum quantity',
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
          Text(
            'Saving replaces any current rule on this listing. Orders already confirmed keep the price they were confirmed at.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: 'Save tawad', onPressed: _save, busy: _busy),
        ],
      ),
    );
  }
}
