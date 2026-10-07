import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';

class AvailabilityChip extends StatelessWidget {
  const AvailabilityChip({super.key, required this.state});

  final String? state;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final label = s.availabilityChip(state);
    final scheme = Theme.of(context).colorScheme;
    final color = switch (state) {
      'upcoming' => scheme.tertiary,
      'expired' => scheme.outline,
      _ => scheme.primary,
    };

    return Chip(
      key: ValueKey('availability-$label'),
      label: Text(label),
      visualDensity: VisualDensity.compact,
      side: BorderSide(color: color),
      labelStyle: Theme.of(context).textTheme.labelMedium
          ?.copyWith(color: color, fontWeight: FontWeight.w700),
    );
  }
}
