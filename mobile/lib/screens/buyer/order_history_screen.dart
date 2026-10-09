import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/buyer_cancel_order_button.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/order_look.dart';
import '../../widgets/order_progress.dart';
import '../../widgets/order_status_poll.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../../widgets/unverified_email_banner.dart';
import '../chat/order_chat_screen.dart';
import 'buyer_order_detail_screen.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key, this.active = true});

  final bool active;

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
        appBar: AppBar(
          title: Text(s.orderHistory),
          actions: const [NotificationBellButton()],
          bottom: TabBar(
            labelColor: Colors.white,
            labelStyle: const TextStyle(fontWeight: FontWeight.w700),
            unselectedLabelColor: Colors.white.withValues(alpha: 0.75),
            indicator: const UnderlineTabIndicator(
              borderSide: BorderSide(color: Colors.white, width: 3),
            ),
            dividerColor: Colors.transparent,
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
                const UnverifiedEmailBanner(),
                Expanded(
                  child: AsyncView<List<OrderRecord>>(
                    future: _future,
                    onRetry: _reload,
                    emptyMessage: AppStrings.of(context).noOrders,
                    builder: (context, items) {
                      return RefreshIndicator(
                        onRefresh: _reload,
                        child: ListView.separated(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(
                            AniHowSpace.screen,
                            AniHowSpace.screen,
                            AniHowSpace.screen,
                            AniHowSpace.screen + AniHowSpace.section,
                          ),
                          itemCount: items.length,
                          separatorBuilder: (_, _) =>
                              const SizedBox(height: AniHowSpace.cardGap),
                          itemBuilder: (context, index) => BuyerOrderCard(
                            order: items[index],
                            onChanged: (_) => _reload(),
                            onTap: () async {
                              await Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => BuyerOrderDetailScreen(
                                    order: items[index],
                                  ),
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
    final location = order.location;
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
    );
    final when = order.placedAt == null ? null : relativeTime(order.placedAt);
    final summary = [AniHowMoney.peso(order.total), ?when].join('  ·  ');

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
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AniHowAvatar(name: order.stallName, radius: 20),
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              order.stallName,
                              style: theme.textTheme.titleMedium,
                            ),
                            const SizedBox(height: 4),
                            Text(summary, style: muted),
                            if (tawadIsActive(order.tawadDisplay))
                              Padding(
                                padding: const EdgeInsets.only(top: 2),
                                child: Text(
                                  s.discountTawadMinus(
                                    AniHowMoney.peso(order.tawadDisplay),
                                  ),
                                  style: muted?.copyWith(
                                    color: AniHowColors.sage,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      Flexible(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            StatusPill.order(
                              order.status,
                              strings: s,
                              fulfillmentPreference:
                                  order.fulfillmentPreference,
                            ),
                            const SizedBox(height: 6),
                            StatusPill.payment(order.paymentMethod, strings: s),
                            if (order.isPaymentTracked) ...[
                              const SizedBox(height: 6),
                              PaymentTrackingPill(status: order.paymentStatus),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  OrderProgress(order: order),
                  if (location != null && location.isNotEmpty)
                    OrderMetaRow(icon: Icons.place_outlined, text: location),
                  if (order.hasCancellationNote)
                    OrderMetaRow(
                      icon: Icons.notes_outlined,
                      text: order.cancellationNote!.trim(),
                    ),
                  if (order.canBeReviewed)
                    OrderMetaRow(icon: Icons.star_outline, text: s.writeReview),
                  if (order.reviewRating != null)
                    OrderMetaRow(
                      icon: Icons.star,
                      text: s.youRated(order.reviewRating!),
                    ),
                ],
              ),
            ),
            if (!order.isWalkIn || order.status == 'placed')
              Wrap(
                spacing: 8,
                runSpacing: 4,
                crossAxisAlignment: WrapCrossAlignment.center,
                children: [
                  if (!order.isWalkIn)
                    TextButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => OrderChatScreen(order: order),
                          ),
                        );
                      },
                      icon: const Icon(Icons.chat_bubble_outline, size: 18),
                      label: Text(s.chatWithStall),
                      style: TextButton.styleFrom(
                        padding: const EdgeInsets.only(top: 6, right: 8),
                        visualDensity: VisualDensity.compact,
                      ),
                    ),
                  BuyerCancelOrderButton(
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
                  ),
                ],
              ),
          ],
        ),
      ),
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
            onCancel: onCancel == null ? null : () => onCancel!(reservation),
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
      child: ListTile(
        onTap: onOpen,
        title: Row(
          children: [
            Expanded(child: Text(reservation.listingName)),
            const SizedBox(width: 8),
            _reservationStatus(s, reservation),
          ],
        ),
        subtitle: Text(
          '$quantity $unit · ${AniHowMoney.peso(reservation.lineTotal)}',
        ),
        trailing: onCancel == null
            ? null
            : TextButton(
                style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                onPressed: onCancel,
                child: Text(s.cancelReservation),
              ),
      ),
    );
  }
}
