import 'package:flutter/material.dart';

import '../l10n/app_strings.dart';
import 'form_label.dart';

class PasswordField extends StatefulWidget {
  const PasswordField({
    super.key,
    required this.controller,
    required this.label,
    this.enabled = true,
    this.showLockIcon = false,
  });

  final TextEditingController controller;
  final String label;
  final bool enabled;
  final bool showLockIcon;

  @override
  State<PasswordField> createState() => _PasswordFieldState();
}

class _PasswordFieldState extends State<PasswordField> {
  bool _hidden = true;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return AniHowField(
      label: widget.label,
      child: TextField(
        controller: widget.controller,
        obscureText: _hidden,
        enabled: widget.enabled,
        decoration: InputDecoration(
          prefixIcon: widget.showLockIcon ? const Icon(Icons.lock_outline) : null,
          suffixIcon: IconButton(
            tooltip: _hidden ? s.showPassword : s.hidePassword,
            onPressed: () => setState(() => _hidden = !_hidden),
            icon: Icon(
              _hidden ? Icons.visibility_outlined : Icons.visibility_off_outlined,
            ),
          ),
        ),
      ),
    );
  }
}
