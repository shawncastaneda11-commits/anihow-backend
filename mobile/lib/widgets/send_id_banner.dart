import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../screens/auth/verify_id_screen.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';

/// In the market of a seller who also buys, until they send an ID photo.
class SendIdBanner extends StatelessWidget {
  const SendIdBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final AuthController auth;
    try {
      auth = context.watch<AuthController>();
    } on ProviderNotFoundException {
      return const SizedBox.shrink();
    }
    final idCheck = auth.user?.idVerification;
    final show = auth.user?.alsoBuys == true &&
        auth.buying &&
        idCheck != null &&
        idCheck.blocksOrders &&
        idCheck.status == 'none';
    if (!show) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    const background = Color(0xFFFFF4E0);
    const foreground = Color(0xFF6B4300);

    return Padding(
      key: const ValueKey('side-send-id'),
      padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 0, AniHowSpace.screen, AniHowSpace.cardGap),
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(AniHowSpace.radius),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(14, 12, 8, 6),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                s.startBuyingIdCheck,
                style: const TextStyle(color: foreground, fontWeight: FontWeight.w600, fontSize: 15),
              ),
              const SizedBox(height: 4),
              Text(s.sendIdBannerBody, style: const TextStyle(color: foreground, fontSize: 14, height: 1.4)),
              TextButton(
                style: TextButton.styleFrom(foregroundColor: foreground, padding: EdgeInsets.zero),
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => const VerifyIdScreen()),
                ),
                child: Text(s.sendIdPhoto),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
