import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../screens/chat/open_stall_chat.dart';
import '../services/api_client.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';
import 'order_chat_head.dart';

/// Buyer control that messages this stall from a shop, listing, or cart.
class ChatWithStallButton extends StatefulWidget {
  const ChatWithStallButton({
    super.key,
    required this.sellerId,
    this.compact = false,
    this.iconOnly = false,
    this.listingId,
  });

  final int sellerId;
  final bool compact;

  /// Tinted circle with a tooltip, for the cart seller header.
  final bool iconOnly;
  final int? listingId;

  @override
  State<ChatWithStallButton> createState() => _ChatWithStallButtonState();
}

class _ChatWithStallButtonState extends State<ChatWithStallButton> {
  bool _busy = false;

  Future<void> _open() async {
    if (_busy || widget.sellerId <= 0) {
      return;
    }
    setState(() => _busy = true);
    try {
      await openChatWithStall(
        context,
        sellerId: widget.sellerId,
        listingId: widget.listingId,
      );
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final strings = AppStrings.of(context);
    final label = widget.listingId == null
        ? strings.chatWithStall
        : strings.messageSeller;
    final sellerStyle = widget.listingId == null
        ? null
        : const ButtonStyle(minimumSize: WidgetStatePropertyAll(Size(48, 48)));
    final icon = _busy
        ? const SizedBox(
            width: 18,
            height: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        : ChatBubbleMark(
            size: 18,
            color: widget.iconOnly
                ? readableAccent(context)
                : AniHowColors.brand,
          );

    if (widget.iconOnly) {
      return IconButton(
        tooltip: label,
        onPressed: _busy ? null : _open,
        padding: EdgeInsets.zero,
        constraints: const BoxConstraints.tightFor(width: 40, height: 40),
        style: IconButton.styleFrom(
          backgroundColor: accentTint(context),
          foregroundColor: readableAccent(context),
          minimumSize: const Size(40, 40),
          fixedSize: const Size(40, 40),
          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          padding: EdgeInsets.zero,
        ),
        icon: icon,
      );
    }

    if (widget.compact) {
      return Align(
        alignment: Alignment.centerLeft,
        child: TextButton.icon(
          style: sellerStyle,
          onPressed: _busy ? null : _open,
          icon: icon,
          label: Text(label),
        ),
      );
    }

    return OutlinedButton.icon(
      onPressed: _busy ? null : _open,
      icon: icon,
      label: Text(label),
    );
  }
}
