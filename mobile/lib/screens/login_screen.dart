import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../widgets/app_header.dart';
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

    return Scaffold(
      body: Column(
        children: [
          const AppHeader(
            title: 'AniHow',
            subtitle: "Farmers' market hub",
            brandMark: true,
          ),
          Expanded(
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                AniHowField(
                  label: 'Email',
                  child: TextField(
                    controller: _email,
                    keyboardType: TextInputType.emailAddress,
                  ),
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(
                  label: 'Password',
                  child: TextField(
                    controller: _password,
                    obscureText: _hidePassword,
                    decoration: InputDecoration(
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
                  Text(error, style: const TextStyle(color: AniHowColors.fruit, fontSize: AniHowSpace.body)),
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
          ),
        ],
      ),
    );
  }
}
