import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../state/auth_controller.dart';
import '../../widgets/notification_bell.dart';
import 'favorites_screen.dart';
import 'marketplace_screen.dart';
import 'reservations_screen.dart';
import '../profile/profile_screen.dart';

class BuyerShell extends StatefulWidget {
  const BuyerShell({super.key});

  @override
  State<BuyerShell> createState() => _BuyerShellState();
}

class _BuyerShellState extends State<BuyerShell> {
  int _index = 0;
  bool _hideVerifyBanner = false;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final pages = const [
      MarketplaceScreen(),
      BuyerReservationsScreen(),
      FavoritesScreen(),
      ProfileScreen(),
    ];
    final titles = ['Marketplace', 'Reservations', 'Favorites', 'Profile'];

    return Scaffold(
      appBar: _index == 0
          ? null
          : AppBar(
              title: Text(titles[_index]),
              actions: const [NotificationBellButton()],
            ),
      body: Column(
        children: [
          if (auth.user?.isVerified == false && !_hideVerifyBanner)
            MaterialBanner(
              content: const Text(
                'Verify your email before reserving or saving favorites.',
              ),
              actions: [
                TextButton(
                  onPressed: () => setState(() => _hideVerifyBanner = true),
                  child: const Text('OK'),
                ),
              ],
            ),
          Expanded(child: pages[_index]),
        ],
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(top: BorderSide(color: Theme.of(context).dividerColor)),
        ),
        child: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (value) => setState(() => _index = value),
          destinations: const [
            NavigationDestination(icon: Icon(Icons.storefront_outlined), label: 'Market'),
            NavigationDestination(icon: Icon(Icons.receipt_long_outlined), label: 'Reservations'),
            NavigationDestination(icon: Icon(Icons.favorite_outline), label: 'Favorites'),
            NavigationDestination(icon: Icon(Icons.person_outline), label: 'Profile'),
          ],
        ),
      ),
    );
  }
}
