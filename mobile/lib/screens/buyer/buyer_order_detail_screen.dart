import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/price_breakdown.dart';
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.order?.orderNumber ?? AppStrings.of(context).order),
      ),
      body: AsyncView<OrderRecord>(
        future: _future,
        onRetry: _reload,
        builder: (context, order) {
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              Row(
                children: [
                  AniHowAvatar(name: order.stallName),
                  const SizedBox(width: AniHowSpace.cardGap),
                  Expanded(
                    child: Text(
                      order.stallName,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  StatusPill.order(order.status, strings: AppStrings.of(context)),
                ],
              ),
              if (!order.isWalkIn) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                FilledButton.tonalIcon(
                  onPressed: () => _openChat(order),
                  icon: const Icon(Icons.chat_bubble_outline),
                  label: Text(AppStrings.of(context).chatWithStall),
                ),
              ],
              const SizedBox(height: AniHowSpace.section),
              AniHowHintCard(
                icon: Icons.payments_outlined,
                title: AppStrings.of(context).payCashTitle,
                body: AppStrings.of(context).payCashBody,
                tone: AniHowHintTone.cash,
              ),
              const SizedBox(height: AniHowSpace.section),
              Text(order.orderNumber ?? 'Order #${order.id}'),
              const SizedBox(height: AniHowSpace.labelGap),
              PriceBreakdown(
                listed: order.listedTotal,
                tawad: order.tawadDisplay,
                total: order.total,
              ),
              if (order.fulfillmentLabel != null) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(order.fulfillmentLabel!),
              ],
              if (order.fulfillmentNote != null && order.fulfillmentNote!.isNotEmpty)
                Text(order.fulfillmentNote!),
              if (order.location != null && order.location!.isNotEmpty) Text(order.location!),
              if (order.placedAt != null) Text(relativeTime(order.placedAt)),
              if (order.isCancelled && order.cancellationLabel != null)
                Text(order.cancellationLabel!),
              if (order.canBeReviewed) Text(AppStrings.of(context).readyToReview),
              const SizedBox(height: AniHowSpace.section),
              for (final item in order.items) ...[
                Text(item.listingName, style: Theme.of(context).textTheme.titleSmall),
                Text(item.quantityLabel),
                const SizedBox(height: AniHowSpace.labelGap),
                PriceBreakdown(
                  listed: item.lineSubtotal,
                  tawad: item.tawadAmount ?? '0',
                  total: item.lineTotal ?? item.lineSubtotal,
                ),
                const SizedBox(height: AniHowSpace.cardGap),
              ],
            ],
          );
        },
      ),
    );
  }
}
