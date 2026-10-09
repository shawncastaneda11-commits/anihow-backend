import 'dart:typed_data';

import 'package:gal/gal.dart';

/// Saves a QR into the photo gallery. Tests replace [saveImageBytes].
class QrGallery {
  static Future<void> Function(Uint8List bytes, {required String name})
  saveImageBytes = _save;

  static Future<void> _save(Uint8List bytes, {required String name}) async {
    if (!await Gal.hasAccess()) {
      await Gal.requestAccess();
    }
    await Gal.putImageBytes(bytes, name: name);
  }
}
