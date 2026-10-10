import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../push/push_preferences.dart';
import '../../push/push_runtime.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../state/theme_controller.dart';
import '../../support/crop_language.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/anihow_logo.dart';
import '../../widgets/status_pill.dart';
import 'change_password_screen.dart';
import 'my_data_screen.dart';
import 'profile_screen.dart';
import 'verify_email_screen.dart';
import '../auth/seller_info_screen.dart';
import '../faq/faq_bot_screen.dart';
import 'help_screen.dart';

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final theme = context.watch<ThemeController>();
    final prefs = context.watch<PreferencesController>();
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);
    final user = auth.user;
    final scheme = Theme.of(context).colorScheme;
    final language = prefs.language == CropLanguage.filipino
        ? CropLanguage.filipino
        : CropLanguage.english;

    return Scaffold(
      appBar: AppBar(title: Text(s.settings)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          _SectionTitle(s.help, first: true),
          _SettingsCard(
            children: [
              _SettingsRow(
                icon: Icons.help_outline,
                label: s.faq,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => const FaqBotScreen()),
                ),
              ),
              _SettingsRow(
                key: const Key('help-how-it-works'),
                icon: Icons.menu_book_outlined,
                label: s.howAnihowWorks,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => const HelpScreen()),
                ),
              ),
              if (user?.isBuyer == true)
                _SettingsRow(
                  icon: Icons.storefront_outlined,
                  label: s.wantToBeASeller,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const SellerInfoScreen(),
                    ),
                  ),
                ),
            ],
          ),
          _SectionTitle(s.appearance),
          _SettingsCard(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: SegmentedButton<ThemeMode>(
                segments: [
                  ButtonSegment(
                    value: ThemeMode.light,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(s.light),
                    ),
                    icon: const Icon(Icons.light_mode_outlined),
                  ),
                  ButtonSegment(
                    value: ThemeMode.dark,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(s.dark),
                    ),
                    icon: const Icon(Icons.dark_mode_outlined),
                  ),
                  ButtonSegment(
                    value: ThemeMode.system,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(s.system),
                    ),
                    icon: const Icon(Icons.phone_android),
                  ),
                ],
                selected: {theme.mode},
                onSelectionChanged: (value) => theme.setMode(value.first),
              ),
            ),
          ),
          _SectionTitle(s.preferences),
          const _PushSettings(),
          _SectionTitle(s.language),
          _SettingsCard(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: SegmentedButton<CropLanguage>(
                segments: [
                  ButtonSegment(
                    value: CropLanguage.english,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(s.english),
                    ),
                  ),
                  ButtonSegment(
                    value: CropLanguage.filipino,
                    label: FittedBox(
                      fit: BoxFit.scaleDown,
                      child: Text(s.filipinoLabel),
                    ),
                  ),
                ],
                selected: {language},
                onSelectionChanged: (value) => prefs.setLanguage(value.first),
              ),
            ),
          ),
          _SectionTitle(s.account),
          _SettingsCard(
            children: [
              if (user?.isFarmerSeller == true)
                _SettingsRow(
                  icon: Icons.storefront_outlined,
                  label: s.editProfile,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const FarmerProfileScreen(),
                    ),
                  ),
                ),
              if (user?.isBuyer == true || user?.isFarmerSeller == true)
                _SettingsRow(
                  icon: Icons.shield_outlined,
                  label: s.myData,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const MyDataScreen(),
                    ),
                  ),
                ),
              _SettingsRow(
                icon: Icons.lock_outline,
                label: s.changePassword,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const ChangePasswordScreen(),
                  ),
                ),
              ),
              _SettingsRow(
                icon: Icons.mail_outline,
                label: s.email,
                onTap: () {
                  if (user?.isVerified == true) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(s.emailVerifiedNotice(user!.email)),
                      ),
                    );
                    return;
                  }
                  Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const VerifyEmailScreen(),
                    ),
                  );
                },
                trailing: user?.isVerified == true
                    ? StatusPill(label: s.verified, color: AniHowColors.ready)
                    : StatusPill(
                        label: s.unverified,
                        color: AniHowColors.pending,
                      ),
              ),
            ],
          ),
          _SectionTitle(s.about),
          _SettingsCard(
            children: [
              _SettingsRow(
                icon: Icons.info_outline,
                label: s.aboutAniHow,
                trailing: const Text('v1.0.0'),
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => _SettingsCopyScreen(
                      title: s.aboutAniHow,
                      showBrandLogo: true,
                      sections: [
                        for (final section in s.aboutSections)
                          _CopySection(
                            title: section.title,
                            body: section.body,
                          ),
                      ],
                    ),
                  ),
                ),
              ),
              _SettingsRow(
                icon: Icons.description_outlined,
                label: s.termsPrivacy,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => _SettingsCopyScreen(
                      title: s.termsPrivacy,
                      sections: [
                        for (final section in s.termsSections)
                          _CopySection(
                            title: section.title,
                            body: section.body,
                          ),
                      ],
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
            child: Text(s.logOut),
          ),
        ],
      ),
    );
  }
}

class _PushSettings extends StatefulWidget {
  const _PushSettings();

  @override
  State<_PushSettings> createState() => _PushSettingsState();
}

class _PushSettingsState extends State<_PushSettings> {
  Map<String, bool> _categories = defaultPushPreferences();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    if (!mounted) {
      return;
    }
    final auth = context.read<AuthController>();
    if (auth.user == null) {
      return;
    }
    try {
      final next = await auth.api.pushPreferences();
      if (mounted) {
        setState(() => _categories = next);
      }
    } catch (_) {}
  }

  Future<void> _setMaster(bool enabled) async {
    final preferences = context.read<PreferencesController>();
    final api = context.read<AuthController>().api;
    await preferences.setNotificationsEnabled(enabled);
    if (!enabled) {
      await PushRuntime.forgetDevice(api);
      if (mounted) {
        setState(() {});
      }
      return;
    }
    try {
      await PushRuntime.device?.requestPermission();
      await PushRuntime.register(api);
    } catch (_) {}
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _setCategory(String key, bool enabled) async {
    final previous = _categories[key] ?? true;
    setState(() => _categories[key] = enabled);
    try {
      final next = await context.read<AuthController>().api.updatePushPreferences({
        key: enabled,
      });
      if (mounted) {
        setState(() => _categories = next);
      }
    } catch (_) {
      if (mounted) {
        setState(() => _categories[key] = previous);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final prefs = context.watch<PreferencesController>();
    final strings = AppStrings.of(context);
    final master = prefs.notificationsEnabled;
    final device = PushRuntime.device;
    final rows = [
      (key: 'orders', label: strings.pushOrders),
      (key: 'payments', label: strings.pushPayments),
      (key: 'chats', label: strings.pushChats),
      (key: 'farm_updates', label: strings.pushFarmUpdates),
    ];
    return _SettingsCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SwitchListTile(
            key: const ValueKey('push-master'),
            secondary: const Icon(Icons.notifications_outlined),
            title: Text(strings.pushOnThisPhone),
            value: master,
            onChanged: (value) => _setMaster(value),
          ),
          if (device?.permissionDenied == true)
            Align(
              alignment: Alignment.centerLeft,
              child: TextButton(
                onPressed: () => device!.openSystemSettings(),
                child: Text(strings.turnOnInPhoneSettings),
              ),
            ),
          for (final row in rows)
            SwitchListTile(
              key: ValueKey('push-${row.key}'),
              title: Text(row.label),
              value: _categories[row.key] ?? true,
              onChanged: master ? (value) => _setCategory(row.key, value) : null,
            ),
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Text(
              strings.pushAlwaysOn,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
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
      child:
          child ??
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
    super.key,
    required this.icon,
    required this.label,
    this.trailing,
    this.onTap,
  });

  final IconData icon;
  final String label;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon),
      title: Text(label, style: Theme.of(context).textTheme.titleMedium),
      trailing: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (trailing != null)
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 120),
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: Alignment.centerRight,
                child: trailing!,
              ),
            ),
          if (trailing != null && onTap != null) const SizedBox(width: 4),
          if (onTap != null) const Icon(Icons.chevron_right),
        ],
      ),
      onTap: onTap,
    );
  }
}

class _CopySection {
  const _CopySection({required this.title, required this.body});

  final String title;
  final String body;
}

class _SettingsCopyScreen extends StatelessWidget {
  const _SettingsCopyScreen({
    required this.title,
    required this.sections,
    this.showBrandLogo = false,
  });

  final String title;
  final List<_CopySection> sections;
  final bool showBrandLogo;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          if (showBrandLogo) ...[
            const Center(
              child: AniHowLogoMark(
                markHeight: 140,
                wordmarkHeight: 76,
                wordmarkWidth: 300,
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
          ],
          for (var i = 0; i < sections.length; i++) ...[
            if (i > 0) const SizedBox(height: AniHowSpace.section),
            Text(sections[i].title, style: textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.labelGap),
            Text(sections[i].body, style: textTheme.bodyLarge),
          ],
        ],
      ),
    );
  }
}
