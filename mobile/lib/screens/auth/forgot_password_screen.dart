import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/password_field.dart';
import '../../widgets/primary_button.dart';
import '../profile/verify_email_screen.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.client});

  final ApiClient? client;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _email = TextEditingController();
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _busy = false;
  bool _codeSent = false;
  String? _error;
  int _cooldownSeconds = 0;
  Timer? _cooldown;

  @override
  void initState() {
    super.initState();
    _code.addListener(_rebuild);
    _password.addListener(_rebuild);
    _confirmation.addListener(_rebuild);
  }

  @override
  void dispose() {
    _cooldown?.cancel();
    _code.removeListener(_rebuild);
    _password.removeListener(_rebuild);
    _confirmation.removeListener(_rebuild);
    _email.dispose();
    _code.dispose();
    _password.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  void _rebuild() {
    if (mounted) {
      setState(() {});
    }
  }

  ApiClient _client() {
    return widget.client ?? context.read<AuthController>().api;
  }

  void _startCooldown() {
    _cooldown?.cancel();
    setState(() => _cooldownSeconds = 30);
    _cooldown = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }
      setState(() {
        _cooldownSeconds -= 1;
        if (_cooldownSeconds <= 0) {
          timer.cancel();
          _cooldownSeconds = 0;
        }
      });
    });
  }

  Future<void> _sendCode() async {
    final email = _email.text.trim();
    if (_busy || email.isEmpty) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client().forgotPassword(email);
      if (!mounted) {
        return;
      }
      setState(() => _codeSent = true);
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

  Future<void> _resend() async {
    if (_busy || _cooldownSeconds > 0) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _busy = true);
    try {
      await _client().forgotPassword(_email.text.trim());
      if (!mounted) {
        return;
      }
      _startCooldown();
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

  Future<void> _reset() async {
    final s = AppStrings.of(context);
    if (_password.text != _confirmation.text) {
      setState(() => _error = s.passwordsDoNotMatch);
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client().resetPassword(
        _email.text.trim(),
        _code.text.trim(),
        _password.text,
        _confirmation.text,
      );
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(s.passwordResetDone)));
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
    final muted = Theme.of(context).textTheme.bodyMedium?.color
        ?.withValues(alpha: 0.7);
    final canResend = !_busy && _cooldownSeconds == 0;
    final canReset =
        !_busy &&
        _code.text.trim().length == 6 &&
        _password.text.isNotEmpty &&
        _confirmation.text.isNotEmpty;

    return Scaffold(
      appBar: AppBar(title: Text(s.forgotPassword)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          if (!_codeSent) ...[
            Text(
              s.forgotPasswordHint,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: muted),
            ),
            const SizedBox(height: AniHowSpace.section),
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              enabled: !_busy,
              decoration: InputDecoration(labelText: s.email),
            ),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(label: s.sendCode, busy: _busy, onPressed: _sendCode),
          ] else ...[
            Text(
              s.resetPasswordHint,
              style: Theme.of(context).textTheme.bodyMedium
                  ?.copyWith(color: muted),
            ),
            const SizedBox(height: AniHowSpace.section),
            VerificationCodeField(
              controller: _code,
              label: s.code,
              enabled: !_busy,
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PasswordField(
              controller: _password,
              label: s.newPassword,
              enabled: !_busy,
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            PasswordField(
              controller: _confirmation,
              label: s.confirmNewPassword,
              enabled: !_busy,
            ),
            if (_error != null) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(
              label: s.resetPassword,
              busy: _busy,
              onPressed: canReset ? _reset : null,
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            SizedBox(
              height: 48,
              child: TextButton(
                onPressed: canResend ? _resend : null,
                child: Text(
                  _cooldownSeconds > 0
                      ? s.resendCodeIn(_cooldownSeconds)
                      : s.resendCode,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}
