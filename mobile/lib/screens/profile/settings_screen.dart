import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../state/theme_controller.dart';
import '../../theme/anihow_space.dart';
import '../notifications/notifications_screen.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeController>();
    final auth = context.watch<AuthController>();
    final user = auth.user;

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text('Appearance', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 8),
          SegmentedButton<ThemeMode>(
            segments: const [
              ButtonSegment(value: ThemeMode.light, label: Text('Light'), icon: Icon(Icons.light_mode_outlined)),
              ButtonSegment(value: ThemeMode.dark, label: Text('Dark'), icon: Icon(Icons.dark_mode_outlined)),
              ButtonSegment(value: ThemeMode.system, label: Text('System'), icon: Icon(Icons.phone_android)),
            ],
            selected: {theme.mode},
            onSelectionChanged: (value) => theme.setMode(value.first),
          ),
          const SizedBox(height: AniHowSpace.section),
          Text('Notifications', style: Theme.of(context).textTheme.titleMedium),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.notifications_outlined),
            title: const Text('In-app notifications'),
            subtitle: const Text('Reservation and stock alerts'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const NotificationsScreen()),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          Text('Account', style: Theme.of(context).textTheme.titleMedium),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.lock_outline),
            title: const Text('Change password'),
            subtitle: const Text('Coming soon — no logged-in change-password API. Use forgot-password on the website.'),
            enabled: false,
          ),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(user?.isVerified == true ? Icons.verified_outlined : Icons.mark_email_unread_outlined),
            title: const Text('Email verification'),
            subtitle: Text(
              user?.isVerified == true
                  ? 'Verified'
                  : 'Not verified. Resend uses POST /api/auth/email/verification-notification.',
            ),
            trailing: user?.isVerified == true
                ? null
                : TextButton(
                    onPressed: () async {
                      try {
                        await auth.api.resendVerification();
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(
                            const SnackBar(content: Text('Verification email queued. Check the mail log.')),
                          );
                        }
                      } on ApiException catch (error) {
                        if (context.mounted) {
                          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
                        }
                      }
                    },
                    child: const Text('Resend'),
                  ),
          ),
          const SizedBox(height: AniHowSpace.section),
          Text('About AniHow', style: Theme.of(context).textTheme.titleMedium),
          const ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('AniHow'),
            subtitle: Text(
              'Version 1.0.0 · Farmers’ digital market hub for General Trias, Cavite.',
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          FilledButton.tonal(
            onPressed: () async {
              await auth.logout();
              if (context.mounted) {
                Navigator.of(context).popUntil((route) => route.isFirst);
              }
            },
            child: const Text('Log out'),
          ),
        ],
      ),
    );
  }
}
