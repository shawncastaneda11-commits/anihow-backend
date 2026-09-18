import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';

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
