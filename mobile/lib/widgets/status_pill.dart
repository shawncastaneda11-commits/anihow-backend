import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class StatusPill extends StatelessWidget {
  const StatusPill({
    super.key,
    required this.label,
    required this.color,
  });

  final String label;
  final Color color;

  factory StatusPill.reservation(String status, {String? label}) {
    final normalized = status.toLowerCase();
    final mapped = switch (normalized) {
      'pending' => (AniHowColors.pending, label ?? 'Pending'),
      'ready_for_pickup' || 'ready' => (AniHowColors.ready, label ?? 'Ready'),
      'completed' => (AniHowColors.completed, label ?? 'Completed'),
      'cancelled' => (AniHowColors.cancelled, label ?? 'Cancelled'),
      _ => (AniHowColors.cancelled, label ?? status),
    };
    return StatusPill(label: mapped.$2, color: mapped.$1);
  }

  factory StatusPill.lowStock() {
    return const StatusPill(label: 'Low stock', color: AniHowColors.pending);
  }

  factory StatusPill.inStock() {
    return const StatusPill(label: 'In stock', color: AniHowColors.ready);
  }

  factory StatusPill.forListing(ListingItem listing) {
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    if (quantity <= 0) {
      return const StatusPill(label: 'Out', color: AniHowColors.cancelled);
    }
    return listing.isLowStock ? StatusPill.lowStock() : StatusPill.inStock();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: AniHowSpace.meta,
        ),
      ),
    );
  }
}
