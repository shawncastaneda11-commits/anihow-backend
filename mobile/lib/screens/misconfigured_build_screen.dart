import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../widgets/anihow_logo.dart';

class MisconfiguredBuildApp extends StatelessWidget {
  const MisconfiguredBuildApp({super.key, required this.reason});

  final String reason;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'AniHow',
      theme: AniHowTheme.light(),
      home: MisconfiguredBuildScreen(reason: reason),
    );
  }
}

class MisconfiguredBuildScreen extends StatelessWidget {
  const MisconfiguredBuildScreen({
    super.key,
    required this.reason,
    this.filipino = false,
  });

  final String reason;
  final bool filipino;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings(filipino);
    final theme = Theme.of(context);

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: AniHowSpace.screenPadding,
          child: Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const AniHowLogoMark(onCard: true),
                const SizedBox(height: AniHowSpace.section),
                Text(
                  s.misconfiguredBuildTitle,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                Text(
                  s.misconfiguredBuildBody,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodyMedium,
                ),
                const SizedBox(height: AniHowSpace.section),
                Text(
                  reason,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(
                    color: AniHowColors.muted,
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
