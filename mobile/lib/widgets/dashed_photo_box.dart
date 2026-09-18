import 'dart:io';

import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class DashedPhotoBox extends StatelessWidget {
  const DashedPhotoBox({
    super.key,
    this.filePath,
    this.networkUrl,
    this.onTap,
    this.enabled = true,
  });

  final String? filePath;
  final String? networkUrl;
  final VoidCallback? onTap;
  final bool enabled;

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
              height: 148,
              width: double.infinity,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(AniHowSpace.radius),
                child: image ??
                    Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.add_a_photo_outlined, color: AniHowColors.brand),
                        const SizedBox(height: AniHowSpace.labelGap),
                        Text(
                          enabled ? 'Add photo' : 'Photo upload unavailable',
                          style: theme.textTheme.labelLarge?.copyWith(color: AniHowColors.brand),
                        ),
                      ],
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
      return Image.file(File(filePath!), fit: BoxFit.cover, width: double.infinity);
    }
    if (networkUrl != null && networkUrl!.isNotEmpty) {
      return Image.network(networkUrl!, fit: BoxFit.cover, width: double.infinity);
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
  bool shouldRepaint(covariant _DashPainter oldDelegate) => oldDelegate.color != color;
}
