import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/primary_button.dart';

class FaqBotScreen extends StatefulWidget {
  const FaqBotScreen({super.key});

  @override
  State<FaqBotScreen> createState() => _FaqBotScreenState();
}

class _FaqBotScreenState extends State<FaqBotScreen> {
  late Future<List<FaqSuggestion>> _chipsFuture;
  final _input = TextEditingController();
  final List<_FaqTurn> _turns = [];
  List<FaqSuggestion> _suggestions = const [];
  bool _asking = false;

  @override
  void initState() {
    super.initState();
    _chipsFuture = _loadChips();
  }

  Future<List<FaqSuggestion>> _loadChips() async {
    final chips = await context.read<AuthController>().api.faqSuggestions();
    if (mounted) {
      setState(() => _suggestions = chips);
    }
    return chips;
  }

  Future<void> _reloadChips() async {
    final future = _loadChips();
    setState(() => _chipsFuture = future);
    await future;
  }

  Future<void> _ask(String question) async {
    final trimmed = question.trim();
    if (_asking || trimmed.isEmpty) {
      return;
    }
    setState(() {
      _asking = true;
      _turns.add(_FaqTurn(question: trimmed));
    });
    _input.clear();
    try {
      final answer = await context.read<AuthController>().api.askFaq(trimmed);
      if (!mounted) {
        return;
      }
      setState(() {
        _turns[_turns.length - 1] = _FaqTurn(
          question: trimmed,
          answer: answer.answer,
        );
        if (answer.suggestions.isNotEmpty) {
          _suggestions = answer.suggestions;
        }
        _asking = false;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _turns[_turns.length - 1] = _FaqTurn(
          question: trimmed,
          answer: error.message,
        );
        _asking = false;
      });
    }
  }

  @override
  void dispose() {
    _input.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.faqTitle)),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                Text(
                  s.faqIntro,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: AniHowSpace.section),
                AsyncView<List<FaqSuggestion>>(
                  future: _chipsFuture,
                  onRetry: _reloadChips,
                  emptyMessage: s.noHelpTopics,
                  builder: (context, chips) {
                    final shown = _suggestions.isNotEmpty ? _suggestions : chips;
                    return Wrap(
                      spacing: AniHowSpace.cardGap,
                      runSpacing: AniHowSpace.cardGap,
                      children: [
                        for (final chip in shown)
                          ActionChip(
                            label: Text(chip.label),
                            onPressed: _asking ? null : () => _ask(chip.label),
                          ),
                      ],
                    );
                  },
                ),
                const SizedBox(height: AniHowSpace.section),
                for (final turn in _turns) ...[
                  Align(
                    alignment: Alignment.centerRight,
                    child: Container(
                      margin: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                      padding: AniHowSpace.cardPadding,
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.primaryContainer,
                        borderRadius: BorderRadius.circular(AniHowSpace.radius),
                      ),
                      child: Text(turn.question),
                    ),
                  ),
                  if (turn.answer != null)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Container(
                        margin: const EdgeInsets.only(bottom: AniHowSpace.section),
                        padding: AniHowSpace.cardPadding,
                        decoration: BoxDecoration(
                          color: Theme.of(context).colorScheme.surfaceContainerHighest,
                          borderRadius: BorderRadius.circular(AniHowSpace.radius),
                        ),
                        child: Text(turn.answer!),
                      ),
                    )
                  else
                    const Padding(
                      padding: EdgeInsets.only(bottom: AniHowSpace.section),
                      child: Align(
                        alignment: Alignment.centerLeft,
                        child: CircularProgressIndicator(),
                      ),
                    ),
                ],
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: AniHowSpace.screenPadding,
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _input,
                      enabled: !_asking,
                      decoration: InputDecoration(
                        hintText: s.askHint,
                      ),
                      textInputAction: TextInputAction.send,
                      onSubmitted: _ask,
                    ),
                  ),
                  const SizedBox(width: AniHowSpace.cardGap),
                  PrimaryButton(
                    label: s.ask,
                    busy: _asking,
                    expand: false,
                    onPressed: () => _ask(_input.text),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _FaqTurn {
  const _FaqTurn({required this.question, this.answer});

  final String question;
  final String? answer;
}
