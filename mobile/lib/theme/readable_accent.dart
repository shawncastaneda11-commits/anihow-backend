import 'package:flutter/material.dart';

/// Accent text and icon color that stays readable on both themes. In dark mode
/// the brand green is too dark against the dark cards, so a light green is used.
Color readableAccent(BuildContext context) {
  final theme = Theme.of(context);
  return theme.brightness == Brightness.dark
      ? const Color(0xFFA8D5BA)
      : theme.colorScheme.primary;
}

/// Soft fill behind numbered steps and small icons, paired with [readableAccent].
Color accentTint(BuildContext context) {
  final theme = Theme.of(context);
  return theme.brightness == Brightness.dark
      ? const Color(0xFF2C4A3B)
      : theme.colorScheme.primary.withValues(alpha: 0.12);
}
