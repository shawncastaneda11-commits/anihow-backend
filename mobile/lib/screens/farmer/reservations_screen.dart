import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';

class FarmerReservationsScreen extends StatefulWidget {
  const FarmerReservationsScreen({super.key});

  @override
  State<FarmerReservationsScreen> createState() => _FarmerReservationsScreenState();
}

class _FarmerReservationsScreenState extends State<FarmerReservationsScreen> {
  late Future<List<ReservationRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.farmerReservations();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.farmerReservations();
    setState(() => _future = future);
    await future;
  }

  Future<void> _ready(int id) async {
    try {
      await context.read<AuthController>().api.markReservationReady(id);
      if (!mounted) {
        return;
      }
      await _reload();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _complete(int id) async {
    try {
      await context.read<AuthController>().api.completeReservation(id);
      if (!mounted) {
        return;
      }
      await _reload();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  List<ReservationRecord> _filter(List<ReservationRecord> items, int tab) {
    return switch (tab) {
      0 => items.where((item) => item.isPending).toList(),
      1 => items.where((item) => item.isReady).toList(),
      2 => items.where((item) => item.isCompleted).toList(),
      _ => items.where((item) => item.isCancelled).toList(),
    };
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 4,
      child: Column(
        children: [
          const TabBar(
            isScrollable: false,
            labelPadding: EdgeInsets.symmetric(horizontal: 4),
            tabs: [
              Tab(height: AniHowSpace.tabHeight, text: 'Pending'),
              Tab(height: AniHowSpace.tabHeight, text: 'Ready'),
              Tab(height: AniHowSpace.tabHeight, text: 'Done'),
              Tab(height: AniHowSpace.tabHeight, text: 'Cancelled'),
            ],
          ),
          Expanded(
            child: FutureBuilder<List<ReservationRecord>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError) {
                  return Center(child: Text('${snapshot.error}'));
                }
                final items = snapshot.data ?? const [];
                return TabBarView(
                  children: List.generate(
                    4,
                    (tab) => _ReservationList(
                      items: _filter(items, tab),
                      onReload: _reload,
                      onReady: _ready,
                      onComplete: _complete,
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _ReservationList extends StatelessWidget {
  const _ReservationList({
    required this.items,
    required this.onReload,
    required this.onReady,
    required this.onComplete,
  });

  final List<ReservationRecord> items;
  final Future<void> Function() onReload;
  final Future<void> Function(int id) onReady;
  final Future<void> Function(int id) onComplete;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Center(child: Text('No reservations in this tab.'));
    }
    return RefreshIndicator(
      onRefresh: onReload,
      child: ListView.separated(
        padding: AniHowSpace.screenPadding,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
        itemBuilder: (context, index) {
          final reservation = items[index];
          return Card(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      AniHowAvatar(name: reservation.counterpartyName ?? 'Buyer'),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              reservation.counterpartyName ?? 'Buyer',
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            Text(
                              '${AniHowMoney.peso(reservation.total)} · #${reservation.id}',
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ],
                        ),
                      ),
                      StatusPill.reservation(reservation.status, label: reservation.statusLabel),
                    ],
                  ),
                  if (reservation.items.isNotEmpty) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    Text(
                      reservation.items.map((item) => item.listingName).join(', '),
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ],
                  if (reservation.isPending) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    PrimaryButton(
                      label: 'Mark ready',
                      onPressed: () => onReady(reservation.id),
                    ),
                  ] else if (reservation.isReady) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    PrimaryButton(
                      label: 'Complete pickup',
                      onPressed: () => onComplete(reservation.id),
                    ),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }
}
