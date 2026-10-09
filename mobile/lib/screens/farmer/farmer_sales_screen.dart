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

typedef SalesRangePicker = Future<DateTimeRange?> Function(BuildContext context);

const Color _piePeach = Color(0xFFE8A87C);
const Color _pieOthers = Color(0xFFC9C4B8);
const double _cardGap = 14;

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
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _openFilter(FarmerAnalytics data) async {
    final years = data.range?.availableYears.isNotEmpty == true
        ? data.range!.availableYears
        : [DateTime.now().year];
    final draft = await showModalBottomSheet<_FilterDraft>(
      context: context,
      showDragHandle: true,
      isScrollControlled: true,
      builder: (context) => _FilterSheet(
        initial: _FilterDraft(
          range: _range,
          from: _from,
          to: _to,
          year: _year,
          category: _category,
        ),
        years: years,
        valueAdded: _valueAdded(context),
        chooseCustomRange: widget.chooseCustomRange,
      ),
    );
    if (draft == null || !mounted) {
      return;
    }
    await _commit(
      range: draft.range,
      from: draft.range == 'custom' ? draft.from : null,
      to: draft.range == 'custom' ? draft.to : null,
      year: draft.range == 'yearly' ? draft.year : null,
      category: draft.category,
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final valueAdded = _valueAdded(context);

    return DefaultTabController(
      length: 2,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
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
            child: AsyncView<FarmerAnalytics>(
              future: _future,
              onRetry: _reload,
              builder: (context, data) {
                _FilterButton filter() => _FilterButton(
                  name: _rangeName(s),
                  dates: _windowText(s, data),
                  category: valueAdded ? _categoryLabel(s) : null,
                  onTap: () => _openFilter(data),
                );
                return TabBarView(
                  children: [
                    _SalesTab(
                      data: data,
                      filter: filter(),
                      yearly: _range == 'yearly',
                      year: _year,
                      selectedPeriod: _selectedPeriod,
                      onSelectPeriod: (key) =>
                          setState(() => _selectedPeriod = key),
                      onRefresh: _reload,
                    ),
                    _HarvestTab(
                      data: data,
                      filter: filter(),
                      showAll: _showAllCrops,
                      onShowAll: () => setState(() => _showAllCrops = true),
                      onRefresh: _reload,
                    ),
                  ],
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  String _rangeName(AppStrings s) {
    return switch (_range) {
      'week' => s.thisWeek,
      'year' => s.thisYear,
      'yearly' => '${_year ?? DateTime.now().year}',
      'custom' => s.customRange,
      _ => s.thisMonth,
    };
  }

  String _categoryLabel(AppStrings s) {
    return switch (_category) {
      'fresh' => s.salesCategoryFresh,
      'value_added' => s.salesCategoryValueAdded,
      _ => s.salesCategoryAll,
    };
  }

  String _windowText(AppStrings s, FarmerAnalytics data) {
    final start = data.range?.from ?? data.windowStart;
    final end = data.range?.to ?? data.windowEnd;
    if (start == null || end == null || start.isEmpty || end.isEmpty) {
      return '';
    }
    return s.compactRange(start, end);
  }
}

class _FilterDraft {
  _FilterDraft({
    required this.range,
    required this.category,
    this.from,
    this.to,
    this.year,
  });

  final String range;
  final String category;
  final String? from;
  final String? to;
  final int? year;

  _FilterDraft copy({
    String? range,
    String? category,
    String? from,
    String? to,
    int? year,
    bool clearDates = false,
    bool clearYear = false,
  }) {
    return _FilterDraft(
      range: range ?? this.range,
      category: category ?? this.category,
      from: clearDates ? null : (from ?? this.from),
      to: clearDates ? null : (to ?? this.to),
      year: clearYear ? null : (year ?? this.year),
    );
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.initial,
    required this.years,
    required this.valueAdded,
    required this.chooseCustomRange,
  });

  final _FilterDraft initial;
  final List<int> years;
  final bool valueAdded;
  final SalesRangePicker? chooseCustomRange;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late _FilterDraft _draft;
  String? _customError;

  @override
  void initState() {
    super.initState();
    _draft = widget.initial;
  }

  Future<void> _pickCustom() async {
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
      setState(() => _customError = AppStrings.of(context).pickAtMost366);
      return;
    }
    setState(() {
      _customError = null;
      _draft = _draft.copy(
        range: 'custom',
        from: _iso(start),
        to: _iso(end),
        clearYear: true,
      );
    });
  }

  void _select(String range) {
    if (range == 'custom') {
      _pickCustom();
      return;
    }
    if (range == 'yearly') {
      setState(() {
        _customError = null;
        _draft = _draft.copy(
          range: 'yearly',
          year: _draft.year ?? widget.years.first,
          clearDates: true,
        );
      });
      return;
    }
    setState(() {
      _customError = null;
      _draft = _draft.copy(range: range, clearDates: true, clearYear: true);
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final caption = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    return SafeArea(
      child: ListView(
        shrinkWrap: true,
        padding: const EdgeInsets.fromLTRB(8, 0, 8, 16),
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
            child: Text(s.showSheet, style: theme.textTheme.titleMedium),
          ),
          _RangeRow(
            rowKey: const Key('sales-range-week'),
            title: s.thisWeek,
            subtitle: s.compactRange(_iso(_weekStart()), _iso(_today())),
            selected: _draft.range == 'week',
            onTap: () => _select('week'),
          ),
          _RangeRow(
            rowKey: const Key('sales-range-month'),
            title: s.thisMonth,
            subtitle: s.compactRange(_iso(_monthStart()), _iso(_today())),
            selected: _draft.range == 'month',
            onTap: () => _select('month'),
          ),
          _RangeRow(
            rowKey: const Key('sales-range-year'),
            title: s.thisYear,
            subtitle: s.compactRange(_iso(_yearStart()), _iso(_today())),
            selected: _draft.range == 'year',
            onTap: () => _select('year'),
          ),
          _RangeRow(
            rowKey: const Key('sales-range-yearly'),
            title: s.aWholeYear,
            subtitle: s.pickYearBelow,
            selected: _draft.range == 'yearly',
            onTap: () => _select('yearly'),
          ),
          if (_draft.range == 'yearly')
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Wrap(
                spacing: 8,
                children: [
                  for (final year in widget.years)
                    ChoiceChip(
                      key: Key('sales-year-$year'),
                      label: Text('$year'),
                      selected: _draft.year == year,
                      onSelected: (_) => setState(() {
                        _draft = _draft.copy(year: year);
                      }),
                    ),
                ],
              ),
            ),
          _RangeRow(
            rowKey: const Key('sales-range-custom'),
            title: s.customDates,
            subtitle: _draft.range == 'custom' && _draft.from != null && _draft.to != null
                ? s.compactRange(_draft.from!, _draft.to!)
                : s.upTo366Days,
            selected: _draft.range == 'custom',
            onTap: () => _select('custom'),
          ),
          if (_customError != null)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 8),
              child: Text(
                _customError!,
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.error,
                ),
              ),
            ),
          if (widget.valueAdded) ...[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
              child: Text(s.productType, style: caption),
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Wrap(
                spacing: 8,
                children: [
                  ChoiceChip(
                    key: const Key('sales-category-all'),
                    label: Text(s.salesCategoryAll),
                    selected: _draft.category == 'all',
                    onSelected: (_) =>
                        setState(() => _draft = _draft.copy(category: 'all')),
                  ),
                  ChoiceChip(
                    key: const Key('sales-category-fresh'),
                    label: Text(s.salesCategoryFresh),
                    selected: _draft.category == 'fresh',
                    onSelected: (_) =>
                        setState(() => _draft = _draft.copy(category: 'fresh')),
                  ),
                  ChoiceChip(
                    key: const Key('sales-category-value_added'),
                    label: Text(s.salesCategoryValueAdded),
                    selected: _draft.category == 'value_added',
                    onSelected: (_) => setState(
                      () => _draft = _draft.copy(category: 'value_added'),
                    ),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: SizedBox(
              width: double.infinity,
              height: 48,
              child: FilledButton(
                key: const Key('sales-apply'),
                onPressed: () {
                  final draft = _draft.range == 'yearly' && _draft.year == null
                      ? _draft.copy(year: widget.years.first)
                      : _draft;
                  Navigator.pop(context, draft);
                },
                child: Text(s.showResults),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _RangeRow extends StatelessWidget {
  const _RangeRow({
    required this.rowKey,
    required this.title,
    required this.subtitle,
    required this.selected,
    required this.onTap,
  });

  final Key rowKey;
  final String title;
  final String subtitle;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return InkWell(
      key: rowKey,
      onTap: onTap,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 54),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
          child: Row(
            children: [
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_off,
                color: selected
                    ? theme.colorScheme.primary
                    : theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title),
                    Text(
                      subtitle,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FilterButton extends StatelessWidget {
  const _FilterButton({
    required this.name,
    required this.dates,
    required this.onTap,
    this.category,
  });

  final String name;
  final String dates;
  final String? category;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      key: const Key('sales-filter'),
      height: 48,
      width: double.infinity,
      child: Material(
        color: theme.colorScheme.surfaceContainerHighest,
        borderRadius: BorderRadius.circular(14),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(14),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: Row(
              children: [
                Icon(
                  Icons.calendar_today_outlined,
                  size: 18,
                  color: theme.colorScheme.onSurface,
                ),
                const SizedBox(width: 8),
                Text(
                  name,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    dates,
                    key: const Key('sales-window'),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ),
                if (category != null) ...[
                  const SizedBox(width: 6),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      color: theme.colorScheme.surface,
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                      child: Text(category!, style: theme.textTheme.labelSmall),
                    ),
                  ),
                ],
                Icon(
                  Icons.expand_more,
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _SalesTab extends StatelessWidget {
  const _SalesTab({
    required this.data,
    required this.filter,
    required this.yearly,
    required this.year,
    required this.selectedPeriod,
    required this.onSelectPeriod,
    required this.onRefresh,
  });

  final FarmerAnalytics data;
  final Widget filter;
  final bool yearly;
  final int? year;
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
            filter,
            const SizedBox(height: _cardGap),
            Text(s.mySalesEmpty, style: Theme.of(context).textTheme.bodyLarge),
          ],
        ),
      );
    }

    final report = data.sales;
    final totals = data.displayTotals;
    final periods = data.displayPeriods;
    final grouping = data.range?.grouping ?? 'day';
    final selected = _chosenPeriod(periods, selectedPeriod);

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: AniHowSpace.screenPadding,
        children: [
          filter,
          const SizedBox(height: _cardGap),
          _SummaryCard(totals: totals, yearly: yearly, year: year, report: report),
          const SizedBox(height: _cardGap),
          _SalesBars(
            periods: periods,
            grouping: grouping,
            selected: selected,
            onSelect: onSelectPeriod,
          ),
          if (_hasBestSellers(data)) ...[
            const SizedBox(height: _cardGap),
            _BestSellers(data: data, total: totals.sales),
          ],
          const SizedBox(height: _cardGap),
          _PaidCard(payment: report?.paymentSplit, source: data.displaySource),
        ],
      ),
    );
  }
}

class _HarvestTab extends StatelessWidget {
  const _HarvestTab({
    required this.data,
    required this.filter,
    required this.showAll,
    required this.onShowAll,
    required this.onRefresh,
  });

  final FarmerAnalytics data;
  final Widget filter;
  final bool showAll;
  final VoidCallback onShowAll;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final harvest = data.harvest;
    final empty = harvest == null || (harvest.records == 0 && harvest.crops.isEmpty);
    final estimated = harvest?.estimatedRecords ?? 0;
    final note = estimated > 0
        ? '${s.harvestNote} ${s.estimatedHarvests(estimated)}'
        : s.harvestNote;

    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: AniHowSpace.screenPadding,
        children: [
          filter,
          const SizedBox(height: _cardGap),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.info_outline,
                size: 16,
                color: Theme.of(context).colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  note,
                  key: const Key('sales-harvest-note'),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: _cardGap),
          if (empty)
            Text(s.noHarvests, style: Theme.of(context).textTheme.bodyLarge)
          else ...[
            _HarvestSummary(harvest: harvest),
            const SizedBox(height: _cardGap),
            ..._cropCards(context, harvest, showAll, onShowAll),
            _ProfitCard(harvest: harvest),
          ],
        ],
      ),
    );
  }

  List<Widget> _cropCards(
    BuildContext context,
    FarmerHarvestReport harvest,
    bool showAll,
    VoidCallback onShowAll,
  ) {
    final crops = [...harvest.crops]..sort((a, b) => b.harvested.compareTo(a.harvested));
    final visible = showAll ? crops : crops.take(6).toList();
    return [
      for (final crop in visible) ...[
        _HarvestCropCard(crop: crop),
        const SizedBox(height: _cardGap),
      ],
      if (!showAll && crops.length > 6)
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton(
            onPressed: onShowAll,
            child: Text(AppStrings.of(context).showAllCrops),
          ),
        ),
    ];
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.child, this.panelKey});

  final Widget child;
  final Key? panelKey;

  @override
  Widget build(BuildContext context) {
    return Card(
      key: panelKey,
      elevation: 0,
      margin: EdgeInsets.zero,
      color: Theme.of(context).colorScheme.surface,
      surfaceTintColor: Colors.transparent,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      child: Padding(padding: const EdgeInsets.all(16), child: child),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  const _SummaryCard({
    required this.totals,
    required this.yearly,
    required this.year,
    required this.report,
  });

  final FarmerSalesTotals totals;
  final bool yearly;
  final int? year;
  final FarmerSalesReport? report;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final caption = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final best = report?.yearTotal?.bestMonth;
    final bestDate = best == null ? null : _monthFromKey(best.key);
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            yearly && year != null ? s.yearTotalLabel(year!) : s.totalSales,
            style: caption,
          ),
          const SizedBox(height: 2),
          Text(AniHowMoney.peso(totals.sales), style: theme.textTheme.headlineMedium),
          const Divider(height: 24),
          Row(
            children: [
              Expanded(child: Text(s.ordersCount(totals.orders), textAlign: TextAlign.center)),
              _columnRule(context),
              Expanded(
                child: _metric(context, s.avgOrderShort, AniHowMoney.peso(totals.averageOrder)),
              ),
              _columnRule(context),
              Expanded(
                child: _metric(context, s.avgTawadShort, AniHowMoney.peso(totals.averageTawad)),
              ),
            ],
          ),
          if (yearly && best != null && bestDate != null) ...[
            const SizedBox(height: 12),
            Text(
              s.bestMonthLine(s.monthName(bestDate), AniHowMoney.peso(best.sales)),
              style: caption,
            ),
          ],
        ],
      ),
    );
  }

  Widget _metric(BuildContext context, String label, String value) {
    return Column(
      children: [
        Text(value, style: const TextStyle(fontWeight: FontWeight.w700)),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    );
  }

  Widget _columnRule(BuildContext context) {
    return SizedBox(
      height: 36,
      child: VerticalDivider(width: 1, color: Theme.of(context).colorScheme.outlineVariant),
    );
  }
}

class _BestSellers extends StatefulWidget {
  const _BestSellers({required this.data, required this.total});

  final FarmerAnalytics data;
  final double total;

  @override
  State<_BestSellers> createState() => _BestSellersState();
}

class _BestSellersState extends State<_BestSellers> {
  bool _othersOpen = false;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final caption = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final pie = widget.data.sales?.pie;
    final crops = widget.data.displayTopCrops;
    final parts = pie == null ? const <_PiePart>[] : _pieParts(context, pie);
    final othersCrops = _othersCrops(pie, crops);
    final cropTotal = pie == null
        ? crops.map((crop) => crop.crop).toSet().length
        : pie.slices.length + (pie.others?.crops ?? 0);

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            s.bestSellers,
            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
          ),
          Text(s.shareOfSales, style: caption),
          if (parts.isNotEmpty) ...[
            const SizedBox(height: 12),
            Center(
              child: SizedBox(
                height: 136,
                width: 136,
                child: CustomPaint(
                  painter: _DonutPainter(
                    parts.map((part) => (part.amount, part.color)).toList(),
                  ),
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          AniHowMoney.peso(widget.total),
                          style: theme.textTheme.titleSmall,
                          textAlign: TextAlign.center,
                        ),
                        Text(s.cropCount(cropTotal), style: caption, textAlign: TextAlign.center),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
          const SizedBox(height: 8),
          if (parts.isEmpty)
            for (final crop in _grouped(crops))
              _SellerRow(
                color: _sliceColor(context, 0),
                name: crop.name,
                detail: _quantityLine(s, crop.rows),
                sales: crop.sales,
                percent: null,
              )
          else
            for (final part in parts)
              if (part.others)
                Column(
                  children: [
                    InkWell(
                      onTap: () => setState(() => _othersOpen = !_othersOpen),
                      child: _SellerRow(
                        color: part.color,
                        name: s.othersWithCount(pie!.others?.crops ?? othersCrops.length),
                        detail: null,
                        sales: part.amount,
                        percent: part.percent,
                      ),
                    ),
                    if (_othersOpen)
                      for (final crop in othersCrops)
                        Padding(
                          padding: const EdgeInsets.only(left: 20),
                          child: _SellerRow(
                            color: part.color,
                            name: crop.name,
                            detail: _quantityLine(s, crop.rows),
                            sales: crop.sales,
                            percent: null,
                          ),
                        ),
                  ],
                )
              else
                _SellerRow(
                  color: part.color,
                  name: part.label,
                  detail: _quantityLine(s, _rowsForSlice(part.slice!, crops)),
                  sales: part.amount,
                  percent: part.percent,
                ),
        ],
      ),
    );
  }
}

class _SellerRow extends StatelessWidget {
  const _SellerRow({
    required this.color,
    required this.name,
    required this.detail,
    required this.sales,
    required this.percent,
  });

  final Color color;
  final String name;
  final String? detail;
  final double sales;
  final double? percent;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Container(
              width: 10,
              height: 10,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(name),
                if (detail != null)
                  Text(
                    detail!,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
              ],
            ),
          ),
          Text(AniHowMoney.peso(sales), style: const TextStyle(fontWeight: FontWeight.w700)),
          if (percent != null) ...[
            const SizedBox(width: 8),
            Text(_percent(percent!)),
          ],
        ],
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
    this.slice,
    this.others = false,
  });

  final String label;
  final double amount;
  final double percent;
  final Color color;
  final FarmerPieSlice? slice;
  final bool others;
}

List<_PiePart> _pieParts(BuildContext context, FarmerSalesPie pie) {
  final parts = <_PiePart>[];
  for (var i = 0; i < pie.slices.length; i++) {
    final slice = pie.slices[i];
    parts.add(
      _PiePart(
        label: slice.crop,
        amount: slice.sales,
        percent: slice.percent,
        color: _sliceColor(context, i),
        slice: slice,
      ),
    );
  }
  final others = pie.others;
  if (others != null) {
    parts.add(
      _PiePart(
        label: '',
        amount: others.sales,
        percent: others.percent,
        color: _pieOthers,
        others: true,
      ),
    );
  }
  return parts.where((part) => part.amount > 0 || part.percent > 0).toList();
}

Color _sliceColor(BuildContext context, int index) {
  final dark = Theme.of(context).brightness == Brightness.dark;
  final colors = [
    dark ? const Color(0xFFA8D5BA) : AniHowColors.brand,
    AniHowColors.sage,
    AniHowColors.eggplant,
    AniHowColors.root,
    _piePeach,
  ];
  return colors[index % colors.length];
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
    final theme = Theme.of(context);
    final maxSales = periods.fold<double>(
      0,
      (max, point) => point.sales > max ? point.sales : max,
    );
    final caption = grouping == 'day' ? _monthCaption(s, periods) : null;

    return _Panel(
      panelKey: const Key('sales-chart'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  s.salesOverTime,
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
                ),
              ),
              Text(
                s.byGrouping(grouping),
                style: theme.textTheme.bodySmall?.copyWith(
                  color: theme.colorScheme.onSurfaceVariant,
                ),
              ),
            ],
          ),
          if (selected != null) ...[
            const SizedBox(height: 6),
            Text(
              _periodDetail(s, selected!),
              key: const Key('sales-bar-detail'),
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w700,
                color: _chartColor(context, selected: true),
              ),
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
                      for (final point in periods) Expanded(child: column(point, fitWidth)),
                    ],
                  );
                }
                return ListView.builder(
                  scrollDirection: Axis.horizontal,
                  reverse: true,
                  itemCount: periods.length,
                  itemExtent: minWidth,
                  itemBuilder: (context, index) =>
                      column(periods[periods.length - 1 - index], minWidth),
                );
              },
            ),
          ),
          if (caption != null) ...[
            const SizedBox(height: 6),
            Text(
              caption,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ],
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
      'week' when start != null && end != null => '${s.shortDate(start)}–${s.shortDate(end)}',
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
      ..addRRect(RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(6)));
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

Color _chartColor(BuildContext context, {bool selected = false}) {
  final dark = Theme.of(context).brightness == Brightness.dark;
  if (dark) {
    return selected ? const Color(0xFFA8D5BA) : AniHowColors.sage;
  }
  return selected ? AniHowColors.brand : AniHowColors.sage;
}

class _PaidCard extends StatelessWidget {
  const _PaidCard({required this.payment, required this.source});

  final FarmerPaymentSplit? payment;
  final FarmerSourceSplit source;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final online = payment?.online ?? const FarmerSalesBucket(orders: 0, sales: 0);
    final cash = payment?.cash ?? const FarmerSalesBucket(orders: 0, sales: 0);
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            s.howBuyersPaid,
            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
          ),
          const SizedBox(height: 10),
          _Segments(parts: [(online.sales, _onlineColor), (cash.sales, _cashColor)]),
          const SizedBox(height: 6),
          _SplitLine(
            leftLabel: s.onlinePay,
            left: online,
            leftColor: _onlineColor,
            rightLabel: s.cashPay,
            right: cash,
            rightColor: _cashColor,
          ),
          const SizedBox(height: 4),
          Text(
            s.cashIncludesWalkIn,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 12),
          _Segments(parts: [(source.app.sales, _appColor), (source.walkIn.sales, _walkInColor)]),
          const SizedBox(height: 6),
          _SplitLine(
            leftLabel: s.appSales,
            left: source.app,
            leftColor: _appColor,
            rightLabel: s.walkInSales,
            right: source.walkIn,
            rightColor: _walkInColor,
          ),
        ],
      ),
    );
  }
}

class _SplitLine extends StatelessWidget {
  const _SplitLine({
    required this.leftLabel,
    required this.left,
    required this.leftColor,
    required this.rightLabel,
    required this.right,
    required this.rightColor,
  });

  final String leftLabel;
  final FarmerSalesBucket left;
  final Color leftColor;
  final String rightLabel;
  final FarmerSalesBucket right;
  final Color rightColor;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(child: _side(context, leftLabel, left, leftColor)),
        Expanded(child: _side(context, rightLabel, right, rightColor, end: true)),
      ],
    );
  }

  Widget _side(
    BuildContext context,
    String label,
    FarmerSalesBucket bucket,
    Color color, {
    bool end = false,
  }) {
    final style = Theme.of(context).textTheme.bodySmall;
    return Row(
      mainAxisAlignment: end ? MainAxisAlignment.end : MainAxisAlignment.start,
      children: [
        Container(
          width: 8,
          height: 8,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 4),
        Flexible(
          child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: style),
        ),
        const SizedBox(width: 4),
        Text(
          AniHowMoney.peso(bucket.sales),
          style: style?.copyWith(fontWeight: FontWeight.w700),
        ),
        Text(' · ${bucket.orders}', style: style),
      ],
    );
  }
}

class _Segments extends StatelessWidget {
  const _Segments({required this.parts, this.barKey});

  final List<(double, Color)> parts;
  final Key? barKey;

  @override
  Widget build(BuildContext context) {
    final total = parts.fold<double>(0, (sum, part) => sum + (part.$1 > 0 ? part.$1 : 0));
    return ClipRRect(
      key: barKey,
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

const Color _onlineColor = Color(0xFF378ADD);
const Color _cashColor = Color(0xFFE2A24A);
const Color _appColor = AniHowColors.sage;
const Color _walkInColor = Color(0xFFA8D5BA);
const Color _rejectedColor = Color(0xFFE8A87C);
const Color _soldColor = Color(0xFF2E8B57);
const Color _waitingColor = Color(0xFF378ADD);
const Color _removedColor = AniHowColors.fruit;
const Color _leftColor = Color(0xFFA8D5BA);

class _HarvestSummary extends StatelessWidget {
  const _HarvestSummary({required this.harvest});

  final FarmerHarvestReport harvest;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final caption = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final potential = harvest.income.potential;
    final actual = harvest.income.actual;
    final percent = potential <= 0 ? null : ((actual / potential) * 100).round();
    final value = potential <= 0 ? 0.0 : (actual / potential).clamp(0.0, 1.0);
    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(s.earnedSoFar, style: caption),
                    Text(AniHowMoney.peso(actual), style: theme.textTheme.headlineMedium),
                  ],
                ),
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(s.expectedShort, style: caption),
                  Text(
                    AniHowMoney.peso(potential),
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(6),
            child: LinearProgressIndicator(
              minHeight: 8,
              value: value,
              color: _soldColor,
              backgroundColor: theme.colorScheme.surfaceContainerHighest,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            s.harvestSummaryLine(
              percent: percent,
              harvests: harvest.records,
              crops: harvest.crops.length,
            ),
            style: caption,
          ),
        ],
      ),
    );
  }
}

class _HarvestCropCard extends StatelessWidget {
  const _HarvestCropCard({required this.crop});

  final FarmerHarvestCrop crop;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final caption = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurfaceVariant,
    );
    final unit = s.unitWord(crop.unit, crop.harvested);
    final parts = <(String, double, Color)>[
      (s.soldLabel, crop.sold, _soldColor),
      (s.waitingShort, crop.waiting, _waitingColor),
      (s.removedLabel, crop.removed, _removedColor),
      (s.leftLabel, crop.remaining, _leftColor),
      (s.rejectedLabel, crop.rejected, _rejectedColor),
    ];
    final visible = parts.where((part) => part.$2 > 0).toList();
    final rejected = _reasonBits(s, crop.rejectedByReason, crop.unit);
    final removed = _reasonBits(s, crop.removedByReason, crop.unit);
    final reasons = [
      if (rejected != null) s.rejectedReasons(rejected),
      if (removed != null) s.removedReasons(removed),
    ].join(' · ');

    return _Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  crop.crop,
                  style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
                ),
              ),
              Text(s.harvestedAmount(_qty(crop.harvested), unit), style: caption),
            ],
          ),
          const SizedBox(height: 10),
          _Segments(
            barKey: Key('sales-crop-bar-${crop.crop}-${crop.unit}'),
            parts: [
              (crop.sold, _soldColor),
              (crop.waiting, _waitingColor),
              (crop.removed, _removedColor),
              (crop.remaining, _leftColor),
              (crop.rejected, _rejectedColor),
            ],
          ),
          const SizedBox(height: 8),
          for (var i = 0; i < visible.length; i += 2)
            Row(
              children: [
                Expanded(child: _legend(context, visible[i])),
                Expanded(
                  child: i + 1 < visible.length
                      ? _legend(context, visible[i + 1])
                      : const SizedBox.shrink(),
                ),
              ],
            ),
          if (reasons.isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(reasons, style: caption),
          ],
        ],
      ),
    );
  }

  Widget _legend(BuildContext context, (String, double, Color) part) {
    final unit = AppStrings.of(context).unitWord(crop.unit, part.$2);
    final amount = unit.isEmpty ? _qty(part.$2) : '${_qty(part.$2)} $unit';
    return Padding(
      padding: const EdgeInsets.only(top: 3),
      child: Row(
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(color: part.$3, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              '${part.$1} $amount',
              style: Theme.of(context).textTheme.bodySmall,
            ),
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
    final theme = Theme.of(context);
    final cost = harvest.cost;
    if (cost.recordsWithCost == 0) {
      return _Panel(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.noCostRecorded,
              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 4),
            Text(
              s.noCostHint,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurfaceVariant,
              ),
            ),
          ],
        ),
      );
    }
    final total = cost.recordsWithCost + cost.recordsWithoutCost;
    return _Panel(
      panelKey: const Key('sales-profit'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _moneyLine(context, s.costLabel, cost.costTotal),
          const Divider(height: 16),
          _moneyLine(context, s.expectedProfit, cost.potentialProfit),
          const Divider(height: 16),
          _moneyLine(context, s.profitSoFar, cost.actualProfit),
          const SizedBox(height: 8),
          Text(
            s.costCoverage(cost.recordsWithCost, total == 0 ? harvest.records : total),
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }

  Widget _moneyLine(BuildContext context, String label, double? amount) {
    final negative = amount != null && amount < 0;
    return Row(
      children: [
        Expanded(child: Text(label)),
        Text(
          _optionalPeso(amount),
          style: TextStyle(
            fontWeight: FontWeight.w700,
            color: negative ? Theme.of(context).colorScheme.error : null,
          ),
        ),
      ],
    );
  }
}

class _CropGroup {
  _CropGroup({required this.name, required this.rows, required this.sales});

  final String name;
  final List<FarmerTopCrop> rows;
  final double sales;
}

bool _hasBestSellers(FarmerAnalytics data) {
  final pie = data.sales?.pie;
  if (pie != null && (pie.slices.isNotEmpty || pie.others != null)) {
    return true;
  }
  return data.displayTopCrops.isNotEmpty;
}

List<FarmerTopCrop> _rowsForSlice(FarmerPieSlice slice, List<FarmerTopCrop> crops) {
  if (slice.cropTypeId != null) {
    final byId = crops.where((crop) => crop.cropTypeId == slice.cropTypeId).toList();
    if (byId.isNotEmpty) {
      return byId;
    }
  }
  return crops.where((crop) => crop.crop == slice.crop).toList();
}

List<_CropGroup> _othersCrops(FarmerSalesPie? pie, List<FarmerTopCrop> crops) {
  final sliceNames = pie?.slices.map((slice) => slice.crop).toSet() ?? {};
  return _grouped(crops.where((crop) => !sliceNames.contains(crop.crop)).toList());
}

List<_CropGroup> _grouped(List<FarmerTopCrop> crops) {
  final groups = <String, _CropGroup>{};
  for (final crop in crops) {
    final current = groups[crop.crop];
    if (current == null) {
      groups[crop.crop] = _CropGroup(name: crop.crop, rows: [crop], sales: crop.sales);
    } else {
      current.rows.add(crop);
      groups[crop.crop] = _CropGroup(
        name: crop.crop,
        rows: current.rows,
        sales: current.sales + crop.sales,
      );
    }
  }
  return groups.values.toList();
}

String? _quantityLine(AppStrings s, List<FarmerTopCrop> crops) {
  if (crops.isEmpty) {
    return null;
  }
  final totals = <String, double>{};
  final order = <String>[];
  for (final crop in crops) {
    if (!totals.containsKey(crop.unit)) {
      order.add(crop.unit);
    }
    totals[crop.unit] = (totals[crop.unit] ?? 0) + crop.quantity;
  }
  return s.quantitiesSold([
    for (final unit in order) (_qty(totals[unit]!), unit, totals[unit]!),
  ]);
}

FarmerSalesPeriod? _chosenPeriod(List<FarmerSalesPeriod> periods, String? selectedKey) {
  for (final point in periods) {
    if (point.key == selectedKey) {
      return point;
    }
  }
  for (var i = periods.length - 1; i >= 0; i--) {
    if (periods[i].sales > 0) {
      return periods[i];
    }
  }
  return periods.isEmpty ? null : periods.last;
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

String? _reasonBits(AppStrings s, Map<String, double> reasons, String unit) {
  final parts = <String>[];
  for (final entry in reasons.entries) {
    if (entry.value <= 0) {
      continue;
    }
    final word = s.unitWord(unit, entry.value);
    final qty = _qty(entry.value);
    parts.add(
      word.isEmpty
          ? '${s.rejectionReasonLabel(entry.key)} $qty'
          : '${s.rejectionReasonLabel(entry.key)} $qty $word',
    );
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

DateTime _today() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
}

DateTime _weekStart() {
  final today = _today();
  return today.subtract(Duration(days: today.weekday - 1));
}

DateTime _monthStart() {
  final today = _today();
  return DateTime(today.year, today.month, 1);
}

DateTime _yearStart() => DateTime(_today().year, 1, 1);

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
