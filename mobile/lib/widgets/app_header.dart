import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class AppHeader extends StatelessWidget implements PreferredSizeWidget {
  const AppHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.leading,
    this.brandMark = false,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget? leading;
  final bool brandMark;

  /// Darker derived shade of [AniHowColors.brand] (same hue, lower lightness).
  static Color get brandGradientEnd {
    final hsl = HSLColor.fromColor(AniHowColors.brand);
    return hsl.withLightness((hsl.lightness * 0.55).clamp(0.08, 1.0)).toColor();
  }

  @override
  Size get preferredSize {
    if (brandMark) {
      return const Size.fromHeight(168);
    }
    return Size.fromHeight(subtitle == null ? kToolbarHeight : kToolbarHeight + 24);
  }

  @override
  Widget build(BuildContext context) {
    if (brandMark) {
      return _brandMark(context);
    }

    return Material(
      color: AniHowColors.brand,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
          child: _bar(context),
        ),
      ),
    );
  }

  Widget _brandMark(BuildContext context) {
    final onPrimary = Theme.of(context).colorScheme.onPrimary;
    final topInset = MediaQuery.paddingOf(context).top;

    return SizedBox(
      width: double.infinity,
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: [AniHowColors.brand, brandGradientEnd],
          ),
          borderRadius: const BorderRadius.vertical(bottom: Radius.circular(28)),
        ),
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            topInset + AniHowSpace.cardGap,
            AniHowSpace.screen,
            AniHowSpace.section + 4,
          ),
          child: Stack(
            alignment: Alignment.center,
            children: [
              Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.eco, color: onPrimary, size: 36),
                  const SizedBox(height: AniHowSpace.labelGap),
                  Text(
                    title,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: onPrimary,
                      fontSize: AniHowSpace.headline,
                      fontWeight: FontWeight.w800,
                      fontFamily: AniHowTheme.fontFamily,
                    ),
                  ),
                  if (subtitle != null) ...[
                    const SizedBox(height: 4),
                    Text(
                      subtitle!,
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: onPrimary.withValues(alpha: 0.86),
                        fontSize: AniHowSpace.body,
                        fontWeight: FontWeight.w500,
                        fontFamily: AniHowTheme.fontFamily,
                      ),
                    ),
                  ],
                ],
              ),
              if (leading != null)
                Positioned(
                  left: 0,
                  top: 0,
                  child: IconTheme(
                    data: IconThemeData(color: onPrimary),
                    child: leading!,
                  ),
                ),
              if (trailing != null)
                Positioned(
                  right: 0,
                  top: 0,
                  child: trailing!,
                ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _bar(BuildContext context) {
    final onPrimary = Theme.of(context).colorScheme.onPrimary;
    final titleBlock = Column(
      mainAxisSize: MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Text(
          title,
          textAlign: TextAlign.center,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: onPrimary,
            fontSize: AniHowSpace.header,
            fontWeight: FontWeight.w500,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(
            subtitle!,
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: onPrimary.withValues(alpha: 0.86),
              fontSize: AniHowSpace.body,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );

    return SizedBox(
      height: kToolbarHeight,
      child: NavigationToolbar(
        middleSpacing: 8,
        centerMiddle: true,
        leading: leading,
        middle: titleBlock,
        trailing: trailing,
      ),
    );
  }
}
