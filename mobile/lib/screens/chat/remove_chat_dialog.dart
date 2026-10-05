import 'package:flutter/material.dart';

import '../../l10n/app_strings.dart';

/// Asks before hiding a stall chat for the signed-in person only.
Future<bool> confirmRemoveStallChat(BuildContext context, String name) async {
  final strings = AppStrings.of(context);
  final theme = Theme.of(context);
  final choice = await showDialog<bool>(
    context: context,
    builder: (context) {
      return AlertDialog(
        title: Text(strings.removeChatTitle),
        content: Text(strings.removeChatBody(name)),
        actions: [
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(strings.cancel),
          ),
          TextButton(
            key: const Key('confirm-remove-chat'),
            style: TextButton.styleFrom(
              foregroundColor: theme.colorScheme.error,
              minimumSize: const Size(48, 48),
            ),
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(strings.remove),
          ),
        ],
      );
    },
  );

  return choice ?? false;
}
