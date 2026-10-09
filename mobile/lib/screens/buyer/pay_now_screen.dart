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

class _PayTarget {
  const _PayTarget({
    required this.amount,
    required this.peerName,
    required this.paymentQrs,
    required this.isReservation,
    this.orderNumber,
    this.listingName,
    this.paymentStatus,
    this.paymentDueAt,
    this.latestProof,
    this.order,
  });

  final String amount;
  final String peerName;
  final String? orderNumber;
  final String? listingName;
  final String? paymentStatus;
  final DateTime? paymentDueAt;
  final PaymentProofRecord? latestProof;
  final List<PaymentQrCode> paymentQrs;
  final bool isReservation;
  final OrderRecord? order;

  bool get isAwaitingPayment => paymentStatus == 'awaiting_payment';

  bool get isPaymentSent => paymentStatus == 'payment_sent';

  bool get paymentIsPaid => paymentStatus == 'paid';

  factory _PayTarget.fromOrder(OrderRecord order) {
    return _PayTarget(
      amount: order.total,
      peerName: order.stallName,
      orderNumber: order.orderNumber,
      paymentStatus: order.paymentStatus,
      paymentDueAt: order.paymentDueAt,
      latestProof: order.latestProof,
      paymentQrs: order.paymentQrs,
      isReservation: false,
      order: order,
    );
  }

  factory _PayTarget.fromReservation(ReservationRecord reservation) {
    return _PayTarget(
      amount: reservation.amountLabel,
      peerName: reservation.buyerName ?? '',
      listingName: reservation.listingName,
      paymentStatus: reservation.paymentStatus,
      paymentDueAt: reservation.paymentDueAt,
      latestProof: reservation.latestProof,
      paymentQrs: reservation.paymentQrs,
      isReservation: true,
    );
  }
}

class PayNowScreen extends StatefulWidget {
  const PayNowScreen({
    super.key,
    this.order,
    this.orderId,
    this.reservation,
    this.reservationId,
  }) : assert(
         order != null ||
             orderId != null ||
             reservation != null ||
             reservationId != null,
       );

  final OrderRecord? order;
  final int? orderId;
  final ReservationRecord? reservation;
  final int? reservationId;

  bool get paysReservation => reservation != null || reservationId != null;

  @override
  State<PayNowScreen> createState() => _PayNowScreenState();
}

class _PayNowScreenState extends State<PayNowScreen> with WidgetsBindingObserver {
  OrderRecord? _order;
  ReservationRecord? _reservation;
  bool _loading = false;
  bool _sending = false;
  bool _savingQr = false;
  bool _showForm = true;
  int? _qrId;
  String? _screenshotPath;
  late final TextEditingController _reference;
  late final TextEditingController _amount;

  bool get _paysReservation => widget.paysReservation;

  _PayTarget? get _target {
    if (_paysReservation) {
      final reservation = _reservation;
      return reservation == null ? null : _PayTarget.fromReservation(reservation);
    }
    final order = _order;
    return order == null ? null : _PayTarget.fromOrder(order);
  }

  @override
  void initState() {
    super.initState();
    _order = widget.order;
    _reservation = widget.reservation;
    _reference = TextEditingController();
    _amount = TextEditingController(
      text: widget.order?.total ?? widget.reservation?.amountLabel ?? '',
    );
    final qrs = widget.order?.paymentQrs ?? widget.reservation?.paymentQrs ?? const [];
    _qrId = qrs.isNotEmpty ? qrs.first.id : null;
    final starting = _target;
    _showForm = starting != null && _startsWithForm(starting);
    WidgetsBinding.instance.addObserver(this);
    if ((_paysReservation && widget.reservation == null) ||
        (!_paysReservation && widget.order == null)) {
      _loading = true;
      _load();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && _order != null) {
      _load();
    }
  }

  bool _startsWithForm(_PayTarget target) {
    return target.isAwaitingPayment && target.latestProof?.isRejected != true;
  }

  Future<void> _load() async {
    if (_paysReservation) {
      await _loadReservation();
      return;
    }
    try {
      final order = await context.read<AuthController>().api.buyerOrder(
        widget.orderId ?? widget.order!.id,
      );
      if (!mounted) {
        return;
      }
      final previous = _order;
      final paymentChanged = previous == null ||
          previous.paymentStatus != order.paymentStatus ||
          previous.latestProof?.id != order.latestProof?.id ||
          previous.latestProof?.status != order.latestProof?.status;
      setState(() {
        _order = order;
        _loading = false;
        _qrId ??= order.paymentQrs.isNotEmpty ? order.paymentQrs.first.id : null;
        // Coming back from the wallet app reloads too. Keep what the buyer
        // typed and the open form unless the payment itself moved on.
        if (paymentChanged) {
          _amount.text = order.total;
          _showForm = _startsWithForm(_PayTarget.fromOrder(order));
        }
      });
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _loading = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _loadReservation() async {
    try {
      final id = widget.reservationId ?? widget.reservation!.id;
      final rows = await context.read<AuthController>().api.buyerReservations();
      ReservationRecord? reservation;
      for (final row in rows) {
        if (row.id == id) {
          reservation = row;
        }
      }
      if (!mounted) {
        return;
      }
      final loaded = reservation;
      if (loaded == null) {
        setState(() => _loading = false);
        return;
      }
      final previous = _reservation;
      final paymentChanged = previous == null ||
          previous.paymentStatus != loaded.paymentStatus ||
          previous.latestProof?.id != loaded.latestProof?.id ||
          previous.latestProof?.status != loaded.latestProof?.status;
      setState(() {
        _reservation = loaded;
        _loading = false;
        _qrId ??= loaded.paymentQrs.isNotEmpty ? loaded.paymentQrs.first.id : null;
        if (paymentChanged) {
          _amount.text = loaded.amountLabel;
          _showForm = _startsWithForm(_PayTarget.fromReservation(loaded));
        }
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
    WidgetsBinding.instance.removeObserver(this);
    _reference.dispose();
    _amount.dispose();
    super.dispose();
  }

  PaymentQrCode? _selected(_PayTarget target) {
    for (final qr in target.paymentQrs) {
      if (qr.id == _qrId) {
        return qr;
      }
    }
    return target.paymentQrs.isEmpty ? null : target.paymentQrs.first;
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

  Future<void> _submit(_PayTarget target) async {
    final s = AppStrings.read(context);
    if (_reference.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.referenceRequired)));
      return;
    }
    final qr = _selected(target);
    if (qr == null) {
      return;
    }
    setState(() => _sending = true);
    final amount = _amount.text.trim().isEmpty ? target.amount : _amount.text.trim();
    try {
      if (target.isReservation) {
        final updated = await context.read<AuthController>().api.submitReservationProof(
          _reservation!.id,
          referenceNumber: _reference.text.trim(),
          amount: amount,
          qrId: qr.id,
          screenshotPath: _screenshotPath,
        );
        if (!mounted) {
          return;
        }
        setState(() {
          _reservation = updated;
          _sending = false;
          _showForm = false;
        });
        return;
      }
      final updated = await context.read<AuthController>().api.submitPaymentProof(
        _order!.id,
        referenceNumber: _reference.text.trim(),
        amount: amount,
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
    final target = _target;
    final rejected = target?.latestProof?.isRejected == true;
    final showPayTools = target != null &&
        _showForm &&
        (target.isAwaitingPayment || rejected);
    return Scaffold(
      appBar: AppBar(title: Text(s.payNow)),
      body: _loading || target == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                _AmountCard(target: target, dueSoon: _dueSoon),
                const SizedBox(height: AniHowSpace.cardGap),
                if (rejected && !_showForm) _RejectedCard(
                  target: target,
                  onSendAgain: () => setState(() => _showForm = true),
                ),
                if (target.isPaymentSent && !rejected)
                  _SentCard(target: target),
                if (target.paymentIsPaid) _PaidCard(target: target),
                if (showPayTools) ...[
                  const _PaySteps(),
                  const SizedBox(height: AniHowSpace.cardGap),
                  if (target.paymentQrs.length > 1)
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final qr in target.paymentQrs)
                          ChoiceChip(
                            key: ValueKey('pay-wallet-${qr.id}'),
                            label: Text(s.walletLabel(qr.wallet)),
                            selected: (_selected(target)?.id ?? 0) == qr.id,
                            onSelected: (_) => setState(() => _qrId = qr.id),
                          ),
                      ],
                    ),
                  if (_selected(target) case final qr?) ...[
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
                    onSubmit: () => _submit(target),
                  ),
                ],
              ],
            ),
    );
  }
}

class _AmountCard extends StatelessWidget {
  const _AmountCard({required this.target, required this.dueSoon});

  final _PayTarget target;
  final bool Function(DateTime due) dueSoon;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final due = target.paymentDueAt;
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
              AniHowMoney.peso(target.amount),
              key: const ValueKey('pay-amount'),
              style: theme.textTheme.displaySmall?.copyWith(
                fontSize: 40,
                fontWeight: FontWeight.w800,
                height: 1.1,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              target.isReservation
                  ? s.reservationPayLine(target.listingName ?? '')
                  : s.shopOrderLine(target.peerName, target.orderNumber),
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
    final due = target.paymentDueAt;
    if (target.paymentIsPaid) {
      return (s.paymentStatusLabel('paid'), AniHowColors.inStock, AniHowColors.inStockBg);
    }
    if (target.isPaymentSent && target.latestProof?.isRejected != true) {
      return (
        s.paymentSentChip,
        AniHowColors.confirmedBlue,
        const Color(0xFFDBEAFE),
      );
    }
    if (target.latestProof?.isRejected == true) {
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
  const _SentCard({required this.target});

  final _PayTarget target;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = target.latestProof;
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
                      Text(
                        target.isReservation
                            ? s.waitingForSeller
                            : s.waitingForShop(target.peerName),
                        style: muted,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            Text('${s.referenceNumber}  ${proof?.reference ?? ''}'),
            Text('${s.proofAmount}  ${AniHowMoney.peso(proof?.amount ?? target.amount)}'),
            Text('${s.paidTo}  $paidTo'),
            if (proof?.sentAt != null) Text('${s.sentLabel}  ${s.agoPhrase(proof!.sentAt!)}'),
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
            if (target.order != null) ...[
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => OrderChatScreen(order: target.order!),
                    ),
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
                      builder: (_) => BuyerOrderDetailScreen(order: target.order),
                    ),
                  );
                },
              ),
              TextButton(
                onPressed: () => showReportSheet(
                  context,
                  targetType: 'order',
                  targetId: target.order!.id,
                ),
                child: Text(s.reportPaymentLink),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _RejectedCard extends StatelessWidget {
  const _RejectedCard({required this.target, required this.onSendAgain});

  final _PayTarget target;
  final VoidCallback onSendAgain;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = target.latestProof;
    final due = target.paymentDueAt;
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
  const _PaidCard({required this.target});

  final _PayTarget target;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final proof = target.latestProof;
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
            Text(
              target.isReservation
                  ? s.reservationSecured
                  : s.shopReceived(target.peerName, AniHowMoney.peso(target.amount)),
            ),
            Text('${s.referenceNumber}  ${proof?.reference ?? ''}'),
            if (when != null) Text('${s.confirmedLabel}  ${s.monthDayClock(when)}'),
            if (!target.isReservation) ...[
              const SizedBox(height: 8),
              Text(s.paidNextNote),
              const SizedBox(height: 8),
              if (target.order != null)
                PrimaryButton(
                  label: s.viewOrder,
                  onPressed: () {
                    Navigator.of(context).push(
                      MaterialPageRoute<void>(
                        builder: (_) => BuyerOrderDetailScreen(order: target.order),
                      ),
                    );
                  },
                ),
            ],
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
