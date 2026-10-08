import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';

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
                  if (_summary?.trackedSince != null)
                    Text(
                      s.trackedSince(_summary!.trackedSince!),
                      key: const ValueKey('tracked-since'),
                    ),
                  if (_summary != null) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    Text(
                      s.stockSummary(
                        harvested: _summary!.harvested,
                        good: _summary!.good,
                        sold: _summary!.sold,
                        left: _summary!.available,
                        removed: _summary!.removed,
                      ),
                      key: const ValueKey('stock-summary'),
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
                  for (final event in _events) _EventTile(event: event),
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

class _EventTile extends StatelessWidget {
  const _EventTile({required this.event});

  final StockEvent event;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final title = event.type == 'removal'
        ? s.rejectionReasonLabel(event.reason ?? 'other')
        : (event.quantityGood ?? event.quantityHarvested ?? '');
    return Card(
      child: ListTile(
        title: Text(title),
        subtitle: Text(
          event.hasCost ? '₱${event.productionCost}' : s.noCostRecorded,
          key: ValueKey(
            event.hasCost
                ? 'cost-${event.id}'
                : 'no-cost-${event.type}-${event.id}',
          ),
        ),
        trailing: Wrap(
          spacing: 4,
          children: [
            if (event.isOpening)
              Chip(
                key: ValueKey('opening-stock-${event.id}'),
                label: Text(s.openingStock),
                visualDensity: VisualDensity.compact,
              ),
            if (event.isEstimated)
              Chip(
                key: ValueKey('estimated-${event.id}'),
                label: Text(s.estimatedHarvest),
                visualDensity: VisualDensity.compact,
              ),
          ],
        ),
      ),
    );
  }
}
