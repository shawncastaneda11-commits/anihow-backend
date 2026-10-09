import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import 'chat_message_bubble.dart';
import 'form_label.dart';
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
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final amountsDiffer = proof?.amount != null &&
        _money(proof!.amount) != _money(order.total);
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
            if (forSeller && order.isPaymentSent && amountsDiffer)
              Text(
                s.proofAmountDiffers,
                key: const ValueKey('proof-amount-differs'),
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            if (forSeller && order.isPaymentSent && proof != null && proof.isPending) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              Row(
                children: [
                  Expanded(
                    child: PrimaryButton(
                      key: const ValueKey('payment-received'),
                      label: s.receivedPayment,
                      expand: false,
                      onPressed: () => _review(context, 'accept'),
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
