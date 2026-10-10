import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import 'cancel_reservations_dialog.dart';

/// Confirms, then deletes [listing]. Returns true when the API delete succeeds.
///
/// Shared by the listing form and the listings menu so both use one flow.
Future<bool> deleteListingFlow(
  BuildContext context,
  ListingItem listing, {
  void Function(bool busy)? onBusy,
}) async {
  var confirmed = false;
  final reserved = listing.activeReservationsCount ?? 0;
  if (reserved > 0) {
    final accepted = await confirmCancelReservations(
      context,
      count: reserved,
      quantity: formatReservedQuantity(listing.reservedQuantity),
      unit: listing.unit ?? '',
      deleting: true,
    );
    if (!accepted || !context.mounted) {
      return false;
    }
    confirmed = true;
  } else {
    final accepted = await showDialog<bool>(
      context: context,
      builder: (context) {
        final s = AppStrings.of(context);
        return AlertDialog(
          title: Text(s.deleteListingAsk),
          content: Text(listing.title),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(s.cancel),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(s.delete),
            ),
          ],
        );
      },
    );
    if (accepted != true || !context.mounted) {
      return false;
    }
  }
  return _delete(context, listing, confirmed, onBusy);
}

Future<bool> _delete(
  BuildContext context,
  ListingItem listing,
  bool confirmed,
  void Function(bool busy)? onBusy,
) async {
  onBusy?.call(true);
  try {
    await context.read<AuthController>().api.deleteListing(
      listing.id,
      confirmCancelReservations: confirmed,
    );
    onBusy?.call(false);
    return true;
  } on ApiException catch (error) {
    onBusy?.call(false);
    final conflict = ReservationConflict.fromException(error);
    if (conflict != null && !confirmed && context.mounted) {
      final accepted = await confirmCancelReservations(
        context,
        count: conflict.count,
        quantity: conflict.quantity,
        unit: listing.unit ?? '',
        deleting: true,
      );
      if (accepted && context.mounted) {
        return _delete(context, listing, true, onBusy);
      }
      return false;
    }
    if (context.mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    }
    return false;
  }
}
