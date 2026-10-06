import 'dart:async';

import 'package:geolocator/geolocator.dart';

/// A buyer point held only for the current request. Never written to storage.
class BuyerPoint {
  const BuyerPoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;
}

/// Result of one location read. Tests replace [BuyerLocation.read].
sealed class BuyerLocationResult {
  const BuyerLocationResult();
}

class BuyerLocationFound extends BuyerLocationResult {
  const BuyerLocationFound(this.point);

  final BuyerPoint point;
}

/// Location services are off, or the buyer denied permission.
class BuyerLocationUnavailable extends BuyerLocationResult {
  const BuyerLocationUnavailable();
}

/// Permission was granted, but there was no fresh fix and no last known point.
class BuyerLocationNoFix extends BuyerLocationResult {
  const BuyerLocationNoFix();
}

class BuyerLocation {
  /// Tests replace this so the device GPS is never called.
  static Future<BuyerLocationResult> Function() read = device;

  static Future<BuyerLocationResult> device() async {
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      if (!enabled) {
        return const BuyerLocationUnavailable();
      }

      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return const BuyerLocationUnavailable();
      }
    } catch (_) {
      return const BuyerLocationUnavailable();
    }

    return lookup(
      current: () async {
        final position = await Geolocator.getCurrentPosition(
          locationSettings: const LocationSettings(
            accuracy: LocationAccuracy.low,
            timeLimit: Duration(seconds: 10),
          ),
        );
        return BuyerPoint(position.latitude, position.longitude);
      },
      lastKnown: () async {
        final position = await Geolocator.getLastKnownPosition();
        if (position == null) {
          return null;
        }
        return BuyerPoint(position.latitude, position.longitude);
      },
    );
  }

  /// A fresh fix, then the last known point, then [BuyerLocationNoFix].
  static Future<BuyerLocationResult> lookup({
    required Future<BuyerPoint?> Function() current,
    required Future<BuyerPoint?> Function() lastKnown,
  }) async {
    try {
      final point = await current();
      if (point != null) {
        return BuyerLocationFound(point);
      }
    } on TimeoutException {
      // The fresh read gave up; the last known point may still be usable.
    } catch (_) {
      // Any other failure after permission was granted tries the last point.
    }

    try {
      final last = await lastKnown();
      if (last != null) {
        return BuyerLocationFound(last);
      }
    } catch (_) {
      return const BuyerLocationNoFix();
    }
    return const BuyerLocationNoFix();
  }
}
