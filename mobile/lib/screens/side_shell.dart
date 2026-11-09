import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_controller.dart';
import 'buyer/buyer_shell.dart';
import 'farmer/farmer_shell.dart';

/// A farmer-seller who also buys: the seller's or the buyer's app, as chosen
/// with the Selling / Buying chip in the header (SideChip). One login.
class SideShell extends StatelessWidget {
  const SideShell({super.key});

  @override
  Widget build(BuildContext context) {
    final buying = context.select<AuthController, bool>((auth) => auth.buying);

    return KeyedSubtree(
      key: ValueKey<bool>(buying),
      child: buying ? const BuyerShell() : const FarmerShell(),
    );
  }
}
