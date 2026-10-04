import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../farm/farm_profile_screen.dart';

typedef AnnouncementPageLoader = Future<PagedBuyerAnnouncements> Function({
  required bool following,
  required int page,
});

class AnnouncementsFeedScreen extends StatefulWidget {
  const AnnouncementsFeedScreen({super.key, this.loadPage});

  /// When set, the screen does not call the network.
  final AnnouncementPageLoader? loadPage;

  @override
  State<AnnouncementsFeedScreen> createState() =>
      _AnnouncementsFeedScreenState();
}

class _AnnouncementsFeedScreenState extends State<AnnouncementsFeedScreen> {
  bool _following = false;
  late Future<PagedBuyerAnnouncements> _future;
  final List<BuyerFarmAnnouncement> _more = [];
  int _page = 1;
  int _lastPage = 1;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _future = _fetch(1);
    _remember(_future);
  }

  void _remember(Future<PagedBuyerAnnouncements> future) {
    future.then((result) {
      if (!mounted) {
        return;
      }
      setState(() {
        _page = result.currentPage;
        _lastPage = result.lastPage;
      });
    });
  }

  Future<PagedBuyerAnnouncements> _fetch(int page) {
    final loader = widget.loadPage;
    if (loader != null) {
      return loader(following: _following, page: page);
    }
    return context.read<AuthController>().api.buyerAnnouncements(
      following: _following,
      page: page,
    );
  }

  void _reload() {
    setState(() {
      _more.clear();
      _page = 1;
      _lastPage = 1;
      _future = _fetch(1);
    });
    _remember(_future);
  }

  Future<void> _loadMore() async {
    if (_loadingMore || _page >= _lastPage) {
      return;
    }
    setState(() => _loadingMore = true);
    try {
      final result = await _fetch(_page + 1);
      if (!mounted) {
        return;
      }
      setState(() {
        _more.addAll(result.items);
        _page = result.currentPage;
        _lastPage = result.lastPage;
      });
    } finally {
      if (mounted) {
        setState(() => _loadingMore = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.updatesFromFarms)),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              AniHowSpace.cardGap,
              AniHowSpace.screen,
              0,
            ),
            child: SegmentedButton<bool>(
              style: SegmentedButton.styleFrom(minimumSize: const Size(48, 48)),
              segments: [
                ButtonSegment(value: false, label: Text(s.allFarms)),
                ButtonSegment(value: true, label: Text(s.farmsIFollow)),
              ],
              selected: {_following},
              onSelectionChanged: (selected) {
                setState(() => _following = selected.first);
                _reload();
              },
            ),
          ),
          Expanded(
            child: AsyncView<PagedBuyerAnnouncements>(
              future: _future,
              onRetry: _reload,
              emptyMessage: s.noFarmUpdates,
              isEmpty: (page) => page.items.isEmpty,
              builder: (context, page) {
                final items = [...page.items, ..._more];
                return NotificationListener<ScrollNotification>(
                  onNotification: (notification) {
                    if (notification.metrics.pixels >=
                        notification.metrics.maxScrollExtent - 240) {
                      _loadMore();
                    }
                    return false;
                  },
                  child: ListView.separated(
                    padding: AniHowSpace.screenPadding,
                    itemCount: items.length + (_loadingMore ? 1 : 0),
                    separatorBuilder: (_, _) =>
                        const SizedBox(height: AniHowSpace.cardGap),
                    itemBuilder: (context, index) {
                      if (index >= items.length) {
                        return const Center(child: CircularProgressIndicator());
                      }
                      return _AnnouncementCard(post: items[index]);
                    },
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _AnnouncementCard extends StatefulWidget {
  const _AnnouncementCard({required this.post});

  final BuyerFarmAnnouncement post;

  @override
  State<_AnnouncementCard> createState() => _AnnouncementCardState();
}

class _AnnouncementCardState extends State<_AnnouncementCard> {
  bool _expanded = false;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final post = widget.post;
    final theme = Theme.of(context);
    final published = DateTime.tryParse(post.publishedAt ?? '');
    final long = post.body.length > 160;
    final body = _expanded || !long
        ? post.body
        : '${post.body.substring(0, 160).trim()}…';
    final image = post.imageUrl;

    return Card(
      key: ValueKey('farm-announcement-card-${post.id}'),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundImage: post.farmCoverUrl == null
                      ? null
                      : NetworkImage(post.farmCoverUrl!),
                  child: post.farmCoverUrl == null
                      ? const Icon(Icons.agriculture_outlined)
                      : null,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextButton(
                    style: TextButton.styleFrom(
                      minimumSize: const Size(48, 48),
                      alignment: Alignment.centerLeft,
                      padding: EdgeInsets.zero,
                    ),
                    onPressed: () => openFarmProfile(context, post.farmId),
                    child: Text(post.farmName),
                  ),
                ),
                if (published != null)
                  Text(
                    s.shortDate(published.toLocal()),
                    style: theme.textTheme.bodySmall,
                  ),
              ],
            ),
            Text(post.title, style: theme.textTheme.titleMedium),
            const SizedBox(height: 4),
            Text(body),
            if (long && !_expanded)
              TextButton(
                style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
                onPressed: () => setState(() => _expanded = true),
                child: Text(s.readMore),
              ),
            if (image != null && image.isNotEmpty) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: Image.network(
                  image,
                  height: 180,
                  width: double.infinity,
                  fit: BoxFit.cover,
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
