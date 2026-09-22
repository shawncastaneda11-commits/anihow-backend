import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';

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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Order history'),
        actions: const [NotificationBellButton()],
      ),
      body: FutureBuilder<List<OrderRecord>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final items = snapshot.data ?? const [];
          if (items.isEmpty) {
            return const Center(child: Text('No orders yet.'));
          }
          return ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final order = items[index];
              return Card(
                child: ListTile(
                  leading: AniHowAvatar(name: order.stallName),
                  title: Text(order.stallName),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(order.orderNumber ?? 'Order #${order.id}'),
                      PriceBreakdown(
                        listed: order.listedTotal,
                        tawad: order.tawadDisplay,
                        total: order.total,
                      ),
                      if (order.location != null && order.location!.isNotEmpty)
                        Text(order.location!),
                      if (order.placedAt != null) Text(relativeTime(order.placedAt)),
                      if (order.isCancelled && order.cancellationLabel != null)
                        Text(order.cancellationLabel!),
                      if (order.canBeReviewed) const Text('Ready to review'),
                    ],
                  ),
                  isThreeLine: true,
                  trailing: StatusPill.order(order.status, label: order.statusLabel),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
