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

class _FarmerReservationsScreenState extends State<FarmerReservationsScreen>
    with SingleTickerProviderStateMixin {
  List<ReservationRecord> _items = const [];
  bool _loading = true;
  Object? _error;
  final Set<int> _acting = {};
  late final TabController _tabs;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 4, vsync: this);
    _reload();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _items.isEmpty;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.farmerReservations();
      if (!mounted) {
        return;
      }
      setState(() {
        _items = items;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  void _replace(ReservationRecord reservation) {
    setState(() {
      _items = [
        for (final item in _items)
          if (item.id == reservation.id) reservation else item,
      ];
    });
  }

  Future<void> _ready(ReservationRecord reservation) async {
    if (!reservation.isPending || _acting.contains(reservation.id)) {
      return;
    }
    _acting.add(reservation.id);
    _replace(reservation.copyWith(
      status: 'ready_for_pickup',
      statusLabel: 'Ready for pickup',
    ));
    _tabs.animateTo(1);
    try {
      final updated = await context.read<AuthController>().api.markReservationReady(reservation.id);
      _acting.remove(reservation.id);
      if (mounted) {
        _replace(updated);
      }
    } on ApiException catch (error) {
      _acting.remove(reservation.id);
      if (!mounted) {
        return;
      }
      _replace(reservation);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _complete(ReservationRecord reservation) async {
    if (!reservation.isReady || _acting.contains(reservation.id)) {
      return;
    }
    _acting.add(reservation.id);
    _replace(reservation.copyWith(
      status: 'completed',
      statusLabel: 'Completed',
    ));
    _tabs.animateTo(2);
    try {
      final updated = await context.read<AuthController>().api.completeReservation(reservation.id);
      _acting.remove(reservation.id);
      if (mounted) {
        _replace(updated);
      }
    } on ApiException catch (error) {
      _acting.remove(reservation.id);
      if (!mounted) {
        return;
      }
      _replace(reservation);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
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
    return Column(
      children: [
        TabBar(
          controller: _tabs,
          isScrollable: false,
          labelPadding: const EdgeInsets.symmetric(horizontal: 4),
          tabs: const [
            Tab(height: AniHowSpace.tabHeight, text: 'Pending'),
            Tab(height: AniHowSpace.tabHeight, text: 'Ready'),
            Tab(height: AniHowSpace.tabHeight, text: 'Done'),
            Tab(height: AniHowSpace.tabHeight, text: 'Cancelled'),
          ],
        ),
        Expanded(child: _body()),
      ],
    );
  }

  Widget _body() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    return TabBarView(
      controller: _tabs,
      children: List.generate(
        4,
        (tab) => _ReservationList(
          items: _filter(_items, tab),
          acting: _acting,
          onReload: _reload,
          onReady: _ready,
          onComplete: _complete,
        ),
      ),
    );
  }
}

class _ReservationList extends StatelessWidget {
  const _ReservationList({
    required this.items,
    required this.acting,
    required this.onReload,
    required this.onReady,
    required this.onComplete,
  });

  final List<ReservationRecord> items;
  final Set<int> acting;
  final Future<void> Function() onReload;
  final Future<void> Function(ReservationRecord reservation) onReady;
  final Future<void> Function(ReservationRecord reservation) onComplete;

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
                      busy: acting.contains(reservation.id),
                      onPressed: () => onReady(reservation),
                    ),
                  ] else if (reservation.isReady) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    PrimaryButton(
                      label: 'Complete pickup',
                      busy: acting.contains(reservation.id),
                      onPressed: () => onComplete(reservation),
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
