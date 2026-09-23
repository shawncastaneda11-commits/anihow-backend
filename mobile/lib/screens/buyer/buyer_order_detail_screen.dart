import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../chat/order_chat_screen.dart';

class BuyerOrderDetailScreen extends StatefulWidget {
  const BuyerOrderDetailScreen({super.key, this.order, this.orderId})
      : assert(order != null || orderId != null);

  final OrderRecord? order;
  final int? orderId;

  @override
  State<BuyerOrderDetailScreen> createState() => _BuyerOrderDetailScreenState();
}

class _BuyerOrderDetailScreenState extends State<BuyerOrderDetailScreen> {
  late Future<OrderRecord> _future;

  int get _id => widget.orderId ?? widget.order!.id;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<OrderRecord> _load() {
    return context.read<AuthController>().api.buyerOrder(_id);
  }

  Future<void> _reload() async {
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  void _openChat(OrderRecord order) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrderChatScreen(order: order),
      ),
    );
  }

  bool _needsCashHint(OrderRecord order) {
    return !order.isWalkIn && (order.isPlaced || order.isConfirmed || order.isReady);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.order?.stallName ?? s.order),
      ),
      body: AsyncView<OrderRecord>(
        future: _future,
        onRetry: _reload,
        builder: (context, order) {
          final theme = Theme.of(context);
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
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
                              style: theme.textTheme.titleMedium,
                            ),
                          ),
                          StatusPill.order(order.status, strings: s),
                        ],
                      ),
                      if (!order.isWalkIn)
                        Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton.icon(
                            onPressed: () => _openChat(order),
                            icon: const Icon(Icons.chat_bubble_outline, size: 18),
                            label: Text(s.chatWithStall),
                            style: TextButton.styleFrom(
                              padding: const EdgeInsets.only(top: 10, right: 8),
                              visualDensity: VisualDensity.compact,
                            ),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              if (_needsCashHint(order)) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                AniHowHintCard(
                  icon: Icons.payments_outlined,
                  title: s.cashAtMeetup,
                  tone: AniHowHintTone.cash,
                ),
              ],
              const SizedBox(height: AniHowSpace.cardGap),
              Card(
                child: Padding(
                  padding: AniHowSpace.cardPadding,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
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
                      if (order.fulfillmentNote != null && order.fulfillmentNote!.isNotEmpty)
                        OrderMetaRow(
                          icon: Icons.notes_outlined,
                          text: order.fulfillmentNote!,
                        ),
                      if (order.location != null && order.location!.isNotEmpty)
                        OrderMetaRow(
                          icon: Icons.place_outlined,
                          text: order.location!,
                        ),
                      if (order.placedAt != null)
                        OrderMetaRow(
                          icon: Icons.schedule_outlined,
                          text: relativeTime(order.placedAt),
                        ),
                      if (order.isCancelled && order.cancellationLabel != null)
                        OrderMetaRow(
                          icon: Icons.info_outline,
                          text: order.cancellationLabel!,
                        ),
                      if (order.canBeReviewed)
                        OrderMetaRow(
                          icon: Icons.star_outline,
                          text: s.readyToReview,
                        ),
                    ],
                  ),
                ),
              ),
              if (order.items.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Card(
                  child: Padding(
                    padding: AniHowSpace.cardPadding,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Text(s.items, style: theme.textTheme.titleMedium),
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
                                      Text(item.listingName, style: theme.textTheme.titleSmall),
                                      Text(
                                        item.quantityLabel,
                                        style: theme.textTheme.bodyMedium?.copyWith(
                                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Text(
                                  AniHowMoney.peso(item.lineTotal ?? item.lineSubtotal),
                                  style: theme.textTheme.titleSmall,
                                ),
                              ],
                            ),
                          ),
                      ],
                    ),
                  ),
                ),
              ],
              if (order.orderNumber != null) ...[
                const SizedBox(height: AniHowSpace.section),
                Text(
                  order.orderNumber!,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.labelSmall,
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}
