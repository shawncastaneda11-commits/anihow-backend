import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import 'form_label.dart';

/// Optional note from the confirm dialog. Null from the dialog means keep the order.
class BuyerCancelChoice {
  const BuyerCancelChoice({this.note});

  final String? note;
}

class BuyerCancelOrderButton extends StatefulWidget {
  const BuyerCancelOrderButton({
    super.key,
    required this.order,
    required this.onUpdated,
    this.onReload,
    this.expanded = false,
    this.compact = false,
  });

  final OrderRecord order;
  final ValueChanged<OrderRecord> onUpdated;
  final Future<void> Function()? onReload;
  final bool expanded;

  /// Text button for the order card. The confirm-and-cancel flow stays here.
  final bool compact;

  @override
  State<BuyerCancelOrderButton> createState() => _BuyerCancelOrderButtonState();
}

class _BuyerCancelOrderButtonState extends State<BuyerCancelOrderButton> {
  bool _busy = false;

  Future<void> _press() async {
    if (_busy || widget.order.status != 'placed') {
      return;
    }
    final choice = await showDialog<BuyerCancelChoice>(
      context: context,
      builder: (_) => const BuyerCancelOrderDialog(),
    );
    if (choice == null || !mounted) {
      return;
    }

    setState(() => _busy = true);
    final s = AppStrings.read(context);
    try {
      final updated = await context.read<AuthController>().api.cancelBuyerOrder(
        widget.order.id,
        note: choice.note,
      );
      if (!mounted) {
        return;
      }
      widget.onUpdated(updated);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(s.orderCancelled)),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      final message = _cancelFailureMessage(s, error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message)),
      );
      await widget.onReload?.call();
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.order.status != 'placed') {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    final error = Theme.of(context).brightness == Brightness.dark
        ? const Color(0xFFFF8A80)
        : const Color(0xFFB3261E);
    if (widget.compact) {
      return TextButton(
        key: const Key('cancel-buyer-order'),
        onPressed: _busy ? null : _press,
        style: TextButton.styleFrom(
          foregroundColor: error,
          minimumSize: const Size(48, 48),
          tapTargetSize: MaterialTapTargetSize.padded,
        ),
        child: Text(_busy ? s.pleaseWait : s.cancelOrder),
      );
    }
    final button = OutlinedButton(
      key: const Key('cancel-buyer-order'),
      onPressed: _busy ? null : _press,
      style: OutlinedButton.styleFrom(
        foregroundColor: error,
        side: BorderSide(color: error),
        minimumSize: const Size(48, 48),
        tapTargetSize: MaterialTapTargetSize.padded,
      ),
      child: Text(_busy ? s.pleaseWait : s.cancelOrder),
    );
    if (!widget.expanded) {
      return button;
    }
    return SizedBox(width: double.infinity, child: button);
  }
}

class BuyerCancelOrderDialog extends StatefulWidget {
  const BuyerCancelOrderDialog({super.key});

  @override
  State<BuyerCancelOrderDialog> createState() => _BuyerCancelOrderDialogState();
}

class _BuyerCancelOrderDialogState extends State<BuyerCancelOrderDialog> {
  final _note = TextEditingController();

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final error = Theme.of(context).colorScheme.error;

    return AlertDialog(
      title: Text(s.cancelThisOrder),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.cancelBeforeConfirm),
            const SizedBox(height: AniHowSpace.cardGap),
            AniHowField(
              label: s.noteOptional,
              child: TextField(
                controller: _note,
                maxLength: 500,
                maxLines: 3,
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
          onPressed: () => Navigator.pop(context),
          child: Text(s.keepOrder),
        ),
        TextButton(
          key: const Key('confirm-cancel-order'),
          style: TextButton.styleFrom(
            foregroundColor: error,
            minimumSize: const Size(48, 48),
          ),
          onPressed: () {
            final trimmed = _note.text.trim();
            Navigator.pop(
              context,
              BuyerCancelChoice(note: trimmed.isEmpty ? null : trimmed),
            );
          },
          child: Text(s.cancelOrder),
        ),
      ],
    );
  }
}

String _cancelFailureMessage(AppStrings strings, ApiException error) {
  if (error.statusCode == 403) {
    if (error.message.toLowerCase().contains('not verified')) {
      return strings.verifyBanner;
    }
    return strings.orderAlreadyConfirmed;
  }
  return error.message;
}
