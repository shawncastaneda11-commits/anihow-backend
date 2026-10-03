import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../state/preferences_controller.dart';
import '../support/crop_language.dart';
import '../theme/anihow_space.dart';
import '../widgets/auth_layout.dart';
import '../widgets/form_label.dart';
import '../widgets/password_field.dart';
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
    final s = AppStrings.of(context);
    final muted = Theme.of(context).textTheme.bodyMedium?.color?.withValues(alpha: 0.7);

    return AuthLayout(
      form: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            s.signIn,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
          ),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(
            s.welcomeBack,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: muted),
          ),
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: s.email,
            child: TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                prefixIcon: Icon(Icons.mail_outline),
              ),
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          PasswordField(
            controller: _password,
            label: s.password,
            showLockIcon: true,
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
          PrimaryButton(label: s.signIn, busy: _busy, onPressed: _submit),
          const SizedBox(height: AniHowSpace.cardGap),
          TextButton(
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const RegisterScreen()),
              );
            },
            child: Text(s.createBuyerAccount),
          ),
          const SizedBox(height: AniHowSpace.section),
          SegmentedButton<CropLanguage>(
            segments: [
              ButtonSegment(value: CropLanguage.english, label: Text(s.english)),
              ButtonSegment(value: CropLanguage.filipino, label: Text(s.filipinoLabel)),
            ],
            selected: {
              context.watch<PreferencesController>().language == CropLanguage.filipino
                  ? CropLanguage.filipino
                  : CropLanguage.english,
            },
            onSelectionChanged: (value) =>
                context.read<PreferencesController>().setLanguage(value.first),
          ),
        ],
      ),
    );
  }
}
