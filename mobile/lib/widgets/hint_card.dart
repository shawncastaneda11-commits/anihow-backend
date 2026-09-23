import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

/// Icon + short title + optional line. Used instead of a wall of body copy.
class AniHowHintCard extends StatelessWidget {
  const AniHowHintCard({
    super.key,
    required this.icon,
    required this.title,
    this.body,
    this.tone = AniHowHintTone.neutral,
  });

  final IconData icon;
  final String title;
  final String? body;
  final AniHowHintTone tone;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colors = tone._colors(theme);
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        border: Border.all(color: colors.border),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                color: colors.iconWash,
                shape: BoxShape.circle,
              ),
              child: Padding(
                padding: const EdgeInsets.all(8),
                child: Icon(icon, size: 20, color: colors.icon),
              ),
            ),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: theme.textTheme.titleMedium),
                  if (body != null && body!.isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(
                      body!,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

enum AniHowHintTone { neutral, brand, cash }

class _HintColors {
  const _HintColors({
    required this.background,
    required this.border,
    required this.icon,
    required this.iconWash,
  });

  final Color background;
  final Color border;
  final Color icon;
  final Color iconWash;
}

extension on AniHowHintTone {
  _HintColors _colors(ThemeData theme) {
    final dark = theme.brightness == Brightness.dark;
    switch (this) {
      case AniHowHintTone.brand:
        return _HintColors(
          background: dark ? const Color(0xFF1A2A22) : const Color(0xFFEDF6F0),
          border: dark ? const Color(0xFF2E4A3A) : const Color(0xFFC7E0D2),
          icon: AniHowColors.brand,
          iconWash: dark ? const Color(0xFF24382D) : const Color(0xFFD7EADF),
        );
      case AniHowHintTone.cash:
        return _HintColors(
          background: dark ? const Color(0xFF2A2418) : const Color(0xFFF8F1E2),
          border: dark ? const Color(0xFF4A3E24) : const Color(0xFFE6D3A8),
          icon: AniHowColors.root,
          iconWash: dark ? const Color(0xFF3A3120) : const Color(0xFFF0E4C4),
        );
      case AniHowHintTone.neutral:
        return _HintColors(
          background: theme.cardTheme.color ?? theme.colorScheme.surface,
          border: theme.dividerColor,
          icon: AniHowColors.sage,
          iconWash: dark ? const Color(0xFF24302A) : const Color(0xFFEEF4EF),
        );
    }
  }
}
