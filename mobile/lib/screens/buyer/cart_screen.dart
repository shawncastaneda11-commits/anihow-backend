import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/cart_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/primary_button.dart';
import 'checkout_screen.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  final Set<int> _acting = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartController>().reload();
    });
  }

  Future<void> _run(int id, Future<void> Function() action) async {
    if (_acting.contains(id)) {
      return;
    }
    setState(() => _acting.add(id));
    try {
      await action();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _acting.remove(id));
      } else {
        _acting.remove(id);
      }
    }
  }

  Future<void> _editQuantity(CartLine item) async {
    final controller = TextEditingController(text: item.quantity);
    final next = await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: Text(item.listingName),
          content: TextField(
            controller: controller,
            autofocus: true,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: 'Quantity${item.unitLabel.isEmpty ? '' : ' (${item.unitLabel})'}',
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Back')),
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
              child: const Text('Update'),
            ),
          ],
        );
      },
    );
    controller.dispose();
    if (!mounted || next == null || next.isEmpty || next == item.quantity) {
      return;
    }
    await _run(item.id, () => context.read<CartController>().updateQuantity(item.id, next));
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartController>();
    return Scaffold(
      appBar: AppBar(title: const Text('Cart')),
      body: _body(cart),
      bottomNavigationBar: cart.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: AniHowSpace.screenPadding,
                child: PrimaryButton(
                  label: 'Checkout',
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const CheckoutScreen()),
                    );
                  },
                ),
              ),
            ),
    );
  }

  Widget _body(CartController cart) {
    if (cart.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (cart.error != null) {
      return Center(child: Text('${cart.error}'));
    }
    if (cart.isEmpty) {
      return const Center(child: Text('Your cart is empty.'));
    }
    final groups = cart.snapshot.groupsBySeller;
    return RefreshIndicator(
      onRefresh: cart.reload,
      child: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(
            cart.snapshot.splitMessage,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          for (final group in groups) ...[
            Text(group.sellerName, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.labelGap),
            for (final item in group.items)
              Card(
                child: Padding(
                  padding: AniHowSpace.cardPadding,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(item.listingName),
                        subtitle: Text(
                          [
                            if (item.cropDisplayLabel != null) item.cropDisplayLabel!,
                            '${item.quantity}${item.unitLabel.isEmpty ? '' : ' ${item.unitLabel}'} · listed ${AniHowMoney.peso(item.listedPrice)}',
                          ].join(' · '),
                        ),
                        trailing: IconButton(
                          tooltip: 'Remove',
                          onPressed: _acting.contains(item.id)
                              ? null
                              : () => _run(item.id, () => cart.remove(item.id)),
                          icon: const Icon(Icons.delete_outline),
                        ),
                        onTap: () => _editQuantity(item),
                      ),
                      PriceBreakdown(
                        listed: item.lineSubtotal,
                        tawad: item.tawadAmount,
                        total: item.lineTotal,
                      ),
                    ],
                  ),
                ),
              ),
            Padding(
              padding: const EdgeInsets.only(
                top: AniHowSpace.labelGap,
                bottom: AniHowSpace.section,
              ),
              child: PriceBreakdown(
                listed: group.listedSubtotal,
                tawad: group.tawadTotal,
                total: group.total,
              ),
            ),
          ],
        ],
      ),
    );
  }
}
