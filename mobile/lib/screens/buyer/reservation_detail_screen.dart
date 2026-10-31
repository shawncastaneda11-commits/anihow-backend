import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/chat_message_bubble.dart';
import '../../widgets/order_payment_summary.dart';
import '../../widgets/payment_card.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/status_pill.dart';
import 'pay_now_screen.dart';

class ReservationDetailScreen extends StatefulWidget {
  const ReservationDetailScreen({
    super.key,
    required this.reservation,
    this.forSeller = false,
  });

  final ReservationRecord reservation;
  final bool forSeller;

  @override
  State<ReservationDetailScreen> createState() => _ReservationDetailScreenState();
}

class _ReservationDetailScreenState extends State<ReservationDetailScreen> {
  late ReservationRecord _reservation;
  String? _message;

  @override
  void initState() {
    super.initState();
    _reservation = widget.reservation;
  }

  Future<void> _review(String decision, {String? reason, String? note}) async {
    final proof = _reservation.latestProof;
    if (proof?.id == null) {
      return;
    }
    try {
      final updated = await context.read<AuthController>().api.reviewReservationProof(
        _reservation.id,
        proof!.id!,
        decision: decision,
        reason: reason,
        note: note,
      );
      if (mounted) {
        setState(() => _reservation = updated);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _confirmReceived() async {
    final s = AppStrings.of(context);
    final proof = _reservation.latestProof;
    final wallet = s.walletLabel(proof?.wallet);
    final walletName = wallet.isEmpty ? s.walletGcash : wallet;
    final yes = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          s.arrivedInWallet(AniHowMoney.peso(_reservation.amountLabel), walletName),
        ),
        content: Text(s.lookForReference(lastFourOf(proof?.reference), walletName)),
        actions: [
          TextButton(
            key: const ValueKey('payment-received-cancel'),
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(s.cancel),
          ),
          TextButton(
            key: const ValueKey('payment-received-yes'),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(s.yesReceived),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) {
      return;
    }
    await _review('accept');
  }

  Future<void> _reject() async {
    final choice = await showModalBottomSheet<({String reason, String? note})>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const RejectPaymentProofSheet(),
    );
    if (choice == null || !mounted) {
      return;
    }
    await _review('reject', reason: choice.reason, note: choice.note);
  }

  Future<void> _refund() async {
    final reference = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const RefundReferenceSheet(),
    );
    if (reference == null || !mounted) {
      return;
    }
    try {
      final updated = await context.read<AuthController>().api.refundReservation(
        _reservation.id,
        refundReference: reference,
      );
      if (mounted) {
        setState(() => _reservation = updated);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  /// Only for a payment the seller never confirmed: the cancellation made it
  /// a refund due, but no money reached the seller to send back.
  Future<void> _neverArrived() async {
    final s = AppStrings.of(context);
    final yes = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(s.paymentNeverArrivedTitle),
        content: Text(s.paymentNeverArrivedBody(AniHowMoney.peso(_reservation.amountLabel))),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(s.cancel),
          ),
          TextButton(
            key: const ValueKey('payment-never-arrived-yes'),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(s.closeRefund),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) {
      return;
    }
    try {
      final updated = await context.read<AuthController>().api.markReservationPaymentNotReceived(
        _reservation.id,
      );
      if (mounted) {
        setState(() => _reservation = updated);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _cancel() async {
    final listingId = _reservation.listingId;
    if (listingId == null) {
      return;
    }
    try {
      final message = await context.read<AuthController>().api.cancelFarmerReservation(
        listingId,
        _reservation.id,
      );
      if (!mounted) {
        return;
      }
      setState(() => _message = message);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _message = error.message);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final reservation = _reservation;
    final proof = reservation.latestProof;
    final showCheck = widget.forSeller &&
        reservation.isPaymentSent &&
        proof != null &&
        !proof.isRejected;
    return Scaffold(
      appBar: AppBar(title: Text(reservation.listingName)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          if (widget.forSeller)
            _SellerBuyerCard(reservation: reservation)
          else ...[
            Text(
              reservation.buyerName ?? reservation.listingName,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Text(AniHowMoney.peso(reservation.lineTotal)),
                PaymentTrackingPill(status: reservation.paymentStatus),
              ],
            ),
          ],
          if (reservation.isAwaitingPayment && reservation.paymentDueAt != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(s.payBefore(reservation.paymentDueAt!)),
            ),
          if (reservation.refundReference != null &&
              reservation.refundReference!.isNotEmpty &&
              (reservation.paymentStatus == 'refund_due' ||
                  reservation.paymentStatus == 'refunded'))
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text('${s.refundReference}: ${reservation.refundReference}'),
            ),
          if (_message != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_message!, key: const ValueKey('reservation-server-message')),
            ),
          const SizedBox(height: AniHowSpace.cardGap),
          if (showCheck) _CheckCard(
            reservation: reservation,
            onReceived: _confirmReceived,
            onReject: _reject,
          ),
          if (!widget.forSeller && reservation.isAwaitingPayment)
            PrimaryButton(
              key: ValueKey('reservation-pay-now-${reservation.id}'),
              label: s.payNow,
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => PayNowScreen(reservation: reservation),
                  ),
                );
              },
            ),
          if (!widget.forSeller &&
              reservation.isPaymentTracked &&
              !reservation.isAwaitingPayment)
            OutlinedButton(
              key: ValueKey('reservation-view-payment-${reservation.id}'),
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => PayNowScreen(reservation: reservation),
                  ),
                );
              },
              child: Text(s.viewPayment),
            ),
          if (widget.forSeller && reservation.paymentStatus == 'refund_due') ...[
            _RefundDueCard(reservation: reservation),
            if (proof != null) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              _BuyerSentCard(reservation: reservation),
            ],
            const SizedBox(height: AniHowSpace.cardGap),
            PrimaryButton(
              key: const ValueKey('mark-refunded'),
              label: s.markRefunded,
              onPressed: _refund,
            ),
            if (reservation.paidAt == null) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              OutlinedButton(
                key: const ValueKey('payment-never-arrived'),
                onPressed: _neverArrived,
                child: Text(s.paymentNeverArrived),
              ),
              const SizedBox(height: 6),
              Text(
                s.neverArrivedHelp,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.68),
                ),
              ),
            ],
          ],
          if (widget.forSeller && reservation.isActive && reservation.listingId != null)
            TextButton(
              onPressed: _cancel,
              child: Text(s.cancelReservation),
            ),
        ],
      ),
    );
  }
}

class _CheckCard extends StatelessWidget {
  const _CheckCard({
    required this.reservation,
    required this.onReceived,
    required this.onReject,
  });

  final ReservationRecord reservation;
  final VoidCallback onReceived;
  final VoidCallback onReject;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final proof = reservation.latestProof;
    final wallet = s.walletLabel(proof?.wallet);
    final last4 = proof?.accountLast4;
    final paidTo = last4 == null || last4.isEmpty
        ? wallet
        : '$wallet ${s.maskedLast4(last4)}';
    final shot = proof != null && proof.hasScreenshot && proof.screenshotUrl != null;
    return Padding(
      padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
      child: DecoratedBox(
        key: const ValueKey('order-payment-card'),
        decoration: paymentCardDecoration(
          context,
          borderColor: AniHowColors.confirmedBlue,
          width: 2,
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      s.checkThisPayment,
                      style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                  ),
                  PaymentTonePill(
                    label: s.paymentStatusLabel('payment_sent'),
                    foreground: AniHowColors.confirmedBlue,
                    background: const Color(0xFFDBEAFE),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              if (shot)
                GestureDetector(
                  key: const ValueKey('payment-screenshot'),
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => Scaffold(
                          appBar: AppBar(),
                          body: Center(
                            child: AuthorizedChatImage(
                              url: proof.screenshotUrl!,
                              fit: BoxFit.contain,
                            ),
                          ),
                        ),
                      ),
                    );
                  },
                  child: SizedBox(
                    height: 72,
                    width: 72,
                    child: AuthorizedChatImage(url: proof.screenshotUrl!),
                  ),
                ),
              Text('${s.referenceEndsIn}  ${lastFourOf(proof?.reference)}'),
              Text('${s.proofAmount}  ${AniHowMoney.peso(proof?.amount)}'),
              Text('${s.toLabel}  $paidTo'),
              const SizedBox(height: 8),
              Text(s.checkReferenceNote),
              const SizedBox(height: AniHowSpace.cardGap),
              Row(
                children: [
                  Expanded(
                    child: PrimaryButton(
                      key: const ValueKey('payment-received'),
                      label: s.receivedPayment,
                      expand: false,
                      onPressed: onReceived,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton(
                      key: const ValueKey('payment-not-received'),
                      onPressed: onReject,
                      child: Text(s.notReceived),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// The seller's header: who reserved, what, and where the payment stands.
class _SellerBuyerCard extends StatelessWidget {
  const _SellerBuyerCard({required this.reservation});

  final ReservationRecord reservation;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final name = reservation.buyerName ?? reservation.listingName;
    final refundDue = reservation.paymentStatus == 'refund_due';
    return Card(
      key: const ValueKey('reservation-buyer-card'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Row(
          children: [
            AniHowAvatar(name: name, imageUrl: reservation.buyerAvatarUrl, radius: 20),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, style: theme.textTheme.titleMedium),
                  Text(
                    s.reservationSubtitle(reservation.listingName, reservation.quantityLabel),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            if (refundDue)
              StatusPill(label: s.paymentStatusLabel('refund_due'), color: AniHowColors.root)
            else
              PaymentTrackingPill(status: reservation.paymentStatus),
          ],
        ),
      ),
    );
  }
}

/// How much to send back, why, and the two steps to finish the refund.
class _RefundDueCard extends StatelessWidget {
  const _RefundDueCard({required this.reservation});

  final ReservationRecord reservation;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    final amount = AniHowMoney.peso(reservation.lineTotal);
    final wallet = s.walletLabel(reservation.latestProof?.wallet);
    return DecoratedBox(
      key: const ValueKey('refund-due-card'),
      decoration: paymentCardDecoration(context, borderColor: AniHowColors.root, width: 2),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.sendBackLabel,
              style: theme.textTheme.labelSmall?.copyWith(
                color: muted,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 2),
            Text(
              amount,
              style: theme.textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 2),
            Text(
              s.cancelledOnBecause(
                reservation.cancelledAt,
                s.reservationCancelReasonForSeller(reservation.cancellationReason),
              ),
              style: theme.textTheme.bodyMedium?.copyWith(color: muted),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            _RefundStep(
              number: 1,
              text: s.refundStepSend(
                wallet.isEmpty ? s.walletGcash : wallet,
                amount,
                reservation.buyerName ?? '',
              ),
            ),
            const SizedBox(height: 8),
            _RefundStep(number: 2, text: s.refundStepMark),
          ],
        ),
      ),
    );
  }
}

class _RefundStep extends StatelessWidget {
  const _RefundStep({required this.number, required this.text});

  final int number;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 22,
          height: 22,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: AniHowColors.root.withValues(alpha: 0.18),
            shape: BoxShape.circle,
          ),
          child: Text(
            '$number',
            style: const TextStyle(
              color: AniHowColors.root,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(text)),
      ],
    );
  }
}

/// The buyer's payment, so the seller can find it in their wallet.
class _BuyerSentCard extends StatelessWidget {
  const _BuyerSentCard({required this.reservation});

  final ReservationRecord reservation;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    final proof = reservation.latestProof!;
    final wallet = s.walletLabel(proof.wallet);
    final last4 = proof.accountLast4;
    final paidTo = last4 == null || last4.isEmpty ? wallet : '$wallet ${s.maskedLast4(last4)}';
    final rows = <(String, String, Color?)>[
      (s.referenceEndsIn, lastFourOf(proof.reference), null),
      (s.proofAmount, AniHowMoney.peso(proof.amount ?? reservation.amountLabel), null),
      if (paidTo.isNotEmpty) (s.toLabel, paidTo, null),
      if (proof.sentAt != null) (s.sentLabel, s.shortDate(proof.sentAt!.toLocal()), null),
      (
        s.youConfirmed,
        reservation.paidAt == null ? s.notYet : s.yesLabel,
        reservation.paidAt == null ? AniHowColors.root : null,
      ),
    ];
    return Card(
      key: const ValueKey('buyer-sent-card'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.whatBuyerSent,
              style: theme.textTheme.labelSmall?.copyWith(
                color: muted,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 8),
            for (final row in rows)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Row(
                  children: [
                    SizedBox(width: 130, child: Text(row.$1, style: TextStyle(color: muted))),
                    Expanded(
                      child: Text(
                        row.$2,
                        style: TextStyle(
                          color: row.$3,
                          fontWeight: row.$3 == null ? null : FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}
