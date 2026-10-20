import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/order_card_parts.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/order_status_poll.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/order_payment_summary.dart';
import '../../widgets/status_pill.dart';
import '../chat/order_chat_screen.dart';
import 'farmer_payments_screen.dart';
import 'walk_in_sale_screen.dart';

const _sellerCancelReasons = ['seller_declined', 'no_show', 'other'];

class FarmerOrdersScreen extends StatefulWidget {
  const FarmerOrdersScreen({
    super.key,
    this.active = true,
    this.showAccountMenu = false,
  });

  final bool active;

  /// Set by [FarmerShell] only.
  final bool showAccountMenu;

  @override
  State<FarmerOrdersScreen> createState() => _FarmerOrdersScreenState();
}

class _FarmerOrdersScreenState extends State<FarmerOrdersScreen>
    with WidgetsBindingObserver, RouteAware, OrderStatusPoll {
  List<OrderRecord> _items = const [];
  bool _loading = true;
  Object? _error;
  final Set<int> _acting = {};
  bool _showFab = true;

  @override
  bool get orderPollEnabled => widget.active;

  @override
  bool get orderPollBlocked => _acting.isNotEmpty;

  @override
  void initState() {
    super.initState();
    _reload();
    startOrderStatusPoll();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    bindOrderStatusRoute();
  }

  @override
  void didUpdateWidget(FarmerOrdersScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active) {
      pollOrderStatus(force: true);
    }
  }

  @override
  void dispose() {
    stopOrderStatusPoll();
    super.dispose();
  }

  @override
  Future<void> refreshPolledOrders() => _reload();

  Future<void> _reload() async {
    setState(() {
      _loading = _items.isEmpty;
      _error = null;
    });
    try {
      final auth = context.read<AuthController>();
      if (auth.user != null) {
        try {
          await auth.refreshUser();
        } catch (_) {}
      }
      final items = await auth.api.farmerOrders();
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

  Future<void> _run(
    OrderRecord order,
    Future<OrderRecord> Function() action,
  ) async {
    if (_acting.contains(order.id)) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _acting.add(order.id));
    try {
      final updated = await action();
      if (!mounted) {
        return;
      }
      _replace(updated);
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _acting.remove(order.id));
      } else {
        _acting.remove(order.id);
      }
    }
  }

  Future<void> _confirm(OrderRecord order) {
    return _run(
      order,
      () => context.read<AuthController>().api.confirmOrder(order.id),
    );
  }

  Future<void> _ready(OrderRecord order) {
    return _run(
      order,
      () => context.read<AuthController>().api.markOrderReady(order.id),
    );
  }

  Future<void> _complete(OrderRecord order) async {
    final api = context.read<AuthController>().api;
    if (order.paymentIsPaid) {
      await _run(order, () => api.completeOrder(order.id));
      return;
    }
    final amount = await askAmountReceived(context, order);
    if (!mounted || amount == null) {
      return;
    }
    await _run(
      order,
      () => api.completeOrder(order.id, amountReceived: amount),
    );
  }

  Future<void> _cancel(OrderRecord order) async {
    final api = context.read<AuthController>().api;
    final choice = await askCancellation(context);
    if (!mounted || choice == null) {
      return;
    }
    await _run(
      order,
      () => api.cancelOrder(order.id, reason: choice.reason, note: choice.note),
    );
  }

  bool _walkInAllowed(BuildContext context) {
    final user = context.watch<AuthController>().user;
    return (user?.canRecordWalkInSales ?? false) &&
        (user?.farmFeatures.walkIn ?? true);
  }

  Future<void> _openWalkIn() async {
    final recorded = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => const WalkInSaleScreen()));
    if (!mounted || recorded != true) {
      return;
    }
    await _reload();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final activeCount = _items.where(_orderIsOpen).length;
    final tabs = _statusTabs(s, activeCount);
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: widget.showAccountMenu
            ? mainTabAppBar(
                title: s.orders,
                showAccountMenu: true,
                bottom: onBrandTabBar(tabs: tabs),
              )
            : null,
        floatingActionButton: _walkInAllowed(context)
            ? AnimatedSlide(
                offset: _showFab ? Offset.zero : const Offset(0, 2),
                duration: const Duration(milliseconds: 200),
                child: FloatingActionButton.extended(
                  heroTag: 'farmer-walk-in',
                  tooltip: s.recordWalkIn,
                  backgroundColor: Theme.of(context).brightness == Brightness.dark
                      ? AniHowColors.switchOn
                      : AniHowColors.brand,
                  foregroundColor: Colors.white,
                  shape: const StadiumBorder(),
                  extendedIconLabelSpacing: 8,
                  onPressed: _openWalkIn,
                  icon: const Icon(Icons.storefront_outlined),
                  label: Text(s.walkInSale),
                ),
              )
            : null,
        body: Column(
          children: [
            if (!widget.showAccountMenu) TabBar(tabs: tabs),
            Expanded(
              child: NotificationListener<UserScrollNotification>(
                onNotification: _onScroll,
                child: _body(s),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _onScroll(UserScrollNotification notification) {
    final show = switch (notification.direction) {
      ScrollDirection.reverse => false,
      ScrollDirection.forward => true,
      ScrollDirection.idle => _showFab,
    };
    if (show != _showFab) {
      setState(() => _showFab = show);
    }
    return false;
  }

  Widget _body(AppStrings s) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    final placed = _sorted(_items.where((order) => order.isPlaced));
    final confirmed = _sorted(_items.where((order) => order.isConfirmed));
    final ready = _sorted(_items.where((order) => order.isReady));
    final completed = _sorted(_items.where((order) => order.isCompleted));
    final cancelled = _sorted(_items.where((order) => order.isCancelled));
    return TabBarView(
      children: [
        _ActiveOrders(
          placed: placed,
          confirmed: confirmed,
          ready: ready,
          payments: _PaymentsCard(
            toCheck: _items
                .where((order) => !order.isCancelled && order.isPaymentSent)
                .length,
            refunds: _items
                .where(
                  (order) => !order.isCancelled && order.paymentStatus == 'refund_due',
                )
                .length,
            onOpen: _openPayments,
          ),
          emptyLabel: s.noOrders,
          acting: _acting,
          onReload: _reload,
          onOpen: _open,
          onConfirm: _confirm,
          onReady: _ready,
          onComplete: _complete,
          onCancel: _cancel,
        ),
        _OrderList(
          items: completed,
          emptyLabel: s.noCompletedOrders,
          past: true,
          acting: _acting,
          onReload: _reload,
          onOpen: _open,
          onConfirm: _confirm,
          onReady: _ready,
          onComplete: _complete,
          onCancel: _cancel,
        ),
        _OrderList(
          items: cancelled,
          emptyLabel: s.noCancelledOrders,
          past: true,
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

  Future<void> _openPayments() async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute(builder: (_) => const FarmerPaymentsScreen()),
    );
    if (mounted) {
      await _reload();
    }
  }
}

bool _orderIsOpen(OrderRecord order) {
  return order.isPlaced || order.isConfirmed || order.isReady;
}

List<OrderRecord> _sorted(Iterable<OrderRecord> orders) {
  final items = orders.toList();
  items.sort((a, b) {
    final left = DateTime.tryParse(a.placedAt ?? '') ?? DateTime(1970);
    final right = DateTime.tryParse(b.placedAt ?? '') ?? DateTime(1970);
    return right.compareTo(left);
  });
  return items;
}

List<Widget> _statusTabs(AppStrings s, int activeCount) {
  return [
    Tab(
      height: AniHowSpace.tabHeight,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(s.activeReservations),
          if (activeCount > 0) ...[
            const SizedBox(width: 6),
            DecoratedBox(
              decoration: BoxDecoration(
                color: const Color(0xFFFBF3DC),
                borderRadius: BorderRadius.circular(999),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 1),
                child: Text(
                  '$activeCount',
                  style: const TextStyle(
                    color: Color(0xFF7A4E0C),
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    ),
    Tab(height: AniHowSpace.tabHeight, text: s.ordersTabCompleted),
    Tab(height: AniHowSpace.tabHeight, text: s.ordersTabCancelled),
  ];
}

class _PaymentsCard extends StatelessWidget {
  const _PaymentsCard({
    required this.toCheck,
    required this.refunds,
    required this.onOpen,
  });

  final int toCheck;
  final int refunds;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final alert = toCheck > 0;
    final muted = theme.textTheme.bodySmall?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
    );
    final fill = alert
        ? (dark ? const Color(0xFF5A431C) : const Color(0xFFFBF3DC))
        : theme.cardColor;
    final ink = alert
        ? (dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C))
        : theme.colorScheme.onSurface;
    return Material(
      color: fill,
      borderRadius: BorderRadius.circular(AniHowSpace.radius),
      child: InkWell(
        key: const ValueKey('open-payments'),
        borderRadius: BorderRadius.circular(AniHowSpace.radius),
        onTap: onOpen,
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            child: Row(
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    color: alert ? theme.cardColor : accentTint(context),
                    shape: BoxShape.circle,
                  ),
                  child: SizedBox(
                    width: 40,
                    height: 40,
                    child: Icon(
                      Icons.payments_outlined,
                      color: alert ? ink : readableAccent(context),
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        alert ? s.paymentsToCheck(toCheck) : s.payments,
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: alert ? ink : null,
                        ),
                      ),
                      Text(
                        alert ? s.buyersSentProof : s.allPaymentsChecked,
                        style: muted?.copyWith(color: alert ? ink : null),
                      ),
                      if (refunds > 0)
                        Text(s.refundsDue(refunds), style: muted),
                    ],
                  ),
                ),
                if (alert)
                  DecoratedBox(
                    decoration: BoxDecoration(
                      color: dark ? const Color(0xFF7A4E0C) : const Color(0xFFF3E2B3),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                      child: Text(
                        s.reviewPayments,
                        style: TextStyle(
                          color: dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C),
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                        ),
                      ),
                    ),
                  )
                else
                  Icon(Icons.chevron_right, color: theme.colorScheme.onSurface.withValues(alpha: 0.6)),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ActiveOrders extends StatelessWidget {
  const _ActiveOrders({
    required this.placed,
    required this.confirmed,
    required this.ready,
    required this.payments,
    required this.emptyLabel,
    required this.acting,
    required this.onReload,
    required this.onOpen,
    required this.onConfirm,
    required this.onReady,
    required this.onComplete,
    required this.onCancel,
  });

  final List<OrderRecord> placed;
  final List<OrderRecord> confirmed;
  final List<OrderRecord> ready;
  final Widget payments;
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
    final s = AppStrings.of(context);
    final children = <Widget>[payments];
    void addSection(String title, List<OrderRecord> orders) {
      if (orders.isEmpty) {
        return;
      }
      children.add(const SizedBox(height: AniHowSpace.section));
      children.add(OrderSectionLabel(title));
      children.add(const SizedBox(height: 8));
      for (var index = 0; index < orders.length; index++) {
        if (index > 0) {
          children.add(const SizedBox(height: AniHowSpace.cardGap));
        }
        children.add(
          _SellerOrderCard(
            order: orders[index],
            past: false,
            busy: acting.contains(orders[index].id),
            onOpen: () => onOpen(orders[index]),
            onConfirm: () => onConfirm(orders[index]),
            onReady: () => onReady(orders[index]),
            onComplete: () => onComplete(orders[index]),
            onCancel: () => onCancel(orders[index]),
          ),
        );
      }
    }

    addSection(s.sectionWithCount(s.newOrders, placed.length), placed);
    addSection(s.sectionWithCount(s.confirmed, confirmed.length), confirmed);
    addSection(s.sectionWithCount(s.readyForPickup, ready.length), ready);
    if (placed.isEmpty && confirmed.isEmpty && ready.isEmpty) {
      children.add(
        Padding(
          padding: const EdgeInsets.only(top: 48),
          child: Center(child: Text(emptyLabel)),
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onReload,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AniHowSpace.screen,
          AniHowSpace.cardGap,
          AniHowSpace.screen,
          96,
        ),
        children: children,
      ),
    );
  }
}

class _OrderList extends StatelessWidget {
  const _OrderList({
    required this.items,
    required this.emptyLabel,
    required this.past,
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
  final bool past;
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
      return RefreshIndicator(
        onRefresh: onReload,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 80),
            Center(child: Text(emptyLabel)),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: onReload,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(
          AniHowSpace.screen,
          AniHowSpace.cardGap,
          AniHowSpace.screen,
          96,
        ),
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
        itemBuilder: (context, index) {
          final order = items[index];
          return _SellerOrderCard(
            order: order,
            past: past,
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

class _SellerOrderCard extends StatelessWidget {
  const _SellerOrderCard({
    required this.order,
    required this.past,
    required this.busy,
    required this.onOpen,
    required this.onConfirm,
    required this.onReady,
    required this.onComplete,
    required this.onCancel,
  });

  final OrderRecord order;
  final bool past;
  final bool busy;
  final VoidCallback onOpen;
  final VoidCallback onConfirm;
  final VoidCallback onReady;
  final VoidCallback onComplete;
  final VoidCallback onCancel;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final step = OrderNextStep.of(order);
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
    );
    final when = orderWhen(s, order, active: !past);
    final title = order.isWalkIn ? s.walkInSale : order.buyerName;
    final meta = [
      '#${order.id}',
      if (when.isNotEmpty) when,
    ].join(' · ');
    final itemLine = orderItemLine(s, order);
    final showChat = !order.isWalkIn;
    final showStep = step.kind != null;
    final showMenu = step.canCancel;
    final showMiddle = step.blockedByPayment || step.notPaidYet;
    final showFooter = showChat || showStep || showMenu || showMiddle;

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            InkWell(
              onTap: onOpen,
              borderRadius: BorderRadius.circular(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (order.isWalkIn)
                        DecoratedBox(
                          decoration: BoxDecoration(
                            color: accentTint(context),
                            shape: BoxShape.circle,
                          ),
                          child: SizedBox(
                            width: 40,
                            height: 40,
                            child: Icon(
                              Icons.storefront_outlined,
                              color: readableAccent(context),
                              size: 20,
                            ),
                          ),
                        )
                      else
                        AniHowAvatar(
                          name: order.buyerName,
                          imageUrl: order.buyerAvatarUrl,
                          radius: 20,
                        ),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            Text(meta, style: muted),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        AniHowMoney.peso(order.total),
                        style: theme.textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ),
                  if (itemLine.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(itemLine, style: theme.textTheme.bodyMedium),
                  ],
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    crossAxisAlignment: WrapCrossAlignment.center,
                    children: [
                      if (order.isCancelled)
                        _TinyChip(
                          text: s.cancellationReasonText(
                            order.cancellationReason,
                            fallback: order.cancellationLabel,
                          ),
                          background: theme.brightness == Brightness.dark
                              ? const Color(0xFF5C2A2A)
                              : const Color(0xFFFDECEC),
                          foreground: theme.brightness == Brightness.dark
                              ? const Color(0xFFF5C2C0)
                              : const Color(0xFF8C1D18),
                        )
                      else
                        _TinyChip(
                          text: orderPaymentWords(s, order),
                          background: _paymentFill(context, order),
                          foreground: _paymentInk(context, order),
                        ),
                      if (order.fulfillmentLabel != null)
                        Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              Icons.place_outlined,
                              size: 14,
                              color: theme.colorScheme.onSurface.withValues(alpha: 0.62),
                            ),
                            const SizedBox(width: 2),
                            Text(order.fulfillmentLabel!, style: muted),
                          ],
                        ),
                    ],
                  ),
                  if (order.isCancelled && order.hasCancellationNote)
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(order.cancellationNote!.trim(), style: muted),
                    ),
                  if (tawadIsActive(order.tawadDisplay))
                    Padding(
                      padding: const EdgeInsets.only(top: 4),
                      child: Text(
                        s.discountTawadMinus(AniHowMoney.peso(order.tawadDisplay)),
                        style: muted?.copyWith(
                          color: readableAccent(context),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            if (showFooter) ...[
              const Divider(height: 16),
              Row(
                children: [
                  if (showChat)
                    TextButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => OrderChatScreen(order: order),
                          ),
                        );
                      },
                      icon: const Icon(Icons.chat_bubble_outline, size: 18),
                      label: Text(s.chat),
                      style: TextButton.styleFrom(
                        minimumSize: const Size(48, 48),
                        padding: const EdgeInsets.symmetric(horizontal: 4),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                  Expanded(
                    child: showMiddle
                        ? Text(
                            step.blockedByPayment
                                ? s.waitingForPayment
                                : s.notPaidYet,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            textAlign: TextAlign.end,
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: step.blockedByPayment
                                  ? (theme.brightness == Brightness.dark
                                      ? const Color(0xFFF6D48A)
                                      : const Color(0xFF7A4E0C))
                                  : theme.colorScheme.onSurface.withValues(alpha: 0.72),
                              fontWeight:
                                  step.blockedByPayment ? FontWeight.w700 : null,
                            ),
                          )
                        : const SizedBox.shrink(),
                  ),
                  if (showMenu)
                    PopupMenuButton<String>(
                      tooltip: s.moreActions,
                      padding: EdgeInsets.zero,
                      constraints: const BoxConstraints(minWidth: 40, minHeight: 48),
                      icon: const Icon(Icons.more_vert),
                      color: theme.cardColor,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(14),
                      ),
                      onSelected: (_) => onCancel(),
                      itemBuilder: (context) => [
                        PopupMenuItem(
                          value: 'cancel',
                          child: Text(
                            s.cancelOrder,
                            style: TextStyle(color: theme.colorScheme.error),
                          ),
                        ),
                      ],
                    ),
                  if (showStep)
                    FilledButton(
                      key: step.buttonKey,
                      onPressed: !step.enabled || busy
                          ? null
                          : step.press(
                              onConfirm: onConfirm,
                              onReady: onReady,
                              onComplete: onComplete,
                            ),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size(0, 40),
                        maximumSize: const Size(160, 40),
                        backgroundColor: AniHowColors.brand,
                        foregroundColor: Colors.white,
                        disabledBackgroundColor: theme.colorScheme.onSurface.withValues(alpha: 0.12),
                        disabledForegroundColor: theme.colorScheme.onSurface.withValues(alpha: 0.72),
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        textStyle: const TextStyle(
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                        ),
                        shape: const StadiumBorder(),
                      ),
                      child: busy
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(step.shortLabel(s)),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

(Color, Color) _paymentColors(BuildContext context, OrderRecord order) {
  final dark = Theme.of(context).brightness == Brightness.dark;
  if (order.paymentMethod != 'online_transfer') {
    return dark
        ? (const Color(0xFF2E3834), const Color(0xFFD5D1C8))
        : (const Color(0xFFEEEDE8), const Color(0xFF3E4642));
  }
  return switch (order.paymentStatus) {
    'payment_sent' => dark
        ? (const Color(0xFF5A431C), const Color(0xFFF6D48A))
        : (const Color(0xFFFBF3DC), const Color(0xFF7A4E0C)),
    'awaiting_payment' => dark
        ? (const Color(0xFF1E3A5C), const Color(0xFFC5DDF5))
        : (const Color(0xFFE7F0FA), const Color(0xFF1A4E86)),
    'paid' => dark
        ? (const Color(0xFF245C42), const Color(0xFFB7E4C7))
        : (const Color(0xFFE5F4EB), const Color(0xFF145C38)),
    'refund_due' => dark
        ? (const Color(0xFF5C2A2A), const Color(0xFFF5C2C0))
        : (const Color(0xFFFDECEC), const Color(0xFF8C1D18)),
    _ => dark
        ? (const Color(0xFF2E3834), const Color(0xFFD5D1C8))
        : (const Color(0xFFEEEDE8), const Color(0xFF3E4642)),
  };
}

Color _paymentFill(BuildContext context, OrderRecord order) =>
    _paymentColors(context, order).$1;

Color _paymentInk(BuildContext context, OrderRecord order) =>
    _paymentColors(context, order).$2;

class _TinyChip extends StatelessWidget {
  const _TinyChip({
    required this.text,
    required this.background,
    required this.foreground,
  });

  final String text;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    if (text.trim().isEmpty) {
      return const SizedBox.shrink();
    }
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Text(
          text,
          style: TextStyle(
            color: foreground,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

enum OrderStepKind { confirm, ready, complete }

/// The one decision for which seller step comes next, and whether payment blocks it.
class OrderNextStep {
  const OrderNextStep({
    this.kind,
    required this.enabled,
    required this.blockedByPayment,
    required this.notPaidYet,
    required this.canCancel,
  });

  final OrderStepKind? kind;
  final bool enabled;
  final bool blockedByPayment;
  final bool notPaidYet;
  final bool canCancel;

  Key? get buttonKey => switch (kind) {
    OrderStepKind.ready => const ValueKey('mark-ready'),
    OrderStepKind.complete => const ValueKey('complete-order'),
    _ => null,
  };

  String shortLabel(AppStrings s) => switch (kind) {
    OrderStepKind.confirm => s.confirmStep,
    OrderStepKind.ready => s.markReady,
    OrderStepKind.complete => s.completeStep,
    null => '',
  };

  String detailLabel(AppStrings s) => switch (kind) {
    OrderStepKind.confirm => s.confirmOrder,
    OrderStepKind.ready => s.markReady,
    OrderStepKind.complete => s.completeHandover,
    null => '',
  };

  VoidCallback? press({
    required VoidCallback onConfirm,
    required VoidCallback onReady,
    required VoidCallback onComplete,
  }) {
    return switch (kind) {
      OrderStepKind.confirm => onConfirm,
      OrderStepKind.ready => onReady,
      OrderStepKind.complete => onComplete,
      null => null,
    };
  }

  static OrderNextStep of(OrderRecord order) {
    final confirm = order.canAdvanceTo('confirmed');
    final ready = order.canAdvanceTo('ready');
    final complete = order.canAdvanceTo('completed');
    final waiting = order.isPaymentTracked &&
        !order.paymentIsPaid &&
        order.paymentStatus != 'refunded';
    final showReady = ready || (waiting && order.isConfirmed);
    final showComplete = complete || (waiting && order.isReady);
    OrderStepKind? kind;
    var enabled = false;
    if (confirm) {
      kind = OrderStepKind.confirm;
      enabled = true;
    } else if (showReady) {
      kind = OrderStepKind.ready;
      enabled = ready && !waiting;
    } else if (showComplete) {
      kind = OrderStepKind.complete;
      enabled = complete && !waiting;
    }
    return OrderNextStep(
      kind: kind,
      enabled: enabled,
      blockedByPayment: kind != OrderStepKind.confirm && !enabled && waiting && kind != null,
      notPaidYet: confirm && waiting,
      canCancel: order.canAdvanceTo('cancelled'),
    );
  }
}

class FarmerOrderDetailScreen extends StatefulWidget {
  const FarmerOrderDetailScreen({super.key, this.order, this.orderId})
    : assert(order != null || orderId != null);

  final OrderRecord? order;
  final int? orderId;

  @override
  State<FarmerOrderDetailScreen> createState() =>
      _FarmerOrderDetailScreenState();
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
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _acting = true);
    try {
      final updated = await action();
      if (!mounted) {
        return;
      }
      setState(() {
        _order = updated;
        _acting = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _acting = false);
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
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
    final api = context.read<AuthController>().api;
    if (order.paymentIsPaid) {
      await _run(() => api.completeOrder(_id));
      return;
    }
    final amount = await askAmountReceived(context, order);
    if (!mounted || amount == null) {
      return;
    }
    await _run(() => api.completeOrder(_id, amountReceived: amount));
  }

  Future<void> _cancel() async {
    final api = context.read<AuthController>().api;
    final choice = await askCancellation(context);
    if (!mounted || choice == null) {
      return;
    }
    await _run(
      () => api.cancelOrder(_id, reason: choice.reason, note: choice.note),
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
          title: Text(order?.buyerName ?? AppStrings.of(context).order),
          leading: BackButton(onPressed: _pop),
        ),
        body: _buildBody(order),
      ),
    );
  }

  Widget _buildBody(OrderRecord? order) {
    final s = AppStrings.of(context);
    if (_loading && order == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (order == null) {
      return Center(child: Text(_error?.toString() ?? s.orderNotFound));
    }
    final theme = Theme.of(context);
    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    AniHowAvatar(
                      name: order.buyerName,
                      imageUrl: order.buyerAvatarUrl,
                      radius: 20,
                    ),
                    const SizedBox(width: AniHowSpace.cardGap),
                    Expanded(
                      child: Text(
                        order.buyerName,
                        style: theme.textTheme.titleMedium,
                      ),
                    ),
                    Flexible(
                      child: StatusPill.order(
                        order.status,
                        strings: s,
                        fulfillmentPreference: order.fulfillmentPreference,
                      ),
                    ),
                  ],
                ),
                if (order.isWalkIn)
                  OrderMetaRow(icon: Icons.storefront_outlined, text: s.walkIn),
                if (!order.isWalkIn &&
                    order.contact != null &&
                    order.contact!.isNotEmpty)
                  OrderMetaRow(icon: Icons.call_outlined, text: order.contact!),
                if (!order.isWalkIn)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => OrderChatScreen(order: order),
                          ),
                        );
                      },
                      icon: const Icon(Icons.chat_bubble_outline, size: 18),
                      label: Text(s.chatWithBuyer),
                      style: TextButton.styleFrom(
                        padding: const EdgeInsets.only(top: 10, right: 8),
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
        if (order.isPaymentSent || order.paymentIsPaid) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          OrderPaymentSummary(
            order: order,
            forSeller: true,
            onChanged: (updated) => setState(() => _order = updated),
          ),
        ],
        if (order.isReady && order.paymentMethod == 'online_transfer') ...[
          const SizedBox(height: AniHowSpace.cardGap),
          AniHowHintCard(
            icon: Icons.qr_code_2_outlined,
            title: s.onlinePayment,
            tone: AniHowHintTone.brand,
          ),
        ] else if (order.isReady) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          AniHowHintCard(
            icon: Icons.payments_outlined,
            title: s.cashAtMeetup,
            tone: AniHowHintTone.cash,
          ),
        ],
        const SizedBox(height: AniHowSpace.cardGap),
        Card(
          child: Padding(
            padding: AniHowSpace.cardPadding,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                OrderTotalHero(
                  total: order.total,
                  tawadLine: tawadIsActive(order.tawadDisplay)
                      ? s.discountTawadMinus(
                          AniHowMoney.peso(order.tawadDisplay),
                        )
                      : null,
                ),
                if (order.fulfillmentLabel != null)
                  OrderMetaRow(
                    icon: Icons.handshake_outlined,
                    text: order.fulfillmentLabel!,
                  ),
                OrderMetaRow(
                  icon: Icons.payments_outlined,
                  text: s.paymentMethodLabel(order.paymentMethod),
                ),
                if (order.fulfillmentNote != null &&
                    order.fulfillmentNote!.isNotEmpty)
                  OrderMetaRow(
                    icon: Icons.notes_outlined,
                    text: order.fulfillmentNote!,
                  ),
                if (order.placedAt != null)
                  OrderMetaRow(
                    icon: Icons.schedule_outlined,
                    text: relativeTime(order.placedAt),
                  ),
                if (order.amountReceived != null)
                  OrderMetaRow(
                    icon: Icons.payments_outlined,
                    text: order.paymentMethod == 'online_transfer' && order.paymentIsPaid
                        ? s.paidOnlineLine(
                            AniHowMoney.peso(order.amountReceived),
                            s.walletLabel(order.latestProof?.wallet),
                          )
                        : s.cashReceivedLine(
                            AniHowMoney.peso(order.amountReceived),
                          ),
                  ),
                if (order.hasCancellationReason)
                  OrderMetaRow(
                    icon: Icons.info_outline,
                    text: s.cancellationReasonText(
                      order.cancellationReason,
                      fallback: order.cancellationLabel,
                    ),
                  ),
                if (order.hasCancellationNote)
                  OrderMetaRow(
                    icon: Icons.notes_outlined,
                    text: order.cancellationNote!.trim(),
                  ),
                if (order.canBeReviewed)
                  OrderMetaRow(
                    icon: Icons.star_outline,
                    text: s.reviewUnlocked,
                  ),
                if (order.reviewRating != null)
                  OrderMetaRow(
                    icon: Icons.star_outline,
                    text: s.buyerRated(order.reviewRating!),
                  ),
              ],
            ),
          ),
        ),
        if (order.items.isNotEmpty) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          Card(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(s.items, style: theme.textTheme.titleMedium),
                  const SizedBox(height: 4),
                  for (final item in order.items)
                    Padding(
                      padding: const EdgeInsets.only(top: 10),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.listingName,
                                  style: theme.textTheme.titleSmall,
                                ),
                                Text(
                                  item.quantityLabel,
                                  style: theme.textTheme.bodyMedium?.copyWith(
                                    color: theme.colorScheme.onSurface
                                        .withValues(alpha: 0.68),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            AniHowMoney.peso(
                              item.lineTotal ?? item.lineSubtotal,
                            ),
                            style: theme.textTheme.titleSmall,
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
        if (order.orderNumber != null) ...[
          const SizedBox(height: AniHowSpace.section),
          Text(
            order.orderNumber!,
            textAlign: TextAlign.center,
            style: theme.textTheme.labelSmall,
          ),
        ],
        if (order.isPaymentTracked && !order.isPaymentSent && !order.paymentIsPaid) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          OrderPaymentSummary(
            order: order,
            forSeller: true,
            onChanged: (updated) => setState(() => _order = updated),
          ),
        ],
        OrderAdvanceButtons(
          order: order,
          busy: _acting,
          onConfirm: _confirm,
          onReady: _ready,
          onComplete: _complete,
          onCancel: _cancel,
        ),
        const SizedBox(height: 48),
      ],
    );
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
    final step = OrderNextStep.of(order);
    final confirm = step.kind == OrderStepKind.confirm;
    final showReady = step.kind == OrderStepKind.ready;
    final showComplete = step.kind == OrderStepKind.complete;
    final readyEnabled = showReady && step.enabled;
    final completeEnabled = showComplete && step.enabled;
    final cancel = step.canCancel;
    if (step.kind == null && !cancel) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (confirm) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(
            label: step.detailLabel(s),
            onPressed: onConfirm,
            busy: busy,
          ),
          if (order.isPaymentTracked &&
              !order.paymentIsPaid &&
              order.paymentStatus != 'refunded')
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                s.confirmWhileUnpaid,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.62),
                ),
              ),
            ),
        ],
        if (showReady) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(
            key: const ValueKey('mark-ready'),
            label: s.markReady,
            onPressed: readyEnabled ? onReady : null,
            busy: busy,
          ),
          if (!readyEnabled) Text(s.waitingForPayment),
        ],
        if (showComplete) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          PrimaryButton(
            key: const ValueKey('complete-order'),
            label: s.completeHandover,
            onPressed: completeEnabled ? onComplete : null,
            busy: busy,
          ),
          if (!completeEnabled) Text(s.waitingForPayment),
        ],
        if (cancel) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          OutlinedButton(
            onPressed: busy ? null : onCancel,
            child: Text(busy ? s.pleaseWait : s.cancelOrder),
          ),
        ],
      ],
    );
  }
}

Future<String?> askAmountReceived(BuildContext context, OrderRecord order) {
  return showDialog<String>(
    context: context,
    builder: (_) => _AmountReceivedDialog(order: order),
  );
}

class _AmountReceivedDialog extends StatefulWidget {
  const _AmountReceivedDialog({required this.order});

  final OrderRecord order;

  @override
  State<_AmountReceivedDialog> createState() => _AmountReceivedDialogState();
}

class _AmountReceivedDialogState extends State<_AmountReceivedDialog> {
  late final TextEditingController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TextEditingController(text: widget.order.total);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return AlertDialog(
      title: Text(
        widget.order.paymentMethod == 'online_transfer'
            ? s.amountReceivedOnline
            : s.cashReceived,
      ),
      content: AniHowField(
        label: widget.order.paymentMethod == 'online_transfer'
            ? s.amountReceivedOnline
            : s.amountReceived,
        child: TextField(
          controller: _controller,
          autofocus: true,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          inputFormatters: [
            FilteringTextInputFormatter.allow(RegExp(r'[0-9.]')),
          ],
          decoration: InputDecoration(
            hintText: s.orderTotalHint(AniHowMoney.peso(widget.order.total)),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(s.back),
        ),
        TextButton(
          onPressed: () {
            final amount = _controller.text.trim();
            if (amount.isEmpty) {
              ScaffoldMessenger.of(context)
                  .showSnackBar(SnackBar(content: Text(s.enterCashReceived)));
              return;
            }
            Navigator.pop(context, amount);
          },
          child: Text(s.record),
        ),
      ],
    );
  }
}

class CancellationChoice {
  const CancellationChoice({required this.reason, this.note});

  final String reason;
  final String? note;
}

Future<CancellationChoice?> askCancellation(BuildContext context) {
  return showDialog<CancellationChoice>(
    context: context,
    builder: (_) => const _CancelOrderDialog(),
  );
}

class _CancelOrderDialog extends StatefulWidget {
  const _CancelOrderDialog();

  @override
  State<_CancelOrderDialog> createState() => _CancelOrderDialogState();
}

class _CancelOrderDialogState extends State<_CancelOrderDialog> {
  String? _reason;
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return AlertDialog(
      title: Text(s.cancelOrder),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.cancelReasonHint),
            const SizedBox(height: AniHowSpace.cardGap),
            RadioGroup<String>(
              groupValue: _reason,
              onChanged: (value) => setState(() => _reason = value),
              child: Column(
                children: [
                  for (final value in _sellerCancelReasons)
                    RadioListTile<String>(
                      title: Text(s.sellerCancelReason(value)),
                      value: value,
                      contentPadding: EdgeInsets.zero,
                      selected: _reason == value,
                    ),
                ],
              ),
            ),
            AniHowField(
              label: s.noteOptional,
              child: TextField(controller: _note, maxLength: 500),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(s.back),
        ),
        TextButton(
          onPressed: () {
            if (_reason == null) {
              ScaffoldMessenger.of(context)
                  .showSnackBar(SnackBar(content: Text(s.chooseCancelReason)));
              return;
            }
            final trimmed = _note.text.trim();
            Navigator.pop(
              context,
              CancellationChoice(
                reason: _reason!,
                note: trimmed.isEmpty ? null : trimmed,
              ),
            );
          },
          child: Text(s.cancelOrder),
        ),
      ],
    );
  }
}
