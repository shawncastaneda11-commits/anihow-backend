import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/api_config.dart';
import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';
import '../widgets/app_header.dart';

/// Super Admins and Content Editors work in the web CMS. This screen points
/// them to it on the server the app is connected to.
class AdminGateScreen extends StatelessWidget {
  const AdminGateScreen({super.key});

  /// The CMS address. A debug build reaches the server through the emulator
  /// alias, so it shows the address that works on the computer instead.
  static String get cmsUrl =>
      '${ApiConfig.hasReleaseApi ? ApiConfig.host : ApiConfig.localHost}/admin';

  Future<void> _open(BuildContext context) async {
    final messenger = ScaffoldMessenger.of(context);
    final s = AppStrings.read(context);
    var opened = false;
    try {
      opened = await launchUrl(
        Uri.parse(cmsUrl),
        mode: LaunchMode.externalApplication,
      );
    } catch (_) {
      opened = false;
    }
    if (!opened) {
      await Clipboard.setData(ClipboardData(text: cmsUrl));
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            s.t(
              'No browser found. The link is copied.',
              'Walang browser. Nakopya na ang link.',
            ),
          ),
        ),
      );
    }
  }

  Future<void> _copy(BuildContext context) async {
    final messenger = ScaffoldMessenger.of(context);
    final s = AppStrings.read(context);
    await Clipboard.setData(ClipboardData(text: cmsUrl));
    messenger.showSnackBar(
      SnackBar(content: Text(s.t('Link copied.', 'Nakopya ang link.'))),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final user = auth.user;
    final role = user?.isSuperAdmin == true
        ? s.superAdmin
        : s.t('Content editor', 'Content editor');
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);

    return Scaffold(
      body: Column(
        children: [
          AppHeader(title: role, subtitle: user?.name),
          Expanded(
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                Card(
                  child: Padding(
                    padding: AniHowSpace.cardPadding,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Icon(
                          Icons.desktop_windows_outlined,
                          size: 36,
                          color: readableAccent(context),
                        ),
                        const SizedBox(height: AniHowSpace.cardGap),
                        Text(
                          s.t(
                            'Your work is in the admin panel',
                            'Nasa admin panel ang iyong trabaho',
                          ),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          s.t(
                            'The phone app is for buyers and farmer-sellers. Open the admin panel in a browser, preferably on a computer, and sign in with this same email and password.',
                            'Para sa mamimili at magsasaka ang phone app. Buksan ang admin panel sa browser, mas mainam sa computer, at mag-sign in gamit ang parehong email at password.',
                          ),
                          style: theme.textTheme.bodyMedium?.copyWith(
                            color: muted,
                          ),
                        ),
                        const SizedBox(height: AniHowSpace.cardGap),
                        DecoratedBox(
                          decoration: BoxDecoration(
                            color: dark
                                ? const Color(0xFF16201C)
                                : AniHowColors.cream,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Padding(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 10,
                            ),
                            child: Row(
                              children: [
                                Icon(Icons.link, size: 18, color: muted),
                                const SizedBox(width: 8),
                                Expanded(
                                  child: SelectableText(
                                    cmsUrl,
                                    key: const ValueKey('admin-cms-url'),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w600,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: AniHowSpace.cardGap),
                        FilledButton.icon(
                          key: const ValueKey('admin-open-cms'),
                          onPressed: () => _open(context),
                          icon: const Icon(Icons.open_in_new, size: 18),
                          label: Text(
                            s.t('Open admin panel', 'Buksan ang admin panel'),
                          ),
                        ),
                        const SizedBox(height: 8),
                        OutlinedButton.icon(
                          key: const ValueKey('admin-copy-cms'),
                          onPressed: () => _copy(context),
                          icon: const Icon(Icons.copy, size: 18),
                          label: Text(s.t('Copy link', 'Kopyahin ang link')),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: AniHowSpace.section),
                OutlinedButton.icon(
                  key: const ValueKey('admin-log-out'),
                  onPressed: auth.logout,
                  icon: const Icon(Icons.logout, size: 18),
                  label: Text(s.logOut),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
