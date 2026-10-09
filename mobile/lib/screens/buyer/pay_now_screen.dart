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
import '../../widgets/chat_message_bubble.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';

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

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final order = _order;
    return Scaffold(
      appBar: AppBar(title: Text(s.payNow)),
      body: _loading || order == null
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                Text(
                  AniHowMoney.peso(order.total),
                  key: const ValueKey('pay-amount'),
                  style: Theme.of(context).textTheme.displaySmall,
                ),
                const SizedBox(height: 4),
                Text(order.stallName, key: const ValueKey('pay-seller')),
                if (order.paymentDueAt != null)
                  Text(
                    s.payBefore(order.paymentDueAt!),
                    key: const ValueKey('pay-deadline'),
                  ),
                const SizedBox(height: AniHowSpace.section),
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
                  _QrPreview(qr: qr),
                  const SizedBox(height: 8),
                  Text(qr.accountName, key: const ValueKey('pay-account-name')),
                  Text(
                    s.maskedLast4(qr.accountLast4),
                    key: const ValueKey('pay-account-last4'),
                  ),
                  TextButton(
                    key: const ValueKey('pay-save-qr'),
                    onPressed: _savingQr ? null : () => _saveQr(qr),
                    child: Text(s.saveQr),
                  ),
                ],
                if (order.latestProof?.isRejected == true && !_showForm)
                  Card(
                    key: const ValueKey('pay-rejected'),
                    color: Theme.of(context).colorScheme.errorContainer,
                    child: Padding(
                      padding: AniHowSpace.cardPadding,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(s.paymentRejected),
                          if (order.latestProof?.rejectionReason != null)
                            Text(s.paymentRejectReason(order.latestProof!.rejectionReason!)),
                          if (order.latestProof?.rejectionNote != null)
                            Text(order.latestProof!.rejectionNote!),
                          TextButton(
                            key: const ValueKey('pay-send-again'),
                            onPressed: () => setState(() => _showForm = true),
                            child: Text(s.sendAgain),
                          ),
                        ],
                      ),
                    ),
                  ),
                if (order.isPaymentSent && order.latestProof?.isRejected != true)
                  Text(
                    s.paymentSentWaiting,
                    key: const ValueKey('pay-sent-status'),
                  ),
                if (_showForm && (order.isAwaitingPayment || order.latestProof?.isRejected == true)) ...[
                  const SizedBox(height: AniHowSpace.section),
                  Text(s.ivePaid, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: AniHowSpace.fieldGap),
                  AniHowField(
                    label: s.referenceNumber,
                    child: TextField(
                      key: const ValueKey('pay-reference'),
                      controller: _reference,
                      decoration: InputDecoration(hintText: s.referenceHint),
                    ),
                  ),
                  const SizedBox(height: AniHowSpace.fieldGap),
                  AniHowField(
                    label: s.proofAmount,
                    child: TextField(
                      key: const ValueKey('pay-proof-amount'),
                      controller: _amount,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                    ),
                  ),
                  TextButton(
                    key: const ValueKey('pay-add-screenshot'),
                    onPressed: _pickScreenshot,
                    child: Text(
                      _screenshotPath == null ? s.pickScreenshot : s.screenshotOptional,
                    ),
                  ),
                  PrimaryButton(
                    key: const ValueKey('pay-submit'),
                    label: s.submitProof,
                    busy: _sending,
                    onPressed: _sending ? null : () => _submit(order),
                  ),
                ],
              ],
            ),
    );
  }
}

class _QrPreview extends StatelessWidget {
  const _QrPreview({required this.qr});

  final PaymentQrCode qr;

  @override
  Widget build(BuildContext context) {
    final url = qr.imageUrl;
    return GestureDetector(
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
      child: SizedBox(
        height: 220,
        width: double.infinity,
        child: url == null
            ? const Icon(Icons.qr_code_2_outlined)
            : AuthorizedChatImage(url: url, fit: BoxFit.contain),
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
