import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import 'reservation_detail_screen.dart';

class BuyerReservationsScreen extends StatefulWidget {
  const BuyerReservationsScreen({super.key});

  @override
  State<BuyerReservationsScreen> createState() => _BuyerReservationsScreenState();
}

class _BuyerReservationsScreenState extends State<BuyerReservationsScreen> {
  late Future<List<ReservationRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerReservations();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.buyerReservations();
    setState(() => _future = future);
    await future;
  }

  List<ReservationRecord> _filter(List<ReservationRecord> items, int tab) {
    return switch (tab) {
      0 => items.where((item) => item.isPending).toList(),
      1 => items.where((item) => item.isReady).toList(),
      2 => items.where((item) => item.isCompleted).toList(),
      _ => items.where((item) => item.isCancelled).toList(),
    };
  }

  Future<void> _open(ReservationRecord reservation) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => ReservationDetailScreen(reservationId: reservation.id),
      ),
    );
    if (mounted) {
      _reload();
    }
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
              Tab(height: AniHowSpace.tabHeight, text: 'Completed'),
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
                    (tab) => _BuyerList(
                      items: _filter(items, tab),
                      empty: _emptyFor(tab),
                      onReload: _reload,
                      onOpen: _open,
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

  ({IconData icon, String message}) _emptyFor(int tab) {
    return switch (tab) {
      0 => (icon: Icons.hourglass_empty, message: 'No pending reservations'),
      1 => (icon: Icons.storefront_outlined, message: 'Nothing waiting for pickup'),
      2 => (icon: Icons.check_circle_outline, message: 'No completed pickups'),
      _ => (icon: Icons.cancel_outlined, message: 'No cancelled reservations'),
    };
  }
}

class _BuyerList extends StatelessWidget {
  const _BuyerList({
    required this.items,
    required this.empty,
    required this.onReload,
    required this.onOpen,
  });

  final List<ReservationRecord> items;
  final ({IconData icon, String message}) empty;
  final Future<void> Function() onReload;
  final Future<void> Function(ReservationRecord reservation) onOpen;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return RefreshIndicator(
        onRefresh: onReload,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: AniHowSpace.screenPadding,
          children: [
            const SizedBox(height: 80),
            Icon(empty.icon, size: 48, color: AniHowColors.brand),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              empty.message,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: AniHowSpace.body),
            ),
          ],
        ),
      );
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
            child: InkWell(
              onTap: () => onOpen(reservation),
              borderRadius: BorderRadius.circular(AniHowSpace.radius),
              child: Padding(
                padding: AniHowSpace.cardPadding,
                child: Row(
                  children: [
                    AniHowAvatar(name: reservation.stallName),
                    const SizedBox(width: AniHowSpace.cardGap),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            reservation.stallName,
                            style: const TextStyle(
                              fontSize: AniHowSpace.name,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            reservation.itemSummary,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(fontSize: AniHowSpace.body),
                          ),
                          Text(
                            AniHowMoney.peso(reservation.total),
                            style: const TextStyle(
                              fontSize: AniHowSpace.body,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          const SizedBox(height: AniHowSpace.labelGap),
                          Row(
                            children: [
                              StatusPill.reservation(
                                reservation.status,
                                label: reservation.statusLabel,
                              ),
                              const SizedBox(width: 8),
                              Text(
                                relativeTime(reservation.createdAt),
                                style: const TextStyle(fontSize: AniHowSpace.label),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right),
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
