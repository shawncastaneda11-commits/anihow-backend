import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/readable_accent.dart';
import 'primary_button.dart';

Future<void> showReportSheet(
  BuildContext context, {
  required String targetType,
  required int targetId,
  Future<void> Function(String reason, String? details)? submit,
}) {
  return showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    builder: (sheetContext) {
      return Padding(
        padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(sheetContext).bottom),
        child: ReportBottomSheet(
          targetType: targetType,
          targetId: targetId,
          submit: submit,
        ),
      );
    },
  );
}

class ReportBottomSheet extends StatefulWidget {
  const ReportBottomSheet({
    super.key,
    required this.targetType,
    required this.targetId,
    this.submit,
  });

  final String targetType;
  final int targetId;
  final Future<void> Function(String reason, String? details)? submit;

  @override
  State<ReportBottomSheet> createState() => _ReportBottomSheetState();
}

class _ReportBottomSheetState extends State<ReportBottomSheet> {
  final _details = TextEditingController();
  String? _reason;
  String? _reasonError;
  String? _submitError;
  bool _sent = false;
  bool _sending = false;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final s = AppStrings.read(context);
    if (_reason == null) {
      setState(() => _reasonError = s.reportChooseReason);
      return;
    }
    if (_sending) {
      return;
    }
    setState(() {
      _sending = true;
      _reasonError = null;
      _submitError = null;
    });
    final details = _details.text.trim();
    try {
      final submit = widget.submit ??
          ((reason, note) => context.read<AuthController>().api.submitReport(
                targetType: widget.targetType,
                targetId: widget.targetId,
                reason: reason,
                details: note,
              ));
      await submit(_reason!, details.isEmpty ? null : details);
      if (!mounted) {
        return;
      }
      // Shown inside the sheet: a snackbar fired while the sheet closes is
      // easy to miss behind it or under the bottom bar.
      setState(() => _sent = true);
    } on ApiException catch (error) {
      // A snackbar would sit behind this sheet, so the error shows inside it.
      if (mounted) {
        setState(() => _submitError = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _sending = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    if (_sent) {
      return _ReportSent(
        onDone: () {
          if (Navigator.of(context).canPop()) {
            Navigator.of(context).pop();
          }
        },
      );
    }
    // Scrolls so Submit stays reachable on a small phone with the keyboard up.
    return SafeArea(
      child: SingleChildScrollView(
        padding: AniHowSpace.screenPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.report, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.cardGap),
            RadioGroup<String>(
              groupValue: _reason,
              onChanged: (value) {
                if (_sending) {
                  return;
                }
                setState(() {
                  _reason = value;
                  _reasonError = null;
                });
              },
              child: Column(
                children: [
                  for (final reason in widget.targetType == 'order'
                      ? [(value: 'payment_problem', label: s.paymentProblem)]
                      : s.reportReasons)
                    RadioListTile<String>(
                      key: ValueKey('report-reason-${reason.value}'),
                      title: Text(reason.label),
                      value: reason.value,
                      contentPadding: EdgeInsets.zero,
                      visualDensity: VisualDensity.compact,
                      selected: _reason == reason.value,
                    ),
                ],
              ),
            ),
            if (_reasonError != null)
              Padding(
                padding: const EdgeInsets.only(bottom: AniHowSpace.labelGap),
                child: Text(
                  _reasonError!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            TextField(
              controller: _details,
              maxLength: 500,
              maxLines: 3,
              enabled: !_sending,
              decoration: InputDecoration(labelText: s.reportDetails),
            ),
            if (_submitError != null)
              Padding(
                padding: const EdgeInsets.only(top: AniHowSpace.labelGap),
                child: Text(
                  _submitError!,
                  key: const ValueKey('report-error'),
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PrimaryButton(
              key: const ValueKey('report-submit'),
              label: s.submitReport,
              onPressed: _sending ? null : _submit,
              busy: _sending,
            ),
          ],
        ),
      ),
    );
  }
}

/// The sheet's own confirmation after a report goes through.
class _ReportSent extends StatelessWidget {
  const _ReportSent({required this.onDone});

  final VoidCallback onDone;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    return SafeArea(
      child: Padding(
        key: const ValueKey('report-sent'),
        padding: AniHowSpace.screenPadding,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const SizedBox(height: AniHowSpace.cardGap),
            Icon(Icons.check_circle, size: 48, color: readableAccent(context)),
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              s.reportSentTitle,
              textAlign: TextAlign.center,
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            Text(s.reportSubmitted, textAlign: TextAlign.center),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(
              key: const ValueKey('report-done'),
              label: s.done,
              onPressed: onDone,
            ),
          ],
        ),
      ),
    );
  }
}
