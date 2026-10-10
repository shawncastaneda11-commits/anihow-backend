import 'package:flutter/material.dart';

/// Tabs on a green app bar. The theme's default tab color is the same green,
/// so an unstyled selected label disappears.
///
/// No vertical label padding: TabBar.preferredSize ignores it, so the theme's
/// 8dp top and bottom made the bar 64dp tall while it reported 48dp, and an
/// AppBar squeezed its toolbar row to 40dp to make room.
TabBar onBrandTabBar({
  TabController? controller,
  required List<Widget> tabs,
  bool isScrollable = false,
}) {
  return TabBar(
    controller: controller,
    isScrollable: isScrollable,
    labelColor: Colors.white,
    labelStyle: const TextStyle(fontWeight: FontWeight.w700),
    unselectedLabelColor: Colors.white.withValues(alpha: 0.75),
    indicator: const UnderlineTabIndicator(
      borderSide: BorderSide(color: Colors.white, width: 3),
    ),
    dividerColor: Colors.transparent,
    labelPadding: const EdgeInsets.symmetric(horizontal: 8),
    tabs: tabs,
  );
}
