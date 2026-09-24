import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

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

class DismissedAnnouncementStore {
  static String keyFor(int userId) => 'anihow_dismissed_announcements_$userId';

  static Future<Set<int>> load(int userId) async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getStringList(keyFor(userId)) ?? const [];
    return {
      for (final value in raw)
        if (int.tryParse(value) != null) int.parse(value),
    };
  }

  static Future<void> remember(int userId, int announcementId) async {
    final ids = await load(userId);
    ids.add(announcementId);
    final prefs = await SharedPreferences.getInstance();
    await prefs.setStringList(keyFor(userId), ids.map((id) => '$id').toList());
  }
}

class FarmerAnnouncementHomeBanner extends StatefulWidget {
  const FarmerAnnouncementHomeBanner({
    super.key,
    required this.userId,
    required this.announcements,
    this.onOpen,
  });

  final int userId;
  final List<FarmAnnouncement> announcements;
  final VoidCallback? onOpen;

  @override
  State<FarmerAnnouncementHomeBanner> createState() => _FarmerAnnouncementHomeBannerState();
}

class _FarmerAnnouncementHomeBannerState extends State<FarmerAnnouncementHomeBanner> {
  Set<int> _dismissed = {};
  bool _ready = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void didUpdateWidget(FarmerAnnouncementHomeBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.userId != widget.userId) {
      _load();
    }
  }

  Future<void> _load() async {
    final ids = await DismissedAnnouncementStore.load(widget.userId);
    if (!mounted) {
      return;
    }
    setState(() {
      _dismissed = ids;
      _ready = true;
    });
  }

  Future<void> _dismiss(int announcementId) async {
    setState(() => _dismissed = {..._dismissed, announcementId});
    await DismissedAnnouncementStore.remember(widget.userId, announcementId);
  }

  FarmAnnouncement? get _banner {
    for (final item in widget.announcements) {
      if (!_dismissed.contains(item.id)) {
        return item;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    if (!_ready) {
      return const SizedBox.shrink();
    }
    final banner = _banner;
    if (banner == null) {
      return const SizedBox.shrink();
    }
    return FarmAnnouncementBanner(
      announcement: banner,
      onOpen: widget.onOpen,
      onDismiss: () => _dismiss(banner.id),
    );
  }
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
