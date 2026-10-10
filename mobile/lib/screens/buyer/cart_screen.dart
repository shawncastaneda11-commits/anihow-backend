import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/cart_controller.dart';
import '../../support/order_quantity.dart';
import '../../support/walk_in_quote.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../state/auth_controller.dart';
import '../../widgets/chat_with_stall_button.dart';
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

  List<SellerCartGroup> _groups(CartController cart) {
    return cart.snapshot.groupsBySeller
        .map(
          (group) => SellerCartGroup(
            sellerId: group.sellerId,
            sellerName: group.sellerName,
            items: [for (final item in group.items) _display(item)],
          ),
        )
        .toList();
  }

  void _openCheckout() {
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const CheckoutScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final cart = context.watch<CartController>();
    final s = AppStrings.of(context);
    final groups = _groups(cart);
    final listed = groups.fold<double>(
      0,
      (sum, group) => sum + group.listedSubtotal,
    );
    final tawad = groups.fold<double>(
      0,
      (sum, group) => sum + group.tawadTotal,
    );
    final total = groups.fold<double>(0, (sum, group) => sum + group.total);
    final showBar = !cart.loading && cart.error == null && !cart.isEmpty;
    final itemCount = groups.fold<int>(
      0,
      (sum, group) => sum + group.items.length,
    );
    return Scaffold(
      appBar: AppBar(
        toolbarHeight: showBar ? 64 : kToolbarHeight,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(s.cart),
            if (showBar)
              Text(
                s.cartLineup(itemCount, groups.length),
                style: TextStyle(
                  fontSize: AniHowSpace.label,
                  fontWeight: FontWeight.w400,
                  color: Theme.of(context).colorScheme.onPrimary,
                ),
              ),
          ],
        ),
      ),
      body: _body(cart, s, groups, listed, tawad, total),
      bottomNavigationBar: showBar
          ? _CartCheckoutBar(
              total: total,
              orders: groups.length,
              onCheckout: _openCheckout,
            )
          : null,
    );
  }

  Widget _body(
    CartController cart,
    AppStrings s,
    List<SellerCartGroup> groups,
    double listed,
    double tawad,
    double total,
  ) {
    if (cart.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (cart.error != null) {
      return Center(child: Text('${cart.error}'));
    }
    if (cart.isEmpty) {
      return Center(child: Text(s.emptyCart));
    }
    final buyer = context.watch<AuthController>().user?.isBuyer == true;
    final muted = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.72);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            AniHowSpace.cardGap,
            AniHowSpace.screen,
            0,
          ),
          child: Row(
            children: [
              Icon(Icons.storefront_outlined, size: 18, color: muted),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  cart.snapshot.splitMessage,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: muted,
                  ),
                ),
              ),
            ],
          ),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _refresh,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(
                AniHowSpace.screen,
                AniHowSpace.cardGap,
                AniHowSpace.screen,
                AniHowSpace.screen,
              ),
              children: [
                for (var index = 0; index < groups.length; index++) ...[
                  if (index > 0) const SizedBox(height: AniHowSpace.cardGap),
                  _SellerCartCard(
                    group: groups[index],
                    buyer: buyer,
                    acting: _acting,
                    onQuantity: _queue,
                    onEdit: _editQuantity,
                    onRemove: (item) =>
                        _run(item.id, () => cart.remove(item.id)),
                  ),
                ],
                const SizedBox(height: AniHowSpace.cardGap),
                _CartOrderSummary(
                  itemCount: groups.fold<int>(
                    0,
                    (sum, group) => sum + group.items.length,
                  ),
                  listed: listed,
                  tawad: tawad,
                  total: total,
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _SellerCartCard extends StatelessWidget {
  const _SellerCartCard({
    required this.group,
    required this.buyer,
    required this.acting,
    required this.onQuantity,
    required this.onEdit,
    required this.onRemove,
  });

  final SellerCartGroup group;
  final bool buyer;
  final Set<int> acting;
  final void Function(CartLine item, String next) onQuantity;
  final void Function(CartLine item) onEdit;
  final void Function(CartLine item) onRemove;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.cardPad,
              AniHowSpace.cardPad,
              AniHowSpace.cardPad,
              8,
            ),
            child: Row(
              children: [
                AniHowAvatar(name: group.sellerName, radius: 18),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  child: Text(
                    group.sellerName,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                if (buyer)
                  ChatWithStallButton(
                    sellerId: group.sellerId,
                    iconOnly: true,
                  ),
              ],
            ),
          ),
          const Divider(height: 1),
          for (var index = 0; index < group.items.length; index++) ...[
            if (index > 0) const Divider(height: 1),
            _CartLine(
              item: group.items[index],
              busy: acting.contains(group.items[index].id),
              onQuantity: (next) => onQuantity(group.items[index], next),
              onEdit: () => onEdit(group.items[index]),
              onRemove: () => onRemove(group.items[index]),
            ),
          ],
          _SellerFooter(group: group, label: s.orderSubtotal),
        ],
      ),
    );
  }
}

class _SellerFooter extends StatelessWidget {
  const _SellerFooter({required this.group, required this.label});

  final SellerCartGroup group;
  final String label;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final fill = theme.brightness == Brightness.dark
        ? const Color(0xFF2A3330)
        : const Color(0xFFF1EFE8);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: fill,
        borderRadius: const BorderRadius.vertical(
          bottom: Radius.circular(AniHowSpace.radius),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(
          horizontal: AniHowSpace.cardPad,
          vertical: 10,
        ),
        child: Column(
          children: [
            if (tawadIsActive(group.tawadTotal))
              Padding(
                padding: const EdgeInsets.only(bottom: 4),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        s.discountTawadMinus(AniHowMoney.peso(group.tawadTotal)),
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: readableAccent(context),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            Row(
              children: [
                Expanded(child: Text(label)),
                Text(
                  AniHowMoney.peso(group.total),
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CartLine extends StatefulWidget {
  const _CartLine({
    required this.item,
    required this.busy,
    required this.onQuantity,
    required this.onEdit,
    required this.onRemove,
  });

  final CartLine item;
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
    final theme = Theme.of(context);
    final item = widget.item;
    final listing = item.listing;
    final unit = listing?.unit ?? item.unitLabel;
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    return Padding(
      padding: const EdgeInsets.all(AniHowSpace.cardPad),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _CartThumb(item: item),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            item.listingName,
                            style: theme.textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          AniHowMoney.peso(item.lineTotal),
                          key: ValueKey('cart-line-total-${item.id}'),
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    if (double.tryParse(item.listedPrice) != null)
                      Text(
                        '${AniHowMoney.peso(item.listedPrice)} / $unit',
                        style: theme.textTheme.bodySmall?.copyWith(color: muted),
                      ),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        if (listing == null)
                          Text(item.quantity)
                        else
                          OrderQuantityStepper(
                            controller: _controller,
                            min: listing.minOrderQuantity,
                            step: listing.orderStep,
                            unit: unit,
                            max: double.tryParse(
                              listing.sellableQuantity ??
                                  listing.quantityAvailable,
                            ),
                            lineId: item.id,
                            onChanged: _changed,
                            onValueTap: widget.onEdit,
                            pill: true,
                          ),
                        const Spacer(),
                        IconButton(
                          tooltip: s.remove,
                          onPressed: widget.busy ? null : widget.onRemove,
                          icon: const Icon(Icons.delete_outline, size: 20),
                          style: IconButton.styleFrom(
                            minimumSize: const Size(44, 44),
                            fixedSize: const Size(44, 44),
                            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                            padding: EdgeInsets.zero,
                          ),
                        ),
                      ],
                    ),
                    if (listing != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        _ruleLine(s, listing, unit),
                        key: ValueKey('cart-qty-rule-${item.id}'),
                        style: theme.textTheme.bodySmall?.copyWith(color: muted),
                      ),
                      _CartDiscountNudge(
                        controller: _controller,
                        listing: listing,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _ruleLine(AppStrings s, ListingItem listing, String unit) {
    final hint = s.quantityStepHint(
      formatOrderAmount(listing.minOrderQuantity),
      formatOrderAmount(listing.orderStep),
      unit,
    );
    final equivalent = orderQuantitySmallUnit(1, unit);
    if (equivalent == null) {
      return hint;
    }
    return '$hint · ${s.unitEquals(unit, equivalent)}';
  }
}

class _CartThumb extends StatelessWidget {
  const _CartThumb({required this.item});

  final CartLine item;

  @override
  Widget build(BuildContext context) {
    final url = item.listing?.thumbnailUrl ?? item.listing?.imageUrl;
    final fallback = _fallback(context);
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        width: 60,
        height: 60,
        child: url == null || url.isEmpty
            ? fallback
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => fallback,
              ),
      ),
    );
  }

  Widget _fallback(BuildContext context) {
    return ColoredBox(
      key: ValueKey('cart-thumb-fallback-${item.id}'),
      color: accentTint(context),
      child: Icon(
        Icons.eco_outlined,
        color: readableAccent(context),
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
    final quantity = double.tryParse(widget.controller.text.trim());
    if (rule == null || !rule.isActive || quantity == null) {
      return const SizedBox.shrink();
    }
    final quote = WalkInQuote.forListing(
      widget.listing,
      widget.controller.text,
    );
    if (quote != null && tawadIsActive(quote.tawad)) {
      return Padding(
        padding: const EdgeInsets.only(top: 6),
        child: _TawadChip(
          text: AppStrings.of(context).tawadApplied(
            AniHowMoney.peso(quote.tawad),
          ),
          applied: true,
        ),
      );
    }
    if (!rule.isMinQuantity) {
      return const SizedBox.shrink();
    }
    final minimum = double.tryParse(rule.minQuantity ?? '');
    if (minimum == null) {
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
      padding: const EdgeInsets.only(top: 6),
      child: _TawadChip(
        text: s.cartDiscountNudge(
          formatOrderAmount(missing),
          widget.listing.unit ?? '',
          AniHowMoney.peso(rule.discountAmount),
        ),
        applied: false,
      ),
    );
  }
}

class _TawadChip extends StatelessWidget {
  const _TawadChip({required this.text, required this.applied});

  final String text;
  final bool applied;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    final background = applied
        ? (dark ? const Color(0xFF245C42) : const Color(0xFFE5F4EB))
        : (dark ? const Color(0xFF5A431C) : const Color(0xFFFBF3DC));
    final foreground = applied
        ? (dark ? const Color(0xFFB7E4C7) : const Color(0xFF145C38))
        : (dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C));
    return Row(
      children: [
        Flexible(
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(999),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.local_offer_outlined, size: 14, color: foreground),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      text,
                      key: applied
                          ? null
                          : const ValueKey('cart-discount-nudge'),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: foreground,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
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
    required this.itemCount,
    required this.listed,
    required this.tawad,
    required this.total,
  });

  final int itemCount;
  final double listed;
  final double tawad;
  final double total;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _MoneyRow(
              label: s.itemsCount(itemCount),
              amount: AniHowMoney.peso(listed),
            ),
            if (tawadIsActive(tawad)) ...[
              const SizedBox(height: 6),
              Text(
                s.discountTawadMinus(AniHowMoney.peso(tawad)),
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: readableAccent(context),
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const Divider(height: 20),
            _MoneyRow(
              label: s.total,
              amount: AniHowMoney.peso(total),
              amountKey: const ValueKey('cart-summary-total'),
              emphasize: true,
            ),
            const SizedBox(height: 4),
            Text(
              s.eachSellerPaidSeparately,
              style: theme.textTheme.bodySmall?.copyWith(color: muted),
            ),
          ],
        ),
      ),
    );
  }
}

class _MoneyRow extends StatelessWidget {
  const _MoneyRow({
    required this.label,
    required this.amount,
    this.amountKey,
    this.emphasize = false,
  });

  final String label;
  final String amount;
  final Key? amountKey;
  final bool emphasize;

  @override
  Widget build(BuildContext context) {
    final style = emphasize
        ? Theme.of(context).textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w700,
          )
        : Theme.of(context).textTheme.bodyLarge;
    return Row(
      children: [
        Expanded(child: Text(label, style: style)),
        Text(amount, key: amountKey, style: style),
      ],
    );
  }
}

class _CartCheckoutBar extends StatelessWidget {
  const _CartCheckoutBar({
    required this.total,
    required this.orders,
    required this.onCheckout,
  });

  final double total;
  final int orders;
  final VoidCallback onCheckout;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.surface,
        border: Border(top: BorderSide(color: theme.dividerColor)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.08),
            blurRadius: 12,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 10),
          child: Row(
            children: [
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${s.total} · ${s.orderCount(orders)}',
                      style: theme.textTheme.bodySmall?.copyWith(color: muted),
                    ),
                    Text(
                      AniHowMoney.peso(total),
                      key: const ValueKey('cart-bottom-total'),
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              PrimaryButton(
                label: s.checkout,
                expand: false,
                onPressed: onCheckout,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
