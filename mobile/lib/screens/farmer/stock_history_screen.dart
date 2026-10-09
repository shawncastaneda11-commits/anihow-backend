import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/order_quantity.dart';
import '../../theme/anihow_space.dart';

DateTime? stockLocalDate(String? raw) {
  if (raw == null || raw.trim().isEmpty) {
    return null;
  }
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(raw.trim());
  if (match != null) {
    return DateTime(
      int.parse(match.group(1)!),
      int.parse(match.group(2)!),
      int.parse(match.group(3)!),
    );
  }
  return DateTime.tryParse(raw)?.toLocal();
}

String stockAmount(String? raw, String unit) {
  final shown = formatOrderAmount(double.tryParse(raw ?? '') ?? 0);
  if (unit.trim().isEmpty) {
    return shown;
  }
  return '$shown ${unit.trim()}';
}

class StockHistoryScreen extends StatefulWidget {
  const StockHistoryScreen({
    super.key,
    required this.listingId,
    this.listingTitle,
  });

  final int listingId;
  final String? listingTitle;

  @override
  State<StockHistoryScreen> createState() => _StockHistoryScreenState();
}

class _StockHistoryScreenState extends State<StockHistoryScreen> {
  final _events = <StockEvent>[];
  StockSummary? _summary;
  int _page = 1;
  int _lastPage = 1;
  bool _loading = true;
  bool _loadingMore = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _events.isEmpty;
      _error = null;
    });
    try {
      final page = await context.read<AuthController>().api.stockHistory(
        widget.listingId,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _events
          ..clear()
          ..addAll(page.events);
        _summary = page.summary;
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _loading = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error.message;
      });
    }
  }

  Future<void> _more() async {
    if (_loadingMore || _page >= _lastPage) {
      return;
    }
    setState(() => _loadingMore = true);
    try {
      final page = await context.read<AuthController>().api.stockHistory(
        widget.listingId,
        page: _page + 1,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _events.addAll(page.events);
        _page = page.currentPage;
        _lastPage = page.lastPage;
        _loadingMore = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loadingMore = false;
        _error = error.message;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final summary = _summary;
    final tracked = stockLocalDate(summary?.trackedSince);
    final unit = summary?.unit ?? '';
    return Scaffold(
      appBar: AppBar(title: Text(s.stockHistory)),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : RefreshIndicator(
              onRefresh: _reload,
              child: ListView(
                padding: AniHowSpace.screenPadding,
                children: [
                  if (widget.listingTitle != null)
                    Text(
                      widget.listingTitle!,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  if (tracked != null)
                    Text(
                      s.trackedSince(tracked),
                      key: const ValueKey('tracked-since'),
                    ),
                  if (summary != null) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    Wrap(
                      key: const ValueKey('stock-summary'),
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if ((double.tryParse(summary.starting) ?? 0) > 0)
                          _StatTile(
                            label: s.statStarting,
                            value: stockAmount(summary.starting, unit),
                            tileKey: 'starting',
                          ),
                        _StatTile(
                          label: s.statHarvested,
                          value: stockAmount(summary.harvested, unit),
                          tileKey: 'harvested',
                        ),
                        _StatTile(
                          label: s.statGood,
                          value: stockAmount(summary.good, unit),
                          tileKey: 'good',
                        ),
                        _StatTile(
                          label: s.statSold,
                          value: stockAmount(summary.sold, unit),
                          tileKey: 'sold',
                        ),
                        _StatTile(
                          label: s.statLeft,
                          value: stockAmount(summary.available, unit),
                          tileKey: 'left',
                        ),
                        _StatTile(
                          label: s.statRemoved,
                          value: stockAmount(summary.removed, unit),
                          tileKey: 'removed',
                        ),
                        if (summary.costTotal != null)
                          _StatTile(
                            label: s.statCost,
                            value:
                                '₱${formatOrderAmount(double.tryParse(summary.costTotal!) ?? 0)}',
                            tileKey: 'cost',
                          ),
                      ],
                    ),
                    if (summary.harvestsMissingCost > 0)
                      Padding(
                        padding: const EdgeInsets.only(top: 8),
                        child: Text(
                          s.harvestsWithoutCost(summary.harvestsMissingCost),
                          key: const ValueKey('records-without-cost'),
                        ),
                      ),
                  ],
                  if (_error != null)
                    Text(
                      _error!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  if (_events.isEmpty)
                    Padding(
                      padding: const EdgeInsets.only(top: 24),
                      child: Text(
                        s.stockHistoryEmpty,
                        key: const ValueKey('stock-history-empty'),
                      ),
                    ),
                  for (final event in _events)
                    _EventCard(event: event, unit: unit),
                  if (_page < _lastPage)
                    TextButton(
                      onPressed: _loadingMore ? null : _more,
                      child: Text(s.loadMore),
                    ),
                ],
              ),
            ),
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    required this.tileKey,
  });

  final String label;
  final String value;
  final String tileKey;

  @override
  Widget build(BuildContext context) {
    return Container(
      key: ValueKey('stock-stat-$tileKey'),
      width: 104,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 10),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        children: [
          Text(
            value,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: 2),
          Text(
            label,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ),
    );
  }
}

class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.unit});

  final StockEvent event;
  final String unit;

  @override
  Widget build(BuildContext context) {
    if (event.type == 'removal') {
      return _RemovalCard(event: event, unit: unit);
    }
    return _HarvestCard(event: event, unit: unit);
  }
}

class _HarvestCard extends StatelessWidget {
  const _HarvestCard({required this.event, required this.unit});

  final StockEvent event;
  final String unit;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final date = stockLocalDate(event.harvestedOn ?? event.createdAt);
    final amount = stockAmount(
      event.quantityGood ?? event.quantityHarvested,
      unit,
    );
    return Card(
      margin: const EdgeInsets.only(top: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (date != null) Text(s.shortDate(date)),
            Text(
              s.harvestKind(event.kind),
              key: ValueKey('kind-${event.type}-${event.id}'),
            ),
            if (event.isOpening)
              Text(
                s.startingStock(amount),
                key: ValueKey('opening-stock-${event.id}'),
              )
            else ...[
              Text(
                s.harvestRecordLine(
                  harvested: stockAmount(event.quantityHarvested, unit),
                  rejected: stockAmount(event.quantityRejected, unit),
                  good: stockAmount(event.quantityGood, unit),
                  reason:
                      event.quantityRejected == null ||
                          (double.tryParse(event.quantityRejected!) ?? 0) <= 0
                      ? null
                      : s.rejectionReasonLabel(event.reason ?? ''),
                ),
              ),
              Text(
                event.hasCost
                    ? s.costRecorded(
                        formatOrderAmount(
                          double.tryParse(event.productionCost!) ?? 0,
                        ),
                      )
                    : s.noCostRecorded,
                key: ValueKey(
                  event.hasCost
                      ? 'cost-${event.id}'
                      : 'no-cost-${event.type}-${event.id}',
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RemovalCard extends StatelessWidget {
  const _RemovalCard({required this.event, required this.unit});

  final StockEvent event;
  final String unit;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final date = stockLocalDate(event.createdAt);
    final tone = Theme.of(context).colorScheme.error;
    return Card(
      key: ValueKey('removal-${event.id}'),
      margin: const EdgeInsets.only(top: 12),
      color: tone.withValues(alpha: 0.08),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (date != null)
              Text(s.shortDate(date), style: TextStyle(color: tone)),
            Text(
              s.removedQuantity(stockAmount(event.quantity, unit)),
              style: TextStyle(color: tone, fontWeight: FontWeight.w700),
            ),
            Text(
              s.rejectionReasonLabel(event.reason ?? 'other'),
              style: TextStyle(color: tone),
            ),
            if (event.note != null && event.note!.trim().isNotEmpty)
              Text(event.note!, style: TextStyle(color: tone)),
          ],
        ),
      ),
    );
  }
}
