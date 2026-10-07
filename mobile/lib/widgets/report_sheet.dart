import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../services/api_client.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
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
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.reportSubmitted)));
      if (Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
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
    return SafeArea(
      child: Padding(
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
                  for (final reason in s.reportReasons)
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
