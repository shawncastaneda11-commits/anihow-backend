import 'package:flutter/material.dart';

import '../theme/anihow_space.dart';
import 'account_menu_button.dart';
import 'cart_icon_button.dart';
import 'notification_bell.dart';

/// The one header for every main tab. It is the themed [AppBar], so the title
/// and the account button sit in the same place on Farms, Market, Orders,
/// Favorites, and every seller tab.
AppBar mainTabAppBar({
  required String title,
  bool showAccountMenu = false,
  bool showCart = false,
  PreferredSizeWidget? bottom,
}) {
  return AppBar(
    toolbarHeight: kToolbarHeight,
    titleSpacing: AniHowSpace.screen,
    title: Text(title),
    actions: [
      if (showCart) const CartIconButton(),
      const NotificationBellButton(),
      if (showAccountMenu) const AccountMenuButton(),
    ],
    bottom: bottom,
  );
}

/// 12dp between the bottom of the header and the first piece of content.
const Widget mainTabBodyGap = SizedBox(height: AniHowSpace.cardGap);
