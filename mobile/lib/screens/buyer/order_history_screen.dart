import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../chat/order_chat_screen.dart';
import 'buyer_order_detail_screen.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  late Future<List<OrderRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerOrders();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.buyerOrders();
    setState(() {
      _future = future;
    });
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(AppStrings.of(context).orderHistory),
        actions: const [NotificationBellButton()],
      ),
      body: AsyncView<List<OrderRecord>>(
        future: _future,
        onRetry: _reload,
        emptyMessage: AppStrings.of(context).noOrders,
        builder: (context, items) {
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              AniHowSpace.screen,
              AniHowSpace.screen,
              AniHowSpace.screen + AniHowSpace.section,
            ),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) => BuyerOrderCard(
              order: items[index],
              onTap: () async {
                await Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => BuyerOrderDetailScreen(order: items[index]),
                  ),
                );
                if (mounted) {
                  await _reload();
                }
              },
            ),
          );
        },
      ),
    );
  }
}

class BuyerOrderCard extends StatelessWidget {
  const BuyerOrderCard({super.key, required this.order, this.onTap});

  final OrderRecord order;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final location = order.location;
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            InkWell(
              onTap: onTap,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AniHowAvatar(name: order.stallName),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Text(
                          order.stallName,
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: StatusPill.order(order.status, strings: AppStrings.of(context)),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AniHowSpace.cardGap),
                  Text(order.orderNumber ?? 'Order #${order.id}'),
                  PriceBreakdown(
                    listed: order.listedTotal,
                    tawad: order.tawadDisplay,
                    total: order.total,
                  ),
                  if (location != null && location.isNotEmpty) Text(location),
                  if (order.placedAt != null) Text(relativeTime(order.placedAt)),
                  if (order.isCancelled && order.cancellationLabel != null)
                    Text(order.cancellationLabel!),
                  if (order.canBeReviewed) Text(AppStrings.of(context).readyToReview),
                ],
              ),
            ),
            if (!order.isWalkIn) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              FilledButton.tonalIcon(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => OrderChatScreen(order: order),
                    ),
                  );
                },
                icon: const Icon(Icons.chat_bubble_outline),
                label: Text(AppStrings.of(context).chatWithStall),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
