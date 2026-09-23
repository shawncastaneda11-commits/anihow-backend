import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../screens/buyer/cart_screen.dart';
import '../state/cart_controller.dart';

class CartIconButton extends StatelessWidget {
  const CartIconButton({super.key, this.color});

  final Color? color;

  @override
  Widget build(BuildContext context) {
    final count = context.watch<CartController>().count;
    final label = count > 99 ? '99+' : '$count';
    return IconButton(
      tooltip: 'Cart',
      onPressed: () {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const CartScreen()),
        );
      },
      padding: EdgeInsets.zero,
      constraints: const BoxConstraints.tightFor(width: 48, height: 48),
      icon: Badge(
        isLabelVisible: count > 0,
        label: Text(label),
        child: Icon(
          Icons.shopping_basket_outlined,
          size: 24,
          color: color ?? Theme.of(context).colorScheme.onPrimary,
        ),
      ),
    );
  }
}
