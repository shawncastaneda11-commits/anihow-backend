import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/cart_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/crop_language.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
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
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: Text(AppStrings.of(dialogContext).back)),
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, controller.text.trim()),
              child: Text(AppStrings.of(dialogContext).update),
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
    final language = context.watch<PreferencesController>().language;
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.cart)),
      body: _body(cart, language, s),
      bottomNavigationBar: cart.isEmpty
          ? null
          : SafeArea(
              child: Padding(
                padding: AniHowSpace.screenPadding,
                child: PrimaryButton(
                  label: s.checkout,
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

  Widget _body(CartController cart, CropLanguage language, AppStrings s) {
    if (cart.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (cart.error != null) {
      return Center(child: Text('${cart.error}'));
    }
    if (cart.isEmpty) {
      return Center(child: Text(s.emptyCart));
    }
    final groups = cart.snapshot.groupsBySeller;
    final listed = groups.fold<double>(0, (sum, group) => sum + group.listedSubtotal);
    final tawad = groups.fold<double>(0, (sum, group) => sum + group.tawadTotal);
    final total = groups.fold<double>(0, (sum, group) => sum + group.total);
    return RefreshIndicator(
      onRefresh: cart.reload,
      child: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowHintCard(
            icon: Icons.storefront_outlined,
            title: cart.snapshot.splitMessage,
            tone: AniHowHintTone.brand,
          ),
          const SizedBox(height: AniHowSpace.section),
          for (final group in groups) ...[
            Card(
              child: Padding(
                padding: AniHowSpace.cardPadding,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        AniHowAvatar(name: group.sellerName),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: Text(
                            group.sellerName,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    for (final item in group.items)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(item.listingName),
                        subtitle: Text(
                          [
                            if (item.cropLabel(language) != null) item.cropLabel(language)!,
                            '${item.quantity}${item.unitLabel.isEmpty ? '' : ' ${item.unitLabel}'}',
                          ].join(' · '),
                        ),
                        trailing: IconButton(
                          tooltip: s.remove,
                          onPressed: _acting.contains(item.id)
                              ? null
                              : () => _run(item.id, () => cart.remove(item.id)),
                          icon: const Icon(Icons.delete_outline),
                        ),
                        onTap: () => _editQuantity(item),
                      ),
                    const Divider(height: 20),
                    PriceBreakdown(
                      listed: group.listedSubtotal,
                      tawad: group.tawadTotal,
                      total: group.total,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
          ],
          _CartOrderSummary(listed: listed, tawad: tawad, total: total),
        ],
      ),
    );
  }
}

class _CartOrderSummary extends StatelessWidget {
  const _CartOrderSummary({
    required this.listed,
    required this.tawad,
    required this.total,
  });

  final double listed;
  final double tawad;
  final double total;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(AppStrings.of(context).orderSummary, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.cardGap),
            PriceBreakdown(listed: listed, tawad: tawad, total: total),
          ],
        ),
      ),
    );
  }
}
