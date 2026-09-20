import 'package:flutter/material.dart';

import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';

/// Labelled stand-in for a screen that lands in a later recovery pass.
/// Do not point this at a deleted API route.
class ComingSoonPlaceholder extends StatelessWidget {
  const ComingSoonPlaceholder({
    super.key,
    required this.title,
    required this.laterPass,
    required this.body,
  });

  final String title;
  final String laterPass;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: AniHowSpace.screenPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.construction_outlined, size: 48, color: AniHowColors.brand),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              title,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            Text(
              laterPass,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: AniHowColors.brand,
                fontWeight: FontWeight.w700,
                fontSize: AniHowSpace.meta,
              ),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              body,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
          ],
        ),
      ),
    );
  }
}

class ComingSoonPage extends StatelessWidget {
  const ComingSoonPage({
    super.key,
    required this.title,
    required this.laterPass,
    required this.body,
  });

  final String title;
  final String laterPass;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ComingSoonPlaceholder(
        title: title,
        laterPass: laterPass,
        body: body,
      ),
    );
  }
}
