import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../screens/auth/seller_info_screen.dart';
import '../screens/faq/faq_bot_screen.dart';
import '../screens/profile/help_screen.dart';
import '../screens/profile/profile_screen.dart';
import '../screens/profile/settings_screen.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';
import 'profile_avatar_button.dart';

/// Red used for the Log out label on the account card.
///
/// Dark mode uses a lighter red so the label stays at least 4.5:1 on
/// [AniHowColors.darkCard] (`#1C2622`). `#FF8A80` on that card is 6.82:1.
Color accountMenuLogoutColor(Brightness brightness) {
  return brightness == Brightness.dark
      ? const Color(0xFFFF8A80)
      : const Color(0xFFB3261E);
}

Color accountMenuLogoutFill(Brightness brightness) {
  return brightness == Brightness.dark
      ? const Color(0xFF4A2C2A)
      : const Color(0xFFFDECEA);
}

/// Filled Log out button. White on `#FF8A80` fails contrast, so both themes
/// keep the darker red with white text (about 5.9:1).
const Color accountMenuLogoutButton = Color(0xFFB3261E);

bool _logOutBusy = false;

/// Confirms, then runs [AuthController.logout] and returns to the first route.
///
/// A second tap while the request is in flight does nothing.
Future<void> confirmAndLogOut(BuildContext context) async {
  if (_logOutBusy) {
    return;
  }
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (_) => const _LogOutDialog(),
  );
  if (confirmed != true || !context.mounted || _logOutBusy) {
    return;
  }
  _logOutBusy = true;
  try {
    await context.read<AuthController>().logout();
    if (context.mounted) {
      Navigator.of(context).popUntil((route) => route.isFirst);
    }
  } finally {
    _logOutBusy = false;
  }
}

class AccountMenuButton extends StatefulWidget {
  const AccountMenuButton({super.key});

  @override
  State<AccountMenuButton> createState() => _AccountMenuButtonState();
}

class _AccountMenuButtonState extends State<AccountMenuButton> {
  final MenuController _menu = MenuController();

  void _push(Widget screen) {
    _menu.close();
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final user = context.watch<AuthController>().user;
    final seller = user?.isFarmerSeller == true;
    final shop = user?.shopName?.trim() ?? '';
    final avatarName = seller && shop.isNotEmpty ? shop : (user?.name ?? '');
    final screenWidth = MediaQuery.sizeOf(context).width;
    final menuWidth = math.min(320.0, math.max(160.0, screenWidth - 20));
    final open = _menu.isOpen;

    return MenuAnchor(
      controller: _menu,
      consumeOutsideTap: true,
      crossAxisUnconstrained: true,
      clipBehavior: Clip.none,
      alignmentOffset: Offset(48 - menuWidth, 4),
      style: const MenuStyle(
        backgroundColor: WidgetStatePropertyAll(Colors.transparent),
        surfaceTintColor: WidgetStatePropertyAll(Colors.transparent),
        elevation: WidgetStatePropertyAll(0),
        padding: WidgetStatePropertyAll(EdgeInsets.zero),
      ),
      onOpen: () => setState(() {}),
      onClose: () => setState(() {}),
      menuChildren: [
        _AccountMenuPanel(
          width: menuWidth,
          onProfile: () => _push(
            seller ? const FarmerProfileScreen() : const ProfileScreen(),
          ),
          onSettings: () => _push(const SettingsScreen()),
          onHelp: () =>
              _push(seller ? const FaqBotScreen() : const HelpScreen()),
          onExtra: () =>
              _push(seller ? const HelpScreen() : const SellerInfoScreen()),
          onLogOut: () {
            _menu.close();
            confirmAndLogOut(context);
          },
        ),
      ],
      builder: (context, controller, child) {
        return IconButton(
          tooltip: s.accountMenu,
          onPressed: () {
            if (controller.isOpen) {
              controller.close();
            } else {
              controller.open();
            }
          },
          padding: EdgeInsets.zero,
          constraints: const BoxConstraints.tightFor(width: 48, height: 48),
          icon: SizedBox(
            width: 48,
            height: 48,
            child: Stack(
              clipBehavior: Clip.none,
              alignment: Alignment.center,
              children: [
                DecoratedBox(
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: open
                        ? Border.all(color: Colors.white, width: 3)
                        : null,
                  ),
                  child: AniHowAvatar(
                    name: avatarName,
                    imageUrl: user?.avatarUrl,
                    radius: 18,
                    backgroundColor: AniHowColors.avatarOnBrand,
                    foregroundColor: Theme.of(context).colorScheme.onPrimary,
                  ),
                ),
                Positioned(
                  right: 0,
                  bottom: 0,
                  child: Container(
                    width: 18,
                    height: 18,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      border: Border.all(color: AniHowColors.brand, width: 2),
                    ),
                    child: const Icon(
                      Icons.expand_more,
                      size: 12,
                      color: AniHowColors.brand,
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _AccountMenuPanel extends StatelessWidget {
  const _AccountMenuPanel({
    required this.width,
    required this.onProfile,
    required this.onSettings,
    required this.onHelp,
    required this.onExtra,
    required this.onLogOut,
  });

  final double width;
  final VoidCallback onProfile;
  final VoidCallback onSettings;
  final VoidCallback onHelp;
  final VoidCallback onExtra;
  final VoidCallback onLogOut;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final user = context.watch<AuthController>().user;
    final seller = user?.isFarmerSeller == true;
    final shop = user?.shopName?.trim() ?? '';
    final person = user?.name ?? '';
    final title = seller && shop.isNotEmpty ? shop : person;
    final farm = user?.farmName?.trim() ?? '';
    final subtitle = seller
        ? (farm.isEmpty ? person : '$person · $farm')
        : (user?.email ?? '');
    final link = seller ? s.viewYourShopProfile : s.viewYourProfile;
    final card = theme.cardTheme.color ?? theme.colorScheme.surface;
    final accent = readableAccent(context);
    final logout = accountMenuLogoutColor(theme.brightness);

    return SizedBox(
      width: width,
      child: Material(
        key: const Key('account-menu-panel'),
        color: card,
        elevation: 8,
        shadowColor: Colors.black.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(18),
        clipBehavior: Clip.antiAlias,
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Material(
                color: accentTint(context),
                borderRadius: BorderRadius.circular(14),
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: onProfile,
                  child: Padding(
                    padding: const EdgeInsets.all(12),
                    child: Row(
                      children: [
                        AniHowAvatar(
                          name: title,
                          imageUrl: user?.avatarUrl,
                          radius: 24,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                title,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleMedium?.copyWith(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                subtitle,
                                maxLines: 2,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.bodyMedium?.copyWith(
                                  color: theme.colorScheme.onSurfaceVariant,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Row(
                                children: [
                                  Flexible(
                                    child: Text(
                                      link,
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                      style: theme.textTheme.bodyMedium
                                          ?.copyWith(
                                            color: accent,
                                            fontWeight: FontWeight.w700,
                                          ),
                                    ),
                                  ),
                                  Icon(
                                    Icons.chevron_right,
                                    size: 18,
                                    color: accent,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              const Divider(height: 1),
              const SizedBox(height: 4),
              _MenuRow(
                icon: Icons.settings_outlined,
                label: s.settings,
                onTap: onSettings,
              ),
              _MenuRow(
                icon: seller ? Icons.smart_toy_outlined : Icons.quiz_outlined,
                label: seller ? s.askTheFaqBot : s.helpAndFaq,
                onTap: onHelp,
              ),
              _MenuRow(
                icon: seller
                    ? Icons.menu_book_outlined
                    : Icons.storefront_outlined,
                label: seller ? s.howAnihowWorks : s.wantToBeASeller,
                onTap: onExtra,
              ),
              const SizedBox(height: 4),
              const Divider(height: 1),
              const SizedBox(height: 4),
              _MenuRow(
                icon: Icons.logout,
                label: s.logOut,
                color: logout,
                fill: accountMenuLogoutFill(theme.brightness),
                onTap: onLogOut,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MenuRow extends StatelessWidget {
  const _MenuRow({
    required this.icon,
    required this.label,
    required this.onTap,
    this.color,
    this.fill,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? color;
  final Color? fill;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = color ?? readableAccent(context);
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 52),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: fill ?? accentTint(context),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 20, color: accent),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  label,
                  style: theme.textTheme.titleMedium?.copyWith(color: color),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LogOutDialog extends StatefulWidget {
  const _LogOutDialog();

  @override
  State<_LogOutDialog> createState() => _LogOutDialogState();
}

class _LogOutDialogState extends State<_LogOutDialog> {
  bool _busy = false;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return AlertDialog(
      title: Text(s.logOutConfirmTitle),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(s.logOutConfirmBody),
          const SizedBox(height: 20),
          Row(
            children: [
              Expanded(
                child: FilledButton.tonal(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: () => Navigator.of(context).pop(false),
                  child: Text(s.cancel),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: FilledButton(
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                    backgroundColor: accountMenuLogoutButton,
                    foregroundColor: Colors.white,
                  ),
                  onPressed: () {
                    if (_busy) {
                      return;
                    }
                    _busy = true;
                    Navigator.of(context).pop(true);
                  },
                  child: Text(s.logOut),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
