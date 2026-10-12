import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../models/models.dart';
import '../screens/auth/verify_id_screen.dart';
import '../state/auth_controller.dart';

/// Background and text colour pairs for the ID check states, matched to the
/// approved mockup in light and dark mode.
class IdToneColors {
  const IdToneColors(this.background, this.foreground);

  final Color background;
  final Color foreground;

  static IdToneColors blue(BuildContext context) => _pick(
    context,
    const IdToneColors(Color(0xFFDCE8F6), Color(0xFF1D4E89)),
    const IdToneColors(Color(0xFF1F2E3D), Color(0xFF9CC3EE)),
  );

  static IdToneColors amber(BuildContext context) => _pick(
    context,
    const IdToneColors(Color(0xFFFBEBD2), Color(0xFF7A4B05)),
    const IdToneColors(Color(0xFF3B3222), Color(0xFFF2C27B)),
  );

  static IdToneColors red(BuildContext context) => _pick(
    context,
    const IdToneColors(Color(0xFFF8DEDB), Color(0xFF9B2C21)),
    const IdToneColors(Color(0xFF3A2222), Color(0xFFF2A7A7)),
  );

  static IdToneColors green(BuildContext context) => _pick(
    context,
    const IdToneColors(Color(0xFFDCEFE3), Color(0xFF1F5A3E)),
    const IdToneColors(Color(0xFF23392D), Color(0xFFA8D5BA)),
  );

  static IdToneColors _pick(
    BuildContext context,
    IdToneColors light,
    IdToneColors dark,
  ) => Theme.of(context).brightness == Brightness.dark ? dark : light;
}

String _longDate(AppStrings s, DateTime? date) {
  if (date == null) {
    return '';
  }
  final local = date.toLocal();
  return '${s.shortDate(local)}, ${local.year}';
}

Future<void> openVerifyId(BuildContext context) {
  return Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => const VerifyIdScreen()),
  );
}

/// Small green "ID verified" chip shown next to a buyer's name.
class IdVerifiedBadge extends StatelessWidget {
  const IdVerifiedBadge({super.key});

  @override
  Widget build(BuildContext context) {
    final tone = IdToneColors.green(context);
    return Container(
      key: const ValueKey('id-verified-badge'),
      height: 24,
      padding: const EdgeInsets.symmetric(horizontal: 8),
      decoration: BoxDecoration(
        color: tone.background,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.verified_user, size: 14, color: tone.foreground),
          const SizedBox(width: 4),
          Text(
            AppStrings.of(context).idVerifiedBadge,
            style: TextStyle(
              color: tone.foreground,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

/// The profile card that says where the buyer is with the ID check.
class IdVerificationCard extends StatelessWidget {
  const IdVerificationCard({super.key, required this.info});

  final IdVerificationInfo info;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final (tone, title, body, action) = info.verified
        ? (
            IdToneColors.green(context),
            s.yourIdIsVerified,
            s.idVerifiedOn(_longDate(s, info.reviewedAt)),
            null,
          )
        : info.isPending
        ? (
            IdToneColors.amber(context),
            s.checkingYourId,
            s.idSubmittedOn(_longDate(s, info.submittedAt)),
            null,
          )
        : info.isRejected
        ? (
            IdToneColors.red(context),
            s.couldNotVerifyId,
            s.idRejectedReason(info.rejectionReason ?? ''),
            s.tryIdAgain,
          )
        : info.required
        ? (
            IdToneColors.blue(context),
            s.verifyIdToOrder,
            s.verifyIdToOrderBody,
            s.verifyMyId,
          )
        : (
            IdToneColors.blue(context),
            s.getVerifiedBadge,
            s.getVerifiedBadgeBody,
            s.verifyMyId,
          );

    return Semantics(
      container: true,
      child: Container(
        key: ValueKey('id-card-${info.verified ? 'approved' : info.status}'),
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: tone.background,
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.verified_user_outlined, color: tone.foreground),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: TextStyle(
                          color: tone.foreground,
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        body,
                        style: TextStyle(
                          color: tone.foreground,
                          fontSize: 13.5,
                          height: 1.45,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if (action != null) ...[
              const SizedBox(height: 12),
              FilledButton(
                key: const ValueKey('id-card-action'),
                onPressed: () => openVerifyId(context),
                style: FilledButton.styleFrom(
                  backgroundColor: tone.foreground,
                  foregroundColor: tone.background,
                  minimumSize: const Size.fromHeight(44),
                ),
                child: Text(action),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

/// Shown at checkout when the buyer must verify before ordering. The cart is
/// kept; only placing the order waits.
class IdOrderGate extends StatelessWidget {
  const IdOrderGate({super.key, required this.info});

  final IdVerificationInfo info;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final tone = IdToneColors.amber(context);
    final accent = Theme.of(context).colorScheme.primary;
    return Column(
      key: const ValueKey('id-order-gate'),
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: tone.background,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(Icons.info_outline, size: 20, color: tone.foreground),
              const SizedBox(width: 10),
              Expanded(
                child: Text.rich(
                  TextSpan(
                    style: TextStyle(
                      color: tone.foreground,
                      fontSize: 13.5,
                      height: 1.45,
                    ),
                    children: [
                      TextSpan(
                        text: info.isPending
                            ? s.waitingForIdReview
                            : s.verifyIdToOrder,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      const TextSpan(text: '\n'),
                      TextSpan(
                        text: info.isPending
                            ? s.cartSavedUntilApproved
                            : s.cartIsSaved,
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
        if (!info.isPending) ...[
          const SizedBox(height: 10),
          OutlinedButton(
            key: const ValueKey('id-order-gate-verify'),
            onPressed: () => openVerifyId(context),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size.fromHeight(44),
              side: BorderSide(color: accent, width: 1.5),
            ),
            child: Text(s.verifyMyId),
          ),
        ],
      ],
    );
  }
}

/// Asks the buyer to verify before reserving. Returns true when they may go
/// ahead (no check needed).
Future<bool> ensureIdForOrdering(BuildContext context) async {
  final info = context.read<AuthController>().user?.idVerification;
  if (info == null || !info.blocksOrders) {
    return true;
  }
  final s = AppStrings.read(context);
  final verify = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(info.isPending ? s.waitingForIdReview : s.verifyIdToOrder),
      content: Text(info.isPending ? s.reserveWaitsForId : s.reserveNeedsId),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(false),
          child: Text(info.isPending ? s.ok : s.notNow),
        ),
        if (!info.isPending)
          FilledButton(
            key: const ValueKey('id-dialog-verify'),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: Text(s.verifyMyId),
          ),
      ],
    ),
  );
  if (verify == true && context.mounted) {
    await openVerifyId(context);
  }
  return false;
}
