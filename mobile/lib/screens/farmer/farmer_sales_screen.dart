import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/hint_card.dart';

typedef SalesRangePicker = Future<DateTimeRange?> Function(BuildContext context);

const Color _piePeach = Color(0xFFE8A87C);
const Color _pieOthers = Color(0xFFC9C4B8);

class FarmerSalesScreen extends StatefulWidget {
  const FarmerSalesScreen({super.key, this.preview, this.chooseCustomRange});

  final FarmerAnalytics? preview;
  final SalesRangePicker? chooseCustomRange;

  @override
  State<FarmerSalesScreen> createState() => _FarmerSalesScreenState();
}

class _FarmerSalesScreenState extends State<FarmerSalesScreen> {
  String _range = 'month';
  String? _from;
  String? _to;
  int? _year;
  String _category = 'all';
  String? _selectedPeriod;
  bool _showAllCrops = false;
  late Future<FarmerAnalytics> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  bool _valueAdded(BuildContext context) {
    try {
      return context.read<AuthController>().user?.farmFeatures.valueAdded ??
          false;
    } on ProviderNotFoundException {
      return false;
    }
  }

  Future<FarmerAnalytics> _load() {
    final preview = widget.preview;
    if (preview != null) {
      return Future.value(preview);
    }
    final category = _valueAdded(context) ? _category : 'all';
    return context.read<AuthController>().api.farmerAnalytics(
      range: _range,
      from: _from,
      to: _to,
      year: _year,
      category: category,
    );
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _commit({
    required String range,
    String? from,
    String? to,
    int? year,
    String? category,
  }) async {
    final previousRange = _range;
    final previousFrom = _from;
    final previousTo = _to;
    final previousYear = _year;
    final previousCategory = _category;
    final previousFuture = _future;
    setState(() {
      _range = range;
      _from = from;
      _to = to;
      _year = year;
      if (category != null) {
        _category = category;
      }
      _selectedPeriod = null;
      _showAllCrops = false;
      _future = _load();
    });
    try {
      await _future;
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _range = previousRange;
        _from = previousFrom;
        _to = previousTo;
        _year = previousYear;
        _category = previousCategory;
        _future = previousFuture;
      });
      _snack(error.message);
    }
  }

  void _snack(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _chooseCustom() async {
    final picker = widget.chooseCustomRange;
    final picked = picker != null
        ? await picker(context)
        : await showDateRangePicker(
            context: context,
            firstDate: DateTime(2020),
            lastDate: DateTime.now(),
          );
    if (picked == null || !mounted) {
      return;
    }
    final start = DateTime(picked.start.year, picked.start.month, picked.start.day);
    final end = DateTime(picked.end.year, picked.end.month, picked.end.day);
    if (end.difference(start).inDays + 1 > 366) {
      _snack(AppStrings.of(context).pickAtMost366);
      return;
    }
    await _commit(range: 'custom', from: _iso(start), to: _iso(end));
  }

  Future<void> _chooseYear(FarmerAnalytics data) async {
    final years = data.range?.availableYears.isNotEmpty == true
        ? data.range!.availableYears
        : [DateTime.now().year];
    final picked = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        final s = AppStrings.of(context);
        return SafeArea(
          child: ListView(
            shrinkWrap: true,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
                child: Text(s.chooseYear, style: Theme.of(context).textTheme.titleMedium),
              ),
              for (final year in years)
                ListTile(
                  key: Key('sales-year-$year'),
                  title: Text('$year'),
                  onTap: () => Navigator.pop(context, year),
                ),
            ],
          ),
        );
      },
    );
    if (picked == null || !mounted) {
      return;
    }
    await _commit(range: 'yearly', year: picked);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final valueAdded = _valueAdded(context);

    return AsyncView<FarmerAnalytics>(
      future: _future,
      onRetry: _reload,
      builder: (context, data) {
        return DefaultTabController(
          length: 2,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: _RangeChips(
                  range: _range,
                  year: _year,
                  onWeek: () => _commit(range: 'week'),
                  onMonth: () => _commit(range: 'month'),
                  onYear: () => _commit(range: 'year'),
                  onYearly: () => _chooseYear(data),
                  onCustom: _chooseCustom,
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: _WindowLine(
                  text: _windowText(s, data),
                ),
              ),
              if (valueAdded)
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                  child: _CategoryToggle(
                    category: _category,
                    onChanged: (category) => _commit(
                      range: _range,
                      from: _from,
                      to: _to,
                      year: _year,
                      category: category,
                    ),
                  ),
                ),
              const SizedBox(height: 8),
              Material(
                color: AniHowColors.brand,
                child: onBrandTabBar(
                  tabs: [
                    Tab(text: s.sales),
                    Tab(text: s.harvestTab),
                  ],
                ),
              ),
              Expanded(
                child: TabBarView(
                  children: [
                    _SalesTab(
                      data: data,
                      selectedPeriod: _selectedPeriod,
                      onSelectPeriod: (key) => setState(() => _selectedPeriod = key),
                      onRefresh: _reload,
                    ),
                    _HarvestTab(
                      data: data,
                      showAll: _showAllCrops,
                      onShowAll: () => setState(() => _showAllCrops = true),
                      onRefresh: _reload,
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  String _windowText(AppStrings s, FarmerAnalytics data) {
    final start = data.range?.from ?? data.windowStart;
    final end = data.range?.to ?? data.windowEnd;
    if (start == null || end == null || start.isEmpty || end.isEmpty) {
      return '';
    }
    return s.salesRangeLine(start, end, data.range?.grouping);
  }
}

class _RangeChips extends StatelessWidget {
  const _RangeChips({
    required this.range,
    required this.year,
    required this.onWeek,
    required this.onMonth,
    required this.onYear,
    required this.onYearly,
    required this.onCustom,
  });

  final String range;
  final int? year;
  final VoidCallback onWeek;
  final VoidCallback onMonth;
  final VoidCallback onYear;
  final VoidCallback onYearly;
  final VoidCallback onCustom;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final yearlyLabel = range == 'yearly' && year != null
        ? s.yearlyChip(year!)
        : s.yearly;

    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _PeriodChip(
            key: const Key('sales-period-week'),
            label: s.thisWeek,
            selected: range == 'week',
            onTap: onWeek,
          ),
          const SizedBox(width: 8),
          _PeriodChip(
            key: const Key('sales-period-month'),
            label: s.thisMonth,
            selected: range == 'month',
            onTap: onMonth,
          ),
          const SizedBox(width: 8),
          _PeriodChip(
            key: const Key('sales-range-year'),
            label: s.thisYear,
            selected: range == 'year',
            onTap: onYear,
          ),
          const SizedBox(width: 8),
          _PeriodChip(
            key: const Key('sales-range-yearly'),
            label: yearlyLabel,
            selected: range == 'yearly',
            onTap: onYearly,
          ),
          const SizedBox(width: 8),
          _PeriodChip(
            key: const Key('sales-range-custom'),
            label: s.customRange,
            selected: range == 'custom',
            onTap: onCustom,
          ),
        ],
      ),
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
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Center(
              child: Text(
                label,
                style: theme.textTheme.titleSmall?.copyWith(
                  color: selected
                      ? AniHowColors.brand
                      : theme.colorScheme.onSurface,
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _WindowLine extends StatelessWidget {
  const _WindowLine({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      key: const Key('sales-window'),
      style: Theme.of(context).textTheme.bodyMedium,
    );
  }
}

class _CategoryToggle extends StatelessWidget {
  const _CategoryToggle({required this.category, required this.onChanged});

  final String category;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
      children: [
        _PeriodChip(
          key: const Key('sales-category-all'),
          label: s.salesCategoryAll,
          selected: category == 'all',
          onTap: () => onChanged('all'),
        ),
        const SizedBox(width: 8),
        _PeriodChip(
          key: const Key('sales-category-fresh'),
          label: s.salesCategoryFresh,
          selected: category == 'fresh',
          onTap: () => onChanged('fresh'),
        ),
        const SizedBox(width: 8),
        _PeriodChip(
          key: const Key('sales-category-value-added'),
          label: s.salesCategoryValueAdded,
          selected: category == 'value_added',
          onTap: () => onChanged('value_added'),
        ),
      ],
      ),
    );
  }
}

class _SalesTab extends StatelessWidget {
  const _SalesTab({
    required this.data,
    required this.selectedPeriod,
    required this.onSelectPeriod,
    required this.onRefresh,
  });

  final FarmerAnalytics data;
  final String? selectedPeriod;
  final ValueChanged<String> onSelectPeriod;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    if (!data.hasCompletedSales) {
      return RefreshIndicator(
        onRefresh: onRefresh,
        child: ListView(
          key: const Key('sales-empty'),
          physics: const AlwaysScrollableScrollPhysics(),
          padding: AniHowSpace.screenPadding,
          children: [
            AniHowHintCard(icon: Icons.insights_outlined, title: s.mySalesEmpty),
          ],
        ),
      );
    }

    final report = data.sales;
    final totals = data.displayTotals;
    final periods = data.displayPeriods;
    final grouping = data.range?.grouping ?? 'day';
    FarmerSalesPeriod? selected;
    for (final point in periods) {
      if (point.key == selectedPeriod) {
        selected = point;
      }
    }

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: AniHowSpace.screenPadding,
        children: [
          _MoneyTiles(
            tiles: [
              (s.totalSales, AniHowMoney.peso(totals.sales)),
              (s.completedOrders, '${totals.orders}'),
              (s.averageOrder, AniHowMoney.peso(totals.averageOrder)),
              (s.averageTawad, AniHowMoney.peso(totals.averageTawad)),
            ],
          ),
          if (report != null && _hasPie(report.pie)) ...[
            const SizedBox(height: AniHowSpace.section),
            _DonutCard(pie: report.pie, total: totals.sales),
          ],
          const SizedBox(height: AniHowSpace.section),
          _SalesBars(
            periods: periods,
            grouping: grouping,
            selected: selected,
            onSelect: onSelectPeriod,
          ),
          if (data.range?.key == 'yearly' || report?.yearTotal != null) ...[
            const SizedBox(height: AniHowSpace.section),
            _YearCard(total: report?.yearTotal),
          ],
          const SizedBox(height: AniHowSpace.section),
          Text(s.topCrops, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AniHowSpace.cardGap),
          for (final crop in data.displayTopCrops) ...[
            _TopCropRow(crop: crop),
            const SizedBox(height: AniHowSpace.cardGap),
          ],
          _PaidCard(
            payment: report?.paymentSplit,
            source: data.displaySource,
          ),
        ],
      ),
    );
  }
}

class _HarvestTab extends StatelessWidget {
  const _HarvestTab({
    required this.data,
    required this.showAll,
    required this.onShowAll,
    required this.onRefresh,
  });

  final FarmerAnalytics data;
  final bool showAll;
  final VoidCallback onShowAll;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final harvest = data.harvest;
    final empty = harvest == null || (harvest.records == 0 && harvest.crops.isEmpty);

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowHintCard(
            key: const Key('sales-harvest-note'),
            icon: Icons.agriculture_outlined,
            title: s.harvestNote,
            body: harvest != null && harvest.estimatedRecords > 0
                ? s.estimatedHarvests(harvest.estimatedRecords)
                : null,
          ),
          const SizedBox(height: AniHowSpace.section),
          if (empty)
            Text(s.noHarvests, style: Theme.of(context).textTheme.bodyLarge)
          else ...[
            _MoneyTiles(
              tiles: [
                (s.harvestsRecorded, '${harvest.records}'),
                (s.cropsHarvested, '${harvest.crops.length}'),
                (s.expectedIncome, AniHowMoney.peso(harvest.income.potential)),
                (s.actualIncomeSoFar, AniHowMoney.peso(harvest.income.actual)),
              ],
            ),
            const SizedBox(height: AniHowSpace.section),
            ..._cropCards(s, harvest, showAll, onShowAll),
            _IncomeBars(income: harvest.income),
            const SizedBox(height: AniHowSpace.section),
            _ProfitCard(harvest: harvest),
          ],
        ],
      ),
    );
  }

  List<Widget> _cropCards(
    AppStrings s,
    FarmerHarvestReport harvest,
    bool showAll,
    VoidCallback onShowAll,
  ) {
    final crops = [...harvest.crops]
      ..sort((a, b) => b.harvested.compareTo(a.harvested));
    final visible = showAll ? crops : crops.take(6).toList();
    return [
      for (final crop in visible) ...[
        _HarvestCropCard(crop: crop),
        const SizedBox(height: AniHowSpace.cardGap),
      ],
      if (!showAll && crops.length > 6)
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton(onPressed: onShowAll, child: Text(s.showAllCrops)),
        ),
      const SizedBox(height: AniHowSpace.section),
    ];
  }
}

class _MoneyTiles extends StatelessWidget {
  const _MoneyTiles({required this.tiles});

  final List<(String, String)> tiles;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        for (var i = 0; i < tiles.length; i += 2) ...[
          if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
          Row(
            children: [
              Expanded(child: _StatTile(label: tiles[i].$1, value: tiles[i].$2)),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: i + 1 < tiles.length
                    ? _StatTile(label: tiles[i + 1].$1, value: tiles[i + 1].$2)
                    : const SizedBox.shrink(),
              ),
            ],
          ),
        ],
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

class _DonutCard extends StatelessWidget {
  const _DonutCard({required this.pie, required this.total});

  final FarmerSalesPie pie;
  final double total;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final parts = _pieParts(pie, s.othersSlice);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          children: [
            SizedBox(
              height: 160,
              width: 160,
              child: CustomPaint(
                painter: _DonutPainter(parts.map((part) => (part.amount, part.color)).toList()),
                child: Center(
                  child: Text(
                    AniHowMoney.peso(total),
                    style: Theme.of(context).textTheme.titleSmall,
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            for (final part in parts)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Row(
                  children: [
                    Container(
                      width: 12,
                      height: 12,
                      decoration: BoxDecoration(
                        color: part.color,
                        borderRadius: BorderRadius.circular(3),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(child: Text(part.label)),
                    Text(AniHowMoney.peso(part.amount)),
                    const SizedBox(width: 8),
                    Text(_percent(part.percent)),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _PiePart {
  const _PiePart({
    required this.label,
    required this.amount,
    required this.percent,
    required this.color,
  });

  final String label;
  final double amount;
  final double percent;
  final Color color;
}

bool _hasPie(FarmerSalesPie pie) => pie.slices.isNotEmpty || pie.others != null;

List<_PiePart> _pieParts(FarmerSalesPie pie, String othersLabel) {
  const colors = [
    AniHowColors.brand,
    AniHowColors.sage,
    AniHowColors.eggplant,
    AniHowColors.root,
    _piePeach,
  ];
  final parts = <_PiePart>[];
  for (var i = 0; i < pie.slices.length; i++) {
    final slice = pie.slices[i];
    parts.add(
      _PiePart(
        label: slice.crop,
        amount: slice.sales,
        percent: slice.percent,
        color: colors[i % colors.length],
      ),
    );
  }
  final others = pie.others;
  if (others != null) {
    parts.add(
      _PiePart(
        label: othersLabel,
        amount: others.sales,
        percent: others.percent,
        color: _pieOthers,
      ),
    );
  }
  return parts.where((part) => part.amount > 0 || part.percent > 0).toList();
}

class _DonutPainter extends CustomPainter {
  const _DonutPainter(this.parts);

  final List<(double, Color)> parts;

  @override
  void paint(Canvas canvas, Size size) {
    final total = parts.fold<double>(0, (sum, part) => sum + part.$1);
    if (total <= 0) {
      return;
    }
    final stroke = size.shortestSide * 0.16;
    final rect = Rect.fromCircle(
      center: size.center(Offset.zero),
      radius: size.shortestSide / 2 - stroke / 2,
    );
    var start = -math.pi / 2;
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke;
    for (final part in parts) {
      final sweep = part.$1 / total * math.pi * 2;
      paint.color = part.$2;
      canvas.drawArc(rect, start, sweep, false, paint);
      start += sweep;
    }
  }

  @override
  bool shouldRepaint(covariant _DonutPainter oldDelegate) => oldDelegate.parts != parts;
}

class _SalesBars extends StatelessWidget {
  const _SalesBars({
    required this.periods,
    required this.grouping,
    required this.selected,
    required this.onSelect,
  });

  final List<FarmerSalesPeriod> periods;
  final String grouping;
  final FarmerSalesPeriod? selected;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final maxSales = periods.fold<double>(
      0,
      (max, point) => point.sales > max ? point.sales : max,
    );
    final caption = grouping == 'day' ? _monthCaption(s, periods) : null;

    return Card(
      key: const Key('sales-chart'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.salesOverTime, style: Theme.of(context).textTheme.titleSmall),
            if (selected != null) ...[
              const SizedBox(height: 6),
              Text(
                _periodDetail(s, selected!),
                key: const Key('sales-bar-detail'),
              ),
            ],
            const SizedBox(height: 8),
            SizedBox(
              height: 140,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  Widget column(FarmerSalesPeriod point, double width) {
                    return _BarColumn(
                      point: point,
                      label: _barLabel(s, point),
                      barWidth: math.min(22.0, width * 0.6),
                      heightFactor: maxSales <= 0 || point.sales <= 0
                          ? 0
                          : (point.sales / maxSales).clamp(0.06, 1).toDouble(),
                      selected: point.key == selected?.key,
                      onTap: () => onSelect(point.key),
                    );
                  }

                  final minWidth = grouping == 'week' ? 48.0 : _minBarColumnWidth;
                  final fitWidth = periods.isEmpty
                      ? constraints.maxWidth
                      : constraints.maxWidth / periods.length;
                  if (fitWidth >= minWidth) {
                    return Row(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        for (final point in periods)
                          Expanded(child: column(point, fitWidth)),
                      ],
                    );
                  }

                  // Too many bars to fit: scroll, starting at the newest.
                  return ListView.builder(
                    scrollDirection: Axis.horizontal,
                    reverse: true,
                    itemCount: periods.length,
                    itemExtent: minWidth,
                    itemBuilder: (context, index) => column(
                      periods[periods.length - 1 - index],
                      minWidth,
                    ),
                  );
                },
              ),
            ),
            if (caption != null) ...[
              const SizedBox(height: 6),
              Text(caption, style: Theme.of(context).textTheme.bodySmall),
            ],
          ],
        ),
      ),
    );
  }

  String _barLabel(AppStrings s, FarmerSalesPeriod point) {
    final start = _calendar(point.start);
    if (start == null) {
      return point.key;
    }
    return switch (grouping) {
      'month' => s.monthName(start),
      'week' => s.shortDate(start),
      _ => '${start.day}',
    };
  }

  String _periodDetail(AppStrings s, FarmerSalesPeriod point) {
    final start = _calendar(point.start);
    final end = _calendar(point.end);
    final when = switch (grouping) {
      'week' when start != null && end != null =>
        '${s.shortDate(start)}–${s.shortDate(end)}',
      'month' when start != null => s.monthName(start),
      _ when start != null => s.shortDate(start),
      _ => point.key,
    };
    return '$when · ${AniHowMoney.peso(point.sales)} · ${s.ordersCount(point.orders)}';
  }
}

const double _minBarColumnWidth = 24;

class _BarColumn extends StatelessWidget {
  const _BarColumn({
    required this.point,
    required this.label,
    required this.barWidth,
    required this.heightFactor,
    required this.selected,
    required this.onTap,
  });

  final FarmerSalesPeriod point;
  final String label;
  final double barWidth;
  final double heightFactor;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = _chartColor(context, selected: selected);
    return InkWell(
      onTap: onTap,
      child: Column(
        children: [
          Expanded(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: point.future
                  ? CustomPaint(
                      key: Key('sales-future-${point.key}'),
                      painter: _DashedOutlinePainter(Theme.of(context).colorScheme.outline),
                      child: SizedBox(width: barWidth, height: 72),
                    )
                  : heightFactor <= 0
                      ? SizedBox(
                          width: barWidth,
                          height: 2,
                          child: ColoredBox(color: color.withValues(alpha: 0.35)),
                        )
                      : FractionallySizedBox(
                          heightFactor: heightFactor,
                          child: SizedBox(
                            width: barWidth,
                            child: DecoratedBox(
                              decoration: BoxDecoration(
                                color: color,
                                borderRadius: const BorderRadius.all(Radius.circular(6)),
                              ),
                            ),
                          ),
                        ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall,
          ),
        ],
      ),
    );
  }
}

class _DashedOutlinePainter extends CustomPainter {
  const _DashedOutlinePainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(6)),
      );
    final paint = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5
      ..color = color;
    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = math.min(distance + 4, metric.length);
        canvas.drawPath(metric.extractPath(distance, next), paint);
        distance = next + 3;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashedOutlinePainter oldDelegate) => oldDelegate.color != color;
}

/// Bar color that stays readable on both the light and the dark card.
Color _chartColor(BuildContext context, {bool selected = false}) {
  final dark = Theme.of(context).brightness == Brightness.dark;
  if (dark) {
    return selected ? const Color(0xFFA8D5BA) : AniHowColors.sage;
  }
  return selected ? AniHowColors.brand : AniHowColors.sage;
}

class _YearCard extends StatelessWidget {
  const _YearCard({required this.total});

  final FarmerYearTotal? total;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final year = total;
    if (year == null) {
      return const SizedBox.shrink();
    }
    final orders = year.orders;
    final average = orders == 0 ? 0 : year.sales / orders;
    final best = year.bestMonth;
    final bestDate = best == null ? null : _monthFromKey(best.key);
    final bestLabel = bestDate == null ? '—' : s.monthName(bestDate);

    return Card(
      color: AniHowColors.brand,
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: DefaultTextStyle(
          style: Theme.of(context).textTheme.bodyMedium!.copyWith(color: Colors.white),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(s.yearTotalTitle, style: const TextStyle(fontWeight: FontWeight.w700)),
              const SizedBox(height: 8),
              Text('${s.totalSales}: ${AniHowMoney.peso(year.sales)}'),
              Text('${s.completedOrders}: $orders'),
              Text('${s.bestMonth}: $bestLabel'),
              Text('${s.averageOrder}: ${AniHowMoney.peso(average)}'),
            ],
          ),
        ),
      ),
    );
  }
}

class _TopCropRow extends StatelessWidget {
  const _TopCropRow({required this.crop});

  final FarmerTopCrop crop;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Card(
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: AniHowSpace.cardPad),
        title: Text(crop.crop),
        subtitle: Text(s.quantitySold(_qty(crop.quantity), crop.unit)),
        trailing: Text(AniHowMoney.peso(crop.sales)),
      ),
    );
  }
}

class _PaidCard extends StatelessWidget {
  const _PaidCard({required this.payment, required this.source});

  final FarmerPaymentSplit? payment;
  final FarmerSourceSplit source;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final online = payment?.online ?? const FarmerSalesBucket(orders: 0, sales: 0);
    final cash = payment?.cash ?? const FarmerSalesBucket(orders: 0, sales: 0);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.howBuyersPaid, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            _SplitBar(
              leftLabel: s.onlinePay,
              rightLabel: s.cashPay,
              left: online,
              right: cash,
              leftColor: _onlineColor,
              rightColor: _cashColor,
            ),
            const SizedBox(height: 4),
            Text(s.cashIncludesWalkIn, style: Theme.of(context).textTheme.bodySmall),
            const SizedBox(height: 12),
            _SplitBar(
              leftLabel: s.appSales,
              rightLabel: s.walkInSales,
              left: source.app,
              right: source.walkIn,
              leftColor: _appColor,
              rightColor: _walkInColor,
            ),
          ],
        ),
      ),
    );
  }
}

class _SplitBar extends StatelessWidget {
  const _SplitBar({
    required this.leftLabel,
    required this.rightLabel,
    required this.left,
    required this.right,
    required this.leftColor,
    required this.rightColor,
  });

  final String leftLabel;
  final String rightLabel;
  final FarmerSalesBucket left;
  final FarmerSalesBucket right;
  final Color leftColor;
  final Color rightColor;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Segments(parts: [(left.sales, leftColor), (right.sales, rightColor)]),
        const SizedBox(height: 6),
        _LegendLine(color: leftColor, text: leftLabel),
        _amountLine(context, s, left),
        _LegendLine(color: rightColor, text: rightLabel),
        _amountLine(context, s, right),
      ],
    );
  }
}

Widget _amountLine(BuildContext context, AppStrings s, FarmerSalesBucket bucket) {
  return Padding(
    padding: const EdgeInsets.only(left: 18),
    child: Text(
      '${AniHowMoney.peso(bucket.sales)} · ${s.ordersCount(bucket.orders)}',
      style: Theme.of(context).textTheme.bodySmall,
    ),
  );
}

/// One horizontal bar split into colored parts by size. Zero parts are left
/// out; an all-zero bar shows an empty track.
class _Segments extends StatelessWidget {
  const _Segments({required this.parts});

  final List<(double, Color)> parts;

  @override
  Widget build(BuildContext context) {
    final total = parts.fold<double>(0, (sum, part) => sum + (part.$1 > 0 ? part.$1 : 0));
    return ClipRRect(
      borderRadius: BorderRadius.circular(6),
      child: SizedBox(
        height: 12,
        width: double.infinity,
        child: total <= 0
            ? ColoredBox(color: Theme.of(context).colorScheme.surfaceContainerHighest)
            : Row(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (final part in parts)
                    if (part.$1 > 0)
                      Expanded(
                        flex: math.max(1, (part.$1 / total * 1000).round()),
                        child: ColoredBox(color: part.$2),
                      ),
                ],
              ),
      ),
    );
  }
}

class _LegendLine extends StatelessWidget {
  const _LegendLine({required this.color, required this.text});

  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 3),
      child: Row(
        children: [
          SizedBox(
            width: 10,
            height: 10,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: color,
                borderRadius: const BorderRadius.all(Radius.circular(3)),
              ),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(text, style: Theme.of(context).textTheme.bodySmall)),
        ],
      ),
    );
  }
}

const Color _onlineColor = Color(0xFF378ADD);
const Color _cashColor = Color(0xFFE2A24A);
const Color _appColor = AniHowColors.sage;
const Color _walkInColor = Color(0xFFA8D5BA);
const Color _goodColor = AniHowColors.sage;
const Color _rejectedColor = Color(0xFFE8A87C);
const Color _soldColor = Color(0xFF2E8B57);
const Color _waitingColor = Color(0xFF378ADD);
const Color _removedColor = AniHowColors.fruit;
const Color _leftColor = Color(0xFFA8D5BA);

class _HarvestCropCard extends StatelessWidget {
  const _HarvestCropCard({required this.crop});

  final FarmerHarvestCrop crop;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final unit = crop.unit.isEmpty ? '' : ' ${crop.unit}';
    final rejected = _reasonLine(s, crop.rejectedByReason, unit);
    final removed = _reasonLine(s, crop.removedByReason, unit);
    final nothingRemoved = crop.removedByReason.values.every((value) => value == 0);

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              crop.unit.isEmpty ? crop.crop : '${crop.crop} · ${crop.unit}',
              style: Theme.of(context).textTheme.titleSmall,
            ),
            const SizedBox(height: 8),
            Text('${s.harvestedLabel} ${_qty(crop.harvested)}$unit'),
            _Meter(value: crop.harvested, max: math.max(crop.harvested, crop.sold), color: _leftColor),
            Text('${s.soldLabel} ${_qty(crop.sold)}$unit'),
            _Meter(value: crop.sold, max: math.max(crop.harvested, crop.sold), color: _soldColor),
            const SizedBox(height: 8),
            Text('${s.goodLabel} / ${s.rejectedLabel}'),
            const SizedBox(height: 6),
            _Segments(
              parts: [
                (crop.good, _goodColor),
                (crop.rejected, _rejectedColor),
              ],
            ),
            _LegendLine(color: _goodColor, text: '${s.goodLabel} ${_qty(crop.good)}$unit'),
            _LegendLine(color: _rejectedColor, text: '${s.rejectedLabel} ${_qty(crop.rejected)}$unit'),
            if (rejected != null) ...[
              const SizedBox(height: 4),
              Text(s.whyRejected(rejected), style: Theme.of(context).textTheme.bodySmall),
            ],
            const SizedBox(height: 12),
            Text(s.whereGoodWent),
            const SizedBox(height: 6),
            _Segments(
              parts: [
                (crop.sold, _soldColor),
                (crop.waiting, _waitingColor),
                (crop.removed, _removedColor),
                (crop.remaining, _leftColor),
              ],
            ),
            _LegendLine(color: _soldColor, text: '${s.soldLabel} ${_qty(crop.sold)}$unit'),
            _LegendLine(color: _waitingColor, text: '${s.waitingForPickup} ${_qty(crop.waiting)}$unit'),
            _LegendLine(color: _removedColor, text: '${s.removedLabel} ${_qty(crop.removed)}$unit'),
            _LegendLine(color: _leftColor, text: '${s.leftLabel} ${_qty(crop.remaining)}$unit'),
            const SizedBox(height: 4),
            Text(
              nothingRemoved ? s.nothingRemoved : (removed ?? s.nothingRemoved),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }
}

class _Meter extends StatelessWidget {
  const _Meter({required this.value, required this.max, required this.color});

  final double value;
  final double max;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final factor = max <= 0 ? 0.0 : (value / max).clamp(0.0, 1.0);
    return Padding(
      padding: const EdgeInsets.only(top: 4, bottom: 6),
      child: SizedBox(
        height: 8,
        child: FractionallySizedBox(
          alignment: Alignment.centerLeft,
          widthFactor: factor == 0 ? 0.02 : factor,
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(4),
            ),
          ),
        ),
      ),
    );
  }
}

class _IncomeBars extends StatelessWidget {
  const _IncomeBars({required this.income});

  final FarmerHarvestIncome income;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final max = math.max(income.potential, income.actual);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.expectedVsActual, style: Theme.of(context).textTheme.titleSmall),
            const SizedBox(height: 8),
            SizedBox(
              height: 120,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  _VerticalMoney(
                    label: s.expectedIncome,
                    amount: income.potential,
                    factor: max <= 0 ? 0.02 : (income.potential / max).clamp(0.02, 1).toDouble(),
                    color: _leftColor,
                  ),
                  const SizedBox(width: 16),
                  _VerticalMoney(
                    label: s.actualIncomeSoFar,
                    amount: income.actual,
                    factor: max <= 0 ? 0.02 : (income.actual / max).clamp(0.02, 1).toDouble(),
                    color: _soldColor,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 8),
            Text(s.incomeCaption, style: Theme.of(context).textTheme.bodySmall),
          ],
        ),
      ),
    );
  }
}

class _VerticalMoney extends StatelessWidget {
  const _VerticalMoney({
    required this.label,
    required this.amount,
    required this.factor,
    required this.color,
  });

  final String label;
  final double amount;
  final double factor;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          Text(AniHowMoney.peso(amount), style: Theme.of(context).textTheme.labelSmall),
          const SizedBox(height: 4),
          Expanded(
            child: Align(
              alignment: Alignment.bottomCenter,
              child: FractionallySizedBox(
                heightFactor: factor,
                widthFactor: 0.5,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: color,
                    borderRadius: BorderRadius.circular(6),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall,
          ),
        ],
      ),
    );
  }
}

class _ProfitCard extends StatelessWidget {
  const _ProfitCard({required this.harvest});

  final FarmerHarvestReport harvest;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final cost = harvest.cost;
    if (cost.recordsWithCost == 0) {
      return AniHowHintCard(
        icon: Icons.payments_outlined,
        title: s.noCostRecorded,
        body: s.noCostHint,
      );
    }
    final total = cost.recordsWithCost + cost.recordsWithoutCost;
    return Card(
      key: const Key('sales-profit'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _moneyLine(context, s.costLabel, cost.costTotal),
            _moneyLine(context, s.expectedProfit, cost.potentialProfit),
            _moneyLine(context, s.actualProfitSoFar, cost.actualProfit),
            const SizedBox(height: 6),
            Text(
              s.costCoverage(cost.recordsWithCost, total == 0 ? harvest.records : total),
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ],
        ),
      ),
    );
  }

  Widget _moneyLine(BuildContext context, String label, double? amount) {
    final negative = amount != null && amount < 0;
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Text(
        '$label: ${_optionalPeso(amount)}',
        style: TextStyle(color: negative ? AniHowColors.cancelledRed : null),
      ),
    );
  }
}

String? _monthCaption(AppStrings s, List<FarmerSalesPeriod> periods) {
  final labels = <String>[];
  for (final point in periods) {
    final date = _calendar(point.start);
    if (date == null) {
      continue;
    }
    final label = '${s.monthName(date)} ${date.year}';
    if (!labels.contains(label)) {
      labels.add(label);
    }
  }
  if (labels.isEmpty) {
    return null;
  }
  return labels.join(' · ');
}

String? _reasonLine(AppStrings s, Map<String, double> reasons, String unit) {
  final parts = <String>[];
  for (final entry in reasons.entries) {
    if (entry.value <= 0) {
      continue;
    }
    parts.add('${s.rejectionReasonLabel(entry.key)} ${_qty(entry.value)}$unit');
  }
  if (parts.isEmpty) {
    return null;
  }
  return parts.join(', ');
}

String _optionalPeso(double? amount) => amount == null ? '—' : AniHowMoney.peso(amount);

String _percent(double value) {
  if ((value - value.roundToDouble()).abs() < 0.05) {
    return '${value.round()}%';
  }
  return '${value.toStringAsFixed(1)}%';
}

String _qty(double value) {
  if (value == value.roundToDouble()) {
    return value.round().toString();
  }
  var text = value.toStringAsFixed(2);
  if (text.contains('.')) {
    text = text.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
  }
  return text;
}

String _iso(DateTime date) {
  final year = date.year.toString().padLeft(4, '0');
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '$year-$month-$day';
}

DateTime? _calendar(String iso) {
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(iso);
  if (match == null) {
    return null;
  }
  return DateTime(
    int.parse(match.group(1)!),
    int.parse(match.group(2)!),
    int.parse(match.group(3)!),
  );
}

DateTime? _monthFromKey(String key) {
  final match = RegExp(r'^(\d{4})-(\d{2})').firstMatch(key);
  if (match == null) {
    return null;
  }
  return DateTime(int.parse(match.group(1)!), int.parse(match.group(2)!), 1);
}
