import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

bool tawadIsActive(Object? value) {
  final amount = value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
  return amount > 0.004;
}

class OrderTotalHero extends StatelessWidget {
  const OrderTotalHero({
    super.key,
    required this.total,
    this.tawadLine,
  });

  final Object total;
  final String? tawadLine;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          AniHowMoney.peso(total),
          style: theme.textTheme.headlineSmall?.copyWith(
            fontWeight: FontWeight.w700,
            letterSpacing: 0.2,
          ),
        ),
        if (tawadLine != null && tawadLine!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(top: 2),
            child: Text(
              tawadLine!,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: AniHowColors.sage,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }
}

class OrderMetaRow extends StatelessWidget {
  const OrderMetaRow({
    super.key,
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: AniHowColors.muted),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
