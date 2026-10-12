import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../l10n/app_strings.dart';
import '../state/auth_controller.dart';
import '../theme/anihow_space.dart';
import '../theme/anihow_theme.dart';
import '../theme/readable_accent.dart';
import '../widgets/anihow_logo.dart';
import '../widgets/form_label.dart';
import '../widgets/password_field.dart';
import '../widgets/password_rules.dart';
import '../widgets/primary_button.dart';
import 'auth/terms_screen.dart';

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
  late final TapGestureRecognizer _termsTap;
  late final TapGestureRecognizer _privacyTap;
  bool _agreed = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    for (final controller in [_name, _email, _password, _confirm]) {
      controller.addListener(_refresh);
    }
    _termsTap = TapGestureRecognizer()..onTap = () => _openTerms(0);
    _privacyTap = TapGestureRecognizer()..onTap = () => _openTerms(1);
  }

  void _refresh() => setState(() {});

  void _openTerms(int tab) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => TermsScreen(initialTab: tab)),
    );
  }

  @override
  void dispose() {
    _termsTap.dispose();
    _privacyTap.dispose();
    _name.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  bool get _passwordsMatch =>
      _confirm.text.isNotEmpty && _confirm.text == _password.text;

  List<String> _missing(AppStrings s) => [
    if (_name.text.trim().isEmpty) s.needName,
    if (_email.text.trim().isEmpty) s.needEmail,
    if (!PasswordRules.allMet(_password.text)) s.needStrongerPassword,
    if (!_passwordsMatch) s.needMatchingPasswords,
    if (!_agreed) s.needTerms,
  ];

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
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final sheet =
        theme.cardTheme.color ??
        (dark ? AniHowColors.darkCard : AniHowColors.card);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final good = dark ? const Color(0xFF7FD3A0) : const Color(0xFF1F7A45);
    final accent = readableAccent(context);
    final missing = _missing(s);
    final linkStyle = TextStyle(color: accent, fontWeight: FontWeight.w700);

    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        // The card colour, not green: space under the form (for example
        // when the keyboard is open) must not show a green band.
        backgroundColor: sheet,
        body: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              ColoredBox(
                color: AniHowColors.brand,
                child: _Header(onBack: () => Navigator.of(context).maybePop()),
              ),
              ColoredBox(
                color: AniHowColors.brand,
                child: Material(
                  color: sheet,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(24),
                  ),
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(
                      AniHowSpace.screen,
                      22,
                      AniHowSpace.screen,
                      24 + MediaQuery.paddingOf(context).bottom,
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        AniHowField(
                          label: s.fullName,
                          child: TextField(
                            key: const ValueKey('register-name'),
                            controller: _name,
                            textCapitalization: TextCapitalization.words,
                            autofillHints: const [AutofillHints.name],
                            decoration: const InputDecoration(
                              prefixIcon: Icon(Icons.person_outline),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        AniHowField(
                          label: s.email,
                          child: TextField(
                            key: const ValueKey('register-email'),
                            controller: _email,
                            keyboardType: TextInputType.emailAddress,
                            autofillHints: const [AutofillHints.email],
                            decoration: const InputDecoration(
                              prefixIcon: Icon(Icons.mail_outline),
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        AniHowField(
                          label: s.phoneOptional,
                          child: TextField(
                            key: const ValueKey('register-phone'),
                            controller: _phone,
                            keyboardType: TextInputType.phone,
                            autofillHints: const [
                              AutofillHints.telephoneNumber,
                            ],
                            decoration: const InputDecoration(
                              prefixIcon: Icon(Icons.phone_outlined),
                              hintText: '0912 345 6789',
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        PasswordField(
                          key: const ValueKey('register-password'),
                          controller: _password,
                          label: s.password,
                          showLockIcon: true,
                        ),
                        const SizedBox(height: 8),
                        PasswordRulesChecklist(password: _password.text),
                        const SizedBox(height: 16),
                        PasswordField(
                          key: const ValueKey('register-confirm'),
                          controller: _confirm,
                          label: s.confirmPassword,
                          showLockIcon: true,
                        ),
                        if (_confirm.text.isNotEmpty) ...[
                          const SizedBox(height: 6),
                          Text(
                            _passwordsMatch
                                ? s.passwordsMatch
                                : s.passwordsDontMatch,
                            key: const ValueKey('register-match'),
                            style: theme.textTheme.bodySmall?.copyWith(
                              color: _passwordsMatch
                                  ? good
                                  : theme.colorScheme.error,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                        const SizedBox(height: 12),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Checkbox(
                              key: const ValueKey('register-terms'),
                              value: _agreed,
                              onChanged: (value) =>
                                  setState(() => _agreed = value ?? false),
                            ),
                            Expanded(
                              child: Padding(
                                padding: const EdgeInsets.only(top: 12),
                                child: Text.rich(
                                  TextSpan(
                                    style: theme.textTheme.bodyMedium,
                                    children: [
                                      TextSpan(text: s.iAgreePrefix),
                                      TextSpan(
                                        text: s.termsOfService,
                                        style: linkStyle,
                                        recognizer: _termsTap,
                                      ),
                                      TextSpan(text: s.andWord),
                                      TextSpan(
                                        text: s.privacyPolicy,
                                        style: linkStyle,
                                        recognizer: _privacyTap,
                                      ),
                                      const TextSpan(text: '.'),
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                        if (error != null) ...[
                          const SizedBox(height: AniHowSpace.cardGap),
                          Text(
                            error,
                            style: TextStyle(
                              color: theme.colorScheme.error,
                              fontSize: AniHowSpace.body,
                            ),
                          ),
                        ],
                        const SizedBox(height: 16),
                        PrimaryButton(
                          key: const ValueKey('register-submit'),
                          label: s.createAccount,
                          busy: _busy,
                          onPressed: missing.isEmpty ? _submit : null,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          missing.isEmpty
                              ? s.weWillSendCode
                              : s.stillNeeded(missing.join(', ')),
                          key: const ValueKey('register-hint'),
                          textAlign: TextAlign.center,
                          style: theme.textTheme.bodySmall?.copyWith(
                            color: muted,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          alignment: WrapAlignment.center,
                          crossAxisAlignment: WrapCrossAlignment.center,
                          children: [
                            Text(
                              s.alreadyHaveAccount,
                              style: theme.textTheme.bodyMedium,
                            ),
                            TextButton(
                              onPressed: () => Navigator.of(context).maybePop(),
                              child: Text(
                                s.logIn,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.onBack});

  final VoidCallback onBack;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: EdgeInsets.fromLTRB(
        8,
        MediaQuery.paddingOf(context).top + 4,
        16,
        24,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconButton(
            tooltip: s.back,
            onPressed: onBack,
            color: Colors.white,
            icon: const Icon(Icons.arrow_back),
          ),
          const SizedBox(height: 8),
          Padding(
            padding: const EdgeInsets.only(left: 8),
            child: Row(
              children: [
                Container(
                  width: 56,
                  height: 56,
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Image.asset(
                    AniHowLogo.markPath,
                    fit: BoxFit.contain,
                    semanticLabel: 'AniHow',
                  ),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s.createYourAccount,
                        style: theme.textTheme.headlineSmall?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        s.signUpTagline,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFFD7EADF),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
