import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class ListingActiveBadge extends StatelessWidget {
  const ListingActiveBadge({
    super.key,
    required this.isActive,
    this.onTap,
  });

  final bool isActive;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.maybeOf(context);
    final enabled = onTap != null;
    final foreground = isActive ? Colors.white : AniHowColors.muted;
    return Opacity(
      opacity: enabled ? 1 : 0.7,
      child: Material(
        color: isActive ? AniHowColors.brand : AniHowColors.card,
        shape: StadiumBorder(
          side: BorderSide(
            color: isActive ? AniHowColors.brand : AniHowColors.cardBorder,
          ),
        ),
        child: InkWell(
          customBorder: const StadiumBorder(),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                Icon(
                  isActive ? Icons.check_circle : Icons.radio_button_unchecked,
                  size: 16,
                  color: foreground,
                ),
                const SizedBox(width: 6),
                Text(
                  isActive ? s.listingActive : s.listingInactive,
                  style: TextStyle(
                    color: foreground,
                    fontWeight: FontWeight.w700,
                    fontSize: AniHowSpace.label,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
