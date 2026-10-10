import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../l10n/help_topics.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../support/seller_mailto.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/primary_button.dart';
import '../faq/faq_bot_screen.dart';
import '../../theme/readable_accent.dart';

class HelpScreen extends StatefulWidget {
  const HelpScreen({super.key});

  @override
  State<HelpScreen> createState() => _HelpScreenState();
}

class _HelpScreenState extends State<HelpScreen> {
  SellerHelp? _help;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    try {
      final help = await context.read<AuthController>().api.sellerHelp();
      if (!mounted) {
        return;
      }
      setState(() {
        _help = help;
        _failed = help.email.isEmpty;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _help = null;
        _failed = true;
      });
    }
  }

  Future<void> _email(String email) async {
    final s = AppStrings.read(context);
    final mailto = sellerMailto(email: email, subject: helpEmailSubject, body: '');
    try {
      final opened = await launchUrl(
        Uri.parse(mailto),
        mode: LaunchMode.externalApplication,
      );
      if (!opened) {
        await Clipboard.setData(ClipboardData(text: email));
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.noEmailApp)));
        }
      }
    } catch (_) {
      await Clipboard.setData(ClipboardData(text: email));
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(s.noEmailApp)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final seller = context.watch<AuthController>().user?.isFarmerSeller == true;

    return DefaultTabController(
      length: 2,
      initialIndex: seller ? 1 : 0,
      child: Scaffold(
        appBar: AppBar(
          title: Text(s.howAnihowWorks),
          bottom: onBrandTabBar(
            tabs: [
              Tab(text: s.forBuyers),
              Tab(text: s.forSellers),
            ],
          ),
        ),
        body: TabBarView(
          children: [
            _TopicList(
              topics: s.buyerHelpTopics,
              footer: _Footer(help: _help, failed: _failed, onEmail: _email),
            ),
            _TopicList(
              topics: s.sellerHelpTopics,
              footer: _Footer(help: _help, failed: _failed, onEmail: _email),
            ),
          ],
        ),
      ),
    );
  }
}

class _TopicList extends StatefulWidget {
  const _TopicList({required this.topics, required this.footer});

  final List<HelpTopic> topics;
  final Widget footer;

  @override
  State<_TopicList> createState() => _TopicListState();
}

class _TopicListState extends State<_TopicList> {
  int? _open;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          child: Column(
            children: [
              for (var i = 0; i < widget.topics.length; i++) ...[
                if (i > 0) const Divider(height: 1),
                _TopicRow(
                  index: i + 1,
                  topic: widget.topics[i],
                  open: _open == i,
                  onTap: () => setState(() => _open = _open == i ? null : i),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(height: AniHowSpace.section),
        widget.footer,
      ],
    );
  }
}

class _TopicRow extends StatelessWidget {
  const _TopicRow({
    required this.index,
    required this.topic,
    required this.open,
    required this.onTap,
  });

  final int index;
  final HelpTopic topic;
  final bool open;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      children: [
        InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 56),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
              child: Row(
                children: [
                  Container(
                    width: 32,
                    height: 32,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: accentTint(context),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Text(
                      '$index',
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: readableAccent(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(topic.title, style: theme.textTheme.titleMedium),
                  ),
                  Icon(open ? Icons.expand_less : Icons.chevron_right),
                ],
              ),
            ),
          ),
        ),
        if (open)
          Padding(
            padding: const EdgeInsets.fromLTRB(56, 0, 16, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                for (var i = 0; i < topic.steps.length; i++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 6),
                    child: Text('${i + 1}. ${topic.steps[i]}'),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}

class _Footer extends StatelessWidget {
  const _Footer({
    required this.help,
    required this.failed,
    required this.onEmail,
  });

  final SellerHelp? help;
  final bool failed;
  final Future<void> Function(String email) onEmail;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final showContact = !failed && help != null && help!.email.isNotEmpty;

    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(s.stillStuck, style: theme.textTheme.titleMedium),
            const SizedBox(height: 8),
            Text(
              showContact
                  ? s.stillStuckBody(help!.office, help!.email)
                  : s.askTheFaqBotOnly,
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            PrimaryButton(
              label: s.askTheFaqBot,
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const FaqBotScreen()),
              ),
            ),
            if (showContact) ...[
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () => onEmail(help!.email),
                child: Text(s.emailOffice(help!.office)),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
