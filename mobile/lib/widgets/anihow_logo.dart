import 'package:flutter/material.dart';

import '../theme/anihow_theme.dart';

class AniHowLogo {
  static const lockupPath = 'assets/branding/anihow-logo.png';
  static const markPath = 'assets/branding/anihow-mark.png';
  static const wordmarkPath = 'assets/branding/anihow-wordmark.png';
  static const wordmarkNamePath = 'assets/branding/anihow-wordmark-name.png';
  static const tagline = 'FROM FARM TO MARKET';

  /// Matches Filament admin login tagline color.
  static const Color taglineColor = Color(0xFF8A5526);
}

/// Same lockup as Filament admin login: leaf mark + name art + HTML-style tagline.
class AniHowLogoMark extends StatelessWidget {
  const AniHowLogoMark({
    super.key,
    this.markHeight = 104,
    this.wordmarkHeight = 50,
    this.wordmarkWidth = 272,
    this.taglineSize = 22,
    this.onCard = false,
  });

  final double markHeight;
  final double wordmarkHeight;
  final double wordmarkWidth;
  final double taglineSize;
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
        SizedBox(height: onCard ? 10 : 12),
        Image.asset(
          AniHowLogo.wordmarkNamePath,
          height: wordmarkHeight,
          width: wordmarkWidth,
          fit: BoxFit.contain,
          filterQuality: FilterQuality.high,
          isAntiAlias: true,
        ),
        SizedBox(height: onCard ? 10 : 8),
        Text(
          AniHowLogo.tagline,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: AniHowLogo.taglineColor,
            fontSize: taglineSize,
            fontWeight: FontWeight.w800,
            letterSpacing: taglineSize * 0.2,
            height: 1.2,
          ),
        ),
      ],
    );

    if (!onCard) {
      return lockup;
    }

    return DecoratedBox(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: AniHowColors.brand.withValues(alpha: 0.14), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.16),
            blurRadius: 30,
            offset: const Offset(0, 12),
          ),
          BoxShadow(
            color: AniHowColors.brand.withValues(alpha: 0.10),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(32, 22, 32, 18),
        child: lockup,
      ),
    );
  }
}
