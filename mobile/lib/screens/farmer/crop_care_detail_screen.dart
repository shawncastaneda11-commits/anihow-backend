import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import 'crop_care_form_screen.dart';

class CropCareDetailScreen extends StatefulWidget {
  const CropCareDetailScreen({
    super.key,
    required this.articleId,
    this.category,
  });

  final int articleId;
  final CategoryItem? category;

  @override
  State<CropCareDetailScreen> createState() => _CropCareDetailScreenState();
}

class _CropCareDetailScreenState extends State<CropCareDetailScreen> {
  late Future<CropCareArticle> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.cropCareShow(widget.articleId);
  }

  void _reload() {
    setState(() {
      _future = context.read<AuthController>().api.cropCareShow(widget.articleId);
    });
  }

  Future<void> _edit(CropCareArticle article) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(builder: (_) => CropCareFormScreen(article: article)),
    );
    if (saved == true && mounted) {
      _reload();
    }
  }

  Future<void> _delete(CropCareArticle article) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this guide?'),
        content: Text(article.title),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Delete')),
        ],
      ),
    );
    if (confirmed != true || !mounted) {
      return;
    }
    try {
      await context.read<AuthController>().api.deleteCropCare(article.id);
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<CropCareArticle>(
      future: _future,
      builder: (context, snapshot) {
        final article = snapshot.data;
        return Scaffold(
          backgroundColor: AniHowColors.cream,
          appBar: AppBar(
            title: Text(article?.title ?? 'Care Guide Details'),
            actions: [
              if (article != null && article.canEdit)
                PopupMenuButton<String>(
                  icon: const Icon(Icons.more_horiz),
                  tooltip: 'More',
                  onSelected: (value) {
                    if (value == 'edit') {
                      _edit(article);
                    } else if (value == 'delete') {
                      _delete(article);
                    }
                  },
                  itemBuilder: (context) => const [
                    PopupMenuItem(value: 'edit', child: Text('Edit')),
                    PopupMenuItem(value: 'delete', child: Text('Delete')),
                  ],
                ),
            ],
          ),
          body: _body(context, snapshot),
        );
      },
    );
  }

  Widget _body(BuildContext context, AsyncSnapshot<CropCareArticle> snapshot) {
    if (snapshot.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snapshot.hasError) {
      return Center(child: Text('${snapshot.error}'));
    }
    final article = snapshot.data;
    if (article == null) {
      return const Center(child: Text('Guide not found.'));
    }

    final category = article.category ?? widget.category;
    final cropType = category?.name ?? 'General';

    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          color: AniHowColors.card,
          elevation: 0,
          shadowColor: Colors.transparent,
          clipBehavior: Clip.antiAlias,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: const BorderSide(color: AniHowColors.cardBorder),
          ),
          child: _FeaturedMedia(imageUrl: article.imageUrl),
        ),
        const SizedBox(height: AniHowSpace.cardGap),
        Card(
          color: AniHowColors.card,
          elevation: 0,
          shadowColor: Colors.transparent,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: const BorderSide(color: AniHowColors.cardBorder),
          ),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Detailed Instructions',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AniHowColors.text,
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                _MetaLine(label: 'Crop Type:', value: cropType),
                const SizedBox(height: 16),
                const Divider(height: 1, color: AniHowColors.cardBorder),
                const SizedBox(height: 16),
                _InstructionBody(body: article.body),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _FeaturedMedia extends StatelessWidget {
  const _FeaturedMedia({this.imageUrl});

  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 188,
      width: double.infinity,
      child: imageUrl != null && imageUrl!.isNotEmpty
          ? Image.network(imageUrl!, fit: BoxFit.cover)
          : const ColoredBox(
              color: AniHowColors.photoPlaceholder,
              child: Center(
                child: Icon(
                  Icons.photo_camera_outlined,
                  size: 40,
                  color: AniHowColors.brand,
                ),
              ),
            ),
    );
  }
}

class _MetaLine extends StatelessWidget {
  const _MetaLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Text.rich(
      TextSpan(
        children: [
          TextSpan(
            text: '$label ',
            style: const TextStyle(
              color: AniHowColors.text,
              fontWeight: FontWeight.w700,
              fontSize: AniHowSpace.body,
            ),
          ),
          TextSpan(
            text: value,
            style: const TextStyle(
              color: AniHowColors.muted,
              fontWeight: FontWeight.w500,
              fontSize: AniHowSpace.body,
            ),
          ),
        ],
      ),
    );
  }
}

class _InstructionBody extends StatelessWidget {
  const _InstructionBody({required this.body});

  final String body;

  @override
  Widget build(BuildContext context) {
    final blocks = _parse(body);
    if (blocks.isEmpty) {
      return Text(
        'No instructions yet.',
        style: Theme.of(context).textTheme.bodyLarge?.copyWith(
              color: AniHowColors.muted,
              height: AniHowSpace.articleHeight,
            ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var i = 0; i < blocks.length; i++) ...[
          if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
          _BlockView(block: blocks[i]),
        ],
      ],
    );
  }

  List<_Block> _parse(String raw) {
    final trimmed = raw.trim();
    if (trimmed.isEmpty) {
      return const [];
    }

    final blocks = <_Block>[];
    var heading = '';
    var kind = _BlockKind.paragraphs;
    var lines = <String>[];

    void flush() {
      if (heading.isEmpty && lines.isEmpty) {
        return;
      }
      blocks.add(_Block(heading: heading, kind: kind, lines: List.of(lines)));
      heading = '';
      kind = _BlockKind.paragraphs;
      lines = [];
    }

    for (final rawLine in trimmed.split('\n')) {
      final line = rawLine.trim();
      if (line.isEmpty) {
        if (kind == _BlockKind.paragraphs && lines.isNotEmpty) {
          flush();
        }
        continue;
      }

      final header = _asHeader(line);
      if (header != null) {
        flush();
        heading = header;
        kind = _kindForHeader(header);
        continue;
      }

      if (_isNumbered(line)) {
        if (kind != _BlockKind.numbered || (heading.isEmpty && lines.isEmpty)) {
          flush();
          kind = _BlockKind.numbered;
        }
        lines.add(_stripNumber(line));
        continue;
      }

      if (_isCheck(line)) {
        if (kind != _BlockKind.checks) {
          flush();
          kind = _BlockKind.checks;
        }
        lines.add(_stripCheck(line));
        continue;
      }

      if (_isBullet(line)) {
        if (kind != _BlockKind.bullets && kind != _BlockKind.checks) {
          flush();
          kind = _BlockKind.bullets;
        }
        lines.add(_stripBullet(line));
        continue;
      }

      if (kind != _BlockKind.paragraphs && lines.isNotEmpty) {
        lines[lines.length - 1] = '${lines.last} $line';
        continue;
      }

      if (kind != _BlockKind.paragraphs) {
        flush();
        kind = _BlockKind.paragraphs;
      }
      lines.add(line);
    }

    flush();
    return blocks;
  }

  String? _asHeader(String line) {
    final value = line.replaceFirst(RegExp(r':+$'), '').trim();
    final lower = value.toLowerCase();
    const headers = [
      'detailed step-by-step instructions',
      'step-by-step instructions',
      'step-by-step',
      'materials checklist',
      'materials needed',
      'materials',
      'common pitfalls to avoid',
      'common pitfalls',
      'common mistakes to avoid',
      'common mistakes',
      'pitfalls',
    ];
    for (final header in headers) {
      if (lower == header) {
        return _prettyHeader(header);
      }
    }
    return null;
  }

  String _prettyHeader(String header) {
    return switch (header) {
      'detailed step-by-step instructions' || 'step-by-step instructions' || 'step-by-step' =>
        'Detailed Step-by-Step Instructions:',
      'materials checklist' || 'materials needed' || 'materials' => 'Materials Checklist:',
      'common pitfalls to avoid' || 'common pitfalls' || 'common mistakes to avoid' || 'common mistakes' || 'pitfalls' =>
        'Common Pitfalls to Avoid:',
      _ => header,
    };
  }

  _BlockKind _kindForHeader(String heading) {
    final lower = heading.toLowerCase();
    if (lower.contains('material')) {
      return _BlockKind.checks;
    }
    if (lower.contains('pitfall') || lower.contains('avoid') || lower.contains('mistake')) {
      return _BlockKind.bullets;
    }
    if (lower.contains('step')) {
      return _BlockKind.numbered;
    }
    return _BlockKind.paragraphs;
  }

  bool _isNumbered(String line) => RegExp(r'^\d+[\.\)]\s+').hasMatch(line);
  bool _isCheck(String line) => RegExp(r'^(([-*•]\s*)?\[[ xX]?\])\s*').hasMatch(line);
  bool _isBullet(String line) => RegExp(r'^[-*•]\s+').hasMatch(line);

  String _stripNumber(String line) => line.replaceFirst(RegExp(r'^\d+[\.\)]\s+'), '');
  String _stripCheck(String line) => line.replaceFirst(RegExp(r'^(([-*•]\s*)?\[[ xX]?\])\s*'), '');
  String _stripBullet(String line) => line.replaceFirst(RegExp(r'^[-*•]\s+'), '');
}

enum _BlockKind { paragraphs, numbered, checks, bullets }

class _Block {
  const _Block({
    required this.heading,
    required this.kind,
    required this.lines,
  });

  final String heading;
  final _BlockKind kind;
  final List<String> lines;
}

class _BlockView extends StatelessWidget {
  const _BlockView({required this.block});

  final _Block block;

  @override
  Widget build(BuildContext context) {
    final bodyStyle = Theme.of(context).textTheme.bodyLarge?.copyWith(
          color: AniHowColors.text,
          height: AniHowSpace.articleHeight,
        );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (block.heading.isNotEmpty) ...[
          Text(
            block.heading,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AniHowColors.text,
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
        ],
        if (block.kind == _BlockKind.numbered)
          for (var i = 0; i < block.lines.length; i++)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Text('${i + 1}. ${block.lines[i]}', style: bodyStyle),
            )
        else if (block.kind == _BlockKind.checks)
          for (final line in block.lines)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Padding(
                    padding: EdgeInsets.only(top: 2),
                    child: Icon(Icons.check_box_outline_blank, size: 18, color: AniHowColors.muted),
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: Text(line, style: bodyStyle)),
                ],
              ),
            )
        else if (block.kind == _BlockKind.bullets)
          for (final line in block.lines)
            Padding(
              padding: const EdgeInsets.only(bottom: 6),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text('•  ', style: TextStyle(fontWeight: FontWeight.w700, height: AniHowSpace.articleHeight)),
                  Expanded(child: Text(line, style: bodyStyle)),
                ],
              ),
            )
        else
          for (var i = 0; i < block.lines.length; i++) ...[
            if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
            Text(block.lines[i], style: bodyStyle),
          ],
      ],
    );
  }
}
