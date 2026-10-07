import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../theme/anihow_theme.dart';

class GrowingBadge extends StatelessWidget {
  const GrowingBadge({super.key, required this.badge, this.certifier});

  final String? badge;
  final String? certifier;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final label = switch (badge) {
      'certified' => s.certifiedOrganic,
      'naturally_grown' => s.naturallyGrownDeclared,
      _ => null,
    };
    if (label == null) {
      return const SizedBox.shrink();
    }

    final theme = Theme.of(context);

    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            key: const ValueKey('growing-badge'),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: theme.textTheme.bodySmall?.copyWith(
              color: AniHowColors.deepGreen,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (badge == 'certified' &&
              certifier != null &&
              certifier!.isNotEmpty)
            Text(
              s.certifiedBy(certifier!),
              key: const ValueKey('organic-certifier'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: theme.textTheme.bodySmall,
            ),
        ],
      ),
    );
  }
}
