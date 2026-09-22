import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';
import '../../widgets/price_breakdown.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import 'walk_in_sale_screen.dart';

const _orderTabs = [
  (status: 'placed', label: 'Placed', empty: 'No placed orders.'),
  (status: 'confirmed', label: 'Confirmed', empty: 'No confirmed orders.'),
  (status: 'ready', label: 'Ready', empty: 'No orders waiting for handover.'),
  (status: 'completed', label: 'Completed', empty: 'No completed orders.'),
  (status: 'cancelled', label: 'Cancelled', empty: 'No cancelled orders.'),
];

const _sellerCancelReasons = [
  (value: 'seller_declined', label: 'Declined by farmer-seller'),
  (value: 'no_show', label: 'No-show at handover'),
  (value: 'other', label: 'Other'),
];

class FarmerOrdersScreen extends StatefulWidget {
  const FarmerOrdersScreen({super.key});

  @override
  State<FarmerOrdersScreen> createState() => _FarmerOrdersScreenState();
}

class _FarmerOrdersScreenState extends State<FarmerOrdersScreen> {
  List<OrderRecord> _items = const [];
  bool _loading = true;
  Object? _error;
  final Set<int> _acting = {};

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _items.isEmpty;
      _error = null;
    });
    try {
      final items = await context.read<AuthController>().api.farmerOrders();
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

  void _replace(OrderRecord order) {
    setState(() {
      _items = [
        for (final item in _items)
          if (item.id == order.id) order else item,
      ];
    });
  }

  Future<void> _open(OrderRecord order) async {
    final updated = await Navigator.of(context).push<OrderRecord>(
      MaterialPageRoute(builder: (_) => FarmerOrderDetailScreen(order: order)),
    );
    if (!mounted || updated == null) {
      return;
    }
    _replace(updated);
  }

  Future<void> _run(OrderRecord order, Future<OrderRecord> Function() action) async {
    if (_acting.contains(order.id)) {
      return;
    }
    setState(() => _acting.add(order.id));
    try {
      final updated = await action();
      if (mounted) {
        _replace(updated);
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _acting.remove(order.id));
      } else {
        _acting.remove(order.id);
      }
    }
  }

  Future<void> _confirm(OrderRecord order) {
    return _run(order, () => context.read<AuthController>().api.confirmOrder(order.id));
  }

  Future<void> _ready(OrderRecord order) {
    return _run(order, () => context.read<AuthController>().api.markOrderReady(order.id));
  }

  Future<void> _complete(OrderRecord order) async {
    final amount = await askAmountReceived(context, order);
    if (!mounted || amount == null) {
      return;
    }
    await _run(
      order,
      () => context.read<AuthController>().api.completeOrder(order.id, amountReceived: amount),
    );
  }

  Future<void> _cancel(OrderRecord order) async {
    final choice = await askCancellation(context);
    if (!mounted || choice == null) {
      return;
    }
    await _run(
      order,
      () => context.read<AuthController>().api.cancelOrder(
            order.id,
            reason: choice.reason,
            note: choice.note,
          ),
    );
  }

  Future<void> _openWalkIn() async {
    final recorded = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const WalkInSaleScreen()),
    );
    if (!mounted || recorded != true) {
      return;
    }
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: _orderTabs.length,
      child: Scaffold(
        body: Column(
          children: [
            if (context.watch<AuthController>().user?.canRecordWalkInSales ?? false)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AniHowSpace.screen,
                  AniHowSpace.cardGap,
                  AniHowSpace.screen,
                  0,
                ),
                child: PrimaryButton(
                  label: 'Record walk-in sale',
                  onPressed: _openWalkIn,
                ),
              ),
            const TabBar(
              isScrollable: true,
              tabs: [
                Tab(height: AniHowSpace.tabHeight, text: 'Placed'),
                Tab(height: AniHowSpace.tabHeight, text: 'Confirmed'),
                Tab(height: AniHowSpace.tabHeight, text: 'Ready'),
                Tab(height: AniHowSpace.tabHeight, text: 'Completed'),
                Tab(height: AniHowSpace.tabHeight, text: 'Cancelled'),
              ],
            ),
            Expanded(child: _body()),
          ],
        ),
      ),
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
      children: [
        for (final tab in _orderTabs)
          _OrderList(
            items: _items.where((order) => order.status == tab.status).toList(),
            emptyLabel: tab.empty,
            acting: _acting,
            onReload: _reload,
            onOpen: _open,
            onConfirm: _confirm,
            onReady: _ready,
            onComplete: _complete,
            onCancel: _cancel,
          ),
      ],
    );
  }
}

class _OrderList extends StatelessWidget {
  const _OrderList({
    required this.items,
    required this.emptyLabel,
    required this.acting,
    required this.onReload,
    required this.onOpen,
    required this.onConfirm,
    required this.onReady,
    required this.onComplete,
    required this.onCancel,
  });

  final List<OrderRecord> items;
  final String emptyLabel;
  final Set<int> acting;
  final Future<void> Function() onReload;
  final Future<void> Function(OrderRecord) onOpen;
  final Future<void> Function(OrderRecord) onConfirm;
  final Future<void> Function(OrderRecord) onReady;
  final Future<void> Function(OrderRecord) onComplete;
  final Future<void> Function(OrderRecord) onCancel;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return Center(child: Text(emptyLabel));
    }
    return RefreshIndicator(
      onRefresh: onReload,
      child: ListView.separated(
        padding: AniHowSpace.screenPadding,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
        itemBuilder: (context, index) {
          final order = items[index];
          return _OrderCard(
            order: order,
            busy: acting.contains(order.id),
            onOpen: () => onOpen(order),
            onConfirm: () => onConfirm(order),
            onReady: () => onReady(order),
            onComplete: () => onComplete(order),
            onCancel: () => onCancel(order),
          );
        },
      ),
    );
  }
}

class _OrderCard extends StatelessWidget {
  const _OrderCard({
    required this.order,
    required this.busy,
    required this.onOpen,
    required this.onConfirm,
    required this.onReady,
    required this.onComplete,
    required this.onCancel,
  });

  final OrderRecord order;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onConfirm;
  final VoidCallback onReady;
  final VoidCallback onComplete;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ListTile(
              contentPadding: EdgeInsets.zero,
              leading: AniHowAvatar(name: order.buyerName),
              title: Text(order.buyerName),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (order.isWalkIn) const _WalkInLabel(),
                  Text(order.orderNumber ?? 'Order #${order.id}'),
                  Text(AniHowMoney.peso(order.total)),
                  Text(order.itemSummary),
                  if (order.fulfillmentLabel != null) Text(order.fulfillmentLabel!),
                  if (order.placedAt != null) Text(relativeTime(order.placedAt)),
                  if (order.isCancelled && order.cancellationLabel != null)
                    Text(order.cancellationLabel!),
                ],
              ),
              isThreeLine: true,
              trailing: StatusPill.order(order.status, label: order.statusLabel),
              onTap: onOpen,
            ),
            OrderAdvanceButtons(
              order: order,
              busy: busy,
              onConfirm: onConfirm,
              onReady: onReady,
              onComplete: onComplete,
              onCancel: onCancel,
            ),
          ],
        ),
      ),
    );
  }
}

class FarmerOrderDetailScreen extends StatefulWidget {
  const FarmerOrderDetailScreen({super.key, this.order, this.orderId})
      : assert(order != null || orderId != null);

  final OrderRecord? order;
  final int? orderId;

  @override
  State<FarmerOrderDetailScreen> createState() => _FarmerOrderDetailScreenState();
}

class _FarmerOrderDetailScreenState extends State<FarmerOrderDetailScreen> {
  OrderRecord? _order;
  bool _loading = false;
  bool _acting = false;
  Object? _error;

  int get _id => widget.orderId ?? widget.order!.id;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    _loading = widget.order == null;
    _load();
  }

  Future<void> _load() async {
    try {
      final order = await context.read<AuthController>().api.farmerOrder(_id);
      if (!mounted) {
        return;
      }
      setState(() {
        _order = order;
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

  Future<void> _run(Future<OrderRecord> Function() action) async {
    if (_acting) {
      return;
    }
    setState(() => _acting = true);
    try {
      final updated = await action();
      if (mounted) {
        setState(() {
          _order = updated;
          _acting = false;
        });
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _acting = false);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  void _pop() {
    Navigator.of(context).pop(_order);
  }

  Future<void> _confirm() {
    return _run(() => context.read<AuthController>().api.confirmOrder(_id));
  }

  Future<void> _ready() {
    return _run(() => context.read<AuthController>().api.markOrderReady(_id));
  }

  Future<void> _complete() async {
    final order = _order;
    if (order == null) {
      return;
    }
    final amount = await askAmountReceived(context, order);
    if (!mounted || amount == null) {
      return;
    }
    await _run(
      () => context.read<AuthController>().api.completeOrder(_id, amountReceived: amount),
    );
  }

  Future<void> _cancel() async {
    final choice = await askCancellation(context);
    if (!mounted || choice == null) {
      return;
    }
    await _run(
      () => context.read<AuthController>().api.cancelOrder(
            _id,
            reason: choice.reason,
            note: choice.note,
          ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(order?.orderNumber ?? 'Order'),
          leading: BackButton(onPressed: _pop),
        ),
        body: _buildBody(order),
      ),
    );
  }

  Widget _buildBody(OrderRecord? order) {
    if (_loading && order == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (order == null) {
      return Center(child: Text(_error?.toString() ?? 'Order not found.'));
    }
    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Row(
          children: [
            AniHowAvatar(name: order.buyerName),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(order.buyerName, style: Theme.of(context).textTheme.titleMedium),
                  if (order.isWalkIn) const _WalkInLabel(),
                  if (!order.isWalkIn && order.contact != null && order.contact!.isNotEmpty)
                    Text(order.contact!),
                ],
              ),
            ),
            StatusPill.order(order.status, label: order.statusLabel),
          ],
        ),
        const SizedBox(height: AniHowSpace.section),
        PriceBreakdown(
          listed: order.listedTotal,
          tawad: order.tawadDisplay,
          total: order.total,
        ),
        if (order.fulfillmentLabel != null) Text(order.fulfillmentLabel!),
        if (order.fulfillmentNote != null && order.fulfillmentNote!.isNotEmpty)
          Text(order.fulfillmentNote!),
        if (order.placedAt != null) Text(relativeTime(order.placedAt)),
        if (order.amountReceived != null) Text('Cash received ${AniHowMoney.peso(order.amountReceived)}'),
        if (order.isCancelled && order.cancellationLabel != null) Text(order.cancellationLabel!),
        if (order.canBeReviewed) const Text('Review unlocked for the buyer'),
        if (order.reviewRating != null) Text('Buyer rated ${order.reviewRating}'),
        const SizedBox(height: AniHowSpace.section),
        for (final item in order.items) ...[
          Text(item.listingName, style: Theme.of(context).textTheme.titleSmall),
          Text(
            '${item.quantityLabel} · ${AniHowMoney.peso(item.listedPrice)} → ${AniHowMoney.peso(item.lineSubtotal)}',
          ),
          const SizedBox(height: AniHowSpace.cardGap),
        ],
        OrderAdvanceButtons(
          order: order,
          busy: _acting,
          onConfirm: _confirm,
          onReady: _ready,
          onComplete: _complete,
          onCancel: _cancel,
        ),
      ],
    );
  }
}

class _WalkInLabel extends StatelessWidget {
  const _WalkInLabel();

  @override
  Widget build(BuildContext context) {
    return Text('Walk-in', style: Theme.of(context).textTheme.labelSmall);
  }
}

class OrderAdvanceButtons extends StatelessWidget {
  const OrderAdvanceButtons({
    super.key,
    required this.order,
    required this.busy,
    required this.onConfirm,
    required this.onReady,
    required this.onComplete,
    required this.onCancel,
  });

  final OrderRecord order;
  final bool busy;
  final VoidCallback onConfirm;
  final VoidCallback onReady;
  final VoidCallback onComplete;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final confirm = order.canAdvanceTo('confirmed');
    final ready = order.canAdvanceTo('ready');
    final complete = order.canAdvanceTo('completed');
    final cancel = order.canAdvanceTo('cancelled');
    if (!confirm && !ready && !complete && !cancel) {
      return const SizedBox.shrink();
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (confirm) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(label: 'Confirm order', onPressed: onConfirm, busy: busy),
        ],
        if (ready) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(label: 'Mark ready', onPressed: onReady, busy: busy),
        ],
        if (complete) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(label: 'Complete handover', onPressed: onComplete, busy: busy),
        ],
        if (cancel) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          OutlinedButton(
            onPressed: busy ? null : onCancel,
            child: Text(busy ? 'Please wait…' : 'Cancel order'),
          ),
        ],
      ],
    );
  }
}

Future<String?> askAmountReceived(BuildContext context, OrderRecord order) async {
  final controller = TextEditingController(text: order.total);
  try {
    return await showDialog<String>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Cash received'),
          content: AniHowField(
            label: 'Amount received',
            child: TextField(
              controller: controller,
              autofocus: true,
              keyboardType: const TextInputType.numberWithOptions(decimal: true),
              inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[0-9.]'))],
              decoration: InputDecoration(hintText: 'Order total ${AniHowMoney.peso(order.total)}'),
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(dialogContext), child: const Text('Back')),
            TextButton(
              onPressed: () {
                final amount = controller.text.trim();
                if (amount.isEmpty) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(content: Text('Enter the cash amount received.')),
                  );
                  return;
                }
                Navigator.pop(dialogContext, amount);
              },
              child: const Text('Record'),
            ),
          ],
        );
      },
    );
  } finally {
    controller.dispose();
  }
}

class CancellationChoice {
  const CancellationChoice({required this.reason, this.note});

  final String reason;
  final String? note;
}

Future<CancellationChoice?> askCancellation(BuildContext context) {
  String? reason;
  final note = TextEditingController();
  return showDialog<CancellationChoice>(
    context: context,
    builder: (context) {
      return StatefulBuilder(
        builder: (context, setState) {
          return AlertDialog(
            title: const Text('Cancel order'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('A no-show is a cancellation reason, not a separate status.'),
                  const SizedBox(height: AniHowSpace.cardGap),
                  RadioGroup<String>(
                    groupValue: reason,
                    onChanged: (value) => setState(() => reason = value),
                    child: Column(
                      children: [
                        for (final option in _sellerCancelReasons)
                          RadioListTile<String>(
                            title: Text(option.label),
                            value: option.value,
                            contentPadding: EdgeInsets.zero,
                            selected: reason == option.value,
                          ),
                      ],
                    ),
                  ),
                  AniHowField(
                    label: 'Note (optional)',
                    child: TextField(controller: note, maxLength: 500),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: const Text('Back'),
              ),
              TextButton(
                onPressed: () {
                  if (reason == null) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(content: Text('Choose a cancellation reason.')),
                    );
                    return;
                  }
                  final trimmed = note.text.trim();
                  Navigator.pop(
                    context,
                    CancellationChoice(
                      reason: reason!,
                      note: trimmed.isEmpty ? null : trimmed,
                    ),
                  );
                },
                child: const Text('Cancel order'),
              ),
            ],
          );
        },
      );
    },
  ).whenComplete(note.dispose);
}
