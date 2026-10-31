import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/payment_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../buyer/reservation_detail_screen.dart';
import 'farmer_orders_screen.dart';

class FarmerPaymentsScreen extends StatefulWidget {
  const FarmerPaymentsScreen({super.key});

  @override
  State<FarmerPaymentsScreen> createState() => _FarmerPaymentsScreenState();
}

class _FarmerPaymentsScreenState extends State<FarmerPaymentsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  final Map<String, int> _counts = {};

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

  void _setCounts(Map<String, int> counts) {
    if (counts.entries.every((entry) => _counts[entry.key] == entry.value)) {
      return;
    }
    setState(() => _counts.addAll(counts));
  }

  Widget _tab(String label, String status) {
    final count = _counts[status] ?? 0;
    return Tab(
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(label),
          if (count > 0) ...[
            const SizedBox(width: 6),
            Container(
              key: ValueKey('payments-tab-count-$status'),
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
              decoration: BoxDecoration(
                color: status == 'refund_due'
                    ? const Color(0xFFE9B566)
                    : Colors.white.withValues(alpha: 0.22),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Text(
                '$count',
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w800,
                  color: status == 'refund_due' ? const Color(0xFF2A1D05) : Colors.white,
                ),
              ),
            ),
          ],
        ],
      ),
    );
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
            _tab(s.toCheckTab, 'to_check'),
            _tab(s.confirmedPayments, 'confirmed'),
            _tab(s.refundDueTab, 'refund_due'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: [
          _PaymentList(status: 'to_check', onCounts: _setCounts),
          _PaymentList(status: 'confirmed', onCounts: _setCounts),
          _PaymentList(status: 'refund_due', onCounts: _setCounts),
        ],
      ),
    );
  }
}

class _PaymentList extends StatefulWidget {
  const _PaymentList({required this.status, required this.onCounts});

  final String status;

  /// Every tab's count from the server, or this tab's own when it sends none.
  final ValueChanged<Map<String, int>> onCounts;

  @override
  State<_PaymentList> createState() => _PaymentListState();
}

class _PaymentListState extends State<_PaymentList> {
  final List<PaymentListItem> _items = [];
  int _page = 1;
  bool _loading = true;
  bool _complete = false;
  Object? _error;
  double? _total;

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
        _total = page.totalAmount;
        _loading = false;
      });
      widget.onCounts(
        page.counts.isEmpty ? {widget.status: page.items.length} : page.counts,
      );
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
    if (page.counts.isEmpty) {
      widget.onCounts({widget.status: _items.length});
    }
  }

  Future<void> _open(PaymentListItem item) async {
    if (item.isReservation) {
      final id = item.reservationId ?? item.id;
      final found = await context.read<AuthController>().api.farmerReservation(id);
      if (!mounted) {
        return;
      }
      final reservation = found ??
          ReservationRecord(
            id: id,
            listingName: item.title,
            quantity: 0,
            lineTotal: double.tryParse(item.amount) ?? 0,
            status: widget.status == 'refund_due' ? 'cancelled' : 'active',
            buyerName: item.buyerName,
            paymentStatus: switch (widget.status) {
              'confirmed' => 'paid',
              'refund_due' => 'refund_due',
              _ => 'payment_sent',
            },
            latestProof: item.reference == null
                ? null
                : PaymentProofRecord(
                    reference: item.reference,
                    amount: item.amount,
                    wallet: item.wallet,
                    status: 'pending',
                  ),
          );
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ReservationDetailScreen(
            reservation: reservation,
            forSeller: true,
          ),
        ),
      );
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => FarmerOrderDetailScreen(orderId: item.orderId ?? item.id),
      ),
    );
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
              itemCount: _items.length + 1 + (_complete ? 0 : 1),
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                if (index == 0) {
                  return _PaymentsSummary(
                    status: widget.status,
                    count: _items.length,
                    total: _total ??
                        _items.fold<double>(
                          0,
                          (sum, item) => sum + (double.tryParse(item.amount) ?? 0),
                        ),
                  );
                }
                index -= 1;
                if (index >= _items.length) {
                  return TextButton(
                    key: ValueKey('payments-more-${widget.status}'),
                    onPressed: _more,
                    child: Text(s.payments),
                  );
                }
                final item = _items[index];
                return _PaymentRow(
                  item: item,
                  tab: widget.status,
                  onTap: () => _open(item),
                );
              },
            ),
    );
  }
}

/// The tab's total at the top, e.g. "3 refunds to send · ₱289.00".
class _PaymentsSummary extends StatelessWidget {
  const _PaymentsSummary({
    required this.status,
    required this.count,
    required this.total,
  });

  final String status;
  final int count;
  final double total;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final tone = switch (status) {
      'refund_due' => AniHowColors.root,
      'confirmed' => AniHowColors.inStock,
      _ => AniHowColors.confirmedBlue,
    };
    return DecoratedBox(
      key: ValueKey('payments-summary-$status'),
      decoration: BoxDecoration(
        color: tone.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(paymentCardRadius),
        border: Border.all(color: tone.withValues(alpha: 0.4)),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.paymentsSummary(status, count, AniHowMoney.peso(total)),
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 2),
            Text(
              s.paymentsSummaryHint(status),
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// One payment: who and how much, what it was for, and the wallet and date.
/// The tab already says the status, so the row carries no status pill.
class _PaymentRow extends StatelessWidget {
  const _PaymentRow({
    required this.item,
    required this.tab,
    required this.onTap,
  });

  final PaymentListItem item;
  final String tab;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final mutedColor = theme.colorScheme.onSurface.withValues(alpha: 0.66);
    final muted = theme.textTheme.bodyMedium?.copyWith(color: mutedColor);
    final wallet = s.walletLabel(item.wallet);
    final reference = item.reference;
    final ref = wallet.isNotEmpty && reference != null && reference.isNotEmpty
        ? s.walletRef(wallet, lastFourOf(reference))
        : null;
    final when = _when(s);
    final tagColor = item.isReservation ? readableAccent(context) : AniHowColors.confirmedBlue;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        key: ValueKey(
          item.isReservation ? 'payment-reservation-${item.id}' : 'payment-order-${item.id}',
        ),
        borderRadius: BorderRadius.circular(paymentCardRadius),
        onTap: onTap,
        child: Ink(
          decoration: paymentCardDecoration(context),
          padding: const EdgeInsets.fromLTRB(12, 12, 6, 12),
          child: Row(
            children: [
              AniHowAvatar(name: item.buyerName, imageUrl: item.buyerAvatarUrl, radius: 20),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.buyerName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        Text(
                          AniHowMoney.peso(item.amount),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 2),
                    Row(
                      children: [
                        if (item.title.isNotEmpty)
                          Flexible(
                            child: Text(
                              item.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: muted,
                            ),
                          ),
                        const SizedBox(width: 6),
                        Container(
                          key: item.isReservation ? ValueKey('reservation-badge-${item.id}') : null,
                          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                          decoration: BoxDecoration(
                            color: tagColor.withValues(alpha: 0.14),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            item.isReservation ? s.reservationBadge : s.orderBadge,
                            style: TextStyle(
                              color: tagColor,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (ref != null || when != null) ...[
                      const SizedBox(height: 2),
                      Text.rich(
                        TextSpan(
                          children: [
                            if (ref != null) TextSpan(text: ref),
                            if (ref != null && when != null) const TextSpan(text: ' · '),
                            if (when != null)
                              TextSpan(
                                text: when,
                                style: tab == 'refund_due'
                                    ? const TextStyle(
                                        color: AniHowColors.root,
                                        fontWeight: FontWeight.w600,
                                      )
                                    : null,
                              ),
                          ],
                        ),
                        style: muted,
                      ),
                    ],
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

  String? _when(AppStrings s) {
    return switch (tab) {
      'confirmed' => item.paidAt == null ? null : s.paidOn(item.paidAt!),
      'refund_due' => item.statusAt == null ? null : s.dueSince(item.statusAt!),
      _ => item.sentAt == null ? null : s.sentAgo(item.sentAt!),
    };
  }
}
