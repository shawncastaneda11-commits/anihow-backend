import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/app_version.dart';
import '../l10n/app_strings.dart';
import '../state/app_availability.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/readable_accent.dart';
import '../widgets/primary_button.dart';

/// Stands in front of the app when ICTD has turned on maintenance or set a
/// minimum version this app is below. Checks when the app opens and each time
/// it comes back to the front; any API answer of 503 or 426 also brings it up.
/// If the check cannot reach the server, the app carries on as usual.
class AppGate extends StatefulWidget {
  const AppGate({super.key, required this.child, this.checkOnStart = true});

  final Widget child;

  /// Off in tests that set [AppAvailability] themselves.
  final bool checkOnStart;

  @override
  State<AppGate> createState() => _AppGateState();
}

class _AppGateState extends State<AppGate> with WidgetsBindingObserver {
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    AppAvailability.instance.addListener(_changed);
    if (widget.checkOnStart) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _check());
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed && widget.checkOnStart) {
      _check();
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    AppAvailability.instance.removeListener(_changed);
    super.dispose();
  }

  void _changed() {
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _check() async {
    if (_checking || !mounted) {
      return;
    }
    setState(() => _checking = true);
    try {
      await context.read<AuthController>().api.refreshAppAvailability();
    } catch (_) {
      // Offline, or a server without this check: carry on as usual.
    } finally {
      if (mounted) {
        setState(() => _checking = false);
      }
    }
  }

  Future<void> _openDownload(String url) async {
    try {
      await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
    } catch (_) {
      // Nothing else to do: the message already says to update.
    }
  }

  @override
  Widget build(BuildContext context) {
    final availability = AppAvailability.instance;
    if (!availability.underMaintenance && !availability.mustUpdate) {
      return widget.child;
    }

    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final maintenance = availability.underMaintenance;
    final downloadUrl = availability.downloadUrl;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    width: 72,
                    height: 72,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: accentTint(context),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(
                      maintenance
                          ? Icons.construction_outlined
                          : Icons.system_update_outlined,
                      size: 36,
                      color: readableAccent(context),
                    ),
                  ),
                  const SizedBox(height: 20),
                  Text(
                    maintenance
                        ? s.t('AniHow is under maintenance', 'Inaayos ang AniHow')
                        : s.t('Update AniHow', 'I-update ang AniHow'),
                    key: const ValueKey('app-gate-title'),
                    textAlign: TextAlign.center,
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Text(
                    maintenance
                        ? availability.maintenanceMessage!
                        : s.t(
                            'This version ($kAppVersion) is no longer supported. Install version ${availability.requiredVersion} or newer to keep using AniHow.',
                            'Hindi na suportado ang bersyong ito ($kAppVersion). Mag-install ng bersyon ${availability.requiredVersion} o mas bago para magamit ang AniHow.',
                          ),
                    textAlign: TextAlign.center,
                    style: TextStyle(color: muted, height: 1.4),
                  ),
                  const SizedBox(height: AniHowSpace.section),
                  if (maintenance)
                    PrimaryButton(
                      label: s.retry,
                      busy: _checking,
                      onPressed: _check,
                    )
                  else if (downloadUrl != null)
                    PrimaryButton(
                      key: const ValueKey('app-gate-download'),
                      label: s.t('Get the update', 'Kunin ang update'),
                      onPressed: () => _openDownload(downloadUrl),
                    )
                  else
                    Text(
                      s.t(
                        "Ask your farm's Content Editor or the AniHow team for the new app.",
                        'Humingi ng bagong app sa Content Editor ng inyong farm o sa AniHow team.',
                      ),
                      key: const ValueKey('app-gate-ask'),
                      textAlign: TextAlign.center,
                      style: TextStyle(color: muted, height: 1.4),
                    ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
