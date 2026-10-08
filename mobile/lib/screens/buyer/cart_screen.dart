import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/cart_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/crop_language.dart';
import '../../support/order_quantity.dart';
import '../../support/walk_in_quote.dart';
import '../../theme/anihow_space.dart';
import '../../state/auth_controller.dart';
import '../../widgets/chat_with_stall_button.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/order_quantity_stepper.dart';
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
  final Map<int, String> _quantities = {};
  final Map<int, String> _saved = {};
  final Map<int, Timer> _timers = {};

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<CartController>().reload();
    });
  }

  @override
  void dispose() {
    for (final timer in _timers.values) {
      timer.cancel();
    }
    super.dispose();
  }

  Future<void> _refresh() async {
    final auth = context.read<AuthController>();
    final cart = context.read<CartController>();
    if (auth.user != null) {
      try {
        await auth.refreshUser();
      } catch (_) {}
    }
    await cart.reload();
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
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
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
    final current = _display(item);
    final next = await showDialog<String>(
      context: context,
      builder: (_) => CartQuantityDialog(item: current),
    );
    if (!mounted || next == null || next.isEmpty || next == current.quantity) {
      return;
    }
    _queue(item, next);
  }

  void _queue(CartLine item, String next) {
    _saved.putIfAbsent(item.id, () => item.quantity);
    setState(() => _quantities[item.id] = next);
    _timers[item.id]?.cancel();
    _timers[item.id] = Timer(const Duration(milliseconds: 600), () {
      _commit(item.id, next);
    });
  }

  Future<void> _commit(int id, String next) async {
    final saved = _saved[id];
    await _run(id, () async {
      try {
        await context.read<CartController>().updateQuantity(id, next);
        if (mounted) {
          setState(() {
            _quantities.remove(id);
            _saved.remove(id);
          });
        }
      } on ApiException catch (error) {
        if (mounted) {
          setState(() => _quantities[id] = saved ?? next);
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(error.message)));
        }
      }
    });
  }

  CartLine _display(CartLine item) {
    final quantity = _quantities[item.id];
    if (quantity == null || quantity == item.quantity) {
      return item;
    }
    final listing = item.listing;
    final quote = listing == null
        ? null
        : WalkInQuote.forListing(listing, quantity);
    if (quote == null) {
      return item;
    }
    return CartLine(
      id: item.id,
      quantity: quantity,
      listedPrice: item.listedPrice,
      lineSubtotal: quote.listed.toString(),
      tawadAmount: quote.tawad.toString(),
      lineTotal: quote.total.toString(),
      listing: listing,
    );
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
    final groups = cart.snapshot.groupsBySeller
        .map(
          (group) => SellerCartGroup(
            sellerId: group.sellerId,
            sellerName: group.sellerName,
            items: [for (final item in group.items) _display(item)],
          ),
        )
        .toList();
    final listed = groups.fold<double>(
      0,
      (sum, group) => sum + group.listedSubtotal,
    );
    final tawad = groups.fold<double>(
      0,
      (sum, group) => sum + group.tawadTotal,
    );
    final total = groups.fold<double>(0, (sum, group) => sum + group.total);
    return RefreshIndicator(
      onRefresh: _refresh,
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
                    if (context.watch<AuthController>().user?.isBuyer == true)
                      ChatWithStallButton(
                        sellerId: group.sellerId,
                        compact: true,
                      ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    for (final item in group.items)
                      _CartLine(
                        item: item,
                        language: language,
                        busy: _acting.contains(item.id),
                        onQuantity: (next) => _queue(item, next),
                        onEdit: () => _editQuantity(item),
                        onRemove: () => _run(item.id, () => cart.remove(item.id)),
                      ),
                    const Divider(height: 20),
                    OrderTotalHero(
                      total: group.total,
                      tawadLine: tawadIsActive(group.tawadTotal)
                          ? AppStrings.of(context)
                                .discountTawadMinus(
                                  AniHowMoney.peso(group.tawadTotal),
                                )
                          : null,
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

class _CartLine extends StatefulWidget {
  const _CartLine({
    required this.item,
    required this.language,
    required this.busy,
    required this.onQuantity,
    required this.onEdit,
    required this.onRemove,
  });

  final CartLine item;
  final CropLanguage language;
  final bool busy;
  final ValueChanged<String> onQuantity;
  final VoidCallback onEdit;
  final VoidCallback onRemove;

  @override
  State<_CartLine> createState() => _CartLineState();
}

class _CartLineState extends State<_CartLine> {
  late final TextEditingController _controller;
  bool _applying = false;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.item.quantity);
  }

  @override
  void didUpdateWidget(_CartLine oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.item.quantity != _controller.text) {
      _applying = true;
      _controller.text = widget.item.quantity;
      _applying = false;
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _changed(String next) {
    if (_applying || next == widget.item.quantity) {
      return;
    }
    widget.onQuantity(next);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final item = widget.item;
    final listing = item.listing;
    final crop = item.cropLabel(widget.language);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(item.listingName, style: Theme.of(context).textTheme.titleSmall),
          if (crop != null)
            Text(crop, style: Theme.of(context).textTheme.bodyMedium),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: listing == null
                    ? Text(item.quantity)
                    : OrderQuantityStepper(
                        controller: _controller,
                        min: listing.minOrderQuantity,
                        step: listing.orderStep,
                        unit: listing.unit ?? item.unitLabel,
                        max: double.tryParse(
                          listing.sellableQuantity ?? listing.quantityAvailable,
                        ),
                        lineId: item.id,
                        onChanged: _changed,
                        onValueTap: widget.onEdit,
                      ),
              ),
              const SizedBox(width: 8),
              Padding(
                padding: const EdgeInsets.only(top: 12),
                child: Text(
                  AniHowMoney.peso(item.lineTotal),
                  key: ValueKey('cart-line-total-${item.id}'),
                ),
              ),
              IconButton(
                tooltip: s.remove,
                onPressed: widget.busy ? null : widget.onRemove,
                icon: const Icon(Icons.delete_outline),
                style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
              ),
            ],
          ),
          if (listing != null)
            _CartDiscountNudge(
              controller: _controller,
              listing: listing,
            ),
        ],
      ),
    );
  }
}

class _CartDiscountNudge extends StatefulWidget {
  const _CartDiscountNudge({
    required this.controller,
    required this.listing,
  });

  final TextEditingController controller;
  final ListingItem listing;

  @override
  State<_CartDiscountNudge> createState() => _CartDiscountNudgeState();
}

class _CartDiscountNudgeState extends State<_CartDiscountNudge> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_rebuild);
  }

  @override
  void didUpdateWidget(_CartDiscountNudge oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_rebuild);
      widget.controller.addListener(_rebuild);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_rebuild);
    super.dispose();
  }

  void _rebuild() {
    if (mounted) {
      setState(() {});
    }
  }

  @override
  Widget build(BuildContext context) {
    final rule = widget.listing.tawad;
    if (rule == null || !rule.isActive || !rule.isMinQuantity) {
      return const SizedBox.shrink();
    }
    final quantity = double.tryParse(widget.controller.text.trim());
    final minimum = double.tryParse(rule.minQuantity ?? '');
    if (quantity == null || minimum == null) {
      return const SizedBox.shrink();
    }
    final missing = quantityUntilDiscount(
      quantity: quantity,
      orderMin: widget.listing.minOrderQuantity,
      step: widget.listing.orderStep,
      ruleMin: minimum,
    );
    if (missing == null) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Text(
        s.cartDiscountNudge(
          formatOrderAmount(missing),
          widget.listing.unit ?? '',
          AniHowMoney.peso(rule.discountAmount),
        ),
        key: const ValueKey('cart-discount-nudge'),
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
        ),
      ),
    );
  }
}

/// Owns the field controller so it is not disposed while the dialog
/// route is still leaving the tree.
class CartQuantityDialog extends StatefulWidget {
  const CartQuantityDialog({super.key, required this.item});

  final CartLine item;

  @override
  State<CartQuantityDialog> createState() => _CartQuantityDialogState();
}

class _CartQuantityDialogState extends State<CartQuantityDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.item.quantity);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.read(context);
    final listing = widget.item.listing;
    final unit = widget.item.unitLabel;
    return AlertDialog(
      title: Text(widget.item.listingName),
      content: listing == null
          ? TextField(
              controller: _controller,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(
                decimal: true,
              ),
              decoration: InputDecoration(
                labelText: unit.isEmpty ? s.quantity : '${s.quantity} ($unit)',
              ),
              onSubmitted: (value) => Navigator.pop(context, value.trim()),
            )
          : OrderQuantityStepper(
              controller: _controller,
              min: listing.minOrderQuantity,
              step: listing.orderStep,
              unit: listing.unit ?? unit,
              max: double.tryParse(
                listing.sellableQuantity ?? listing.quantityAvailable,
              ),
            ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(s.back),
        ),
        TextButton(
          key: const ValueKey('cart-quantity-update'),
          onPressed: () {
            if (listing != null) {
              final parsed = double.tryParse(_controller.text.trim());
              final snapped = snapOrderQuantity(
                value: parsed ?? listing.minOrderQuantity,
                min: listing.minOrderQuantity,
                step: listing.orderStep,
                max: double.tryParse(
                  listing.sellableQuantity ?? listing.quantityAvailable,
                ),
              );
              _controller.text = formatOrderAmount(snapped);
            }
            Navigator.pop(context, _controller.text.trim());
          },
          child: Text(s.update),
        ),
      ],
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
            Text(
              AppStrings.of(context).orderSummary,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            OrderTotalHero(
              total: total,
              tawadLine: tawadIsActive(tawad)
                  ? AppStrings.of(context).discountTawadMinus(
                      AniHowMoney.peso(tawad),
                    )
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}
