import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../navigation/route_observer.dart';
import '../../state/auth_controller.dart';
import '../../widgets/order_chat_head.dart';
import '../../widgets/unverified_email_banner.dart';
import 'favorites_screen.dart';
import 'marketplace_screen.dart';
import 'order_history_screen.dart';
import 'shops_screen.dart';
import '../auth/verify_id_screen.dart';
import '../profile/verify_email_screen.dart';

/// Test hook so the banner layout can be pumped without live API pages.
class BuyerShellPreview {
  const BuyerShellPreview({this.index = 0, this.pages});

  final int index;
  final List<Widget>? pages;
}

class BuyerShell extends StatefulWidget {
  const BuyerShell({super.key, this.preview});

  final BuyerShellPreview? preview;

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  int _index = 0;
  bool _hideVerifyBanner = false;

  @override
  void initState() {
    super.initState();
    _index = widget.preview?.index ?? 1;
    if (widget.preview == null) {
      WidgetsBinding.instance.addPostFrameCallback(
        (_) => _openVerifyAfterRegister(),
      );
    }
  }

  /// After sign-up: verify the email first, then offer the ID check once.
  Future<void> _openVerifyAfterRegister() async {
    final auth = context.read<AuthController>();
    if (auth.pendingEmailVerification && auth.user?.isVerified != true) {
      auth.clearPendingEmailVerification();
      await Navigator.of(context).push(
        MaterialPageRoute<void>(builder: (_) => const VerifyEmailScreen()),
      );
      if (!mounted) {
        return;
      }
    }
    if (!auth.pendingIdVerification) {
      return;
    }
    auth.clearPendingIdVerification();
    final idCheck = auth.user?.idVerification;
    if (idCheck == null || !idCheck.blocksOrders || idCheck.status != 'none') {
      return;
    }
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => const VerifyIdScreen(afterSignUp: true),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final pages =
        widget.preview?.pages ??
        [
          const ShopsScreen(showAccountMenu: true),
          const MarketplaceScreen(showAccountMenu: true),
          OrderHistoryScreen(active: _index == 2, showAccountMenu: true),
          FavoritesScreen(active: _index == 3, showAccountMenu: true),
        ];

    return VerifyBannerScope(
      hidden: _hideVerifyBanner,
      hide: () => setState(() => _hideVerifyBanner = true),
      child: Scaffold(
        body: IndexedStack(index: _index, children: pages),
        floatingActionButton: widget.preview == null
            ? const OrderChatHead()
            : null,
        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
        bottomNavigationBar: DecoratedBox(
          decoration: BoxDecoration(
            border: Border(
              top: BorderSide(color: Theme.of(context).dividerColor),
            ),
          ),
          // Labels may grow with the phone's text size only so far, so a long
          // word cannot push the bar onto three lines.
          child: MediaQuery.withClampedTextScaling(
            maxScaleFactor: 1.15,
            child: NavigationBar(
              selectedIndex: _index,
              onDestinationSelected: (value) {
                dismissAniHowSnackBars();
                setState(() => _index = value);
              },
              destinations: [
                NavigationDestination(
                  icon: const Icon(Icons.agriculture_outlined),
                  selectedIcon: const Icon(Icons.agriculture),
                  label: s.navFarms,
                  tooltip: s.shops,
                ),
                NavigationDestination(
                  icon: const Icon(Icons.storefront_outlined),
                  selectedIcon: const Icon(Icons.storefront),
                  label: s.navMarket,
                  tooltip: s.market,
                ),
                NavigationDestination(
                  icon: const Icon(Icons.receipt_long_outlined),
                  label: s.navOrders,
                  tooltip: s.orders,
                ),
                NavigationDestination(
                  icon: const Icon(Icons.favorite_outline),
                  label: s.navFavorites,
                  tooltip: s.favorites,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
