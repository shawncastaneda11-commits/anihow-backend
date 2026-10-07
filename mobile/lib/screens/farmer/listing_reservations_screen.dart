import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/form_label.dart';

class ListingReservationsScreen extends StatefulWidget {
  const ListingReservationsScreen({super.key, required this.listing});

  final ListingItem listing;

  @override
  State<ListingReservationsScreen> createState() =>
      _ListingReservationsScreenState();
}

class _ListingReservationsScreenState extends State<ListingReservationsScreen> {
  late Future<List<ReservationRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.farmerListingReservations(
      widget.listing.id,
    );
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.farmerListingReservations(
      widget.listing.id,
    );
    setState(() => _future = future);
    await future;
  }

  Future<void> _cancel(ReservationRecord reservation) async {
    final s = AppStrings.read(context);
    final note = TextEditingController();
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: Text(s.cancelReservation),
          content: AniHowField(
            label: s.reservationReason,
            child: TextField(controller: note, maxLength: 255),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: Text(s.t('Back', 'Bumalik')),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size(48, 48),
              ),
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: Text(s.cancelReservation),
            ),
          ],
        );
      },
    );
    final reason = note.text.trim();
    note.dispose();
    if (confirmed != true || !mounted) {
      return;
    }
    try {
      await context.read<AuthController>().api.cancelFarmerReservation(
        widget.listing.id,
        reservation.id,
        note: reason,
      );
      await _reload();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    }
  }

  Future<void> _openNow() async {
    final s = AppStrings.read(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: Text(s.openNow),
          content: Text(s.openNowBody),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: Text(s.t('Back', 'Bumalik')),
            ),
            FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size(48, 48),
              ),
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: Text(s.openNow),
            ),
          ],
        );
      },
    );
    if (confirmed != true || !mounted) {
      return;
    }
    try {
      await context.read<AuthController>().api.openListingNow(widget.listing.id);
      if (mounted) {
        Navigator.of(context).pop();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(error.message)),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.reservationsTab)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: FilledButton(
              style: FilledButton.styleFrom(
                minimumSize: const Size.fromHeight(48),
              ),
              onPressed: _openNow,
              child: Text(s.openNow),
            ),
          ),
          Expanded(
            child: AsyncView<List<ReservationRecord>>(
              future: _future,
              onRetry: _reload,
              emptyMessage: s.noReservations,
              builder: (context, items) {
                return ListView.separated(
                  padding: AniHowSpace.screenPadding,
                  itemCount: items.length,
                  separatorBuilder: (_, _) =>
                      const SizedBox(height: AniHowSpace.cardGap),
                  itemBuilder: (context, index) {
                    final reservation = items[index];
                    final quantity =
                        reservation.quantity ==
                            reservation.quantity.roundToDouble()
                        ? reservation.quantity.toStringAsFixed(0)
                        : reservation.quantity.toString();
                    return Card(
                      child: ListTile(
                        title: Text(reservation.buyerName ?? reservation.listingName),
                        subtitle: Text(
                          '$quantity ${reservation.unit ?? ''} · ${AniHowMoney.peso(reservation.lineTotal)}',
                        ),
                        trailing: TextButton(
                          style: TextButton.styleFrom(
                            minimumSize: const Size(48, 48),
                          ),
                          onPressed: () => _cancel(reservation),
                          child: Text(s.cancelReservation),
                        ),
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}
