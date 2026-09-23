import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../navigation/route_observer.dart';
import '../../state/auth_controller.dart';
import '../../widgets/notification_bell.dart';
import 'favorites_screen.dart';
import 'marketplace_screen.dart';
import 'order_history_screen.dart';
import '../profile/profile_screen.dart';
import '../profile/verify_email_screen.dart';

class BuyerShell extends StatefulWidget {
  const BuyerShell({super.key});

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  int _index = 0;
  bool _hideVerifyBanner = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _openVerifyAfterRegister());
  }

  void _openVerifyAfterRegister() {
    final auth = context.read<AuthController>();
    if (!auth.pendingEmailVerification || auth.user?.isVerified == true) {
      return;
    }
    auth.clearPendingEmailVerification();
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const VerifyEmailScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);
    final pages = const [
      MarketplaceScreen(),
      OrderHistoryScreen(),
      FavoritesScreen(),
      ProfileScreen(),
    ];
    final titles = [s.marketplace, s.orders, s.favorites, s.profile];
    final showVerifyBanner = auth.user?.isVerified == false && !_hideVerifyBanner;

    return Scaffold(
      appBar: _index == 0 || _index == 1
          ? null
          : AppBar(
              title: Text(titles[_index]),
              actions: const [NotificationBellButton()],
            ),
      body: Column(
        children: [
          if (showVerifyBanner)
            SafeArea(
              bottom: false,
              child: MaterialBanner(
                content: Text(s.verifyBanner),
                actions: [
                  TextButton(
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const VerifyEmailScreen()),
                    ),
                    child: Text(s.verifyNow),
                  ),
                  TextButton(
                    onPressed: () => setState(() => _hideVerifyBanner = true),
                    child: Text(s.ok),
                  ),
                ],
              ),
            ),
          Expanded(
            child: showVerifyBanner
                ? MediaQuery.removePadding(
                    context: context,
                    removeTop: true,
                    child: pages[_index],
                  )
                : pages[_index],
          ),
        ],
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: Theme.of(context).dividerColor)),
        ),
        child: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (value) {
            dismissAniHowSnackBars();
            setState(() => _index = value);
          },
          destinations: [
            NavigationDestination(icon: const Icon(Icons.storefront_outlined), label: s.market),
            NavigationDestination(icon: const Icon(Icons.receipt_long_outlined), label: s.orders),
            NavigationDestination(icon: const Icon(Icons.favorite_outline), label: s.favorites),
            NavigationDestination(icon: const Icon(Icons.person_outline), label: s.profile),
          ],
        ),
      ),
    );
  }
}
