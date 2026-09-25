import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../navigation/route_observer.dart';
import '../../state/auth_controller.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/profile_avatar_button.dart';
import '../faq/faq_bot_screen.dart';
import '../profile/profile_screen.dart';
import 'crop_care_screen.dart';
import 'farmer_orders_screen.dart';
import 'farmer_sales_screen.dart';
import 'listings_screen.dart';

class FarmerShell extends StatefulWidget {
  const FarmerShell({super.key});

  @override
  State<FarmerShell> createState() => _FarmerShellState();
}

class _FarmerShellState extends State<FarmerShell> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final s = AppStrings.of(context);
    final pages = const [
      FarmerListingsScreen(),
      FarmerOrdersScreen(),
      FarmerSalesScreen(),
      CropCareScreen(),
    ];
    final titles = [s.myListings, s.incomingOrders, s.mySales, s.cropCare];

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_index]),
        actions: [
          IconButton(
            tooltip: s.faq,
            icon: const Icon(Icons.help_outline),
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const FaqBotScreen()),
              );
            },
          ),
          const NotificationBellButton(),
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: ProfileAvatarButton(
              name: user?.shopName ?? user?.name ?? 'F',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const FarmerProfileScreen()),
                );
              },
            ),
          ),
        ],
      ),
      body: pages[_index],
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
            NavigationDestination(icon: const Icon(Icons.inventory_2_outlined), label: s.listings),
            NavigationDestination(icon: const Icon(Icons.inbox_outlined), label: s.orders),
            NavigationDestination(icon: const Icon(Icons.insights_outlined), label: s.mySales),
            NavigationDestination(icon: const Icon(Icons.menu_book_outlined), label: s.cropCare),
          ],
        ),
      ),
    );
  }
}
