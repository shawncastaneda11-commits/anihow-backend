const pushPreferenceKeys = ['orders', 'payments', 'chats', 'farm_updates'];

Map<String, bool> defaultPushPreferences() => {
  for (final key in pushPreferenceKeys) key: true,
};

Map<String, bool> parsePushPreferences(Object? raw) {
  final stored = raw is Map ? Map<String, dynamic>.from(raw) : <String, dynamic>{};
  return {
    for (final key in pushPreferenceKeys)
      key: stored[key] is bool ? stored[key] as bool : true,
  };
}
