import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme/readable_accent.dart';

/// Small pieces shared by the tawad and walk-in sale screens.

/// The listing's photo, or a leaf on a soft green tile.
class ListingThumb extends StatelessWidget {
  const ListingThumb({super.key, required this.listing, this.size = 48});

  final ListingItem listing;
  final double size;

  @override
  Widget build(BuildContext context) {
    final url = listing.thumbnailUrl ?? listing.imageUrl;
    final fallback = Container(
      width: size,
      height: size,
      color: accentTint(context),
      alignment: Alignment.center,
      child: Icon(Icons.eco_outlined, color: readableAccent(context)),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: url == null || url.isEmpty
          ? fallback
          : Image.network(
              url,
              width: size,
              height: size,
              fit: BoxFit.cover,
              errorBuilder: (context, error, stack) => fallback,
            ),
    );
  }
}

enum SaleNoteTone { good, problem, neutral, nudge }

/// One line of feedback in a soft colored box.
class SaleNote extends StatelessWidget {
  const SaleNote({
    super.key,
    required this.text,
    required this.tone,
    this.trailing,
  });

  final String text;
  final SaleNoteTone tone;

  /// Shown at the right, bold, such as an amount.
  final String? trailing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final (Color fill, Color ink, IconData? icon) = switch (tone) {
      SaleNoteTone.good => (
        accentTint(context),
        readableAccent(context),
        trailing == null ? Icons.check_rounded : null,
      ),
      SaleNoteTone.problem => (
        dark ? const Color(0xFF4A2A20) : const Color(0xFFFBEAE3),
        dark ? const Color(0xFFF4B39C) : const Color(0xFF9A3412),
        trailing == null ? Icons.error_outline : null,
      ),
      SaleNoteTone.nudge => (
        dark ? const Color(0xFF473A1E) : const Color(0xFFFFF4E0),
        dark ? const Color(0xFFF2D29B) : const Color(0xFF7A4A00),
        null,
      ),
      SaleNoteTone.neutral => (
        theme.colorScheme.onSurface.withValues(alpha: 0.06),
        theme.colorScheme.onSurface.withValues(alpha: 0.78),
        null,
      ),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 18, color: ink),
            const SizedBox(width: 8),
          ],
          Expanded(
            child: Text(text, style: TextStyle(color: ink, height: 1.4)),
          ),
          if (trailing != null)
            Text(
              trailing!,
              style: TextStyle(color: ink, fontWeight: FontWeight.w700),
            ),
        ],
      ),
    );
  }
}

/// A label and an amount on one line, as in a receipt.
class SaleLine extends StatelessWidget {
  const SaleLine({
    super.key,
    required this.label,
    required this.value,
    this.accent = false,
    this.valueKey,
  });

  final String label;
  final String value;
  final bool accent;
  final Key? valueKey;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = accent ? readableAccent(context) : null;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                color: color ?? theme.colorScheme.onSurface.withValues(alpha: 0.7),
              ),
            ),
          ),
          Text(
            value,
            key: valueKey,
            style: TextStyle(
              color: color,
              fontWeight: accent ? FontWeight.w600 : null,
            ),
          ),
        ],
      ),
    );
  }
}
