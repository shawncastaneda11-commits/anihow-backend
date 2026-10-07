import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';

class OrderProgress extends StatelessWidget {
  const OrderProgress({super.key, required this.order});

  final OrderRecord order;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    if (order.isCancelled) {
      final reason = s.cancellationReasonText(
        order.cancellationReason,
        fallback: order.cancellationLabel,
      );
      return Container(
        key: const Key('order-cancelled-banner'),
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFDE8E8),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.cancelled,
              style: const TextStyle(
                color: Color(0xFF991B1B),
                fontWeight: FontWeight.w700,
              ),
            ),
            if (reason.trim().isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  reason,
                  style: const TextStyle(color: Color(0xFF991B1B)),
                ),
              ),
          ],
        ),
      );
    }

    final steps = orderProgressSteps(s, order);
    final current = orderProgressIndex(order.status);
    final theme = Theme.of(context);
    final fill = theme.colorScheme.primary;
    final idle = theme.colorScheme.outline;

    return Column(
      key: const Key('order-progress'),
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            for (var index = 0; index < steps.length; index++) ...[
              if (index > 0)
                Expanded(
                  child: Container(
                    height: 2,
                    color: index <= current ? fill : idle,
                  ),
                ),
              Container(
                width: 12,
                height: 12,
                decoration: BoxDecoration(
                  color: index <= current ? fill : idle,
                  shape: BoxShape.circle,
                ),
              ),
            ],
          ],
        ),
        const SizedBox(height: 8),
        Text(steps[current].label, style: theme.textTheme.titleSmall),
        Text(steps[current].hint, style: theme.textTheme.bodySmall),
        for (var index = 0; index <= current; index++)
          if (steps[index].time != null)
            Text(
              steps[index].time!,
              style: theme.textTheme.labelSmall,
            ),
      ],
    );
  }
}

int orderProgressIndex(String status) {
  return switch (status) {
    'confirmed' => 1,
    'ready' => 2,
    'completed' => 3,
    _ => 0,
  };
}

class OrderProgressStep {
  const OrderProgressStep(this.label, this.hint, this.time);

  final String label;
  final String hint;
  final String? time;
}

List<OrderProgressStep> orderProgressSteps(AppStrings strings, OrderRecord order) {
  final readyLabel = order.fulfillmentPreference == 'seller_delivers'
      ? strings.outForDelivery
      : strings.readyForPickup;
  return [
    OrderProgressStep(
      strings.stepPending,
      strings.stepPendingHint,
      orderTimestampLabel(order.placedAt),
    ),
    OrderProgressStep(
      strings.stepConfirmed,
      strings.stepConfirmedHint,
      orderTimestampLabel(order.confirmedAt),
    ),
    OrderProgressStep(readyLabel, readyLabel, orderTimestampLabel(order.readyAt)),
    OrderProgressStep(
      strings.orderComplete,
      strings.orderCompleteHint,
      orderTimestampLabel(order.completedAt),
    ),
  ];
}

String? orderTimestampLabel(String? iso) {
  if (iso == null || iso.trim().isEmpty) {
    return null;
  }
  if (iso.length >= 16) {
    return iso.substring(0, 16).replaceFirst('T', ' ');
  }
  return iso;
}
