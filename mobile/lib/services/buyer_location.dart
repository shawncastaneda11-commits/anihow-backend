import 'package:geolocator/geolocator.dart';

/// A buyer point held only for the current request. Never written to storage.
class BuyerPoint {
  const BuyerPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;
}

class BuyerLocation {
  /// Tests replace this so the device GPS is never called.
  static Future<BuyerPoint?> Function() read = device;

  static Future<BuyerPoint?> device() async {
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) {
        return null;
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return null;
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.low,
        ),
      );
      return BuyerPoint(position.latitude, position.longitude);
    } catch (_) {
      return null;
    }
  }
}
