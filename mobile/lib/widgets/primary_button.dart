import 'package:flutter/material.dart';

class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    super.key,
    required this.label,
    required this.onPressed,
    this.busy = false,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool busy;

  /// When false, the button sizes to its label so it can sit in a [Row].
  /// The theme’s [Size.fromHeight] minimum is infinite-width and crashes there.
  final bool expand;

  @override
  Widget build(BuildContext context) {
    return FilledButton(
      onPressed: busy ? null : onPressed,
      style: expand ? null : FilledButton.styleFrom(minimumSize: const Size(88, 52)),
      child: Text(busy ? 'Please wait…' : label),
    );
  }
}
