import 'package:flutter/material.dart';

import '../../l10n/app_strings.dart';
import '../../navigation/route_observer.dart';
import '../../widgets/account_menu_button.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/order_chat_head.dart';
import '../chat/order_chats_screen.dart';
import 'crop_care_screen.dart';
import 'farmer_orders_screen.dart';
import 'farmer_sales_screen.dart';
import 'listings_screen.dart';

/// Test hook so the seller bar can be pumped without live API pages.
class FarmerShellPreview {
  const FarmerShellPreview({this.index = 0, this.pages});

  final int index;
  final List<Widget>? pages;
}

class FarmerShell extends StatefulWidget {
  const FarmerShell({super.key, this.preview});

  final FarmerShellPreview? preview;

  @override
  State<FarmerShell> createState() => _FarmerShellState();
}

class _FarmerShellState extends State<FarmerShell> {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    _index = widget.preview?.index ?? 0;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final pages =
        widget.preview?.pages ??
        [
          const FarmerListingsScreen(),
          FarmerOrdersScreen(active: _index == 1),
          const FarmerSalesScreen(),
          const CropCareScreen(),
          OrderChatsScreen(
            forSeller: true,
            embedded: true,
            active: _index == 4,
          ),
        ];
    final titles = [
      s.myListings,
      s.incomingOrders,
      s.mySales,
      s.cropCare,
      s.chats,
    ];

    return Scaffold(
      appBar: AppBar(
        title: Text(titles[_index]),
        actions: const [NotificationBellButton(), AccountMenuButton()],
      ),
      body: IndexedStack(index: _index, children: pages),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          border: Border(
            top: BorderSide(color: Theme.of(context).dividerColor),
          ),
        ),
        child: NavigationBar(
          selectedIndex: _index,
          onDestinationSelected: (value) {
            dismissAniHowSnackBars();
            setState(() => _index = value);
          },
          destinations: [
            NavigationDestination(
              icon: const Icon(Icons.inventory_2_outlined),
              label: s.listings,
            ),
            NavigationDestination(
              icon: const Icon(Icons.inbox_outlined),
              label: s.orders,
            ),
            NavigationDestination(
              icon: const Icon(Icons.insights_outlined),
              label: s.mySales,
            ),
            NavigationDestination(
              icon: const Icon(Icons.menu_book_outlined),
              label: s.cropCare,
            ),
            NavigationDestination(
              icon: const ChatBubbleMark(size: 24),
              selectedIcon: const ChatBubbleMark(size: 24),
              label: s.chats,
            ),
          ],
        ),
      ),
    );
  }
}
