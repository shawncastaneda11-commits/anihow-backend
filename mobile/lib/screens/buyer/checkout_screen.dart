import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/cart_requests.dart';
import '../../state/cart_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/crop_language.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../chat/order_chat_screen.dart';

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
    final language = context.watch<PreferencesController>().language;
    final s = AppStrings.of(context);
    final groups = cart.snapshot.groupsBySeller;
    return Scaffold(
      appBar: AppBar(title: Text(s.checkout)),
      body: cart.loading
          ? const Center(child: CircularProgressIndicator())
          : cart.isEmpty
              ? Center(child: Text(s.emptyCart))
              : ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    AniHowHintCard(
                      icon: Icons.storefront_outlined,
                      title: cart.snapshot.splitMessage,
                      tone: AniHowHintTone.brand,
                    ),
                    if (groups.length > 1) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      AniHowHintCard(
                        icon: Icons.call_split,
                        title: s.checkoutSplit(groups.length),
                      ),
                    ],
                    const SizedBox(height: AniHowSpace.section),
                    for (final group in groups) ...[
                      _SellerOrderCard(group: group, language: language),
                      const SizedBox(height: AniHowSpace.cardGap),
                    ],
                    const SizedBox(height: AniHowSpace.cardGap),
                    Text(s.howYouMeet, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(height: AniHowSpace.cardGap),
                    Row(
                      children: [
                        Expanded(
                          child: _MeetChoice(
                            icon: Icons.shopping_bag_outlined,
                            label: s.pickupShort,
                            selected: _preference == CartRequests.buyerPickup,
                            onTap: () => setState(() => _preference = CartRequests.buyerPickup),
                          ),
                        ),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: _MeetChoice(
                            icon: Icons.delivery_dining_outlined,
                            label: s.deliverShort,
                            selected: _preference == CartRequests.sellerDelivers,
                            onTap: () => setState(() => _preference = CartRequests.sellerDelivers),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.section),
                    AniHowFormCard(
                      title: s.agreedTimePlace,
                      child: TextField(
                        controller: _note,
                        maxLength: 1000,
                        minLines: 2,
                        maxLines: 4,
                        decoration: InputDecoration(
                          hintText: s.timePlaceHint,
                          prefixIcon: const Icon(Icons.place_outlined),
                        ),
                      ),
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowHintCard(
                      icon: Icons.payments_outlined,
                      title: s.cashAtMeetup,
                      tone: AniHowHintTone.cash,
                    ),
                    const SizedBox(height: AniHowSpace.section),
                    PrimaryButton(
                      label: groups.length > 1 ? s.placeOrders(groups.length) : s.placeOrder,
                      onPressed: _place,
                      busy: _busy,
                    ),
                  ],
                ),
    );
  }
}

class _SellerOrderCard extends StatelessWidget {
  const _SellerOrderCard({required this.group, required this.language});

  final SellerCartGroup group;
  final CropLanguage language;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                AniHowAvatar(name: group.sellerName, radius: 20),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  child: Text(group.sellerName, style: theme.textTheme.titleMedium),
                ),
              ],
            ),
            const SizedBox(height: 4),
            for (final item in group.items)
              Padding(
                padding: const EdgeInsets.only(top: 10),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(item.listingName, style: theme.textTheme.titleSmall),
                          Text(
                            [
                              if (item.cropLabel(language) != null) item.cropLabel(language)!,
                              '${item.quantity}${item.unitLabel.isEmpty ? '' : ' ${item.unitLabel}'}',
                            ].join(' · '),
                            style: theme.textTheme.bodyMedium?.copyWith(
                              color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                            ),
                          ),
                        ],
                      ),
                    ),
                    Text(
                      AniHowMoney.peso(item.lineTotal),
                      style: theme.textTheme.titleSmall,
                    ),
                  ],
                ),
              ),
            const SizedBox(height: AniHowSpace.cardGap),
            OrderTotalHero(
              total: group.total,
              tawadLine: tawadIsActive(group.tawadTotal)
                  ? s.tawadMinus(AniHowMoney.peso(group.tawadTotal))
                  : null,
            ),
          ],
        ),
      ),
    );
  }
}

class _MeetChoice extends StatelessWidget {
  const _MeetChoice({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final border = selected ? AniHowColors.brand : theme.dividerColor;
    final wash = selected
        ? (theme.brightness == Brightness.dark ? const Color(0xFF1A2A22) : const Color(0xFFEDF6F0))
        : theme.cardTheme.color ?? theme.colorScheme.surface;
    return Material(
      color: wash,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        side: BorderSide(color: border, width: selected ? 1.6 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
          child: Column(
            children: [
              Icon(icon, color: selected ? AniHowColors.brand : AniHowColors.muted),
              const SizedBox(height: 8),
              Text(
                label,
                textAlign: TextAlign.center,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: selected ? AniHowColors.brand : theme.colorScheme.onSurface,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PlacedView extends StatelessWidget {
  const _PlacedView({required this.orders});

  final List<OrderRecord> orders;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.ordersPlaced)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowHintCard(
            icon: Icons.check_circle_outline,
            title: orders.length == 1 ? s.orderPlacedTitle : s.cartSplit(orders.length),
            tone: AniHowHintTone.brand,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          AniHowHintCard(
            icon: Icons.payments_outlined,
            title: s.cashAtMeetup,
            tone: AniHowHintTone.cash,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          for (final order in orders) ...[
            Card(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        AniHowAvatar(name: order.stallName, radius: 20),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: Text(
                            order.stallName,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    OrderTotalHero(
                      total: order.total,
                      tawadLine: tawadIsActive(order.tawadDisplay)
                          ? s.tawadMinus(AniHowMoney.peso(order.tawadDisplay))
                          : null,
                    ),
                    if (order.fulfillmentLabel != null)
                      OrderMetaRow(
                        icon: Icons.handshake_outlined,
                        text: order.fulfillmentLabel!,
                      ),
                    if (order.items.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      for (final item in order.items)
                        Padding(
                          padding: const EdgeInsets.only(top: 10),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(item.listingName, style: Theme.of(context).textTheme.titleSmall),
                                    Text(
                                      item.quantityLabel,
                                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                            color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.68),
                                          ),
                                    ),
                                  ],
                                ),
                              ),
                              Text(
                                AniHowMoney.peso(item.lineTotal ?? item.lineSubtotal),
                                style: Theme.of(context).textTheme.titleSmall,
                              ),
                            ],
                          ),
                        ),
                    ],
                    if (order.orderNumber != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 12),
                        child: Text(
                          order.orderNumber!,
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                      ),
                    if (!order.isWalkIn)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => OrderChatScreen(order: order),
                              ),
                            );
                          },
                          icon: const Icon(Icons.chat_bubble_outline, size: 18),
                          label: Text(s.chatWithStall),
                          style: TextButton.styleFrom(
                            padding: const EdgeInsets.only(top: 6, right: 8),
                            visualDensity: VisualDensity.compact,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
          ],
          PrimaryButton(
            label: s.done,
            onPressed: () => Navigator.of(context).popUntil((route) => route.isFirst),
          ),
        ],
      ),
    );
  }
}
