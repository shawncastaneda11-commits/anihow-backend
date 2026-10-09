import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/chat_message_bubble.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';

const paymentLimitHours = [1, 3, 6, 12, 24, 48];

class OnlinePaymentSection extends StatelessWidget {
  const OnlinePaymentSection({
    super.key,
    required this.qrs,
    required this.acceptsOnline,
    required this.hours,
    required this.busy,
    required this.onAccepts,
    required this.onHours,
    required this.onQrs,
  });

  final List<PaymentQrCode> qrs;
  final bool acceptsOnline;
  final int hours;
  final bool busy;
  final ValueChanged<bool> onAccepts;
  final ValueChanged<int> onHours;
  final ValueChanged<List<PaymentQrCode>> onQrs;

  Future<void> _add(BuildContext context) async {
    final created = await showModalBottomSheet<PaymentQrCode>(
      context: context,
      isScrollControlled: true,
      builder: (_) => const _AddQrSheet(),
    );
    if (created != null) {
      onQrs([...qrs, created]);
    }
  }

  Future<void> _delete(BuildContext context, PaymentQrCode qr) async {
    final s = AppStrings.read(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(s.deleteQr),
        content: Text(s.deleteQrAsk),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(s.back)),
          TextButton(
            key: const ValueKey('confirm-delete-qr'),
            onPressed: () => Navigator.pop(context, true),
            child: Text(s.deleteQr),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) {
      return;
    }
    try {
      await context.read<AuthController>().api.deletePaymentQr(qr.id);
      final next = qrs.where((item) => item.id != qr.id).toList();
      onQrs(next);
      if (next.isEmpty) {
        onAccepts(false);
      }
    } on ApiException catch (error) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final canAccept = qrs.isNotEmpty;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(s.onlinePaymentSection, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: AniHowSpace.cardGap),
        for (final qr in qrs)
          ListTile(
            key: ValueKey('qr-row-${qr.id}'),
            contentPadding: EdgeInsets.zero,
            leading: SizedBox(
              width: 48,
              height: 48,
              child: qr.imageUrl == null
                  ? const Icon(Icons.qr_code_2_outlined)
                  : AuthorizedChatImage(url: qr.imageUrl!),
            ),
            title: Text('${s.walletLabel(qr.wallet)} · ${qr.accountName}'),
            subtitle: Text(s.maskedLast4(qr.accountLast4)),
            trailing: IconButton(
              key: ValueKey('delete-qr-${qr.id}'),
              icon: const Icon(Icons.delete_outline),
              onPressed: busy ? null : () => _delete(context, qr),
            ),
          ),
        if (qrs.length < 3)
          OutlinedButton(
            key: const ValueKey('add-qr-code'),
            onPressed: busy ? null : () => _add(context),
            child: Text(s.addQrCode),
          ),
        const SizedBox(height: AniHowSpace.fieldGap),
        AniHowField(
          label: s.paymentTimeLimit,
          child: DropdownButtonFormField<int>(
            key: const ValueKey('payment-time-limit'),
            initialValue: paymentLimitHours.contains(hours) ? hours : 24,
            items: [
              for (final value in paymentLimitHours)
                DropdownMenuItem(
                  value: value,
                  child: Text(s.paymentTimeLimitHours(value)),
                ),
            ],
            onChanged: busy ? null : (value) {
              if (value != null) {
                onHours(value);
              }
            },
          ),
        ),
        Text(s.paymentTimeLimitHelp, style: Theme.of(context).textTheme.bodySmall),
        SwitchListTile(
          key: const ValueKey('accept-online-payment'),
          contentPadding: EdgeInsets.zero,
          value: canAccept && acceptsOnline,
          onChanged: busy || !canAccept ? null : onAccepts,
          title: Text(s.acceptOnlinePayment),
          subtitle: canAccept ? null : Text(s.addQrFirst),
        ),
      ],
    );
  }
}

class _AddQrSheet extends StatefulWidget {
  const _AddQrSheet();

  @override
  State<_AddQrSheet> createState() => _AddQrSheetState();
}

class _AddQrSheetState extends State<_AddQrSheet> {
  final _name = TextEditingController();
  final _last4 = TextEditingController();
  String _wallet = 'gcash';
  String? _imagePath;
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _last4.dispose();
    super.dispose();
  }

  Future<void> _pick(ImageSource source) async {
    final picked = await ImagePicker().pickImage(
      source: source,
      maxWidth: 1600,
      imageQuality: 82,
    );
    if (picked != null && mounted) {
      setState(() => _imagePath = picked.path);
    }
  }

  Future<void> _save() async {
    final s = AppStrings.read(context);
    if (_imagePath == null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.chooseQrImage)));
      return;
    }
    if (_name.text.trim().isEmpty) {
      return;
    }
    if (!RegExp(r'^\d{4}$').hasMatch(_last4.text.trim())) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.last4MustBeFour)));
      return;
    }
    setState(() => _busy = true);
    try {
      final qr = await context.read<AuthController>().api.addPaymentQr(
        imagePath: _imagePath!,
        wallet: _wallet,
        accountName: _name.text.trim(),
        accountLast4: _last4.text.trim(),
      );
      if (mounted) {
        Navigator.pop(context, qr);
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _busy = false);
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final bottom = MediaQuery.viewInsetsOf(context).bottom;
    return Padding(
      padding: EdgeInsets.fromLTRB(16, 16, 16, 16 + bottom),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(s.addQrCode, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: AniHowSpace.cardGap),
            Wrap(
              spacing: 8,
              children: [
                for (final wallet in ['gcash', 'maya', 'bank_qrph'])
                  ChoiceChip(
                    key: ValueKey('wallet-$wallet'),
                    label: Text(s.walletLabel(wallet)),
                    selected: _wallet == wallet,
                    onSelected: (_) => setState(() => _wallet = wallet),
                  ),
              ],
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            TextField(
              key: const ValueKey('qr-account-name'),
              controller: _name,
              decoration: InputDecoration(labelText: s.accountName),
            ),
            TextField(
              key: const ValueKey('qr-last4'),
              controller: _last4,
              maxLength: 4,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: InputDecoration(labelText: s.last4Digits),
            ),
            Wrap(
              spacing: 8,
              children: [
                TextButton(
                  key: const ValueKey('qr-pick-gallery'),
                  onPressed: () => _pick(ImageSource.gallery),
                  child: Text(s.pickFromGallery),
                ),
                TextButton(
                  onPressed: () => _pick(ImageSource.camera),
                  child: Text(s.pickFromCamera),
                ),
              ],
            ),
            if (_imagePath != null) ...[
              const SizedBox(height: 8),
              Image.file(File(_imagePath!), height: 160, fit: BoxFit.contain),
              Text(s.qrScanNote, key: const ValueKey('qr-scan-note')),
            ],
            const SizedBox(height: AniHowSpace.fieldGap),
            PrimaryButton(label: s.saveShopProfile, busy: _busy, onPressed: _busy ? null : _save),
          ],
        ),
      ),
    );
  }
}
