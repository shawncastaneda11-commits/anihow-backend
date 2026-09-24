import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/hint_card.dart';

class FarmerSalesScreen extends StatefulWidget {
  const FarmerSalesScreen({
    super.key,
    this.preview,
    this.initialPeriod = 'week',
  });

  final FarmerAnalytics? preview;
  final String initialPeriod;

  @override
  State<FarmerSalesScreen> createState() => _FarmerSalesScreenState();
}

class _FarmerSalesScreenState extends State<FarmerSalesScreen> {
  late String _period;
  late Future<FarmerAnalytics> _future;

  @override
  void initState() {
    super.initState();
    _period = widget.initialPeriod;
    _future = _load();
  }

  Future<FarmerAnalytics> _load() async {
    final preview = widget.preview;
    if (preview != null) {
      return preview;
    }

    return context.read<AuthController>().api.farmerAnalytics(period: _period);
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _setPeriod(String period) async {
    if (_period == period) {
      return;
    }
    setState(() => _period = period);
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return AsyncView<FarmerAnalytics>(
      future: _future,
      onRetry: _reload,
      isEmpty: (data) => data.isEmpty,
      emptyBuilder: (context) => _SalesEmpty(
        period: _period,
        onPeriod: _setPeriod,
      ),
      builder: (context, data) {
        return ListView(
          padding: AniHowSpace.screenPadding,
          children: [
            _PeriodToggle(period: _period, onChanged: _setPeriod),
            const SizedBox(height: AniHowSpace.cardGap),
            _SummaryTiles(summary: data.summary),
            const SizedBox(height: AniHowSpace.section),
            Text(s.salesThisPeriod, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.cardGap),
            _SalesBars(points: data.salesPerPeriod),
            const SizedBox(height: AniHowSpace.section),
            Text(s.walkInShare, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.cardGap),
            _WalkInCard(share: data.walkInShare),
            if (data.unitsPerCropType.isNotEmpty) ...[
              const SizedBox(height: AniHowSpace.section),
              Text(s.topCrops, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.cardGap),
              ...data.unitsPerCropType.map((row) => _CropRow(row: row)),
            ],
            if (data.bestSelling.isNotEmpty) ...[
              const SizedBox(height: AniHowSpace.section),
              Text(s.bestSellers, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.cardGap),
              ...data.bestSelling.map((row) => _CropRow(row: row)),
            ],
          ],
        );
      },
    );
  }
}

class _SalesEmpty extends StatelessWidget {
  const _SalesEmpty({required this.period, required this.onPeriod});

  final String period;
  final Future<void> Function(String period) onPeriod;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return ListView(
      key: const Key('sales-empty'),
      padding: AniHowSpace.screenPadding,
      children: [
        _PeriodToggle(period: period, onChanged: onPeriod),
        const SizedBox(height: AniHowSpace.section),
        AniHowHintCard(
          icon: Icons.insights_outlined,
          title: s.mySalesEmpty,
        ),
      ],
    );
  }
}

class _PeriodToggle extends StatelessWidget {
  const _PeriodToggle({required this.period, required this.onChanged});

  final String period;
  final Future<void> Function(String period) onChanged;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return Row(
      children: [
        Expanded(
          child: _PeriodChip(
            key: const Key('sales-period-week'),
            label: s.periodWeek,
            selected: period == 'week',
            onTap: () => onChanged('week'),
          ),
        ),
        const SizedBox(width: AniHowSpace.cardGap),
        Expanded(
          child: _PeriodChip(
            key: const Key('sales-period-month'),
            label: s.periodMonth,
            selected: period == 'month',
            onTap: () => onChanged('month'),
          ),
        ),
      ],
    );
  }
}

class _PeriodChip extends StatelessWidget {
  const _PeriodChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return SizedBox(
      height: 48,
      child: Material(
        color: selected ? AniHowColors.navActive : theme.colorScheme.surface,
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
          child: Center(
            child: Text(
              label,
              style: theme.textTheme.titleMedium?.copyWith(
                color: selected ? AniHowColors.brand : theme.colorScheme.onSurface,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SummaryTiles extends StatelessWidget {
  const _SummaryTiles({required this.summary});

  final FarmerAnalyticsSummary summary;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return Column(
      children: [
        Row(
          children: [
            Expanded(
              child: _StatTile(
                label: s.completedOrders,
                value: '${summary.completedOrders}',
              ),
            ),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: _StatTile(
                label: s.unitsSold,
                value: summary.unitsSold.toStringAsFixed(summary.unitsSold == summary.unitsSold.roundToDouble() ? 0 : 2),
              ),
            ),
          ],
        ),
        const SizedBox(height: AniHowSpace.cardGap),
        Row(
          children: [
            Expanded(
              child: _StatTile(
                label: s.grossSales,
                value: AniHowMoney.peso(summary.grossSales),
              ),
            ),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: _StatTile(
                label: s.averageTawad,
                value: AniHowMoney.peso(summary.averageDiscount),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class _StatTile extends StatelessWidget {
  const _StatTile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: theme.textTheme.bodySmall),
            const SizedBox(height: 4),
            Text(value, style: theme.textTheme.titleMedium),
          ],
        ),
      ),
    );
  }
}

class _SalesBars extends StatelessWidget {
  const _SalesBars({required this.points});

  final List<FarmerSalesPoint> points;

  @override
  Widget build(BuildContext context) {
    final maxRevenue = points.fold<double>(0, (max, point) => point.revenue > max ? point.revenue : max);
    final theme = Theme.of(context);

    return Card(
      key: const Key('sales-chart'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: SizedBox(
          height: 160,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              for (final point in points)
                Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 3),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Expanded(
                          child: Align(
                            alignment: Alignment.bottomCenter,
                            child: FractionallySizedBox(
                              heightFactor: maxRevenue <= 0 ? 0.04 : (point.revenue / maxRevenue).clamp(0.04, 1),
                              widthFactor: 1,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: AniHowColors.brand,
                                  borderRadius: BorderRadius.circular(6),
                                ),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          _shortPeriod(point.period),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.labelSmall,
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }

  String _shortPeriod(String period) {
    if (period.length >= 10 && period.contains('-')) {
      return period.substring(5);
    }
    return period;
  }
}

class _WalkInCard extends StatelessWidget {
  const _WalkInCard({required this.share});

  final FarmerWalkInShare share;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          children: [
            _ShareLine(
              label: s.walkInSales,
              orders: share.walkInOrders,
              sales: share.walkInSales,
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            _ShareLine(
              label: s.appSales,
              orders: share.appOrders,
              sales: share.appSales,
            ),
          ],
        ),
      ),
    );
  }
}

class _ShareLine extends StatelessWidget {
  const _ShareLine({
    required this.label,
    required this.orders,
    required this.sales,
  });

  final String label;
  final int orders;
  final double sales;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Row(
      children: [
        Expanded(child: Text(label, style: theme.textTheme.bodyMedium)),
        Text(
          '$orders · ${AniHowMoney.peso(sales)}',
          style: theme.textTheme.titleSmall,
        ),
      ],
    );
  }
}

class _CropRow extends StatelessWidget {
  const _CropRow({required this.row});

  final FarmerCropSales row;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final unit = row.unit == null || row.unit!.isEmpty ? '' : ' ${row.unit}';

    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(
          horizontal: AniHowSpace.cardPad,
          vertical: 4,
        ),
        title: Text(row.crop),
        subtitle: Text('${row.units}$unit'),
        trailing: Text(AniHowMoney.peso(row.revenue), style: theme.textTheme.titleSmall),
      ),
    );
  }
}
