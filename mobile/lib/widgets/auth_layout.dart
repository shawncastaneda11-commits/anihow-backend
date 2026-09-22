import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import 'app_header.dart';

/// Shared login / register chrome: scrollable brand header + overlapping form sheet.
class AuthLayout extends StatelessWidget {
  const AuthLayout({
    super.key,
    required this.form,
    this.leading,
  });

  final Widget form;
  final Widget? leading;

  static const double _headerFraction = 0.34;
  static const double _sheetOverlap = 28;
  static const double _sheetRadius = 28;
  static const double _logoTile = 64;
  static const double _logoRadius = 20;

  @override
  Widget build(BuildContext context) {
    final sheetColor = Theme.of(context).cardTheme.color ??
        (Theme.of(context).brightness == Brightness.dark
            ? AniHowColors.darkCard
            : AniHowColors.card);
    final onPrimary = Theme.of(context).colorScheme.onPrimary;
    final topInset = MediaQuery.paddingOf(context).top;
    final bottomInset = MediaQuery.paddingOf(context).bottom;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
      ),
      child: Scaffold(
        resizeToAvoidBottomInset: true,
        body: LayoutBuilder(
          builder: (context, constraints) {
            final viewport = constraints.maxHeight;
            final headerHeight = viewport * _headerFraction;
            final headerBodyHeight = headerHeight - _sheetOverlap;
            final sheetMinHeight = viewport - headerBodyHeight;

            return DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [AniHowColors.brand, AppHeader.brandGradientEnd],
                ),
              ),
              child: Stack(
                children: [
                  Positioned(
                    top: 0,
                    left: 0,
                    right: 0,
                    height: headerHeight,
                    child: const ClipRect(child: _AuthHeaderCircles()),
                  ),
                  SingleChildScrollView(
                    child: ConstrainedBox(
                      constraints: BoxConstraints(minHeight: viewport),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SizedBox(
                            height: headerBodyHeight,
                            child: Stack(
                              children: [
                                if (leading != null)
                                  Positioned(
                                    left: AniHowSpace.screen - 8,
                                    top: topInset + 4,
                                    child: IconTheme(
                                      data: IconThemeData(color: onPrimary),
                                      child: leading!,
                                    ),
                                  ),
                                Padding(
                                  padding: EdgeInsets.only(top: topInset),
                                  child: Center(
                                    child: FittedBox(
                                      fit: BoxFit.scaleDown,
                                      child: Column(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Container(
                                            width: _logoTile,
                                            height: _logoTile,
                                            decoration: BoxDecoration(
                                              color: AniHowColors.card,
                                              borderRadius:
                                                  BorderRadius.circular(_logoRadius),
                                            ),
                                            alignment: Alignment.center,
                                            child: const Icon(
                                              Icons.eco,
                                              color: AniHowColors.brand,
                                              size: 36,
                                            ),
                                          ),
                                          const SizedBox(height: AniHowSpace.cardGap),
                                          Text(
                                            'AniHow',
                                            textAlign: TextAlign.center,
                                            style: TextStyle(
                                              color: onPrimary,
                                              fontSize: AniHowSpace.headline,
                                              fontWeight: FontWeight.w800,
                                              fontFamily: AniHowTheme.fontFamily,
                                            ),
                                          ),
                                          const SizedBox(height: 4),
                                          Text(
                                            "Farmers' market hub",
                                            textAlign: TextAlign.center,
                                            style: TextStyle(
                                              color: onPrimary.withValues(alpha: 0.86),
                                              fontSize: AniHowSpace.body,
                                              fontWeight: FontWeight.w500,
                                              fontFamily: AniHowTheme.fontFamily,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Material(
                            color: sheetColor,
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(_sheetRadius),
                            ),
                            clipBehavior: Clip.antiAlias,
                            child: ConstrainedBox(
                              constraints: BoxConstraints(minHeight: sheetMinHeight),
                              child: Padding(
                                padding: EdgeInsets.fromLTRB(
                                  AniHowSpace.screen,
                                  AniHowSpace.section,
                                  AniHowSpace.screen,
                                  AniHowSpace.screen + bottomInset,
                                ),
                                child: form,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

/// Large faint circles, clipped by the header edges.
class _AuthHeaderCircles extends StatelessWidget {
  const _AuthHeaderCircles();

  @override
  Widget build(BuildContext context) {
    final white = Colors.white.withValues(alpha: 0.07);
    return Stack(
      children: [
        Positioned(left: -48, top: -36, child: _circle(160, white)),
        Positioned(right: -64, top: 28, child: _circle(200, white)),
        Positioned(left: 72, bottom: -70, child: _circle(140, white)),
      ],
    );
  }

  Widget _circle(double size, Color color) {
    return IgnorePointer(
      child: Container(
        width: size,
        height: size,
        decoration: BoxDecoration(shape: BoxShape.circle, color: color),
      ),
    );
  }
}
