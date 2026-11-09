import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import 'auth/verify_id_screen.dart';
import 'buyer/buyer_shell.dart';
import 'farmer/farmer_shell.dart';

/// A farmer-seller who also buys: the Selling / Buying switch at the top,
/// and the seller's or the buyer's app below it. One login for both.
class SideShell extends StatelessWidget {
  const SideShell({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final buying = auth.buying;
    final idCheck = auth.user?.idVerification;
    final needsId = buying && idCheck != null && idCheck.blocksOrders && idCheck.status == 'none';

    return Scaffold(
      body: Column(
        children: [
          _SideSwitchBar(buying: buying),
          if (needsId) const _SendIdBanner(),
          Expanded(
            child: MediaQuery.removePadding(
              context: context,
              removeTop: true,
              child: KeyedSubtree(
                key: ValueKey<bool>(buying),
                child: buying ? const BuyerShell() : const FarmerShell(),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SideSwitchBar extends StatelessWidget {
  const _SideSwitchBar({required this.buying});

  final bool buying;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);

    return Material(
      color: theme.colorScheme.surface,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 8, AniHowSpace.screen, 8),
          child: Semantics(
            label: s.sideSwitchLabel,
            child: SegmentedButton<bool>(
              key: const ValueKey('side-switch'),
              showSelectedIcon: false,
              style: const ButtonStyle(visualDensity: VisualDensity.compact),
              segments: [
                ButtonSegment<bool>(value: false, label: Text(s.sideSelling)),
                ButtonSegment<bool>(value: true, label: Text(s.sideBuying)),
              ],
              selected: {buying},
              onSelectionChanged: (selection) =>
                  context.read<AuthController>().showSide(buying: selection.first),
            ),
          ),
        ),
      ),
    );
  }
}

/// Shown on the Buying side until the seller sends an ID photo.
class _SendIdBanner extends StatelessWidget {
  const _SendIdBanner();

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);

    return Material(
      key: const ValueKey('side-send-id'),
      color: theme.colorScheme.secondaryContainer,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 10, AniHowSpace.screen, 10),
        child: Row(
          children: [
            Expanded(
              child: Text(
                s.startBuyingIdCheck,
                style: theme.textTheme.bodyMedium?.copyWith(
                  color: theme.colorScheme.onSecondaryContainer,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            const SizedBox(width: 12),
            TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const VerifyIdScreen()),
              ),
              child: Text(s.sendIdPhoto),
            ),
          ],
        ),
      ),
    );
  }
}
