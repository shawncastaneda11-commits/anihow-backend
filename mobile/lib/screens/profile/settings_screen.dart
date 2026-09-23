import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../state/theme_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/status_pill.dart';
import 'profile_screen.dart';
import 'verify_email_screen.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeController>();
    final prefs = context.watch<PreferencesController>();
    final auth = context.watch<AuthController>();
    final user = auth.user;
    final scheme = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          const _SectionTitle('Appearance', first: true),
          _SettingsCard(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: SegmentedButton<ThemeMode>(
                segments: const [
                  ButtonSegment(
                    value: ThemeMode.light,
                    label: FittedBox(fit: BoxFit.scaleDown, child: Text('Light')),
                    icon: Icon(Icons.light_mode_outlined),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    label: FittedBox(fit: BoxFit.scaleDown, child: Text('Dark')),
                    icon: Icon(Icons.dark_mode_outlined),
                  ),
                  ButtonSegment(
                    value: ThemeMode.system,
                    label: FittedBox(fit: BoxFit.scaleDown, child: Text('System')),
                    icon: Icon(Icons.phone_android),
                  ),
                ],
                selected: {theme.mode},
                onSelectionChanged: (value) => theme.setMode(value.first),
              ),
            ),
          ),
          const _SectionTitle('Preferences'),
          _SettingsCard(
            children: [
              _SettingsRow(
                icon: Icons.notifications_outlined,
                label: 'Notifications',
                trailing: Transform.scale(
                  scale: AniHowSpace.switchScale,
                  child: Switch(
                    value: prefs.notificationsEnabled,
                    onChanged: prefs.setNotificationsEnabled,
                  ),
                ),
              ),
              const _SettingsRow(
                icon: Icons.language_outlined,
                label: 'Language',
                trailing: Text('English'),
              ),
            ],
          ),
          const _SectionTitle('Account'),
          _SettingsCard(
            children: [
              if (user?.isFarmerSeller == true)
                _SettingsRow(
                  icon: Icons.storefront_outlined,
                  label: 'Edit profile',
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(builder: (_) => const FarmerProfileScreen()),
                  ),
                ),
              const _SettingsRow(
                icon: Icons.lock_outline,
                label: 'Change password',
                enabled: false,
                trailing: _SoonTag(),
              ),
              _SettingsRow(
                icon: Icons.mail_outline,
                label: 'Email',
                trailing: user?.isVerified == true
                    ? const StatusPill(label: 'Verified', color: AniHowColors.ready)
                    : Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const StatusPill(label: 'Unverified', color: AniHowColors.pending),
                          TextButton(
                            onPressed: () => Navigator.of(context).push(
                              MaterialPageRoute(builder: (_) => const VerifyEmailScreen()),
                            ),
                            child: const Text('Resend'),
                          ),
                        ],
                      ),
              ),
            ],
          ),
          const _SectionTitle('About'),
          _SettingsCard(
            children: [
              const _SettingsRow(
                icon: Icons.info_outline,
                label: 'About AniHow',
                trailing: Text('v1.0.0'),
              ),
              _SettingsRow(
                icon: Icons.help_outline,
                label: 'Help & contact',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const _SettingsCopyScreen(
                      title: 'Help & contact',
                      body:
                          'For pickup questions in General Trias, talk to the stall or email hello@anihow.local.\n\nThis page is a placeholder. There is no helpdesk API yet.',
                    ),
                  ),
                ),
              ),
              _SettingsRow(
                icon: Icons.description_outlined,
                label: 'Terms & privacy',
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const _SettingsCopyScreen(
                      title: 'Terms & privacy',
                      body:
                          'AniHow is a pickup-only market hub. This page is a placeholder — no published legal document is stored in the app yet.',
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AniHowSpace.section),
          OutlinedButton(
            onPressed: () async {
              await auth.logout();
              if (context.mounted) {
                Navigator.of(context).popUntil((route) => route.isFirst);
              }
            },
            style: OutlinedButton.styleFrom(
              foregroundColor: scheme.error,
              side: BorderSide(color: scheme.error),
              minimumSize: const Size.fromHeight(48),
            ),
            child: const Text('Log out'),
          ),
        ],
      ),
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.label, {this.first = false});

  final String label;
  final bool first;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(top: first ? 0 : AniHowSpace.section, bottom: 8),
      child: Text(label, style: Theme.of(context).textTheme.titleMedium),
    );
  }
}

class _SettingsCard extends StatelessWidget {
  const _SettingsCard({this.child, this.children});

  final Widget? child;
  final List<Widget>? children;

  @override
  Widget build(BuildContext context) {
    final rows = children;
    return Card(
      child: child ??
          Column(
            children: [
              for (var i = 0; i < (rows?.length ?? 0); i++) ...[
                if (i > 0) const Divider(height: 1),
                rows![i],
              ],
            ],
          ),
    );
  }
}

class _SettingsRow extends StatelessWidget {
  const _SettingsRow({
    required this.icon,
    required this.label,
    this.trailing,
    this.onTap,
    this.enabled = true,
  });

  final IconData icon;
  final String label;
  final Widget? trailing;
  final VoidCallback? onTap;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      enabled: enabled,
      leading: Icon(icon),
      title: Text(label, style: Theme.of(context).textTheme.titleMedium),
      trailing: trailing ?? (onTap == null ? null : const Icon(Icons.chevron_right)),
      onTap: enabled ? onTap : null,
    );
  }
}

class _SoonTag extends StatelessWidget {
  const _SoonTag();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(
        color: AniHowColors.cancelled.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: const Text(
        'Soon',
        style: TextStyle(
          color: AniHowColors.cancelled,
          fontWeight: FontWeight.w700,
          fontSize: AniHowSpace.label,
        ),
      ),
    );
  }
}

class _SettingsCopyScreen extends StatelessWidget {
  const _SettingsCopyScreen({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(body, style: Theme.of(context).textTheme.bodyLarge),
        ],
      ),
    );
  }
}
