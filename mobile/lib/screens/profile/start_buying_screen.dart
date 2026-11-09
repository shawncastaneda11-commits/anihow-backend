import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/primary_button.dart';

/// What changes when a farmer-seller turns on buying, then the switch.
class StartBuyingScreen extends StatefulWidget {
  const StartBuyingScreen({super.key});

  @override
  State<StartBuyingScreen> createState() => _StartBuyingScreenState();
}

class _StartBuyingScreenState extends State<StartBuyingScreen> {
  bool _busy = false;
  bool _done = false;

  Future<void> _turnOn() async {
    setState(() => _busy = true);
    final auth = context.read<AuthController>();
    final ok = await auth.startBuying();
    if (!mounted) {
      return;
    }
    setState(() {
      _busy = false;
      _done = ok;
    });
    if (!ok && auth.error != null) {
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(auth.error!)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);

    if (_done) {
      return Scaffold(
        appBar: AppBar(title: Text(s.startBuying)),
        body: ListView(
          padding: AniHowSpace.screenPadding,
          children: [
            Icon(Icons.check_circle_outline, size: 48, color: theme.colorScheme.primary),
            const SizedBox(height: 12),
            Text(s.buyingIsOn, style: theme.textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(s.buyingIsOnBody, style: theme.textTheme.bodyMedium),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(
              key: const ValueKey('go-to-buying'),
              label: s.goToBuying,
              onPressed: () {
                context.read<AuthController>().showSide(buying: true);
                Navigator.of(context).popUntil((route) => route.isFirst);
              },
            ),
          ],
        ),
      );
    }

    final points = [
      (Icons.swap_horiz, s.startBuyingOneLogin, s.startBuyingOneLoginBody),
      (Icons.badge_outlined, s.startBuyingIdCheck, s.startBuyingIdCheckBody),
      (Icons.storefront_outlined, s.startBuyingNotOwn, s.startBuyingNotOwnBody),
      (Icons.receipt_long_outlined, s.startBuyingSeparate, s.startBuyingSeparateBody),
    ];

    return Scaffold(
      appBar: AppBar(title: Text(s.startBuying)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(s.startBuyingIntro, style: theme.textTheme.bodyLarge),
          const SizedBox(height: AniHowSpace.section),
          for (final (icon, title, body) in points)
            Padding(
              padding: const EdgeInsets.only(bottom: AniHowSpace.fieldGap),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(icon, color: theme.colorScheme.primary),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(title, style: theme.textTheme.titleSmall),
                        const SizedBox(height: 2),
                        Text(body, style: theme.textTheme.bodyMedium),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          Text(
            s.startBuyingSuspend,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(
            key: const ValueKey('turn-on-buying'),
            label: s.turnOnBuying,
            busy: _busy,
            onPressed: _busy ? null : _turnOn,
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: Text(s.notNow),
          ),
        ],
      ),
    );
  }
}
