import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/qr_gallery.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/chat_message_bubble.dart';
import '../../widgets/form_label.dart';
import '../../widgets/payment_card.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/report_sheet.dart';
import '../chat/order_chat_screen.dart';
import 'buyer_order_detail_screen.dart';

class PayNowScreen extends StatefulWidget {
  const PayNowScreen({super.key, this.order, this.orderId})
    : assert(order != null || orderId != null);

  final OrderRecord? order;
  final int? orderId;

  @override
  State<PayNowScreen> createState() => _PayNowScreenState();
}

class _PayNowScreenState extends State<PayNowScreen> {
  OrderRecord? _order;
  bool _loading = false;
  bool _sending = false;
  bool _savingQr = false;
  bool _showForm = true;
  int? _qrId;
  String? _screenshotPath;
  late final TextEditingController _reference;
  late final TextEditingController _amount;

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    _reference = TextEditingController();
    _amount = TextEditingController(text: widget.order?.total ?? '');
    _qrId = widget.order?.paymentQrs.isNotEmpty == true
        ? widget.order!.paymentQrs.first.id
        : null;
    _showForm = widget.order != null && _startsWithForm(widget.order!);
    if (widget.order == null) {
      _loading = true;
      _load();
    }
  }

  bool _startsWithForm(OrderRecord order) {
    return order.isAwaitingPayment && order.latestProof?.isRejected != true;
  }

  Future<void> _load() async {
    try {
      final order = await context.read<AuthController>().api.buyerOrder(
        widget.orderId ?? widget.order!.id,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _order = order;
        _loading = false;
        _amount.text = order.total;
        _qrId ??= order.paymentQrs.isNotEmpty ? order.paymentQrs.first.id : null;
        _showForm = _startsWithForm(order);
      });
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  void dispose() {
    _reference.dispose();
    _amount.dispose();
    super.dispose();
  }

  PaymentQrCode? _selected(OrderRecord order) {
    for (final qr in order.paymentQrs) {
      if (qr.id == _qrId) {
        return qr;
      }
    }
    return order.paymentQrs.isEmpty ? null : order.paymentQrs.first;
  }

  Future<void> _saveQr(PaymentQrCode qr) async {
    final s = AppStrings.read(context);
    final url = qr.imageUrl;
    if (url == null || url.isEmpty || _savingQr) {
      return;
    }
    setState(() => _savingQr = true);
    try {
      final bytes = await context.read<AuthController>().api.downloadAuthorized(url);
      await QrGallery.saveImageBytes(Uint8List.fromList(bytes), name: 'anihow-qr-${qr.id}');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.qrSaved)));
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(s.qrSaveNeedsPermission)),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _savingQr = false);
      }
    }
  }

  Future<void> _pickScreenshot() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (context) {
        final s = AppStrings.of(context);
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                title: Text(s.pickFromGallery),
                onTap: () => Navigator.pop(context, ImageSource.gallery),
              ),
              ListTile(
                title: Text(s.pickFromCamera),
                onTap: () => Navigator.pop(context, ImageSource.camera),
              ),
            ],
          ),
        );
      },
    );
    if (source == null) {
      return;
    }
    final picked = await ImagePicker().pickImage(
      source: source,
      maxWidth: 1600,
      imageQuality: 82,
    );
    if (picked != null && mounted) {
      setState(() => _screenshotPath = picked.path);
    }
  }

  Future<void> _submit(OrderRecord order) async {
    final s = AppStrings.read(context);
    if (_reference.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.referenceRequired)));
      return;
    }
    final qr = _selected(order);
    if (qr == null) {
      return;
    }
    setState(() => _sending = true);
    try {
      final updated = await context.read<AuthController>().api.submitPaymentProof(
        order.id,
        referenceNumber: _reference.text.trim(),
        amount: _amount.text.trim().isEmpty ? order.total : _amount.text.trim(),
        qrId: qr.id,
        screenshotPath: _screenshotPath,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        _order = updated;
        _sending = false;
        _showForm = false;
      });
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _sending = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  bool _dueSoon(DateTime due) {
    return due.difference(DateTime.now()) < const Duration(hours: 1);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final order = _order;
    final rejected = order?.latestProof?.isRejected == true;
    final showPayTools = order != null &&
        _showForm &&
        (order.isAwaitingPayment || rejected);
    return Scaffold(
      appBar: AppBar(title: Text(s.payNow)),
      body: _loading || order == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                _AmountCard(order: order, dueSoon: _dueSoon),
                const SizedBox(height: AniHowSpace.cardGap),
                if (rejected && !_showForm) _RejectedCard(
                  order: order,
                  onSendAgain: () => setState(() => _showForm = true),
                ),
                if (order.isPaymentSent && !rejected)
                  _SentCard(order: order),
                if (order.paymentIsPaid) _PaidCard(order: order),
                if (showPayTools) ...[
                  const _PaySteps(),
                  const SizedBox(height: AniHowSpace.cardGap),
                  if (order.paymentQrs.length > 1)
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final qr in order.paymentQrs)
                          ChoiceChip(
                            key: ValueKey('pay-wallet-${qr.id}'),
                            label: Text(s.walletLabel(qr.wallet)),
                            selected: (_selected(order)?.id ?? 0) == qr.id,
                            onSelected: (_) => setState(() => _qrId = qr.id),
                          ),
                      ],
                    ),
                  if (_selected(order) case final qr?) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    _QrCard(
                      qr: qr,
                      saving: _savingQr,
                      onSave: () => _saveQr(qr),
                    ),
                  ],
                  const SizedBox(height: AniHowSpace.cardGap),
                  _PaidForm(
                    reference: _reference,
                    amount: _amount,
                    screenshotPath: _screenshotPath,
                    sending: _sending,
                    onPick: _pickScreenshot,
                    onClearShot: () => setState(() => _screenshotPath = null),
                    onSubmit: () => _submit(order),
                  ),
                ],
              ],
            ),
    );
  }
}

class _AmountCard extends StatelessWidget {
  const _AmountCard({required this.order, required this.dueSoon});

  final OrderRecord order;
  final bool Function(DateTime due) dueSoon;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final due = order.paymentDueAt;
    final urgent = due != null && dueSoon(due);
    final (label, fg, bg) = _chip(s, urgent);
    return DecoratedBox(
      decoration: paymentCardDecoration(context),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.amountToPay, style: theme.textTheme.bodyMedium),
            Text(
              AniHowMoney.peso(order.total),
              key: const ValueKey('pay-amount'),
              style: theme.textTheme.displaySmall?.copyWith(
                fontSize: 40,
                fontWeight: FontWeight.w800,
                height: 1.1,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              s.shopOrderLine(order.stallName, order.orderNumber),
              key: const ValueKey('pay-seller'),
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
              ),
            ),
            const SizedBox(height: 10),
            DecoratedBox(
              key: const ValueKey('pay-deadline'),
              decoration: BoxDecoration(
                color: bg,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                child: Text(
                  label,
                  style: theme.textTheme.labelLarge?.copyWith(
                    color: fg,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  (String, Color, Color) _chip(AppStrings s, bool urgent) {
    final due = order.paymentDueAt;
    if (order.paymentIsPaid) {
      return (s.paymentStatusLabel('paid'), AniHowColors.inStock, AniHowColors.inStockBg);
    }
    if (order.isPaymentSent && order.latestProof?.isRejected != true) {
      return (
        s.paymentSentChip,
        AniHowColors.confirmedBlue,
        const Color(0xFFDBEAFE),
      );
    }
    if (order.latestProof?.isRejected == true) {
      return (
        due == null ? s.sendAgain : s.sendAgainChip(due),
        AniHowColors.root,
        const Color(0xFFFFF4D6),
      );
    }
    if (urgent) {
      return (
        due == null ? s.payBeforeLabel : s.payBefore(due),
        AniHowColors.root,
        const Color(0xFFFFF4D6),
      );
    }
    return (
      due == null ? s.payBeforeLabel : s.payBefore(due),
      AniHowColors.inStock,
      AniHowColors.inStockBg,
    );
  }
}

class _PaySteps extends StatelessWidget {
  const _PaySteps();

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final steps = [s.payStepSave, s.payStepPay, s.payStepReference];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < steps.length; i++)
          Expanded(
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 4),
              child: Column(
                children: [
                  CircleAvatar(
                    radius: 14,
                    backgroundColor: AniHowColors.brand,
                    child: Text(
                      '${i + 1}',
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    steps[i],
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _QrCard extends StatelessWidget {
  const _QrCard({required this.qr, required this.saving, required this.onSave});

  final PaymentQrCode qr;
  final bool saving;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final url = qr.imageUrl;
    return DecoratedBox(
      decoration: paymentCardDecoration(context),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          children: [
            GestureDetector(
              key: const ValueKey('pay-qr'),
              onTap: url == null
                  ? null
                  : () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) => Scaffold(
                            appBar: AppBar(),
                            body: Center(
                              child: AuthorizedChatImage(url: url, fit: BoxFit.contain),
                            ),
                          ),
                        ),
                      );
                    },
              child: Container(
                constraints: const BoxConstraints(maxWidth: 236, maxHeight: 236),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AniHowColors.hairline),
                ),
                clipBehavior: Clip.antiAlias,
                child: AspectRatio(
                  aspectRatio: 1,
                  child: url == null
                      ? const Icon(Icons.qr_code_2_outlined)
                      : AuthorizedChatImage(url: url, fit: BoxFit.contain),
                ),
              ),
            ),
            const SizedBox(height: 10),
            Text(
              s.walletAccount(s.walletLabel(qr.wallet), qr.accountName),
              key: const ValueKey('pay-account-name'),
              style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
            ),
            Text(
              s.last4Zoom(qr.accountLast4),
              key: const ValueKey('pay-account-last4'),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                key: const ValueKey('pay-save-qr'),
                onPressed: saving ? null : onSave,
                icon: const Icon(Icons.download_outlined),
                label: Text(s.saveQrToGallery),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PaidForm extends StatelessWidget {
  const _PaidForm({
    required this.reference,
    required this.amount,
    required this.screenshotPath,
    required this.sending,
    required this.onPick,
    required this.onClearShot,
    required this.onSubmit,
  });

  final TextEditingController reference;
  final TextEditingController amount;
  final String? screenshotPath;
  final bool sending;
  final VoidCallback onPick;
  final VoidCallback onClearShot;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return DecoratedBox(
      decoration: paymentCardDecoration(context),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.ivePaid, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.referenceNumber,
              child: TextField(
                key: const ValueKey('pay-reference'),
                controller: reference,
                decoration: InputDecoration(
                  hintText: s.referenceHint,
                  helperText: s.referenceHelper,
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.amountPaid,
              child: TextField(
                key: const ValueKey('pay-proof-amount'),
                controller: amount,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            if (screenshotPath == null)
              DashedAction(
                key: const ValueKey('pay-add-screenshot'),
                label: s.addReceiptScreenshot,
                onPressed: onPick,
              )
            else
              Row(
                key: const ValueKey('pay-add-screenshot'),
                children: [
                  ClipRRect(
                    borderRadius: BorderRadius.circular(8),
                    child: Image.file(
                      File(screenshotPath!),
                      width: 64,
                      height: 64,
                      fit: BoxFit.cover,
                    ),
                  ),
                  IconButton(
                    onPressed: onClearShot,
                    icon: const Icon(Icons.close),
                  ),
                ],
              ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PrimaryButton(
              key: const ValueKey('pay-submit'),
              label: s.sendPaymentProof,
              busy: sending,
              onPressed: sending ? null : onSubmit,
            ),
          ],
        ),
      ),
    );
  }
}

class _SentCard extends StatelessWidget {
  const _SentCard({required this.order});

  final OrderRecord order;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final wallet = s.walletLabel(proof?.wallet);
    final last4 = proof?.accountLast4;
    final paidTo = last4 == null || last4.isEmpty ? wallet : '$wallet ${s.maskedLast4(last4)}';
    final muted = Theme.of(context).textTheme.bodyMedium;
    return DecoratedBox(
      key: const ValueKey('pay-sent-status'),
      decoration: paymentCardDecoration(context),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.schedule, color: AniHowColors.confirmedBlue),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s.paymentStatusLabel('payment_sent'),
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      Text(s.waitingForShop(order.stallName), style: muted),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text('${s.referenceNumber}  ${proof?.reference ?? ''}'),
            Text('${s.proofAmount}  ${AniHowMoney.peso(proof?.amount ?? order.total)}'),
            Text('${s.paidTo}  $paidTo'),
            if (proof?.sentAt != null) Text('${s.sentLabel}  ${s.sentAgo(proof!.sentAt!)}'),
            const SizedBox(height: 10),
            DecoratedBox(
              decoration: BoxDecoration(
                color: const Color(0xFFDBEAFE),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Text(s.paymentSentNote),
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => OrderChatScreen(order: order)),
                );
              },
              child: Text(s.chatWithSeller),
            ),
            const SizedBox(height: 8),
            PrimaryButton(
              label: s.viewOrder,
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => BuyerOrderDetailScreen(order: order),
                  ),
                );
              },
            ),
            TextButton(
              onPressed: () => showReportSheet(
                context,
                targetType: 'order',
                targetId: order.id,
              ),
              child: Text(s.reportPaymentLink),
            ),
          ],
        ),
      ),
    );
  }
}

class _RejectedCard extends StatelessWidget {
  const _RejectedCard({required this.order, required this.onSendAgain});

  final OrderRecord order;
  final VoidCallback onSendAgain;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final due = order.paymentDueAt;
    return DecoratedBox(
      key: const ValueKey('pay-rejected'),
      decoration: paymentCardDecoration(context, borderColor: AniHowColors.lowStockBg)
          .copyWith(color: const Color(0xFFFFF1F0)),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.paymentNotReceivedTitle,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AniHowColors.cancelledRed,
                fontWeight: FontWeight.w700,
              ),
            ),
            if (proof?.rejectionReason != null)
              Text(s.reasonLine(s.paymentRejectReason(proof!.rejectionReason!))),
            if (proof?.rejectionNote != null && proof!.rejectionNote!.isNotEmpty)
              Container(
                margin: const EdgeInsets.symmetric(vertical: 8),
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  border: Border(
                    left: BorderSide(color: AniHowColors.cancelledRed.withValues(alpha: 0.5), width: 3),
                  ),
                ),
                child: Text('“${proof.rejectionNote}”'),
              ),
            if (due != null) Text(s.sendAgainBefore(due)),
            const SizedBox(height: 8),
            PrimaryButton(
              key: const ValueKey('pay-send-again'),
              label: s.sendAgain,
              onPressed: onSendAgain,
            ),
          ],
        ),
      ),
    );
  }
}

class _PaidCard extends StatelessWidget {
  const _PaidCard({required this.order});

  final OrderRecord order;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = order.latestProof;
    final when = proof?.reviewedAt;
    return DecoratedBox(
      key: const ValueKey('pay-paid-card'),
      decoration: paymentCardDecoration(context, borderColor: AniHowColors.inStock),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              s.paymentConfirmed,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: AniHowColors.inStock,
                fontWeight: FontWeight.w700,
              ),
            ),
            Text(s.shopReceived(order.stallName, AniHowMoney.peso(order.total))),
            Text('${s.referenceNumber}  ${proof?.reference ?? ''}'),
            if (when != null) Text('${s.confirmedLabel}  ${s.confirmedAt(when)}'),
            const SizedBox(height: 8),
            Text(s.paidNextNote),
            const SizedBox(height: 8),
            PrimaryButton(
              label: s.viewOrder,
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => BuyerOrderDetailScreen(order: order),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class PaySellersScreen extends StatelessWidget {
  const PaySellersScreen({super.key, required this.orders});

  final List<OrderRecord> orders;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final online = orders
        .where((order) => order.paymentMethod == 'online_transfer')
        .toList();
    return Scaffold(
      appBar: AppBar(title: Text(s.paySellers(online.length))),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(
            s.paySellers(online.length),
            key: const ValueKey('pay-sellers-title'),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          for (final order in online)
            Card(
              child: ListTile(
                key: ValueKey('pay-seller-${order.id}'),
                title: Text(order.stallName),
                subtitle: Text(
                  [
                    AniHowMoney.peso(order.total),
                    if (order.paymentDueAt != null) s.payBefore(order.paymentDueAt!),
                    if (order.paymentStatus != null)
                      s.paymentStatusLabel(order.paymentStatus),
                  ].where((line) => line.isNotEmpty).join('\n'),
                ),
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => PayNowScreen(order: order),
                    ),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }
}
