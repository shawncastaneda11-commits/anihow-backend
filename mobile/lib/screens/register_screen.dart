import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../widgets/auth_layout.dart';
import '../widgets/form_label.dart';
import '../widgets/password_field.dart';
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
    final s = AppStrings.of(context);

    return AuthLayout(
      leading: IconButton(
        tooltip: s.back,
        onPressed: () => Navigator.of(context).maybePop(),
        icon: const Icon(Icons.arrow_back),
      ),
      form: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            s.createAccount,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: AniHowSpace.section),
          AniHowField(label: s.fullName, child: TextField(controller: _name)),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.email,
            child: TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.phoneOptional,
            child: TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          PasswordField(controller: _password, label: s.password),
          const SizedBox(height: AniHowSpace.fieldGap),
          PasswordField(controller: _confirm, label: s.confirmPassword),
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
          PrimaryButton(label: s.register, busy: _busy, onPressed: _submit),
        ],
      ),
    );
  }
}
