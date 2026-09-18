import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../state/auth_controller.dart';
import '../../widgets/app_header.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/profile_avatar_button.dart';
import '../profile/profile_screen.dart';
import 'crop_care_screen.dart';
import 'listings_screen.dart';
import 'pos_screen.dart';
import 'reservations_screen.dart';

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
    final pages = const [
      FarmerListingsScreen(),
      FarmerReservationsScreen(),
      PosScreen(),
      CropCareScreen(),
    ];
    final titles = ['My listings', 'Incoming orders', 'Walk-in POS', 'Crop care'];

    return Scaffold(
      appBar: AppHeader(
        title: titles[_index],
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            const NotificationBellButton(),
            ProfileAvatarButton(
              name: user?.shopName ?? user?.name ?? 'F',
              onPressed: () {
                Navigator.of(context).push(
                  MaterialPageRoute(builder: (_) => const FarmerProfileScreen()),
                );
              },
            ),
          ],
        ),
      ),
      body: pages[_index],
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (value) => setState(() => _index = value),
        destinations: const [
          NavigationDestination(icon: Icon(Icons.inventory_2_outlined), label: 'Listings'),
          NavigationDestination(icon: Icon(Icons.inbox_outlined), label: 'Orders'),
          NavigationDestination(icon: Icon(Icons.point_of_sale_outlined), label: 'POS'),
          NavigationDestination(icon: Icon(Icons.menu_book_outlined), label: 'Crop care'),
        ],
      ),
    );
  }
}
