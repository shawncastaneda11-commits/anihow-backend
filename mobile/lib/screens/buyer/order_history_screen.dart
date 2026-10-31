import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/async_view.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/buyer_cancel_order_button.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/order_card_parts.dart';
import '../../widgets/order_look.dart';
import '../../widgets/order_progress.dart';
import '../../widgets/order_status_poll.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../../widgets/unverified_email_banner.dart';
import '../chat/order_chat_screen.dart';
import 'buyer_order_detail_screen.dart';
import 'pay_now_screen.dart';
import 'reservation_detail_screen.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({
    super.key,
    this.active = true,
    this.showAccountMenu = false,
  });

  final bool active;

  /// Set by [BuyerShell] only. Pushed copies of this screen omit the menu.
  final bool showAccountMenu;

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen>
    with WidgetsBindingObserver, RouteAware, OrderStatusPoll {
  late Future<List<OrderRecord>> _future;
  late Future<List<ReservationRecord>> _reservations;

  @override
  bool get orderPollEnabled => widget.active;

  @override
  void initState() {
    super.initState();
    final api = context.read<AuthController>().api;
    _future = api.buyerOrders();
    _reservations = api.buyerReservations();
    startOrderStatusPoll();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    bindOrderStatusRoute();
  }

  @override
  void didUpdateWidget(OrderHistoryScreen oldWidget) {
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

  /// Polls and tab switches refresh both tabs, so a reservation made on a
  /// listing shows here without waiting for a cancel or a restart.
  @override
  Future<void> refreshPolledOrders() async {
    await Future.wait([_reload(), _reloadReservations()]);
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.buyerOrders();
    setState(() {
      _future = future;
    });
    await future;
  }

  Future<void> _reloadReservations() async {
    final future = context.read<AuthController>().api.buyerReservations();
    setState(() {
      _reservations = future;
    });
    await future;
  }

  Future<void> _cancelReservation(ReservationRecord reservation) async {
    await context.read<AuthController>().api.cancelBuyerReservation(
      reservation.id,
    );
    if (mounted) {
      await _reloadReservations();
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: mainTabAppBar(
          title: s.orderHistory,
          showAccountMenu: widget.showAccountMenu,
          bottom: onBrandTabBar(
            tabs: [
              Tab(text: s.ordersTab),
              Tab(text: s.reservationsTab),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            Column(
              children: [
                mainTabBodyGap,
                const UnverifiedEmailBanner(),
                Expanded(
                  child: AsyncView<List<OrderRecord>>(
                    future: _future,
                    onRetry: _reload,
                    emptyMessage: AppStrings.of(context).noOrders,
                    builder: (context, items) {
                      return RefreshIndicator(
                        onRefresh: _reload,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(
                            AniHowSpace.screen,
                            0,
                            AniHowSpace.screen,
                            AniHowSpace.screen + AniHowSpace.section,
                          ),
                          children: _orderSections(
                            context,
                            items,
                            onChanged: (_) => _reload(),
                            onTap: (order) async {
                              await Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) =>
                                      BuyerOrderDetailScreen(order: order),
                                ),
                              );
                              if (mounted) {
                                await _reload();
                              }
                            },
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ],
            ),
            Column(
              children: [
                mainTabBodyGap,
                const UnverifiedEmailBanner(),
                Expanded(
                  child: AsyncView<List<ReservationRecord>>(
                    future: _reservations,
                    onRetry: _reloadReservations,
                    emptyMessage: s.noReservations,
                    emptyBuilder: (context) => RefreshIndicator(
                      onRefresh: _reloadReservations,
                      child: ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: AniHowSpace.screenPadding,
                        children: [
                          const SizedBox(height: 120),
                          Text(
                            s.noReservations,
                            style: Theme.of(context).textTheme.bodyLarge,
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                    builder: (context, items) {
                      return BuyerReservationsList(
                        reservations: items,
                        onRefresh: _reloadReservations,
                        onCancel: _cancelReservation,
                        onOpenOrder: (reservation) {
                          final orderId = reservation.orderId;
                          if (orderId == null) {
                            return;
                          }
                          Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) =>
                                  BuyerOrderDetailScreen(orderId: orderId),
                            ),
                          );
                        },
                      );
                    },
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

int _newestOrder(OrderRecord a, OrderRecord b) {
  final left = DateTime.tryParse(a.placedAt ?? '') ?? DateTime(1970);
  final right = DateTime.tryParse(b.placedAt ?? '') ?? DateTime(1970);
  return right.compareTo(left);
}

bool _orderIsActive(OrderRecord order) {
  return order.isPlaced || order.isConfirmed || order.isReady;
}

List<Widget> _orderSections(
  BuildContext context,
  List<OrderRecord> items, {
  required ValueChanged<OrderRecord> onChanged,
  required ValueChanged<OrderRecord> onTap,
}) {
  final s = AppStrings.of(context);
  final active = items.where(_orderIsActive).toList()..sort(_newestOrder);
  final past = items.where((order) => !_orderIsActive(order)).toList()
    ..sort(_newestOrder);
  final children = <Widget>[];

  void addSection(String title, List<OrderRecord> orders) {
    if (orders.isEmpty) {
      return;
    }
    if (children.isNotEmpty) {
      children.add(const SizedBox(height: AniHowSpace.section));
    }
    children.add(OrderSectionLabel(title));
    children.add(const SizedBox(height: 8));
    for (var index = 0; index < orders.length; index++) {
      if (index > 0) {
        children.add(const SizedBox(height: AniHowSpace.cardGap));
      }
      final order = orders[index];
      children.add(
        BuyerOrderCard(
          order: order,
          onChanged: onChanged,
          onTap: () => onTap(order),
        ),
      );
    }
  }

  addSection(s.activeReservations, active);
  addSection(s.pastOrders, past);
  return children;
}

class BuyerOrderCard extends StatefulWidget {
  const BuyerOrderCard({
    super.key,
    required this.order,
    this.onTap,
    this.onChanged,
  });

  final OrderRecord order;
  final VoidCallback? onTap;
  final ValueChanged<OrderRecord>? onChanged;

  @override
  State<BuyerOrderCard> createState() => _BuyerOrderCardState();
}

class _BuyerOrderCardState extends State<BuyerOrderCard> {
  late OrderRecord _order;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
  }

  @override
  void didUpdateWidget(BuyerOrderCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.order != widget.order) {
      _order = widget.order;
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final active = _orderIsActive(order);
    final location = order.location;
    final mutedColor = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final muted = theme.textTheme.bodyMedium?.copyWith(color: mutedColor);
    final itemLine = orderItemLine(s, order);
    final showChat = !order.isWalkIn;
    final showCancel = order.status == 'placed';
    final showReview = order.reviewRating != null;
    final showWriteReview = !showReview && order.canBeReviewed;
    final showFooter = showChat || showCancel || showReview || showWriteReview;
    final steps = orderProgressSteps(s, order);
    final stepIndex = orderProgressIndex(order.status);

    return Card(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 14, 14, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            InkWell(
              onTap: widget.onTap,
              borderRadius: BorderRadius.circular(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      AniHowAvatar(name: order.stallName, radius: 20),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Text(
                          order.stallName,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      StatusPill.order(
                        order.status,
                        strings: s,
                        fulfillmentPreference: order.fulfillmentPreference,
                      ),
                    ],
                  ),
                  if (itemLine.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(itemLine, style: theme.textTheme.bodyMedium),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    orderSummaryLine(s, order, active: active),
                    style: muted,
                  ),
                  if (tawadIsActive(order.tawadDisplay))
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        s.discountTawadMinus(
                          AniHowMoney.peso(order.tawadDisplay),
                        ),
                        style: muted?.copyWith(
                          color: readableAccent(context),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  if (active) ...[
                    const SizedBox(height: 10),
                    _OrderStepBar(done: stepIndex + 1),
                    const SizedBox(height: 6),
                    Text(
                      '${s.stepOfFour(stepIndex + 1)} · ${steps[stepIndex].hint}',
                      style: muted,
                    ),
                    if (location != null && location.isNotEmpty) ...[
                      const SizedBox(height: 6),
                      Row(
                        children: [
                          Icon(
                            Icons.place_outlined,
                            size: 16,
                            color: mutedColor,
                          ),
                          const SizedBox(width: 4),
                          Expanded(child: Text(location, style: muted)),
                        ],
                      ),
                    ],
                  ],
                ],
              ),
            ),
            if (showFooter) ...[
              const Divider(height: 16),
              Row(
                children: [
                  if (showChat)
                    Flexible(
                      child: Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: TextButton.icon(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute<void>(
                                builder: (_) => OrderChatScreen(order: order),
                              ),
                            );
                          },
                          icon: const Icon(Icons.chat_bubble_outline, size: 18),
                          label: Text(
                            s.chatWithStall,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          style: TextButton.styleFrom(
                            minimumSize: const Size(48, 48),
                          ),
                        ),
                      ),
                    )
                  else
                    const Spacer(),
                  if (showCancel)
                    BuyerCancelOrderButton(
                      compact: true,
                      order: order,
                      onUpdated: (updated) {
                        setState(() => _order = updated);
                        widget.onChanged?.call(updated);
                      },
                      onReload: widget.onChanged == null
                          ? null
                          : () async {
                              widget.onChanged?.call(_order);
                            },
                    )
                  else if (showWriteReview)
                    TextButton(
                      onPressed: widget.onTap,
                      style: TextButton.styleFrom(
                        minimumSize: const Size(48, 48),
                      ),
                      child: Text(s.writeReview),
                    )
                  else if (showReview)
                    Text(
                      '${s.youRated(order.reviewRating!)}★',
                      style: muted,
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

class _OrderStepBar extends StatelessWidget {
  const _OrderStepBar({required this.done});

  final int done;

  @override
  Widget build(BuildContext context) {
    final doneColor = readableAccent(context);
    final track = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.16);
    return Row(
      children: [
        for (var index = 0; index < 4; index++) ...[
          if (index > 0) const SizedBox(width: 4),
          Expanded(
            child: Container(
              height: 5,
              decoration: BoxDecoration(
                color: index < done ? doneColor : track,
                borderRadius: BorderRadius.circular(3),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class BuyerReservationsList extends StatelessWidget {
  const BuyerReservationsList({
    super.key,
    required this.reservations,
    this.onRefresh,
    this.onCancel,
    this.onOpenOrder,
  });

  final List<ReservationRecord> reservations;
  final Future<void> Function()? onRefresh;
  final Future<void> Function(ReservationRecord reservation)? onCancel;
  final void Function(ReservationRecord reservation)? onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final active = reservations.where((item) => item.isActive).toList();
    final history = reservations.where((item) => !item.isActive).toList();

    Future<void> openDetail(ReservationRecord reservation) async {
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ReservationDetailScreen(reservation: reservation),
        ),
      );
      await onRefresh?.call();
    }

    // Same section labels and spacing as the Orders tab.
    final children = <Widget>[];
    void addSection(String title, List<ReservationRecord> items) {
      if (items.isEmpty) {
        return;
      }
      if (children.isNotEmpty) {
        children.add(const SizedBox(height: AniHowSpace.section));
      }
      children.add(OrderSectionLabel(title));
      children.add(const SizedBox(height: 8));
      for (var index = 0; index < items.length; index++) {
        if (index > 0) {
          children.add(const SizedBox(height: AniHowSpace.cardGap));
        }
        final reservation = items[index];
        children.add(
          _ReservationCard(
            reservation: reservation,
            onCancel: onCancel == null || !reservation.canBuyerCancel
                ? null
                : () => onCancel!(reservation),
            onOpen: reservation.isActive
                ? () => openDetail(reservation)
                : reservation.isConverted
                ? () => onOpenOrder?.call(reservation)
                : null,
            onOpenOrder: reservation.isConverted
                ? () => onOpenOrder?.call(reservation)
                : null,
          ),
        );
      }
    }

    addSection(s.activeReservations, active);
    addSection(s.pastReservations, history);

    final list = ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen + AniHowSpace.section,
      ),
      children: children,
    );
    final refresh = onRefresh;
    if (refresh == null) {
      return list;
    }
    return RefreshIndicator(onRefresh: refresh, child: list);
  }
}

StatusPill _reservationStatus(
  AppStrings strings,
  ReservationRecord reservation,
) {
  if (reservation.isActive) {
    return StatusPill(label: strings.reservedPill, color: AniHowColors.pending);
  }
  if (reservation.status == 'cancelled') {
    return StatusPill.order('cancelled', strings: strings);
  }
  return StatusPill(
    label: strings.becameAnOrder,
    color: AniHowColors.completeGreen,
  );
}

/// A reservation laid out like an order card: the stall on top with one
/// status pill, the item, a grey summary line, a step bar while active, and
/// the actions in a footer.
class _ReservationCard extends StatelessWidget {
  const _ReservationCard({
    required this.reservation,
    this.onCancel,
    this.onOpen,
    this.onOpenOrder,
  });

  final ReservationRecord reservation;
  final VoidCallback? onCancel;
  final VoidCallback? onOpen;
  final VoidCallback? onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final mutedColor = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final muted = theme.textTheme.bodyMedium?.copyWith(color: mutedColor);
    final stall = reservation.shopName?.trim();
    final step = _step();
    final payNow = reservation.isAwaitingPayment && reservation.isActive;
    final viewPayment = reservation.isPaymentTracked && !reservation.isAwaitingPayment;
    final showFooter = payNow || viewPayment || onOpenOrder != null || onCancel != null;

    return Card(
      key: ValueKey('reservation-card-${reservation.id}'),
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
                    children: [
                      AniHowAvatar(
                        name: stall == null || stall.isEmpty ? reservation.listingName : stall,
                        radius: 20,
                      ),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Text(
                          stall == null || stall.isEmpty ? reservation.listingName : stall,
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      const SizedBox(width: 8),
                      _reservationStatus(s, reservation),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${reservation.listingName} · ${reservation.quantityLabel}',
                    style: theme.textTheme.bodyMedium,
                  ),
                  const SizedBox(height: 4),
                  Text.rich(
                    _summary(s, theme),
                    key: ValueKey('reservation-summary-${reservation.id}'),
                    style: muted,
                  ),
                  if (reservation.refundReference != null &&
                      reservation.refundReference!.isNotEmpty &&
                      (reservation.paymentStatus == 'refund_due' ||
                          reservation.paymentStatus == 'refunded'))
                    Padding(
                      padding: const EdgeInsets.only(top: 2),
                      child: Text(
                        '${s.refundReference}: ${reservation.refundReference}',
                        style: muted,
                      ),
                    ),
                  if (step != null) ...[
                    const SizedBox(height: 10),
                    _OrderStepBar(done: step.$1),
                    const SizedBox(height: 6),
                    Text('${s.stepOfFour(step.$1)} · ${step.$2(s)}', style: muted),
                  ],
                ],
              ),
            ),
            if (showFooter) ...[
              const Divider(height: 16),
              Row(
                children: [
                  if (payNow)
                    _footerButton(
                      key: ValueKey('reservation-pay-now-${reservation.id}'),
                      icon: Icons.qr_code_2,
                      label: s.payNow,
                      onPressed: () => _openPay(context),
                    )
                  else if (onOpenOrder != null)
                    _footerButton(
                      key: ValueKey('reservation-view-order-${reservation.id}'),
                      icon: Icons.inventory_2_outlined,
                      label: s.viewOrder,
                      onPressed: onOpenOrder!,
                    )
                  else if (viewPayment)
                    _footerButton(
                      key: ValueKey('reservation-view-payment-${reservation.id}'),
                      icon: Icons.receipt_long_outlined,
                      label: s.viewPayment,
                      onPressed: () => _openPay(context),
                    ),
                  const Spacer(),
                  if (onCancel != null)
                    TextButton(
                      key: ValueKey('reservation-cancel-${reservation.id}'),
                      style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                      onPressed: onCancel,
                      child: Text(s.cancelReservation),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _footerButton({
    required Key key,
    required IconData icon,
    required String label,
    required VoidCallback onPressed,
  }) {
    return TextButton.icon(
      key: key,
      style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
      onPressed: onPressed,
      icon: Icon(icon, size: 18),
      label: Text(label),
    );
  }

  void _openPay(BuildContext context) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => PayNowScreen(reservation: reservation),
      ),
    );
  }

  /// Reserved → Payment sent → Paid → Opens as an order. Active only.
  (int, String Function(AppStrings))? _step() {
    if (!reservation.isActive || !reservation.isPaymentTracked) {
      return null;
    }
    if (reservation.isAwaitingPayment) {
      final due = reservation.paymentDueAt;
      return (1, (s) => due == null ? s.reservationStepPay : s.payBefore(due));
    }
    if (reservation.isPaymentSent) {
      return (2, (s) => s.reservationStepChecking);
    }
    return (3, (s) => s.reservationStepOpens(reservation.opensAt?.toLocal()));
  }

  /// "₱156.00 · Payment sent · Opens Nov 2", with Refund due in amber.
  TextSpan _summary(AppStrings s, ThemeData theme) {
    final parts = <InlineSpan>[TextSpan(text: AniHowMoney.peso(reservation.lineTotal))];
    void add(String? text, {TextStyle? style}) {
      if (text == null || text.isEmpty) {
        return;
      }
      parts.add(const TextSpan(text: ' · '));
      parts.add(TextSpan(text: text, style: style));
    }

    final payment = reservation.isPaymentTracked
        ? s.paymentStatusLabel(reservation.paymentStatus)
        : null;
    add(
      payment,
      style: reservation.paymentStatus == 'refund_due'
          ? const TextStyle(color: AniHowColors.root, fontWeight: FontWeight.w700)
          : null,
    );
    if (reservation.isActive) {
      final opens = reservation.opensAt;
      add(opens == null ? null : s.opensOn(opens.toLocal()));
    } else if (reservation.status == 'cancelled') {
      final reason = s.reservationCancelReason(reservation.cancellationReason);
      add(reason.isEmpty ? null : reason[0].toUpperCase() + reason.substring(1));
      final day = reservation.cancelledAt;
      add(day == null ? null : s.monthDay(day.toLocal()));
    } else {
      final day = reservation.paidAt ?? reservation.createdAt;
      add(day == null ? null : s.monthDay(day.toLocal()));
    }
    return TextSpan(children: parts);
  }
}
