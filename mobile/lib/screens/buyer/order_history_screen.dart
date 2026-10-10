import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/async_view.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/buyer_cancel_order_button.dart';
import '../../widgets/main_tab_app_bar.dart';
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

  @override
  Future<void> refreshPolledOrders() => _reload();

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
                    builder: (context, items) {
                      return BuyerReservationsList(
                        reservations: items,
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
    children.add(_OrderSectionLabel(title));
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

class _OrderSectionLabel extends StatelessWidget {
  const _OrderSectionLabel(this.title);

  final String title;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Text(
      title.toUpperCase(),
      style: theme.textTheme.labelSmall?.copyWith(
        color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
        fontWeight: FontWeight.w700,
        letterSpacing: 0.8,
      ),
    );
  }
}

String _orderItemLine(AppStrings s, OrderRecord order) {
  if (order.items.isEmpty) {
    return '';
  }
  final first = order.items.first;
  if (order.items.length > 1) {
    return '${first.listingName} ${s.moreItems(order.items.length - 1)}';
  }
  final quantity = double.tryParse(first.quantity) ?? 0;
  final unit = s.unitWord(first.unit ?? '', quantity);
  final amount = first.quantity.trim();
  if (unit.isEmpty) {
    return '${first.listingName} · $amount';
  }
  return '${first.listingName} · $amount $unit';
}

String _orderWhen(AppStrings s, OrderRecord order, {required bool active}) {
  final iso = order.placedAt;
  if (iso == null || iso.isEmpty) {
    return '';
  }
  if (active) {
    return relativeTime(iso);
  }
  final parsed = DateTime.tryParse(iso);
  if (parsed == null) {
    return '';
  }
  return s.monthDayClock(parsed.toLocal());
}

String _orderPaymentWords(AppStrings s, OrderRecord order) {
  final online = order.paymentMethod == 'online_transfer';
  final method = online ? s.onlinePaymentPill : s.cashPill;
  if (!online || !order.isPaymentTracked) {
    return method;
  }
  final state = s.paymentStatusLabel(order.paymentStatus);
  if (state.isEmpty) {
    return method;
  }
  return '$method, $state';
}

String _orderSummaryLine(AppStrings s, OrderRecord order, {required bool active}) {
  final total = AniHowMoney.peso(order.total);
  final when = _orderWhen(s, order, active: active);
  if (order.isCancelled) {
    final reason = s.cancellationReasonText(
      order.cancellationReason,
      fallback: order.cancellationLabel,
    );
    final note = order.cancellationNote?.trim() ?? '';
    final detail = note.isNotEmpty && note != reason.trim()
        ? (reason.trim().isEmpty ? note : '$reason · $note')
        : reason;
    if (detail.trim().isEmpty) {
      return when.isEmpty ? total : '$total · $when';
    }
    return when.isEmpty ? '$total · $detail' : '$total · $detail · $when';
  }
  final payment = _orderPaymentWords(s, order);
  return when.isEmpty ? '$total · $payment' : '$total · $payment · $when';
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
    final itemLine = _orderItemLine(s, order);
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
                    _orderSummaryLine(s, order, active: active),
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
    this.onCancel,
    this.onOpenOrder,
  });

  final List<ReservationRecord> reservations;
  final Future<void> Function(ReservationRecord reservation)? onCancel;
  final void Function(ReservationRecord reservation)? onOpenOrder;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final active = reservations.where((item) => item.isActive).toList();
    final history = reservations.where((item) => !item.isActive).toList();

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen + AniHowSpace.section,
      ),
      children: [
        if (active.isNotEmpty) Text(s.activeReservations),
        for (final reservation in active)
          _ReservationTile(
            reservation: reservation,
            onCancel: onCancel == null || !reservation.canBuyerCancel
                ? null
                : () => onCancel!(reservation),
            onOpen: () {
              Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) =>
                      ReservationDetailScreen(reservation: reservation),
                ),
              );
            },
          ),
        if (history.isNotEmpty) ...[
          const SizedBox(height: 12),
          Text(s.pastReservations),
        ],
        for (final reservation in history)
          _ReservationTile(
            reservation: reservation,
            onOpen: reservation.isConverted
                ? () => onOpenOrder?.call(reservation)
                : null,
          ),
      ],
    );
  }
}

StatusPill _reservationStatus(
  AppStrings strings,
  ReservationRecord reservation,
) {
  if (reservation.isActive) {
    return StatusPill(
      label: strings.activeReservations,
      color: AniHowColors.pending,
    );
  }
  if (reservation.status == 'cancelled') {
    return StatusPill.order('cancelled', strings: strings);
  }
  return StatusPill(
    label: strings.notificationTitle(
      'reservation_converted',
      strings.orderComplete,
    ),
    color: AniHowColors.completeGreen,
  );
}

class _ReservationTile extends StatelessWidget {
  const _ReservationTile({
    required this.reservation,
    this.onCancel,
    this.onOpen,
  });

  final ReservationRecord reservation;
  final VoidCallback? onCancel;
  final VoidCallback? onOpen;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final unit = reservation.unit ?? '';
    final quantity =
        reservation.quantity == reservation.quantity.roundToDouble()
        ? reservation.quantity.toStringAsFixed(0)
        : reservation.quantity.toString();

    return Card(
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                reservation.listingName,
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 6),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  PaymentTrackingPill(status: reservation.paymentStatus),
                  _reservationStatus(s, reservation),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '$quantity $unit · ${AniHowMoney.peso(reservation.lineTotal)}',
              ),
              if (reservation.isAwaitingPayment &&
                  reservation.paymentDueAt != null)
                Text(s.payBefore(reservation.paymentDueAt!)),
              if (reservation.refundReference != null &&
                  reservation.refundReference!.isNotEmpty &&
                  (reservation.paymentStatus == 'refund_due' ||
                      reservation.paymentStatus == 'refunded'))
                Text('${s.refundReference}: ${reservation.refundReference}'),
              _ReservationActions(reservation: reservation, onCancel: onCancel),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReservationActions extends StatelessWidget {
  const _ReservationActions({required this.reservation, this.onCancel});

  final ReservationRecord reservation;
  final VoidCallback? onCancel;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final payNow = reservation.isAwaitingPayment;
    final viewPayment =
        reservation.isPaymentTracked && !reservation.isAwaitingPayment;
    if (!payNow && !viewPayment && onCancel == null) {
      return const SizedBox.shrink();
    }
    return Wrap(
      spacing: 4,
      children: [
        if (payNow)
          TextButton(
            key: ValueKey('reservation-pay-now-${reservation.id}'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => PayNowScreen(reservation: reservation),
                ),
              );
            },
            child: Text(s.payNow),
          ),
        if (viewPayment)
          TextButton(
            key: ValueKey('reservation-view-payment-${reservation.id}'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute<void>(
                  builder: (_) => PayNowScreen(reservation: reservation),
                ),
              );
            },
            child: Text(s.viewPayment),
          ),
        if (onCancel != null)
          TextButton(
            key: ValueKey('reservation-cancel-${reservation.id}'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: onCancel,
            child: Text(s.cancelReservation),
          ),
      ],
    );
  }
}
