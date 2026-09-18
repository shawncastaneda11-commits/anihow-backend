import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../widgets/form_label.dart';
import '../widgets/primary_button.dart';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _busy = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    final ok = await context.read<AuthController>().register(
          name: _name.text.trim(),
          email: _email.text.trim(),
          password: _password.text,
          passwordConfirmation: _confirm.text,
          phone: _phone.text.trim(),
        );
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (ok) {
      Navigator.of(context).pop();
    }
  }

  @override
  Widget build(BuildContext context) {
    final error = context.watch<AuthController>().error;

    return Scaffold(
      appBar: AppBar(title: const Text('Buyer registration')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowField(label: 'Full name', child: TextField(controller: _name)),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Email',
            child: TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Phone (optional)',
            child: TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Password',
            child: TextField(controller: _password, obscureText: true),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: 'Confirm password',
            child: TextField(controller: _confirm, obscureText: true),
          ),
          if (error != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text(error, style: const TextStyle(color: AniHowColors.fruit, fontSize: AniHowSpace.body)),
          ],
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: 'Register', busy: _busy, onPressed: _submit),
        ],
      ),
    );
  }
}
