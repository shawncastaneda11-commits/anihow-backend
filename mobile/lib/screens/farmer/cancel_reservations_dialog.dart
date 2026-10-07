import 'package:flutter/material.dart';

import '../../l10n/app_strings.dart';
import '../../services/api_client.dart';

class ReservationConflict {
  const ReservationConflict({required this.count, required this.quantity});

  final int count;
  final String quantity;

  static ReservationConflict? fromException(ApiException error) {
    if (error.statusCode != 409) {
      return null;
    }
    final body = error.body;
    if (body == null) {
      return null;
    }
    final count = body['active_reservations_count'];
    final quantity = body['reserved_quantity'];
    if (count == null || quantity == null) {
      return null;
    }
    final parsedCount = count is num
        ? count.toInt()
        : int.tryParse('$count') ?? 0;
    return ReservationConflict(
      count: parsedCount,
      quantity: formatReservedQuantity(quantity),
    );
  }
}

String formatReservedQuantity(Object? value) {
  final number = value is num ? value.toDouble() : double.tryParse('$value');
  if (number == null) {
    return '$value';
  }
  if (number == number.roundToDouble()) {
    return number.toInt().toString();
  }
  return number.toStringAsFixed(2);
}

Future<bool> confirmCancelReservations(
  BuildContext context, {
  required int count,
  required String quantity,
  required String unit,
  required bool deleting,
}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (context) {
      final s = AppStrings.of(context);
      return AlertDialog(
        key: const Key('cancel-reservations-dialog'),
        title: Text(s.cancelReservationsTitle(count)),
        content: Text(s.cancelReservationsBody(quantity, unit, count)),
        actions: [
          TextButton(
            key: const Key('keep-listing'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: () => Navigator.pop(context, false),
            child: Text(s.keepListing),
          ),
          TextButton(
            key: const Key('confirm-cancel-reservations'),
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: () => Navigator.pop(context, true),
            child: Text(deleting ? s.deleteAndCancel : s.turnOffAndCancel),
          ),
        ],
      );
    },
  );
  return confirmed == true;
}
