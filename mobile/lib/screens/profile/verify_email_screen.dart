import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

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
    _requestCodeOnOpen();
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

  Future<void> _requestCodeOnOpen() async {
    final auth = context.read<AuthController>();
    if (auth.user?.isVerified == true) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final api = auth.api;
    setState(() => _busy = true);
    try {
      await api.resendVerification();
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
      messenger.showSnackBar(const SnackBar(content: Text('Email verified.')));
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
    final api = context.read<AuthController>().api;
    setState(() => _busy = true);
    try {
      await api.resendVerification();
      if (!mounted) {
        return;
      }
      _startCooldown();
      messenger.showSnackBar(
        const SnackBar(content: Text('Verification code sent to your email.')),
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
    final user = context.watch<AuthController>().user;
    final muted = Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7);
    final canResend = !_busy && _cooldownSeconds == 0;
    final canVerify = !_busy && _code.text.trim().length == 6;

    return Scaffold(
      appBar: AppBar(title: const Text('Verify Email')),
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
                  label: user.isVerified ? 'Verified' : 'Unverified',
                  color: user.isVerified ? AniHowColors.ready : AniHowColors.pending,
                ),
              ],
            ),
            const SizedBox(height: AniHowSpace.cardGap),
          ],
          Text(
            'Enter the 6-digit code we sent to your email.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: muted),
          ),
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: 'Code',
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
            label: 'Verify',
            busy: _busy,
            onPressed: canVerify ? _verify : null,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          TextButton(
            onPressed: canResend ? _resend : null,
            child: Text(
              _cooldownSeconds > 0
                  ? 'Resend code in ${_cooldownSeconds}s'
                  : 'Resend code',
            ),
          ),
        ],
      ),
    );
  }
}
