import 'package:flutter/material.dart';

import '../theme/anihow_theme.dart';

class AniHowLogo {
  static const lockupPath = 'assets/branding/anihow-logo.png';
  static const markPath = 'assets/branding/anihow-mark.png';
  static const wordmarkPath = 'assets/branding/anihow-wordmark.png';
}

/// Leaf mark on top, wordmark underneath — full artwork, no crop.
class AniHowLogoMark extends StatelessWidget {
  const AniHowLogoMark({
    super.key,
    this.markHeight = 88,
    this.wordmarkHeight = 48,
    this.wordmarkWidth = 240,
    this.onCard = false,
  });

  final double markHeight;
  final double wordmarkHeight;
  final double wordmarkWidth;
  final bool onCard;

  @override
  Widget build(BuildContext context) {
    final lockup = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Image.asset(
          AniHowLogo.markPath,
          height: markHeight,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
          isAntiAlias: true,
          semanticLabel: 'AniHow',
        ),
        SizedBox(height: onCard ? 8 : 12),
        Image.asset(
          AniHowLogo.wordmarkPath,
          height: wordmarkHeight,
          width: wordmarkWidth,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
          isAntiAlias: true,
        ),
      ],
    );

    if (!onCard) {
      return lockup;
    }

    return DecoratedBox(
      decoration: BoxDecoration(
        color: AniHowColors.card,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.12),
            blurRadius: 20,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(24, 18, 24, 16),
        child: lockup,
      ),
    );
  }
}
