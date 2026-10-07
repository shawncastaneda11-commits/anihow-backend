import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import 'package:url_launcher/url_launcher.dart';

import '../l10n/app_strings.dart';
import '../theme/anihow_space.dart';

class FarmMapCard extends StatelessWidget {
  const FarmMapCard({
    super.key,
    required this.latitude,
    required this.longitude,
  });

  static const userAgentPackageName = 'com.anihow.anihow';

  final double latitude;
  final double longitude;

  String get directionsUrl => urlFor(latitude, longitude);

  static String urlFor(double latitude, double longitude) {
    return 'https://www.google.com/maps/dir/?api=1&destination=$latitude,$longitude';
  }

  Future<void> _open() async {
    final uri = Uri.parse(directionsUrl);
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final point = LatLng(latitude, longitude);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SizedBox(
          key: const Key('farm-map'),
          height: 180,
          child: FlutterMap(
            options: MapOptions(
              initialCenter: point,
              initialZoom: 14,
              interactionOptions: const InteractionOptions(
                flags:
                    InteractiveFlag.drag |
                    InteractiveFlag.pinchZoom |
                    InteractiveFlag.doubleTapZoom,
              ),
            ),
            children: [
              TileLayer(
                urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                userAgentPackageName: userAgentPackageName,
              ),
              MarkerLayer(
                markers: [
                  Marker(
                    point: point,
                    width: 40,
                    height: 40,
                    child: const Icon(
                      Icons.location_pin,
                      color: Colors.red,
                      size: 40,
                    ),
                  ),
                ],
              ),
              Align(
                alignment: Alignment.bottomLeft,
                child: Container(
                  color: Colors.white.withValues(alpha: 0.85),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 4,
                    vertical: 2,
                  ),
                  child: Text(
                    s.openStreetMapCredit,
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: Colors.black,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: AniHowSpace.cardGap),
        SizedBox(
          height: 48,
          child: OutlinedButton.icon(
            key: const Key('open-in-google-maps'),
            onPressed: _open,
            icon: const Icon(Icons.directions_outlined),
            label: Text(s.openInGoogleMaps),
          ),
        ),
      ],
    );
  }
}
