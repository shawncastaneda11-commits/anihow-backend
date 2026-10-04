import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../screens/chat/open_stall_chat.dart';
import '../services/api_client.dart';
import '../theme/anihow_theme.dart';
import 'order_chat_head.dart';

/// Buyer control that messages this stall from a shop, listing, or cart.
class ChatWithStallButton extends StatefulWidget {
  const ChatWithStallButton({
    super.key,
    required this.sellerId,
    this.compact = false,
    this.listingId,
  });

  final int sellerId;
  final bool compact;
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
        : const ChatBubbleMark(size: 18, color: AniHowColors.brand);

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
