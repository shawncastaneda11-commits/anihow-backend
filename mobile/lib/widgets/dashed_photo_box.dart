import 'dart:io';

import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';

class DashedPhotoBox extends StatelessWidget {
  const DashedPhotoBox({
    super.key,
    this.filePath,
    this.networkUrl,
    this.onTap,
    this.enabled = true,
    this.emptyLabel = 'Add photo',
    this.emptyIcon = Icons.add_a_photo_outlined,
    this.hint,
    this.changeLabel,
    this.height = 148,
  });

  final String? filePath;
  final String? networkUrl;
  final VoidCallback? onTap;
  final bool enabled;
  final String emptyLabel;
  final IconData emptyIcon;
  final String? hint;
  final String? changeLabel;
  final double height;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final image = _image();

    return Opacity(
      opacity: enabled ? 1 : 0.45,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: enabled ? onTap : null,
          borderRadius: BorderRadius.circular(AniHowSpace.radius),
          child: CustomPaint(
            painter: _DashPainter(color: theme.dividerColor),
            child: SizedBox(
              height: height,
              width: double.infinity,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AniHowSpace.radius),
                child: image != null
                    ? Stack(
                        fit: StackFit.expand,
                        children: [
                          image,
                          if (changeLabel != null)
                            Positioned(
                              right: 8,
                              bottom: 8,
                              child: DecoratedBox(
                                decoration: BoxDecoration(
                                  color: theme.colorScheme.surface,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 6,
                                  ),
                                  child: Text(
                                    changeLabel!,
                                    style: theme.textTheme.labelMedium
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                ),
                              ),
                            ),
                        ],
                      )
                    : Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: AniHowSpace.screen,
                        ),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            if (hint == null)
                              Icon(emptyIcon, color: AniHowColors.brand)
                            else
                              DecoratedBox(
                                decoration: BoxDecoration(
                                  color: accentTint(context),
                                  shape: BoxShape.circle,
                                ),
                                child: SizedBox(
                                  width: 56,
                                  height: 56,
                                  child: Icon(
                                    Icons.photo_camera_outlined,
                                    color: readableAccent(context),
                                  ),
                                ),
                              ),
                            const SizedBox(height: AniHowSpace.labelGap),
                            Text(
                              enabled ? emptyLabel : 'Photo upload unavailable',
                              textAlign: TextAlign.center,
                              style: theme.textTheme.labelLarge?.copyWith(
                                color: hint == null
                                    ? AniHowColors.brand
                                    : theme.colorScheme.onSurface,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            if (hint != null) ...[
                              const SizedBox(height: 4),
                              Text(
                                hint!,
                                textAlign: TextAlign.center,
                                style: theme.textTheme.bodySmall?.copyWith(
                                  color: theme.colorScheme.onSurface.withValues(
                                    alpha: 0.72,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget? _image() {
    if (filePath != null && filePath!.isNotEmpty) {
      return Image.file(
        File(filePath!),
        fit: BoxFit.cover,
        width: double.infinity,
        errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
      );
    }
    if (networkUrl != null && networkUrl!.isNotEmpty) {
      return Image.network(
        networkUrl!,
        fit: BoxFit.cover,
        width: double.infinity,
        errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
      );
    }
    return null;
  }
}

class _DashPainter extends CustomPainter {
  _DashPainter({required this.color});

  final Color color;

  @override
  void paint(Canvas canvas, Size size) {
    const dash = 6.0;
    const gap = 4.0;
    final radius = Radius.circular(AniHowSpace.radius);
    final path = Path()
      ..addRRect(RRect.fromRectAndRadius(Offset.zero & size, radius));
    final paint = Paint()
      ..color = color
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    for (final metric in path.computeMetrics()) {
      var distance = 0.0;
      while (distance < metric.length) {
        final next = (distance + dash).clamp(0, metric.length);
        canvas.drawPath(metric.extractPath(distance, next.toDouble()), paint);
        distance += dash + gap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant _DashPainter oldDelegate) =>
      oldDelegate.color != color;
}
