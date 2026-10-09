import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import 'push_runtime.dart';

const pushPermissionAskedKey = 'anihow_push_permission_asked';

/// Asks once per install, after a checkout, reservation, or listing save.
/// Does nothing until push is actually available, so launch stays quiet.
Future<void> offerPushPermission(BuildContext context) async {
  final device = PushRuntime.device;
  if (device == null) {
    return;
  }
  final preferences = await SharedPreferences.getInstance();
  if (preferences.getBool(pushPermissionAskedKey) == true) {
    return;
  }
  if (!context.mounted) {
    return;
  }
  final allow = await showModalBottomSheet<bool>(
    context: context,
    showDragHandle: true,
    builder: (sheetContext) {
      final strings = AppStrings.of(sheetContext);
      return Padding(
        padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              strings.pushExplainerTitle,
              style: Theme.of(sheetContext).textTheme.titleLarge,
            ),
            const SizedBox(height: 8),
            Text(strings.pushExplainerBody),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: () => Navigator.of(sheetContext).pop(true),
              child: Text(strings.allowNotifications),
            ),
            const SizedBox(height: 8),
            TextButton(
              onPressed: () => Navigator.of(sheetContext).pop(false),
              child: Text(strings.notNow),
            ),
          ],
        ),
      );
    },
  );
  await preferences.setBool(pushPermissionAskedKey, true);
  if (allow != true || !context.mounted) {
    return;
  }
  try {
    await device.requestPermission();
    if (!context.mounted) {
      return;
    }
    await PushRuntime.register(context.read<AuthController>().api);
  } catch (_) {}
}
