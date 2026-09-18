import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key});

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  late Future<List<ReservationRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerOrders();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Order history')),
      body: FutureBuilder<List<ReservationRecord>>(
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
            return const Center(child: Text('No completed or cancelled orders yet.'));
          }
          return ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final order = items[index];
              return Card(
                child: ListTile(
                  leading: AniHowAvatar(name: order.counterpartyName ?? 'Shop'),
                  title: Text(order.counterpartyName ?? 'Order #${order.id}'),
                  subtitle: Text(AniHowMoney.peso(order.total)),
                  trailing: StatusPill.reservation(order.status, label: order.statusLabel),
                ),
              );
            },
          );
        },
      ),
    );
  }
}
