import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/status_pill.dart';
import 'farmer_orders_screen.dart';

class FarmerPaymentsScreen extends StatefulWidget {
  const FarmerPaymentsScreen({super.key});

  @override
  State<FarmerPaymentsScreen> createState() => _FarmerPaymentsScreenState();
}

class _FarmerPaymentsScreenState extends State<FarmerPaymentsScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;

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

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.payments),
        bottom: TabBar(
          controller: _tabs,
          tabs: [
            Tab(text: s.toCheck),
            Tab(text: s.confirmedPayments),
            Tab(text: s.refundDueTab),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabs,
        children: const [
          _PaymentList(status: 'to_check'),
          _PaymentList(status: 'confirmed'),
          _PaymentList(status: 'refund_due'),
        ],
      ),
    );
  }
}

class _PaymentList extends StatefulWidget {
  const _PaymentList({required this.status});

  final String status;

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
  }

  String _empty(AppStrings s) {
    return switch (widget.status) {
      'confirmed' => s.noConfirmedPayments,
      'refund_due' => s.noRefundsDue,
      _ => s.noPaymentsToCheck,
    };
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
                  child: Text(_empty(s), key: ValueKey('payments-empty-${widget.status}')),
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
                return Card(
                  child: ListTile(
                    key: ValueKey('payment-order-${order.id}'),
                    title: Text(order.orderNumber ?? order.buyerName),
                    subtitle: Text(AniHowMoney.peso(order.total)),
                    trailing: PaymentTrackingPill(status: order.paymentStatus),
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => FarmerOrderDetailScreen(order: order),
                        ),
                      );
                    },
                  ),
                );
              },
            ),
    );
  }
}
