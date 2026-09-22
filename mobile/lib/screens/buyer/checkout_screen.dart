import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/cart_requests.dart';
import '../../state/cart_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/primary_button.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  String _preference = CartRequests.buyerPickup;
  final _note = TextEditingController();
  bool _busy = false;
  List<OrderRecord>? _placed;

  static const _preferences = [
    (value: CartRequests.buyerPickup, label: 'Buyer picks up'),
    (value: CartRequests.sellerDelivers, label: 'Farmer-seller delivers'),
  ];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartController>().reload();
    });
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _place() async {
    if (_busy) {
      return;
    }
    setState(() => _busy = true);
    try {
      final orders = await context.read<CartController>().checkout(
            fulfillmentPreference: _preference,
            fulfillmentNote: _note.text.trim(),
          );
      if (!mounted) {
        return;
      }
      setState(() {
        _placed = orders;
        _busy = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_placed != null) {
      return _PlacedView(orders: _placed!);
    }

    final cart = context.watch<CartController>();
    final groups = cart.snapshot.groupsBySeller;
    return Scaffold(
      appBar: AppBar(title: const Text('Checkout')),
      body: cart.loading
          ? const Center(child: CircularProgressIndicator())
          : cart.isEmpty
              ? const Center(child: Text('Your cart is empty.'))
              : ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    Text(
                      cart.snapshot.splitMessage,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    if (groups.length > 1) ...[
                      const SizedBox(height: AniHowSpace.labelGap),
                      Text(
                        'Confirming places ${groups.length} orders. The split happens now, not after.',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                    const SizedBox(height: AniHowSpace.section),
                    for (final group in groups) ...[
                      Text(group.sellerName, style: Theme.of(context).textTheme.titleSmall),
                      const SizedBox(height: AniHowSpace.labelGap),
                      for (final item in group.items) ...[
                        Text(
                          [
                            if (item.cropDisplayLabel != null) item.cropDisplayLabel!,
                            '${item.listingName} · ${item.quantity}${item.unitLabel.isEmpty ? '' : ' ${item.unitLabel}'}',
                          ].join(' · '),
                        ),
                        Text('Listed unit ${AniHowMoney.peso(item.listedPrice)}'),
                        PriceBreakdown(
                          listed: item.lineSubtotal,
                          tawad: item.tawadAmount,
                          total: item.lineTotal,
                        ),
                        const SizedBox(height: AniHowSpace.cardGap),
                      ],
                      PriceBreakdown(
                        listed: group.listedSubtotal,
                        tawad: group.tawadTotal,
                        total: group.total,
                      ),
                      const SizedBox(height: AniHowSpace.section),
                    ],
                    AniHowField(
                      label: 'Fulfillment',
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'A text arrangement for time and place. No courier, fee, or tracking.',
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                          RadioGroup<String>(
                            groupValue: _preference,
                            onChanged: (value) {
                              if (value != null) {
                                setState(() => _preference = value);
                              }
                            },
                            child: Column(
                              children: [
                                for (final option in _preferences)
                                  RadioListTile<String>(
                                    title: Text(option.label),
                                    value: option.value,
                                    contentPadding: EdgeInsets.zero,
                                    selected: _preference == option.value,
                                  ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    AniHowField(
                      label: 'Agreed time and place',
                      child: TextField(
                        controller: _note,
                        maxLength: 1000,
                        minLines: 2,
                        maxLines: 4,
                        decoration: const InputDecoration(
                          hintText: 'Saturday 7am at the barangay hall',
                        ),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.fieldGap),
                    Text(
                      'Payment is cash on handover. It is recorded, not processed.',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: AniHowSpace.section),
                    PrimaryButton(
                      label: groups.length > 1 ? 'Place ${groups.length} orders' : 'Place order',
                      onPressed: _place,
                      busy: _busy,
                    ),
                  ],
                ),
    );
  }
}

class _PlacedView extends StatelessWidget {
  const _PlacedView({required this.orders});

  final List<OrderRecord> orders;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Orders placed')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(
            orders.length == 1
                ? 'Order placed.'
                : 'Your cart was split into ${orders.length} orders, one per seller.',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(
            'Payment is ${orders.first.paymentLabel.toLowerCase()}. Recorded, not processed.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: AniHowSpace.section),
          for (final order in orders) ...[
            Text(order.stallName, style: Theme.of(context).textTheme.titleSmall),
            Text(order.orderNumber ?? 'Order #${order.id}'),
            if (order.fulfillmentLabel != null) Text(order.fulfillmentLabel!),
            for (final item in order.items) ...[
              Text('${item.listingName} · ${item.quantityLabel}'),
              Text('Listed unit ${AniHowMoney.peso(item.listedPrice)}'),
              PriceBreakdown(
                listed: item.lineSubtotal,
                tawad: item.tawadAmount ?? '0',
                total: item.lineTotal ?? item.lineSubtotal,
              ),
              const SizedBox(height: AniHowSpace.cardGap),
            ],
            PriceBreakdown(
              listed: order.listedTotal,
              tawad: order.tawadDisplay,
              total: order.total,
            ),
            const SizedBox(height: AniHowSpace.section),
          ],
          PrimaryButton(
            label: 'Done',
            onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
          ),
        ],
      ),
    );
  }
}
