import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class AniHowField extends StatelessWidget {
  const AniHowField({
    super.key,
    required this.label,
    required this.child,
  });

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: Theme.of(context).textTheme.labelSmall),
        const SizedBox(height: AniHowSpace.labelGap),
        child,
      ],
    );
  }
}

class AniHowFormCard extends StatelessWidget {
  const AniHowFormCard({super.key, required this.child, this.title});

  final Widget child;
  final String? title;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (title != null) ...[
              Text(title!, style: Theme.of(context).textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.cardGap),
            ],
            child,
          ],
        ),
      ),
    );
  }
}

class AniHowChoiceTile extends StatelessWidget {
  const AniHowChoiceTile({
    super.key,
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final border = selected ? AniHowColors.brand : theme.dividerColor;
    final wash = selected
        ? (theme.brightness == Brightness.dark ? const Color(0xFF1A2A22) : const Color(0xFFEDF6F0))
        : theme.cardTheme.color ?? theme.colorScheme.surface;
    return Material(
      color: wash,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        side: BorderSide(color: border, width: selected ? 1.6 : 1),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
          child: Column(
            children: [
              Icon(icon, color: selected ? AniHowColors.brand : AniHowColors.muted),
              const SizedBox(height: 8),
              Text(
                label,
                textAlign: TextAlign.center,
                style: theme.textTheme.labelLarge?.copyWith(
                  color: selected ? AniHowColors.brand : theme.colorScheme.onSurface,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
