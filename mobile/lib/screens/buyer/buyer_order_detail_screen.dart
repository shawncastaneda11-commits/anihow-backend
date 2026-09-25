import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import '../chat/order_chat_screen.dart';

class BuyerOrderDetailScreen extends StatefulWidget {
  const BuyerOrderDetailScreen({
    super.key,
    this.order,
    this.orderId,
    this.preview = false,
  }) : assert(order != null || orderId != null);

  final OrderRecord? order;
  final int? orderId;
  final bool preview;

  @override
  State<BuyerOrderDetailScreen> createState() => _BuyerOrderDetailScreenState();
}

class _BuyerOrderDetailScreenState extends State<BuyerOrderDetailScreen> {
  late Future<OrderRecord> _future;
  final _comment = TextEditingController();
  int _rating = 0;
  bool _submitting = false;

  int get _id => widget.orderId ?? widget.order!.id;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<OrderRecord> _load() {
    if (widget.preview) {
      return Future.value(widget.order!);
    }
    return context.read<AuthController>().api.buyerOrder(_id);
  }

  Future<void> _reload() async {
    if (widget.preview) {
      return;
    }
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  void _openChat(OrderRecord order) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => OrderChatScreen(order: order),
      ),
    );
  }

  bool _needsCashHint(OrderRecord order) {
    return !order.isWalkIn && (order.isPlaced || order.isConfirmed || order.isReady);
  }

  Future<void> _submitReview(OrderRecord order) async {
    final s = AppStrings.read(context);
    if (_rating < 1) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.chooseARating)));
      return;
    }
    if (widget.preview) {
      setState(() {
        _future = Future.value(
          order.copyWith(canBeReviewed: false, reviewRating: _rating),
        );
      });
      return;
    }
    setState(() => _submitting = true);
    try {
      await context.read<AuthController>().api.submitReview(
            orderId: order.id,
            rating: _rating,
            comment: _comment.text.trim(),
          );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.reviewSaved)));
      await _reload();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _submitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.order?.stallName ?? s.order),
      ),
      body: AsyncView<OrderRecord>(
        future: _future,
        onRetry: _reload,
        builder: (context, order) {
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
                          AniHowAvatar(name: order.stallName, radius: 20),
                          const SizedBox(width: AniHowSpace.cardGap),
                          Expanded(
                            child: Text(
                              order.stallName,
                              style: theme.textTheme.titleMedium,
                            ),
                          ),
                          StatusPill.order(order.status, strings: s),
                        ],
                      ),
                      if (!order.isWalkIn)
                        Align(
                          alignment: Alignment.centerLeft,
                          child: TextButton.icon(
                            onPressed: () => _openChat(order),
                            icon: const Icon(Icons.chat_bubble_outline, size: 18),
                            label: Text(s.chatWithStall),
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
              if (_needsCashHint(order)) ...[
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
                            ? s.tawadMinus(AniHowMoney.peso(order.tawadDisplay))
                            : null,
                      ),
                      if (order.fulfillmentLabel != null)
                        OrderMetaRow(
                          icon: Icons.handshake_outlined,
                          text: order.fulfillmentLabel!,
                        ),
                      if (order.fulfillmentNote != null && order.fulfillmentNote!.isNotEmpty)
                        OrderMetaRow(
                          icon: Icons.notes_outlined,
                          text: order.fulfillmentNote!,
                        ),
                      if (order.location != null && order.location!.isNotEmpty)
                        OrderMetaRow(
                          icon: Icons.place_outlined,
                          text: order.location!,
                        ),
                      if (order.placedAt != null)
                        OrderMetaRow(
                          icon: Icons.schedule_outlined,
                          text: relativeTime(order.placedAt),
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
                    ],
                  ),
                ),
              ),
              if (order.canBeReviewed || order.reviewRating != null) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                _OrderReviewCard(
                  order: order,
                  rating: order.reviewRating ?? _rating,
                  comment: _comment,
                  busy: _submitting,
                  onRating: order.canBeReviewed ? (value) => setState(() => _rating = value) : null,
                  onSubmit: order.canBeReviewed ? () => _submitReview(order) : null,
                ),
              ],
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
                                      Text(item.listingName, style: theme.textTheme.titleSmall),
                                      Text(
                                        item.quantityLabel,
                                        style: theme.textTheme.bodyMedium?.copyWith(
                                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                Text(
                                  AniHowMoney.peso(item.lineTotal ?? item.lineSubtotal),
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
            ],
          );
        },
      ),
    );
  }
}

class _OrderReviewCard extends StatelessWidget {
  const _OrderReviewCard({
    required this.order,
    required this.rating,
    required this.comment,
    required this.busy,
    this.onRating,
    this.onSubmit,
  });

  final OrderRecord order;
  final int rating;
  final TextEditingController comment;
  final bool busy;
  final ValueChanged<int>? onRating;
  final VoidCallback? onSubmit;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final locked = onSubmit == null;

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              locked ? s.youRated(order.reviewRating ?? rating) : s.writeReview,
              style: theme.textTheme.titleMedium,
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            Row(
              children: [
                for (var star = 1; star <= 5; star++)
                  IconButton(
                    key: ValueKey('review-star-$star'),
                    onPressed: onRating == null ? null : () => onRating!(star),
                    icon: Icon(
                      star <= rating ? Icons.star_rounded : Icons.star_outline_rounded,
                      color: AniHowColors.pending,
                    ),
                    style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
                  ),
              ],
            ),
            if (!locked) ...[
              const SizedBox(height: AniHowSpace.fieldGap),
              AniHowField(
                label: s.reviewComment,
                child: TextField(
                  controller: comment,
                  maxLines: 3,
                  maxLength: 1000,
                ),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              PrimaryButton(
                label: s.submitReview,
                busy: busy,
                onPressed: onSubmit,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
