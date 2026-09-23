import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';

/// Three lines the spec requires: listed price, tawad, final total.
/// The listed price is never replaced by the discounted figure.
class PriceBreakdown extends StatelessWidget {
  const PriceBreakdown({
    super.key,
    required this.listed,
    required this.tawad,
    required this.total,
  });

  final Object listed;
  final Object tawad;
  final Object total;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final style = theme.textTheme.bodyMedium;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: theme.colorScheme.onSurface.withValues(alpha: 0.04),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Listed ${AniHowMoney.peso(listed)}', style: style),
            Text('Tawad ${AniHowMoney.peso(tawad)}', style: style),
            Text(
              'Total ${AniHowMoney.peso(total)}',
              style: style?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}
