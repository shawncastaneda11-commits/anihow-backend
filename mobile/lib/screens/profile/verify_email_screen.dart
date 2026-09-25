import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/status_pill.dart';

class VerifyEmailScreen extends StatefulWidget {
  const VerifyEmailScreen({super.key});

  @override
  State<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends State<VerifyEmailScreen> {
  final _code = TextEditingController();
  bool _busy = false;
  int _cooldownSeconds = 0;
  Timer? _cooldown;

  @override
  void initState() {
    super.initState();
    _code.addListener(_onCodeChanged);
    final existing = context.read<AuthController>().pendingVerificationCode;
    if (existing != null && existing.length == 6) {
      _code.text = existing;
    }
  }

  @override
  void dispose() {
    _cooldown?.cancel();
    _code.removeListener(_onCodeChanged);
    _code.dispose();
    super.dispose();
  }

  void _onCodeChanged() {
    if (mounted) {
      setState(() {});
    }
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

  Future<void> _verify() async {
    final messenger = ScaffoldMessenger.of(context);
    final navigator = Navigator.of(context);
    final auth = context.read<AuthController>();
    setState(() => _busy = true);
    try {
      await auth.api.verifyEmail(_code.text.trim());
      if (!mounted) {
        return;
      }
      await auth.refreshUser();
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(AppStrings.read(context).emailVerified)));
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

  Future<void> _resend() async {
    if (_busy || _cooldownSeconds > 0) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final auth = context.read<AuthController>();
    setState(() => _busy = true);
    try {
      final code = await auth.api.resendVerification();
      if (!mounted) {
        return;
      }
      if (code != null && code.length == 6) {
        auth.rememberVerificationCode(code);
        _code.text = code;
      }
      _startCooldown();
      messenger.showSnackBar(
        SnackBar(
          content: Text(
            code == null
                ? AppStrings.read(context).verificationSent
                : AppStrings.read(context).verificationLocal,
          ),
        ),
      );
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
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);
    final user = auth.user;
    final shownCode = auth.pendingVerificationCode;
    final muted = Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7);
    final canResend = !_busy && _cooldownSeconds == 0;
    final canVerify = !_busy && _code.text.trim().length == 6;

    return Scaffold(
      appBar: AppBar(title: Text(s.verifyEmail)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          if (user != null) ...[
            Row(
              children: [
                Expanded(
                  child: Text(user.email, style: Theme.of(context).textTheme.titleMedium),
                ),
                StatusPill(
                  label: user.isVerified ? s.verified : s.unverified,
                  color: user.isVerified ? AniHowColors.ready : AniHowColors.pending,
                ),
              ],
            ),
            const SizedBox(height: AniHowSpace.cardGap),
          ],
          Text(
            shownCode == null ? s.verifyHintEmail : s.verifyHintLocal,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: muted),
          ),
          if (shownCode != null) ...[
            const SizedBox(height: AniHowSpace.section),
            Text(s.yourCode, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.labelGap),
            Text(
              shownCode,
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                    letterSpacing: 4,
                  ),
            ),
          ],
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: s.code,
            child: TextField(
              controller: _code,
              keyboardType: TextInputType.number,
              maxLength: 6,
              enabled: !_busy,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              decoration: const InputDecoration(
                hintText: '000000',
                counterText: '',
              ),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(
            label: s.verify,
            busy: _busy,
            onPressed: canVerify ? _verify : null,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          TextButton(
            onPressed: canResend ? _resend : null,
            child: Text(
              _cooldownSeconds > 0
                  ? s.resendCodeIn(_cooldownSeconds)
                  : s.resendCode,
            ),
          ),
        ],
      ),
    );
  }
}
