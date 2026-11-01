import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';

/// A line's price. When a tawad lowered it, the listed amount shows first,
/// struck through, so the buyer sees what they saved.
class LinePrice extends StatelessWidget {
  const LinePrice({
    super.key,
    required this.total,
    this.original,
    this.style,
    this.totalKey,
  });

  /// What the line costs now.
  final String? total;

  /// The line before the tawad. Shown only when it is higher than [total].
  final String? original;

  final TextStyle? style;
  final Key? totalKey;

  /// `cart-line-total-1` gives `cart-line-total-1-original`.
  Key? get _originalKey {
    final key = totalKey;
    return key is ValueKey<String> ? ValueKey('${key.value}-original') : null;
  }

  static bool isDiscounted(String? total, String? original) {
    final now = double.tryParse(total ?? '');
    final before = double.tryParse(original ?? '');
    return now != null && before != null && before - now >= 0.005;
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final price = Text(AniHowMoney.peso(total), key: totalKey, style: style);
    if (!isDiscounted(total, original)) {
      return price;
    }
    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.baseline,
      textBaseline: TextBaseline.alphabetic,
      children: [
        Text(
          AniHowMoney.peso(original),
          key: _originalKey,
          style: theme.textTheme.bodySmall?.copyWith(
            decoration: TextDecoration.lineThrough,
            color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
          ),
        ),
        const SizedBox(width: 6),
        price,
      ],
    );
  }
}
