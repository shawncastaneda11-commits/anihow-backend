import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/hint_card.dart';

void openFarmAnnouncements(BuildContext context) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => const FarmAnnouncementsScreen()),
  );
}

class FarmAnnouncementBanner extends StatelessWidget {
  const FarmAnnouncementBanner({
    super.key,
    required this.announcement,
    this.onOpen,
    this.onDismiss,
  });

  final FarmAnnouncement announcement;
  final VoidCallback? onOpen;
  final VoidCallback? onDismiss;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final title = announcement.isPinned
        ? '${s.announcementPinned}: ${announcement.title}'
        : announcement.title;

    return Dismissible(
      key: ValueKey('farm-announcement-${announcement.id}'),
      direction: DismissDirection.horizontal,
      onDismissed: (_) => onDismiss?.call(),
      background: ColoredBox(
        color: Theme.of(context).colorScheme.surfaceContainerHighest,
        child: Align(
          alignment: Alignment.centerRight,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
            child: Text(s.dismissAnnouncement),
          ),
        ),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onOpen,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 48),
            child: AniHowHintCard(
              icon: Icons.campaign_outlined,
              title: title,
              body: announcement.body,
              tone: AniHowHintTone.brand,
            ),
          ),
        ),
      ),
    );
  }
}

class FarmAnnouncementsScreen extends StatefulWidget {
  const FarmAnnouncementsScreen({
    super.key,
    this.preview,
    this.highlightId,
  });

  final List<FarmAnnouncement>? preview;
  final int? highlightId;

  @override
  State<FarmAnnouncementsScreen> createState() => _FarmAnnouncementsScreenState();
}

class _FarmAnnouncementsScreenState extends State<FarmAnnouncementsScreen> {
  late Future<List<FarmAnnouncement>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<FarmAnnouncement>> _load() async {
    if (widget.preview != null) {
      return widget.preview!;
    }
    return context.read<AuthController>().api.farmerAnnouncements();
  }

  Future<void> _reload() async {
    final future = _load();
    setState(() => _future = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.announcements)),
      body: AsyncView<List<FarmAnnouncement>>(
        future: _future,
        onRetry: _reload,
        isEmpty: (items) => items.isEmpty,
        emptyMessage: s.noAnnouncements,
        builder: (context, items) {
          return ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final item = items[index];
              final highlighted = widget.highlightId != null && widget.highlightId == item.id;
              return KeyedSubtree(
                key: Key('farm-announcement-${item.id}'),
                child: AniHowHintCard(
                  icon: highlighted || item.isPinned
                      ? Icons.push_pin_outlined
                      : Icons.campaign_outlined,
                  title: item.title,
                  body: item.body,
                  tone: highlighted || item.isPinned ? AniHowHintTone.brand : AniHowHintTone.neutral,
                ),
              );
            },
          );
        },
      ),
    );
  }
}
