import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../widgets/auth_layout.dart';
import '../widgets/form_label.dart';
import '../widgets/primary_button.dart';
import 'register_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  bool _busy = false;
  bool _hidePassword = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    await context.read<AuthController>().login(_email.text.trim(), _password.text);
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final error = context.watch<AuthController>().error;
    final muted = Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7);

    return AuthLayout(
      form: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            'Sign in',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(
            'Welcome back',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: muted),
          ),
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: 'Email',
            child: TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.mail_outline),
              ),
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Password',
            child: TextField(
              controller: _password,
              obscureText: _hidePassword,
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.lock_outline),
                suffixIcon: IconButton(
                  tooltip: _hidePassword ? 'Show password' : 'Hide password',
                  onPressed: () => setState(() => _hidePassword = !_hidePassword),
                  icon: Icon(
                    _hidePassword ? Icons.visibility_outlined : Icons.visibility_off_outlined,
                  ),
                ),
              ),
            ),
          ),
          if (error != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              error,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: AniHowSpace.body,
              ),
            ),
          ],
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: 'Sign in', busy: _busy, onPressed: _submit),
          const SizedBox(height: AniHowSpace.cardGap),
          TextButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const RegisterScreen()),
              );
            },
            child: const Text('Create a buyer account'),
          ),
        ],
      ),
    );
  }
}
