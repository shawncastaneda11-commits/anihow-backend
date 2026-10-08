import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/password_field.dart';
import '../../widgets/primary_button.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key, this.forced = false});

  final bool forced;

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _next = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _current.dispose();
    _next.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final auth = context.read<AuthController>();
    setState(() => _busy = true);
    try {
      await auth.api.changePassword(
        currentPassword: _current.text,
        password: _next.text,
        passwordConfirmation: _confirm.text,
      );
      if (!mounted) {
        return;
      }
      if (widget.forced) {
        await auth.refreshUser();
        return;
      }
      messenger.showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).passwordUpdated)),
      );
      navigator.pop();
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return PopScope(
      canPop: !widget.forced,
      child: Scaffold(
        appBar: AppBar(
          automaticallyImplyLeading: !widget.forced,
          title: Text(widget.forced ? s.setYourOwnPassword : s.changePassword),
        ),
        body: ListView(
          padding: AniHowSpace.screenPadding,
          children: [
            Text(
              widget.forced
                  ? s.temporaryPasswordExplanation
                  : s.changePasswordHint,
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: AniHowSpace.section),
            PasswordField(
              controller: _current,
              label: s.currentPassword,
              enabled: !_busy,
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PasswordField(
              controller: _next,
              label: s.newPassword,
              enabled: !_busy,
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PasswordField(
              controller: _confirm,
              label: s.confirmNewPassword,
              enabled: !_busy,
            ),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(label: s.savePassword, busy: _busy, onPressed: _save),
            if (widget.forced) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              TextButton(
                onPressed: _busy
                    ? null
                    : () => context.read<AuthController>().logout(),
                child: Text(s.signOut),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
