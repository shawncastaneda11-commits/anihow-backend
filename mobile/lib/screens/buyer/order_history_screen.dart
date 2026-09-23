import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/order_look.dart';
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
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final location = order.location;
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
    );
    final when = order.placedAt == null ? null : relativeTime(order.placedAt);
    final summary = [
      AniHowMoney.peso(order.total),
      ?when,
    ].join('  ·  ');

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            InkWell(
              onTap: onTap,
              borderRadius: BorderRadius.circular(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AniHowAvatar(name: order.stallName, radius: 20),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(order.stallName, style: theme.textTheme.titleMedium),
                            const SizedBox(height: 4),
                            Text(summary, style: muted),
                            if (tawadIsActive(order.tawadDisplay))
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Text(
                                  s.tawadMinus(AniHowMoney.peso(order.tawadDisplay)),
                                  style: muted?.copyWith(
                                    color: AniHowColors.sage,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusPill.order(order.status, strings: s),
                    ],
                  ),
                  if (location != null && location.isNotEmpty)
                    OrderMetaRow(icon: Icons.place_outlined, text: location),
                  if (order.isCancelled && order.cancellationLabel != null)
                    OrderMetaRow(icon: Icons.info_outline, text: order.cancellationLabel!),
                  if (order.canBeReviewed)
                    OrderMetaRow(icon: Icons.star_outline, text: s.readyToReview),
                ],
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
    );
  }
}
