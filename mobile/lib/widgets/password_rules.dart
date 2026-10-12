import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import '../theme/anihow_theme.dart';

/// The password rules the server enforces: 8+ characters, an uppercase and a
/// lowercase letter, a number and a symbol.
class PasswordRules {
  const PasswordRules._();

  static List<bool> check(String password) => [
    password.length >= 8,
    RegExp('[A-Z]').hasMatch(password),
    RegExp('[a-z]').hasMatch(password),
    RegExp('[0-9]').hasMatch(password),
    RegExp('[^A-Za-z0-9]').hasMatch(password),
  ];

  static int metCount(String password) =>
      check(password).where((met) => met).length;

  static bool allMet(String password) => metCount(password) == 5;
}

/// Live checklist and strength bar shown under a new-password field.
class PasswordRulesChecklist extends StatelessWidget {
  const PasswordRulesChecklist({super.key, required this.password});

  final String password;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final ok = dark ? const Color(0xFF7FD3A0) : const Color(0xFF1F7A45);
    final off = theme.colorScheme.onSurface.withValues(alpha: 0.6);
    final track = dark ? const Color(0xFF2E3A34) : const Color(0xFFE3DFD5);
    final weak = dark ? const Color(0xFFF2A7A7) : const Color(0xFF9B2C21);
    final almost = dark ? const Color(0xFFE0A84A) : const Color(0xFF8A5A00);
    final checks = PasswordRules.check(password);
    final met = checks.where((value) => value).length;
    final (label, color) = met <= 2
        ? (s.strengthWeak, weak)
        : met <= 4
        ? (s.strengthAlmost, almost)
        : (s.strengthStrong, ok);
    final rules = [
      s.pwRuleLength,
      s.pwRuleUpper,
      s.pwRuleLower,
      s.pwRuleNumber,
      s.pwRuleSymbol,
    ];

    return Column(
      key: const ValueKey('password-rules'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Row(
                children: [
                  for (var index = 0; index < 5; index++) ...[
                    if (index > 0) const SizedBox(width: 4),
                    Expanded(
                      child: Container(
                        height: 5,
                        decoration: BoxDecoration(
                          color: index < met ? color : track,
                          borderRadius: BorderRadius.circular(3),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            SizedBox(
              width: 64,
              child: Text(
                password.isEmpty ? '' : label,
                key: const ValueKey('password-strength'),
                textAlign: TextAlign.end,
                style: theme.textTheme.labelMedium?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        DecoratedBox(
          decoration: BoxDecoration(
            color: dark ? const Color(0xFF16201C) : AniHowColors.cream,
            border: Border.all(color: track),
            borderRadius: BorderRadius.circular(12),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
            child: Semantics(
              liveRegion: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    s.passwordMustInclude,
                    style: theme.textTheme.labelLarge?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 6),
                  for (var index = 0; index < rules.length; index++)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 2),
                      child: Row(
                        children: [
                          Icon(
                            checks[index]
                                ? Icons.check_circle
                                : Icons.radio_button_unchecked,
                            key: ValueKey(
                              'password-rule-$index-${checks[index] ? 'met' : 'unmet'}',
                            ),
                            size: 16,
                            color: checks[index] ? ok : off,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              rules[index],
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: checks[index] ? ok : off,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}
