import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../state/preferences_controller.dart';
import '../support/crop_language.dart';
import '../theme/anihow_space.dart';
import '../theme/readable_accent.dart';
import '../widgets/auth_layout.dart';
import '../widgets/form_label.dart';
import '../widgets/password_field.dart';
import '../widgets/primary_button.dart';
import 'auth/forgot_password_screen.dart';
import 'auth/seller_info_screen.dart';
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
  bool _remember = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    await context.read<AuthController>().login(
      _email.text.trim(),
      _password.text,
      remember: _remember,
    );
    if (mounted) {
      setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthController>();
    final s = AppStrings.of(context);
    final error = auth.sessionEnded ? s.sessionEnded : auth.error;
    final muted = Theme.of(context).textTheme.bodyMedium?.color
        ?.withValues(alpha: 0.7);

    return AuthLayout(
      form: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            s.signIn,
            style: Theme.of(context).textTheme.headlineMedium
                ?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(
            s.welcomeBack,
            style: Theme.of(context).textTheme.bodyMedium
                ?.copyWith(color: muted),
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
          SizedBox(
            key: const Key('forgot-password'),
            height: 48,
            width: double.infinity,
            child: TextButton(
              style: TextButton.styleFrom(
                minimumSize: const Size(48, 48),
                alignment: Alignment.centerLeft,
              ),
              onPressed: _busy
                  ? null
                  : () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const ForgotPasswordScreen(),
                        ),
                      );
                    },
              child: Text(s.forgotPassword),
            ),
          ),
          SizedBox(
            height: 48,
            child: CheckboxListTile(
              value: _remember,
              onChanged: _busy
                  ? null
                  : (value) => setState(() => _remember = value ?? false),
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(s.rememberMe),
              dense: true,
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
          PrimaryButton(label: s.signIn, busy: _busy, onPressed: _submit),
          const SizedBox(height: AniHowSpace.cardGap),
          TextButton(
            onPressed: () {
              Navigator.of(
                context,
              ).push(MaterialPageRoute(builder: (_) => const RegisterScreen()));
            },
            child: Text(s.createBuyerAccount),
          ),
          const SizedBox(height: AniHowSpace.section),
          Row(
            children: [
              const Expanded(child: Divider()),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Text(
                  s.orDivider,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
              const Expanded(child: Divider()),
            ],
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          Material(
            color: Theme.of(context).colorScheme.surfaceContainerHighest.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(14),
            child: InkWell(
              key: const Key('want-to-be-seller'),
              borderRadius: BorderRadius.circular(14),
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const SellerInfoScreen()),
              ),
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 56),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                  child: Row(
                    children: [
                      Icon(
                        Icons.storefront_outlined,
                        color: readableAccent(context),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              s.wantToBeASeller,
                              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: readableAccent(context),
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            Text(
                              s.howFarmersGetAccount,
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.68),
                              ),
                            ),
                          ],
                        ),
                      ),
                      Icon(
                        Icons.chevron_right,
                        color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.6),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          SegmentedButton<CropLanguage>(
            segments: [
              ButtonSegment(
                value: CropLanguage.english,
                label: Text(s.english),
              ),
              ButtonSegment(
                value: CropLanguage.filipino,
                label: Text(s.filipinoLabel),
              ),
            ],
            selected: {
              context.watch<PreferencesController>().language ==
                      CropLanguage.filipino
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
