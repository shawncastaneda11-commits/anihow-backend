import 'package:flutter/gestures.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

/// Opens a link tapped in [LinkedText] outside the app. Tests replace this.
Future<bool> Function(Uri uri) launchTextLink = (Uri uri) {
  return launchUrl(uri, mode: LaunchMode.externalApplication);
};

final RegExp _linkPattern = RegExp(
  r'(https?://[^\s<>"]+|www\.[^\s<>"]+)',
  caseSensitive: false,
);

const String _trailingPunctuation = '.,;:!?)]}\'"';

/// Text whose web links (http://, https:// or www.) open in the browser.
/// Punctuation right after a link, like the period ending a sentence, stays
/// plain text.
class LinkedText extends StatefulWidget {
  const LinkedText(this.text, {super.key, this.style});

  final String text;
  final TextStyle? style;

  @override
  State<LinkedText> createState() => _LinkedTextState();
}

class _LinkedTextState extends State<LinkedText> {
  final List<TapGestureRecognizer> _recognizers = [];

  @override
  void dispose() {
    _disposeRecognizers();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    _disposeRecognizers();
    final matches = _linkPattern.allMatches(widget.text).toList();

    if (matches.isEmpty) {
      return Text(widget.text, style: widget.style);
    }

    final color = Theme.of(context).colorScheme.primary;
    final linkStyle = TextStyle(
      color: color,
      decoration: TextDecoration.underline,
      decorationColor: color,
    );
    final text = widget.text;
    final spans = <InlineSpan>[];
    var start = 0;

    for (final match in matches) {
      final raw = match.group(0)!;
      var end = raw.length;
      while (end > 0 && _trailingPunctuation.contains(raw[end - 1])) {
        end--;
      }
      final link = raw.substring(0, end);
      final uri = Uri.tryParse(
        link.toLowerCase().startsWith('www.') ? 'https://$link' : link,
      );

      if (match.start > start) {
        spans.add(TextSpan(text: text.substring(start, match.start)));
      }
      if (uri == null || !uri.hasAuthority || !uri.host.contains('.')) {
        spans.add(TextSpan(text: link));
      } else {
        final recognizer = TapGestureRecognizer()..onTap = () => launchTextLink(uri);
        _recognizers.add(recognizer);
        spans.add(TextSpan(text: link, style: linkStyle, recognizer: recognizer));
      }
      start = match.start + end;
    }

    if (start < text.length) {
      spans.add(TextSpan(text: text.substring(start)));
    }

    return Text.rich(TextSpan(children: spans), style: widget.style);
  }

  void _disposeRecognizers() {
    for (final recognizer in _recognizers) {
      recognizer.dispose();
    }
    _recognizers.clear();
  }
}
