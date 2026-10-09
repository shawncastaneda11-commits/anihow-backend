import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'chat_message_bubble.dart';
import 'form_label.dart';
import 'payment_card.dart';
import 'primary_button.dart';
import 'status_pill.dart';

class OrderPaymentSummary extends StatelessWidget {
  const OrderPaymentSummary({
    super.key,
    required this.order,
    this.forSeller = false,
    this.onChanged,
  });

  final OrderRecord order;
  final bool forSeller;
  final ValueChanged<OrderRecord>? onChanged;

  @override
  Widget build(BuildContext context) {
    if (!order.isPaymentTracked) {
      return const SizedBox.shrink();
    }
    if (forSeller && order.paymentIsPaid) {
      return _paidRow(context);
    }
    if (forSeller && order.isPaymentSent) {
      return _checkCard(context);
    }
    return _plainCard(context);
  }

  Widget _paidRow(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final wallet = s.walletLabel(proof?.wallet);
    final when = proof?.reviewedAt == null ? '' : s.confirmedAt(proof!.reviewedAt!);
    return DecoratedBox(
      key: const ValueKey('order-payment-card'),
      decoration: paymentCardDecoration(context, borderColor: AniHowColors.inStock),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              s.paidVia(AniHowMoney.peso(order.total), wallet.isEmpty ? s.walletGcash : wallet),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: AniHowColors.inStock,
                fontWeight: FontWeight.w700,
              ),
            ),
            if (proof?.reference != null)
              Text(
                s.refConfirmed(proof!.reference!, when),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.68),
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _checkCard(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final proof = order.latestProof;
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
    );
    final wallet = s.walletLabel(proof?.wallet);
    final last4 = proof?.accountLast4;
    final paidTo = last4 == null || last4.isEmpty
        ? wallet
        : '$wallet ${s.maskedLast4(last4)}';
    return DecoratedBox(
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
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _screenshot(proof),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _row(s.referenceNumber, proof?.reference ?? ''),
                      _row(s.proofAmount, AniHowMoney.peso(proof?.amount)),
                      _row(s.toLabel, paidTo),
                      if (proof?.sentAt != null) _row(s.sentLabel, s.sentAgo(proof!.sentAt!)),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            Text(s.checkReferenceNote, style: muted),
            const SizedBox(height: AniHowSpace.cardGap),
            Row(
              children: [
                Expanded(
                  child: PrimaryButton(
                    key: const ValueKey('payment-received'),
                    label: s.receivedPayment,
                    expand: false,
                    onPressed: () => _confirmReceived(context),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: OutlinedButton(
                    key: const ValueKey('payment-not-received'),
                    onPressed: () => _reject(context),
                    child: Text(s.notReceived),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _screenshot(PaymentProofRecord? proof) {
    final hasShot = proof != null && proof.hasScreenshot && proof.screenshotUrl != null;
    final thumb = Container(
      width: 72,
      height: 72,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: AniHowColors.hairline),
      ),
      clipBehavior: Clip.antiAlias,
      child: hasShot
          ? AuthorizedChatImage(url: proof.screenshotUrl!, fit: BoxFit.cover)
          : const Icon(Icons.receipt_long_outlined, color: AniHowColors.muted),
    );
    if (!hasShot) {
      return thumb;
    }
    return Builder(
      builder: (context) => GestureDetector(
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
        child: thumb,
      ),
    );
  }

  Widget _row(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 2),
      child: Text('$label  $value'),
    );
  }

  Widget _plainCard(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    return Card(
      key: const ValueKey('order-payment-card'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(s.onlinePaymentSection, style: Theme.of(context).textTheme.titleMedium),
                ),
                PaymentTrackingPill(status: order.paymentStatus),
              ],
            ),
            if (order.paymentDueAt != null && order.isAwaitingPayment)
              Text(s.payBefore(order.paymentDueAt!)),
            if (proof != null) ...[
              Text('${s.referenceNumber}: ${proof.reference ?? ''}'),
              Text('${s.proofAmount}: ${AniHowMoney.peso(proof.amount)}'),
              if (proof.wallet != null) Text(s.walletLabel(proof.wallet)),
              if (proof.isRejected && proof.rejectionReason != null)
                Text(
                  s.paymentRejectReason(proof.rejectionReason!),
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              if (proof.rejectionNote != null && proof.rejectionNote!.isNotEmpty)
                Text(proof.rejectionNote!),
              if (proof.hasScreenshot && proof.screenshotUrl != null)
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
            ],
            if (order.refundReference != null && order.refundReference!.isNotEmpty)
              Text('${s.refundReference}: ${order.refundReference}'),
            if (forSeller && order.paymentStatus == 'refund_due')
              PrimaryButton(
                key: const ValueKey('mark-refunded'),
                label: s.markRefunded,
                onPressed: () => _refund(context),
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _confirmReceived(BuildContext context) async {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final wallet = s.walletLabel(proof?.wallet);
    final differs = proof?.amount != null && _money(proof!.amount) != _money(order.total);
    final yes = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(s.arrivedInWallet(AniHowMoney.peso(order.total), wallet.isEmpty ? s.walletGcash : wallet)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.lookForReference(proof?.reference ?? '', wallet.isEmpty ? s.walletGcash : wallet)),
            if (differs)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: Text(
                  s.proofAmountDiffers,
                  key: const ValueKey('proof-amount-differs'),
                  style: const TextStyle(color: AniHowColors.root),
                ),
              ),
          ],
        ),
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
    if (yes != true || !context.mounted) {
      return;
    }
    await _review(context, 'accept');
  }

  Future<void> _review(BuildContext context, String decision, {String? reason, String? note}) async {
    final proof = order.latestProof;
    if (proof == null) {
      return;
    }
    try {
      final updated = await context.read<AuthController>().api.reviewPaymentProof(
        order.id,
        proof.id ?? 0,
        decision: decision,
        reason: reason,
        note: note,
      );
      onChanged?.call(updated);
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _reject(BuildContext context) async {
    final choice = await showModalBottomSheet<({String reason, String? note})>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _RejectProofSheet(),
    );
    if (choice == null || !context.mounted) {
      return;
    }
    await _review(context, 'reject', reason: choice.reason, note: choice.note);
  }

  Future<void> _refund(BuildContext context) async {
    final reference = await showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _RefundSheet(),
    );
    if (reference == null || !context.mounted) {
      return;
    }
    try {
      final updated = await context.read<AuthController>().api.refundOrder(
        order.id,
        refundReference: reference,
      );
      onChanged?.call(updated);
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  static String _money(String? value) {
    final parsed = double.tryParse(value ?? '');
    if (parsed == null) {
      return value ?? '';
    }
    return parsed.toStringAsFixed(2);
  }
}

class _RejectProofSheet extends StatefulWidget {
  const _RejectProofSheet();

  @override
  State<_RejectProofSheet> createState() => _RejectProofSheetState();
}

class _RejectProofSheetState extends State<_RejectProofSheet> {
  String _reason = 'not_received';
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 8,
            children: [
              for (final reason in ['not_received', 'wrong_amount', 'wrong_reference', 'other'])
                ChoiceChip(
                  key: ValueKey('reject-$reason'),
                  label: Text(s.paymentRejectReason(reason)),
                  selected: _reason == reason,
                  onSelected: (_) => setState(() => _reason = reason),
                ),
            ],
          ),
          if (_reason == 'other')
            TextField(
              key: const ValueKey('reject-note'),
              controller: _note,
              decoration: InputDecoration(labelText: s.noteOptional),
            ),
          const SizedBox(height: AniHowSpace.fieldGap),
          PrimaryButton(
            key: const ValueKey('reject-submit'),
            label: s.notReceived,
            onPressed: () {
              if (_reason == 'other' && _note.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(s.rejectionNoteRequired)),
                );
                return;
              }
              Navigator.pop(context, (reason: _reason, note: _note.text.trim()));
            },
          ),
        ],
      ),
    );
  }
}

class _RefundSheet extends StatefulWidget {
  const _RefundSheet();

  @override
  State<_RefundSheet> createState() => _RefundSheetState();
}

class _RefundSheetState extends State<_RefundSheet> {
  final _reference = TextEditingController();

  @override
  void dispose() {
    _reference.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AniHowField(
            label: s.refundReference,
            child: TextField(
              key: const ValueKey('refund-reference'),
              controller: _reference,
            ),
          ),
          PrimaryButton(
            key: const ValueKey('refund-submit'),
            label: s.markRefunded,
            onPressed: () {
              if (_reference.text.trim().isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(content: Text(s.refundReferenceRequired)),
                );
                return;
              }
              Navigator.pop(context, _reference.text.trim());
            },
          ),
        ],
      ),
    );
  }
}
