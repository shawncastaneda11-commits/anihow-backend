import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';

class ProfileAvatarButton extends StatelessWidget {
  const ProfileAvatarButton({
    super.key,
    required this.name,
    required this.onPressed,
  });

  final String name;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: 'Profile',
      onPressed: onPressed,
      icon: AniHowAvatar(name: name, radius: 16),
    );
  }
}

class AniHowAvatar extends StatelessWidget {
  const AniHowAvatar({
    super.key,
    required this.name,
    this.radius = AniHowSpace.avatar,
  });

  final String name;
  final double radius;

  String get _initials {
    final parts = name.trim().split(RegExp(r'\s+'));
    if (parts.isEmpty || parts.first.isEmpty) {
      return 'A';
    }
    if (parts.length == 1) {
      return parts.first[0].toUpperCase();
    }
    return (parts.first[0] + parts.last[0]).toUpperCase();
  }

  @override
  Widget build(BuildContext context) {
    return CircleAvatar(
      radius: radius,
      backgroundColor: AniHowColors.deepGreen,
      foregroundColor: Colors.white,
      child: Text(
        _initials,
        style: TextStyle(
          fontWeight: FontWeight.w800,
          fontSize: radius * 0.7,
        ),
      ),
    );
  }
}
