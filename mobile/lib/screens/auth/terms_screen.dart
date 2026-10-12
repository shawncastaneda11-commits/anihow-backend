import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/account_menu_button.dart';

/// Terms of Service and Privacy Policy, one tab each.
class TermsDocument extends StatelessWidget {
  const TermsDocument({super.key, this.initialTab = 0, this.padding});

  final int initialTab;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final chip = dark ? const Color(0xFF24302B) : const Color(0xFFEDEAE2);
    final selected = dark ? const Color(0xFFA8D5BA) : AniHowColors.brand;
    final onSelected = dark ? const Color(0xFF10251A) : Colors.white;

    return DefaultTabController(
      length: 2,
      initialIndex: initialTab,
      child: Padding(
        padding: padding ?? EdgeInsets.zero,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                color: chip,
                borderRadius: BorderRadius.circular(24),
              ),
              child: Padding(
                padding: const EdgeInsets.all(4),
                child: TabBar(
                  indicator: BoxDecoration(
                    color: selected,
                    borderRadius: BorderRadius.circular(20),
                  ),
                  indicatorSize: TabBarIndicatorSize.tab,
                  dividerColor: Colors.transparent,
                  labelColor: onSelected,
                  unselectedLabelColor: theme.colorScheme.onSurface,
                  labelStyle: const TextStyle(fontWeight: FontWeight.w700),
                  tabs: [
                    Tab(height: 40, text: s.termsOfService),
                    Tab(height: 40, text: s.privacyPolicy),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            Expanded(
              child: TabBarView(
                children: [
                  _Sections(sections: s.termsOfServiceSections),
                  _Sections(sections: s.privacyPolicySections),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Sections extends StatelessWidget {
  const _Sections({required this.sections});

  final List<({String title, String body})> sections;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.78);
    return Card(
      margin: EdgeInsets.zero,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
        children: [
          for (var index = 0; index < sections.length; index++) ...[
            const SizedBox(height: 14),
            Text(
              '${index + 1}. ${sections[index].title}',
              style: theme.textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              sections[index].body,
              style: theme.textTheme.bodyMedium?.copyWith(
                color: muted,
                height: 1.5,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// Read-only terms, opened from the sign-up links.
class TermsScreen extends StatelessWidget {
  const TermsScreen({super.key, this.initialTab = 0});

  final int initialTab;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.termsPrivacy)),
      body: SafeArea(
        top: false,
        child: TermsDocument(
          initialTab: initialTab,
          padding: AniHowSpace.screenPadding,
        ),
      ),
    );
  }
}

/// Shown before anything else when an app user has not accepted the current
/// terms. The only ways out are accepting or logging out.
class TermsGateScreen extends StatefulWidget {
  const TermsGateScreen({super.key});

  @override
  State<TermsGateScreen> createState() => _TermsGateScreenState();
}

class _TermsGateScreenState extends State<TermsGateScreen> {
  bool _agreed = false;
  bool _busy = false;

  Future<void> _accept() async {
    setState(() => _busy = true);
    final auth = context.read<AuthController>();
    final ok = await auth.acceptTerms();
    if (!mounted) {
      return;
    }
    setState(() => _busy = false);
    if (!ok) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(auth.error ?? '')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final topInset = MediaQuery.paddingOf(context).top;

    return PopScope(
      canPop: false,
      child: Scaffold(
        body: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ColoredBox(
              color: AniHowColors.brand,
              child: Padding(
                padding: EdgeInsets.fromLTRB(20, topInset + 20, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const CircleAvatar(
                      radius: 22,
                      backgroundColor: Color(0x24FFFFFF),
                      child: Icon(
                        Icons.description_outlined,
                        color: Colors.white,
                      ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      s.beforeYouContinue,
                      style: theme.textTheme.headlineSmall?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      s.termsGateBody,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: const Color(0xFFD7EADF),
                        height: 1.45,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const Expanded(
              child: TermsDocument(
                padding: EdgeInsets.fromLTRB(16, 14, 16, 0),
              ),
            ),
            SafeArea(
              top: false,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    CheckboxListTile(
                      key: const ValueKey('terms-gate-agree'),
                      value: _agreed,
                      onChanged: _busy
                          ? null
                          : (value) => setState(() => _agreed = value ?? false),
                      controlAffinity: ListTileControlAffinity.leading,
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        s.iHaveReadAndAgree,
                        style: theme.textTheme.bodyMedium,
                      ),
                    ),
                    const SizedBox(height: 4),
                    PrimaryButton(
                      key: const ValueKey('terms-gate-accept'),
                      label: s.acceptAndContinue,
                      busy: _busy,
                      onPressed: _agreed ? _accept : null,
                    ),
                    const SizedBox(height: 4),
                    TextButton(
                      onPressed: _busy ? null : () => confirmAndLogOut(context),
                      style: TextButton.styleFrom(
                        minimumSize: const Size.fromHeight(44),
                      ),
                      child: Text(s.logOut),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
