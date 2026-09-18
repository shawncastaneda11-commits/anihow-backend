import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class AppHeader extends StatelessWidget implements PreferredSizeWidget {
  const AppHeader({
    super.key,
    required this.title,
    this.subtitle,
    this.trailing,
    this.brandMark = false,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final bool brandMark;

  @override
  Size get preferredSize {
    if (brandMark) {
      return const Size.fromHeight(128);
    }
    return Size.fromHeight(subtitle == null ? 80 : 104);
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AniHowColors.brand,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            brandMark ? AniHowSpace.cardGap : 12,
            AniHowSpace.screen,
            brandMark ? AniHowSpace.section : 14,
          ),
          child: brandMark ? _brand() : _bar(),
        ),
      ),
    );
  }

  Widget _brand() {
    return Column(
      children: [
        const Icon(Icons.eco, color: Colors.white, size: 32),
        const SizedBox(height: AniHowSpace.labelGap),
        Text(
          title,
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white,
            fontSize: AniHowSpace.headline,
            fontWeight: FontWeight.w800,
          ),
        ),
        if (subtitle != null) ...[
          const SizedBox(height: 4),
          Text(
            subtitle!,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.86),
              fontSize: AniHowSpace.body,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );
  }

  Widget _bar() {
    final titleBlock = Column(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        Text(
          title,
          textAlign: TextAlign.center,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: Colors.white,
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
              color: Colors.white.withValues(alpha: 0.86),
              fontSize: AniHowSpace.body,
              fontWeight: FontWeight.w500,
            ),
          ),
        ],
      ],
    );

    if (trailing == null) {
      return titleBlock;
    }

    return Stack(
      alignment: Alignment.center,
      children: [
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 104),
          child: titleBlock,
        ),
        Align(
          alignment: Alignment.centerRight,
          child: trailing,
        ),
      ],
    );
  }
}
