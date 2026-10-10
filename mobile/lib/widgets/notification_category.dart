import 'package:flutter/material.dart';

import '../models/models.dart';

enum NotificationCategory {
  orders,
  payments,
  messages,
  farmNews,
  listings,
  account,
}

NotificationCategory notificationCategory(String? type) {
  final value = type ?? '';
  if (value == 'order_message') {
    return NotificationCategory.messages;
  }
  if (value.startsWith('order_') || value.startsWith('reservation_')) {
    return NotificationCategory.orders;
  }
  if (value.startsWith('payment_') || value.startsWith('refund_')) {
    return NotificationCategory.payments;
  }
  if (value == 'farm_announcement') {
    return NotificationCategory.farmNews;
  }
  if (value.startsWith('listing_') ||
      value == 'harvest_reminder' ||
      value == 'expired_stock_left' ||
      value == 'floor_price_raised' ||
      value == 'tawad_ceiling_lowered') {
    return NotificationCategory.listings;
  }
  return NotificationCategory.account;
}

class NotificationCategoryStyle {
  const NotificationCategoryStyle({required this.circle, required this.icon});

  final Color circle;
  final Color icon;
}

/// Circle and icon colors. Each pair differs in lightness, not hue alone.
NotificationCategoryStyle notificationCategoryStyle(
  Brightness brightness,
  NotificationCategory category,
) {
  final dark = brightness == Brightness.dark;
  return switch (category) {
    NotificationCategory.orders => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF245C42),
            icon: Color(0xFFB7E4C7),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFE5F4EB),
            icon: Color(0xFF145C38),
          ),
    NotificationCategory.payments => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF27486E),
            icon: Color(0xFFC5DDF8),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFE4EEF8),
            icon: Color(0xFF1A4578),
          ),
    NotificationCategory.messages => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF4A3470),
            icon: Color(0xFFE4CCF7),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFF3EAF8),
            icon: Color(0xFF5C3488),
          ),
    NotificationCategory.farmNews => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF5A431C),
            icon: Color(0xFFF6D48A),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFFBF3DC),
            icon: Color(0xFF7A4E0C),
          ),
    NotificationCategory.listings => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF1A4E48),
            icon: Color(0xFFB6EBE4),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFE3F6F3),
            icon: Color(0xFF0E5C54),
          ),
    NotificationCategory.account => dark
        ? const NotificationCategoryStyle(
            circle: Color(0xFF3A4440),
            icon: Color(0xFFD8DBD6),
          )
        : const NotificationCategoryStyle(
            circle: Color(0xFFEEECEA),
            icon: Color(0xFF3E4642),
          ),
  };
}

IconData notificationCategoryIcon(AppNotification item) {
  if (item.isReportNotice) {
    return switch (item.type) {
      'report_resolved' => Icons.flag,
      'report_dismissed' => Icons.flag_outlined,
      _ => Icons.outlined_flag,
    };
  }
  return switch (notificationCategory(item.type)) {
    NotificationCategory.orders => Icons.inventory_2_outlined,
    NotificationCategory.payments => Icons.credit_card_outlined,
    NotificationCategory.messages => Icons.chat_bubble_outline,
    NotificationCategory.farmNews => Icons.campaign_outlined,
    NotificationCategory.listings => Icons.eco_outlined,
    NotificationCategory.account => Icons.person_outline,
  };
}

class NotificationCategoryBadge extends StatelessWidget {
  const NotificationCategoryBadge({
    super.key,
    required this.item,
    required this.size,
  });

  final AppNotification item;
  final double size;

  @override
  Widget build(BuildContext context) {
    final style = notificationCategoryStyle(
      Theme.of(context).brightness,
      notificationCategory(item.type),
    );
    return SizedBox(
      width: size,
      height: size,
      child: DecoratedBox(
        decoration: BoxDecoration(color: style.circle, shape: BoxShape.circle),
        child: Icon(
          notificationCategoryIcon(item),
          size: size * 0.5,
          color: style.icon,
        ),
      ),
    );
  }
}
