import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../screens/chat/order_chats_screen.dart';
import '../theme/anihow_theme.dart';

/// Round chat button that sits above the bottom bar, on the right.
class OrderChatHead extends StatelessWidget {
  const OrderChatHead({super.key, this.forSeller = false});

  final bool forSeller;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return FloatingActionButton(
      heroTag: forSeller ? 'seller-order-chats' : 'buyer-order-chats',
      tooltip: s.chats,
      backgroundColor: AniHowColors.brand,
      foregroundColor: Colors.white,
      onPressed: () {
        Navigator.of(context).push(
          MaterialPageRoute<void>(
            builder: (_) => OrderChatsScreen(forSeller: forSeller),
          ),
        );
      },
      child: const ChatBubbleMark(size: 26),
    );
  }
}

class ChatBubbleMark extends StatelessWidget {
  const ChatBubbleMark({super.key, this.size = 26, this.color = Colors.white});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return CustomPaint(
      size: Size.square(size),
      painter: _ChatBubblePainter(color),
    );
  }
}

class _ChatBubblePainter extends CustomPainter {
  const _ChatBubblePainter(this.color);

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()..color = color;
    final bubble = RRect.fromRectAndRadius(
      Rect.fromLTWH(1, 1, size.width - 2, size.height * 0.68),
      Radius.circular(size.width * 0.22),
    );
    canvas.drawRRect(bubble, paint);

    final tail = Path()
      ..moveTo(size.width * 0.22, size.height * 0.62)
      ..lineTo(size.width * 0.16, size.height - 1)
      ..lineTo(size.width * 0.46, size.height * 0.66)
      ..close();
    canvas.drawPath(tail, paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
