import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/password_field.dart';
import '../../widgets/password_rules.dart';
import '../../widgets/primary_button.dart';
import '../profile/verify_email_screen.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.client});

  final ApiClient? client;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

enum _ResetStep { email, code, password }

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _email = TextEditingController();
  final _code = TextEditingController();
  final _password = TextEditingController();
  final _confirmation = TextEditingController();
  bool _busy = false;
  _ResetStep _step = _ResetStep.email;
  String? _emailError;
  String? _codeError;
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

  /// The server's message for one field, else its general message.
  String _fieldError(ApiException error, String field) {
    final errors = error.body?['errors'];
    if (errors is Map &&
        errors[field] is List &&
        (errors[field] as List).isNotEmpty) {
      return '${(errors[field] as List).first}';
    }
    return error.message;
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
    final s = AppStrings.of(context);
    final email = _email.text.trim();
    if (_busy) {
      return;
    }
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      setState(() => _emailError = s.enterValidEmail);
      return;
    }
    setState(() {
      _busy = true;
      _emailError = null;
    });
    try {
      await _client().forgotPassword(email);
      if (!mounted) {
        return;
      }
      _code.clear();
      setState(() {
        _step = _ResetStep.code;
        _codeError = null;
      });
      _startCooldown();
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _emailError = _fieldError(error, 'email'));
      }
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
    setState(() {
      _busy = true;
      _codeError = null;
    });
    try {
      await _client().forgotPassword(_email.text.trim());
      if (!mounted) {
        return;
      }
      _startCooldown();
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _codeError = _fieldError(error, 'email'));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _checkCode() async {
    if (_busy || _code.text.trim().length != 6) {
      return;
    }
    setState(() {
      _busy = true;
      _codeError = null;
    });
    try {
      await _client().verifyResetCode(_email.text.trim(), _code.text.trim());
      if (mounted) {
        setState(() {
          _step = _ResetStep.password;
          _error = null;
        });
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _codeError = _fieldError(error, 'code'));
      }
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
      final errors = error.body?['errors'];
      if (errors is Map && errors.containsKey('code')) {
        // The code ran out while they typed: back to the code step.
        setState(() {
          _step = _ResetStep.code;
          _codeError = _fieldError(error, 'code');
        });
      } else {
        setState(() => _error = _fieldError(error, 'password'));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  void _back() {
    setState(() {
      _step = _step == _ResetStep.password ? _ResetStep.code : _ResetStep.email;
      _codeError = null;
      _error = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7);
    final hintStyle = theme.textTheme.bodyMedium?.copyWith(color: muted);
    final errorStyle = TextStyle(color: theme.colorScheme.error);
    final canResend = !_busy && _cooldownSeconds == 0;
    final canReset =
        !_busy && _password.text.isNotEmpty && _confirmation.text.isNotEmpty;
    final stepNumber = _ResetStep.values.indexOf(_step) + 1;

    return PopScope(
      canPop: _step == _ResetStep.email,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) {
          _back();
        }
      },
      child: Scaffold(
        appBar: AppBar(title: Text(s.forgotPassword)),
        body: ListView(
          padding: AniHowSpace.screenPadding,
          children: [
            Text(
              s.resetStepOf(stepNumber, 3),
              key: const ValueKey('reset-step'),
              style: theme.textTheme.labelLarge?.copyWith(color: muted),
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            if (_step == _ResetStep.email) ...[
              Text(s.forgotPasswordHint, style: hintStyle),
              const SizedBox(height: AniHowSpace.section),
              TextField(
                key: const ValueKey('reset-email'),
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                enabled: !_busy,
                onChanged: (_) {
                  if (_emailError != null) {
                    setState(() => _emailError = null);
                  }
                },
                decoration: InputDecoration(
                  labelText: s.email,
                  errorText: _emailError,
                  errorMaxLines: 3,
                ),
              ),
              const SizedBox(height: AniHowSpace.section),
              PrimaryButton(
                label: s.sendCode,
                busy: _busy,
                onPressed: _sendCode,
              ),
            ],
            if (_step == _ResetStep.code) ...[
              Text(s.resetCodeSentTo(_email.text.trim()), style: hintStyle),
              const SizedBox(height: AniHowSpace.section),
              VerificationCodeField(
                controller: _code,
                label: s.code,
                enabled: !_busy,
              ),
              if (_codeError != null) ...[
                const SizedBox(height: AniHowSpace.labelGap),
                Text(
                  _codeError!,
                  key: const ValueKey('reset-code-error'),
                  style: errorStyle,
                ),
              ],
              const SizedBox(height: AniHowSpace.section),
              PrimaryButton(
                label: s.verify,
                busy: _busy,
                onPressed: _code.text.trim().length == 6 ? _checkCode : null,
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
              SizedBox(
                height: 48,
                child: TextButton(
                  key: const ValueKey('reset-other-email'),
                  onPressed: _busy ? null : _back,
                  child: Text(s.useAnotherEmail),
                ),
              ),
            ],
            if (_step == _ResetStep.password) ...[
              Text(s.chooseNewPasswordHint, style: hintStyle),
              const SizedBox(height: AniHowSpace.section),
              PasswordField(
                controller: _password,
                label: s.newPassword,
                enabled: !_busy,
              ),
              const SizedBox(height: 8),
              ListenableBuilder(
                listenable: _password,
                builder: (context, _) =>
                    PasswordRulesChecklist(password: _password.text),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              PasswordField(
                controller: _confirmation,
                label: s.confirmNewPassword,
                enabled: !_busy,
              ),
              if (_error != null) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(_error!, style: errorStyle),
              ],
              const SizedBox(height: AniHowSpace.section),
              PrimaryButton(
                label: s.resetPassword,
                busy: _busy,
                onPressed: canReset ? _reset : null,
              ),
            ],
          ],
        ),
      ),
    );
  }
}
