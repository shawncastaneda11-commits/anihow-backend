import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/status_pill.dart';
import 'shop_profile_screen.dart';

class ReservationDetailScreen extends StatefulWidget {
  const ReservationDetailScreen({super.key, required this.reservationId});

  final int reservationId;

  @override
  State<ReservationDetailScreen> createState() => _ReservationDetailScreenState();
}

class _ReservationDetailScreenState extends State<ReservationDetailScreen> {
  late Future<ReservationRecord> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerReservation(widget.reservationId);
  }

  void _reload() {
    setState(() {
      _future = context.read<AuthController>().api.buyerReservation(widget.reservationId);
    });
  }

  Future<void> _cancel() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.cancelBuyerReservation(widget.reservationId);
      if (mounted) {
        _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _call(String number) async {
    final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      return;
    }
    final uri = Uri(scheme: 'tel', path: digits);
    final opened = await launchUrl(uri);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the phone app.')),
      );
    }
  }

  Future<void> _review(ReservationRecord reservation) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _ReviewSheet(reservationId: reservation.id),
    );
    if (saved == true && mounted) {
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Reservation')),
      body: FutureBuilder<ReservationRecord>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final reservation = snapshot.data;
          if (reservation == null) {
            return const Center(child: Text('Reservation not found.'));
          }
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              Row(
                children: [
                  Expanded(
                    child: InkWell(
                      onTap: reservation.sellerId == null
                          ? null
                          : () => openBuyerShop(context, reservation.sellerId!),
                      child: Text(
                        reservation.stallName,
                        style: TextStyle(
                          fontSize: AniHowSpace.title,
                          fontWeight: FontWeight.w800,
                          color: reservation.sellerId == null ? null : AniHowColors.deepGreen,
                        ),
                      ),
                    ),
                  ),
                  StatusPill.reservation(reservation.status, label: reservation.statusLabel),
                ],
              ),
              const SizedBox(height: AniHowSpace.section),
              _PickupProgress(status: reservation.status),
              const SizedBox(height: AniHowSpace.section),
              const Text(
                'Items',
                style: TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              ...reservation.items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              item.listingName,
                              style: const TextStyle(fontSize: AniHowSpace.body, fontWeight: FontWeight.w600),
                            ),
                            Text(
                              '${item.quantityLabel} × ${AniHowMoney.peso(item.unitPrice)}',
                              style: const TextStyle(fontSize: AniHowSpace.meta),
                            ),
                          ],
                        ),
                      ),
                      Text(
                        AniHowMoney.peso(item.lineSubtotal),
                        style: const TextStyle(fontSize: AniHowSpace.body, fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
              ),
              const Divider(),
              Row(
                children: [
                  const Expanded(
                    child: Text(
                      'Total',
                      style: TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w800),
                    ),
                  ),
                  Text(
                    AniHowMoney.peso(reservation.total),
                    style: const TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w800),
                  ),
                ],
              ),
              const SizedBox(height: AniHowSpace.section),
              const Text(
                'Pickup at the stall',
                style: TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              Card(
                child: Padding(
                  padding: AniHowSpace.cardPadding,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      InkWell(
                        onTap: reservation.sellerId == null
                            ? null
                            : () => openBuyerShop(context, reservation.sellerId!),
                        child: Text(
                          reservation.stallName,
                          style: TextStyle(
                            fontSize: AniHowSpace.body,
                            fontWeight: FontWeight.w700,
                            color: reservation.sellerId == null ? null : AniHowColors.deepGreen,
                          ),
                        ),
                      ),
                      if (reservation.location != null && reservation.location!.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(reservation.location!, style: const TextStyle(fontSize: AniHowSpace.body)),
                      ],
                      if (reservation.contact != null && reservation.contact!.isNotEmpty) ...[
                        const SizedBox(height: AniHowSpace.cardGap),
                        TextButton.icon(
                          onPressed: () => _call(reservation.contact!),
                          icon: const Icon(Icons.phone_outlined),
                          label: Text(reservation.contact!),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
              if (reservation.isCancelled &&
                  reservation.cancellationReason != null &&
                  reservation.cancellationReason!.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(
                  reservation.cancellationReason!,
                  style: const TextStyle(fontSize: AniHowSpace.meta),
                ),
              ],
              if (reservation.isPending) ...[
                const SizedBox(height: AniHowSpace.section),
                PrimaryButton(
                  label: 'Cancel reservation',
                  busy: _busy,
                  onPressed: _cancel,
                ),
              ],
              if (reservation.canReview) ...[
                const SizedBox(height: AniHowSpace.section),
                PrimaryButton(
                  label: 'Rate this farmer',
                  onPressed: () => _review(reservation),
                ),
              ],
              if (reservation.isCompleted && !reservation.canReview && reservation.reviewRating != null) ...[
                const SizedBox(height: AniHowSpace.section),
                Text(
                  'You rated this farmer ${reservation.reviewRating}/5',
                  style: const TextStyle(fontSize: AniHowSpace.body),
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class _PickupProgress extends StatelessWidget {
  const _PickupProgress({required this.status});

  final String status;

  int get _step {
    return switch (status) {
      'ready_for_pickup' || 'ready' => 1,
      'completed' => 2,
      'cancelled' => -1,
      _ => 0,
    };
  }

  @override
  Widget build(BuildContext context) {
    if (_step < 0) {
      return const Text(
        'This reservation was cancelled.',
        style: TextStyle(fontSize: AniHowSpace.body),
      );
    }
    final inactive = Theme.of(context).dividerColor;
    const labels = ['Pending', 'Ready for pickup', 'Completed'];
    return Row(
      children: [
        for (var index = 0; index < labels.length; index++) ...[
          if (index > 0)
            Expanded(
              child: Container(
                height: 2,
                color: index <= _step ? AniHowColors.brand : inactive,
              ),
            ),
          Column(
            children: [
              CircleAvatar(
                radius: 14,
                backgroundColor: index <= _step ? AniHowColors.brand : inactive,
                foregroundColor: Colors.white,
                child: Text(
                  '${index + 1}',
                  style: const TextStyle(fontSize: AniHowSpace.label, fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                labels[index],
                style: TextStyle(
                  fontSize: AniHowSpace.label,
                  fontWeight: index <= _step ? FontWeight.w700 : FontWeight.w500,
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _ReviewSheet extends StatefulWidget {
  const _ReviewSheet({required this.reservationId});

  final int reservationId;

  @override
  State<_ReviewSheet> createState() => _ReviewSheetState();
}

class _ReviewSheetState extends State<_ReviewSheet> {
  int _rating = 0;
  final _comment = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _comment.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_rating < 1) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a rating from 1 to 5.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.submitReview(
            reservationId: widget.reservationId,
            rating: _rating,
            comment: _comment.text.trim(),
          );
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen,
        AniHowSpace.screen + bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Rate this farmer',
            style: TextStyle(fontSize: AniHowSpace.title, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: List.generate(5, (index) {
              final value = index + 1;
              return IconButton(
                onPressed: () => setState(() => _rating = value),
                icon: Icon(
                  value <= _rating ? Icons.star : Icons.star_border,
                  color: AniHowColors.pending,
                ),
              );
            }),
          ),
          TextField(
            controller: _comment,
            maxLines: 3,
            decoration: const InputDecoration(
              hintText: 'Optional comment',
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(
            label: 'Submit review',
            busy: _busy,
            onPressed: _submit,
          ),
        ],
      ),
    );
  }
}
