import 'dart:async';

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

/// Session-only: the same notice pops once per app run, then stays gone until restart.
class DismissedAnnouncementStore {
  static final Map<int, Set<int>> _ids = {};

  static void reset() => _ids.clear();

  static Set<int> load(int userId) => Set<int>.from(_ids[userId] ?? const {});

  static void remember(int userId, int announcementId) {
    _ids.putIfAbsent(userId, () => <int>{}).add(announcementId);
  }
}

class FarmerAnnouncementHomeBanner extends StatefulWidget {
  const FarmerAnnouncementHomeBanner({
    super.key,
    required this.userId,
    required this.announcements,
    this.toastDuration = const Duration(seconds: 6),
  });

  final int userId;
  final List<FarmAnnouncement> announcements;
  final Duration toastDuration;

  @override
  State<FarmerAnnouncementHomeBanner> createState() => _FarmerAnnouncementHomeBannerState();
}

class _FarmerAnnouncementHomeBannerState extends State<FarmerAnnouncementHomeBanner> {
  late Set<int> _seen;
  FarmAnnouncement? _toast;
  Timer? _hide;

  @override
  void initState() {
    super.initState();
    _seen = DismissedAnnouncementStore.load(widget.userId);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) {
        _maybeToast();
      }
    });
  }

  @override
  void dispose() {
    _hide?.cancel();
    super.dispose();
  }

  @override
  void didUpdateWidget(FarmerAnnouncementHomeBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.userId != widget.userId) {
      _seen = DismissedAnnouncementStore.load(widget.userId);
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _maybeToast();
        }
      });
      return;
    }
    if (oldWidget.announcements != widget.announcements) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          _maybeToast();
        }
      });
    }
  }

  FarmAnnouncement? get _unseen {
    for (final item in widget.announcements) {
      if (!_seen.contains(item.id)) {
        return item;
      }
    }
    return null;
  }

  void _maybeToast() {
    if (_toast != null) {
      return;
    }
    final notice = _unseen;
    if (notice == null) {
      return;
    }
    setState(() {
      _toast = notice;
      _seen = {..._seen, notice.id};
    });
    DismissedAnnouncementStore.remember(widget.userId, notice.id);
    _hide?.cancel();
    _hide = Timer(widget.toastDuration, () {
      if (!mounted) {
        return;
      }
      setState(() => _toast = null);
    });
  }

  @override
  Widget build(BuildContext context) {
    final notice = _toast;
    if (notice == null) {
      return const SizedBox.shrink();
    }
    final s = AppStrings.of(context);
    final title = notice.isPinned ? '${s.announcementPinned}: ${notice.title}' : notice.title;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.cardGap,
        AniHowSpace.screen,
        0,
      ),
      child: Material(
        elevation: 6,
        borderRadius: BorderRadius.circular(AniHowSpace.radius),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          key: const ValueKey('farm-announcement-toast'),
          onTap: () => openFarmAnnouncements(context),
          child: AniHowHintCard(
            icon: Icons.campaign_outlined,
            title: title,
            tone: AniHowHintTone.brand,
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
