import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../screens/profile/verify_email_screen.dart';
import '../state/auth_controller.dart';

/// Shared dismiss flag so the banner stays hidden across buyer tabs.
class VerifyBannerScope extends InheritedWidget {
  const VerifyBannerScope({
    super.key,
    required this.hidden,
    required this.hide,
    required super.child,
  });

  final bool hidden;
  final VoidCallback hide;

  static VerifyBannerScope? maybeOf(BuildContext context) {
    return context.dependOnInheritedWidgetOfExactType<VerifyBannerScope>();
  }

  @override
  bool updateShouldNotify(VerifyBannerScope oldWidget) => hidden != oldWidget.hidden;
}

/// Sits in the document flow under the AppBar or [AppHeader], never as an overlay.
class UnverifiedEmailBanner extends StatefulWidget {
  const UnverifiedEmailBanner({super.key});

  static const Key bannerKey = Key('unverified-email-banner');

  @override
  State<UnverifiedEmailBanner> createState() => _UnverifiedEmailBannerState();
}

class _UnverifiedEmailBannerState extends State<UnverifiedEmailBanner> {
  bool _hiddenLocally = false;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final scope = VerifyBannerScope.maybeOf(context);

    if (auth.user?.isVerified != false || _hiddenLocally || (scope?.hidden ?? false)) {
      return const SizedBox.shrink();
    }

    final s = AppStrings.of(context);

    return MaterialBanner(
      key: UnverifiedEmailBanner.bannerKey,
      content: Text(s.verifyBanner),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => const VerifyEmailScreen()),
          ),
          child: Text(s.verifyNow),
        ),
        TextButton(
          onPressed: () {
            if (scope != null) {
              scope.hide();
              return;
            }
            setState(() => _hiddenLocally = true);
          },
          child: Text(s.ok),
        ),
      ],
    );
  }
}
