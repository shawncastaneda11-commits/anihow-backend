import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/payment_card.dart';
import 'farmer_orders_screen.dart';

class FarmerPaymentsScreen extends StatefulWidget {
  const FarmerPaymentsScreen({super.key});

  @override
  State<FarmerPaymentsScreen> createState() => _FarmerPaymentsScreenState();
}

class _FarmerPaymentsScreenState extends State<FarmerPaymentsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final List<int> _counts = [0, 0, 0];

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 3, vsync: this);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  void _setCount(int index, int count) {
    if (_counts[index] == count) {
      return;
    }
    setState(() => _counts[index] = count);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.payments),
        bottom: onBrandTabBar(
          controller: _tabs,
          isScrollable: true,
          tabs: [
            Tab(text: s.toCheckCount(_counts[0])),
            Tab(text: s.confirmedPayments),
            Tab(text: s.refundDueTab),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _PaymentList(status: 'to_check', onCount: (count) => _setCount(0, count)),
          _PaymentList(status: 'confirmed', onCount: (count) => _setCount(1, count)),
          _PaymentList(status: 'refund_due', onCount: (count) => _setCount(2, count)),
        ],
      ),
    );
  }
}

class _PaymentList extends StatefulWidget {
  const _PaymentList({required this.status, required this.onCount});

  final String status;
  final ValueChanged<int> onCount;

  @override
  State<_PaymentList> createState() => _PaymentListState();
}

class _PaymentListState extends State<_PaymentList> {
  final List<OrderRecord> _items = [];
  int _page = 1;
  bool _loading = true;
  bool _complete = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = true;
      _error = null;
      _page = 1;
    });
    try {
      final page = await context.read<AuthController>().api.farmerPayments(
        status: widget.status,
        page: 1,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _items
          ..clear()
          ..addAll(page.items);
        _complete = page.complete;
        _loading = false;
      });
      widget.onCount(page.items.length);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = error;
        });
      }
    }
  }

  Future<void> _more() async {
    final next = _page + 1;
    final page = await context.read<AuthController>().api.farmerPayments(
      status: widget.status,
      page: next,
    );
    if (!mounted) {
      return;
    }
    setState(() {
      _page = next;
      _items.addAll(page.items);
      _complete = page.complete;
    });
    widget.onCount(_items.length);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    return RefreshIndicator(
      onRefresh: _reload,
      child: _items.isEmpty
          ? ListView(
              children: [
                Padding(
                  padding: AniHowSpace.screenPadding,
                  child: Text(
                    s.paymentsEmpty,
                    key: ValueKey('payments-empty-${widget.status}'),
                  ),
                ),
              ],
            )
          : ListView.separated(
              padding: AniHowSpace.screenPadding,
              itemCount: _items.length + (_complete ? 0 : 1),
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                if (index >= _items.length) {
                  return TextButton(
                    key: ValueKey('payments-more-${widget.status}'),
                    onPressed: _more,
                    child: Text(s.payments),
                  );
                }
                final order = _items[index];
                return _PaymentRow(
                  order: order,
                  tab: widget.status,
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => FarmerOrderDetailScreen(order: order),
                      ),
                    );
                  },
                );
              },
            ),
    );
  }
}

class _PaymentRow extends StatelessWidget {
  const _PaymentRow({
    required this.order,
    required this.tab,
    required this.onTap,
  });

  final OrderRecord order;
  final String tab;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.62),
    );
    final proof = order.latestProof;
    final items = order.items.map((item) => item.listingName).where((name) => name.isNotEmpty);
    final detail = [
      if (order.orderNumber != null && order.orderNumber!.isNotEmpty) order.orderNumber!,
      if (items.isNotEmpty) items.join(', '),
      if (tab == 'refund_due' &&
          order.cancellationLabel != null &&
          order.cancellationLabel!.isNotEmpty)
        order.cancellationLabel!,
    ].join(' · ');
    final wallet = s.walletLabel(proof?.wallet);
    final reference = proof?.reference;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        key: ValueKey('payment-order-${order.id}'),
        borderRadius: BorderRadius.circular(paymentCardRadius),
        onTap: onTap,
        child: Ink(
          decoration: paymentCardDecoration(context),
          padding: const EdgeInsets.fromLTRB(14, 12, 8, 12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            order.buyerName,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        Text(
                          AniHowMoney.peso(order.total),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    if (detail.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(detail, style: muted),
                    ],
                    if (wallet.isNotEmpty && reference != null && reference.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(s.walletRef(wallet, reference), style: muted),
                    ],
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 4,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        _pill(s),
                        if (_when(s) case final time?) Text(time, style: muted),
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
  }

  Widget _pill(AppStrings s) {
    return switch (tab) {
      'confirmed' => PaymentTonePill(
        label: s.paymentStatusLabel('paid'),
        foreground: AniHowColors.inStock,
        background: AniHowColors.inStockBg,
      ),
      'refund_due' => PaymentTonePill(
        label: s.refundDueTab,
        foreground: AniHowColors.root,
        background: const Color(0xFFFFF4D6),
      ),
      _ => PaymentTonePill(
        label: s.checkNow,
        foreground: AniHowColors.confirmedBlue,
        background: const Color(0xFFDBEAFE),
      ),
    };
  }

  String? _when(AppStrings s) {
    return switch (tab) {
      'confirmed' => order.paidAt == null ? null : s.paidOn(order.paidAt!),
      'refund_due' => order.cancelledAt == null ? null : s.sinceDate(order.cancelledAt!),
      _ => order.latestProof?.sentAt == null ? null : s.sentAgo(order.latestProof!.sentAt!),
    };
  }
}
