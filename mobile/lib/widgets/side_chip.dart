import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../navigation/route_observer.dart';
import '../state/auth_controller.dart';

/// In the header of a seller who also buys: names the side on screen, and
/// one tap moves to the other side. Nothing for anyone else.
class SideChip extends StatelessWidget {
  const SideChip({super.key});

  @override
  Widget build(BuildContext context) {
    final AuthController auth;
    try {
      auth = context.watch<AuthController>();
    } on ProviderNotFoundException {
      return const SizedBox.shrink();
    }
    if (auth.user?.alsoBuys != true) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    final buying = auth.buying;
    final onBrand = Theme.of(context).colorScheme.onPrimary;
    final brand = Theme.of(context).colorScheme.primary;

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: Center(
        child: Semantics(
          button: true,
          label: buying ? s.sideChipBuyingHint : s.sideChipSellingHint,
          excludeSemantics: true,
          child: Material(
            key: const ValueKey('side-chip'),
            color: buying ? onBrand : onBrand.withValues(alpha: 0.12),
            shape: StadiumBorder(
              side: BorderSide(color: buying ? onBrand : onBrand.withValues(alpha: 0.55)),
            ),
            child: InkWell(
              customBorder: const StadiumBorder(),
              onTap: () {
                context.read<AuthController>().showSide(buying: !buying);
                anihowScaffoldMessengerKey.currentState
                  ?..hideCurrentSnackBar()
                  ..showSnackBar(
                    SnackBar(
                      content: Text(!buying ? s.nowBuying : s.backToSelling),
                      duration: const Duration(seconds: 3),
                    ),
                  );
              },
              child: Padding(
                padding: const EdgeInsets.fromLTRB(9, 6, 11, 6),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.swap_horiz, size: 16, color: buying ? brand : onBrand),
                    const SizedBox(width: 4),
                    Text(
                      buying ? s.sideBuying : s.sideSelling,
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: buying ? brand : onBrand,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
