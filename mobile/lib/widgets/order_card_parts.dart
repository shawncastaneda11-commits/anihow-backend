import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../support/relative_time.dart';
import '../theme/anihow_space.dart';

/// Small uppercase section title used on the buyer and seller order lists.
class OrderSectionLabel extends StatelessWidget {
  const OrderSectionLabel(this.title, {super.key});

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

/// "Kamatis · 3 kg", or "Kamatis +1 more" when the order has extra lines.
String orderItemLine(AppStrings s, OrderRecord order) {
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

String orderWhen(AppStrings s, OrderRecord order, {required bool active}) {
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

/// "Cash" or "Online, Payment sent".
String orderPaymentWords(AppStrings s, OrderRecord order) {
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

String orderSummaryLine(
  AppStrings s,
  OrderRecord order, {
  required bool active,
}) {
  final total = AniHowMoney.peso(order.total);
  final when = orderWhen(s, order, active: active);
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
  final payment = orderPaymentWords(s, order);
  return when.isEmpty ? '$total · $payment' : '$total · $payment · $when';
}
