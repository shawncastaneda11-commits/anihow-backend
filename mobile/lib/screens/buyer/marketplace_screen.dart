import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/buyer_location.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/app_header.dart';
import '../../widgets/async_view.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/unverified_email_banner.dart';
import 'announcements_feed_screen.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

const _updatesHiddenKey = 'marketplace_updates_hidden_on';
const _pageSize = 15;

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key});

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  final _search = TextEditingController();
  final _scroll = ScrollController();
  Timer? _searchDebounce;
  int _requestId = 0;
  int? _cropTypeId;
  String? _category;
  String _sort = 'fair';
  String? _mixDay;
  String? _growingMethod;
  double? _nearLat;
  double? _nearLng;
  bool _locationUnavailable = false;
  bool _locationNoFix = false;
  bool _locating = false;
  bool _updatesHidden = false;
  bool _loading = true;
  bool _loadingMore = false;
  bool _hasMore = false;
  int _page = 1;
  Object? _error;
  List<ListingItem> _items = const [];
  List<BuyerFarmAnnouncement> _updates = const [];
  late Future<List<CategoryItem>> _cropTypes;

  @override
  void initState() {
    super.initState();
    _cropTypes = context.read<AuthController>().api.cropTypes();
    _scroll.addListener(_onScroll);
    _search.addListener(_onSearchText);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      _restoreUpdatesDismissal();
      _reload();
    });
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.removeListener(_onSearchText);
    _scroll.removeListener(_onScroll);
    _scroll.dispose();
    _search.dispose();
    super.dispose();
  }

  void _onSearchText() {
    if (mounted) {
      setState(() {});
    }
  }

  void _onSearchChanged(String _) {
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 400), () {
      if (!mounted) {
        return;
      }
      unawaited(_reload(scrollToTop: true));
    });
  }

  void _submitSearch() {
    _searchDebounce?.cancel();
    unawaited(_reload(scrollToTop: true));
  }

  void _clearSearch() {
    _search.clear();
    _searchDebounce?.cancel();
    unawaited(_reload(scrollToTop: true));
  }

  void _scrollToTop() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) {
        return;
      }
      _scroll.jumpTo(0);
    });
  }

  bool get _filtersActive =>
      _sort != 'fair' || _growingMethod != null || _cropTypeId != null;

  String _today() {
    final now = DateTime.now();
    final month = now.month.toString().padLeft(2, '0');
    final day = now.day.toString().padLeft(2, '0');
    return '${now.year}-$month-$day';
  }

  Future<void> _restoreUpdatesDismissal() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final stored = prefs.getString(_updatesHiddenKey);
      if (!mounted || stored != _today()) {
        return;
      }
      setState(() => _updatesHidden = true);
    } catch (_) {
      // Preferences are optional. The banner stays visible if they fail.
    }
  }

  Future<void> _dismissUpdates() async {
    setState(() => _updatesHidden = true);
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(_updatesHiddenKey, _today());
    } catch (_) {
      // Hidden for this visit even when the date cannot be stored.
    }
  }

  Future<void> _reload({bool scrollToTop = false}) async {
    final request = ++_requestId;
    final auth = context.read<AuthController>();
    if (auth.user != null) {
      try {
        await auth.refreshUser();
      } catch (_) {}
    }
    final api = auth.api;
    setState(() {
      _loading = true;
      _loadingMore = false;
      _error = null;
      _page = 1;
      _mixDay = null;
    });
    try {
      final feed = await api.marketplace(
        search: _search.text.trim(),
        cropTypeId: _cropTypeId,
        sort: _sort,
        category: _category,
        nearLat: _sort == 'nearest' ? _nearLat : null,
        nearLng: _sort == 'nearest' ? _nearLng : null,
        growingMethod: _growingMethod,
      );
      if (!mounted || request != _requestId) {
        return;
      }
      setState(() {
        _items = feed.items;
        _mixDay = feed.mixDay;
        _hasMore = feed.items.length >= _pageSize;
        _loading = false;
      });
      if (scrollToTop) {
        _scrollToTop();
      }
      unawaited(_loadUpdates(request));
    } catch (error) {
      if (!mounted || request != _requestId) {
        return;
      }
      setState(() {
        _error = error;
        _items = const [];
        _loading = false;
        _hasMore = false;
      });
    }
  }

  Future<void> _loadUpdates(int request) async {
    List<BuyerFarmAnnouncement> updates = const [];
    try {
      final page = await context
          .read<AuthController>()
          .api
          .buyerAnnouncements();
      updates = page.items;
    } catch (_) {
      updates = const [];
    }
    if (!mounted || request != _requestId) {
      return;
    }
    setState(() => _updates = updates);
  }

  Future<void> _loadMore() async {
    if (_loading || _loadingMore || !_hasMore) {
      return;
    }
    final request = _requestId;
    setState(() => _loadingMore = true);
    final nextPage = _page + 1;
    try {
      final feed = await context.read<AuthController>().api.marketplace(
        search: _search.text.trim(),
        cropTypeId: _cropTypeId,
        sort: _sort,
        category: _category,
        nearLat: _sort == 'nearest' ? _nearLat : null,
        nearLng: _sort == 'nearest' ? _nearLng : null,
        growingMethod: _growingMethod,
        page: nextPage,
        mixDay: _mixDay,
      );
      if (!mounted || request != _requestId) {
        return;
      }
      setState(() {
        _page = nextPage;
        _items = [..._items, ...feed.items];
        _hasMore = feed.items.length >= _pageSize;
        _loadingMore = false;
      });
    } catch (_) {
      if (mounted && request == _requestId) {
        setState(() => _loadingMore = false);
      }
    }
  }

  void _onScroll() {
    if (!_scroll.hasClients) {
      return;
    }
    final position = _scroll.position;
    if (position.pixels > position.maxScrollExtent - 480) {
      _loadMore();
    }
  }

  Future<void> _openFilters() async {
    final cropTypes = await _cropTypes;
    if (!mounted) {
      return;
    }
    final choice = await showModalBottomSheet<_MarketFilters>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FilterSheet(
        sort: _sort,
        growingMethod: _growingMethod,
        cropTypeId: _cropTypeId,
        cropTypes: cropTypes,
      ),
    );
    if (!mounted || choice == null) {
      return;
    }
    await _applyFilters(choice);
  }

  Future<void> _applyFilters(_MarketFilters choice) async {
    double? latitude;
    double? longitude;
    var unavailable = false;
    var noFix = false;
    if (choice.sort == 'nearest') {
      setState(() => _locating = true);
      try {
        final result = await BuyerLocation.read();
        if (!mounted) {
          return;
        }
        switch (result) {
          case BuyerLocationFound(:final point):
            latitude = point.latitude;
            longitude = point.longitude;
          case BuyerLocationUnavailable():
            unavailable = true;
          case BuyerLocationNoFix():
            noFix = true;
        }
      } catch (_) {
        noFix = true;
      } finally {
        if (mounted) {
          setState(() => _locating = false);
        }
      }
      if (!mounted) {
        return;
      }
    }
    setState(() {
      _sort = choice.sort;
      _growingMethod = choice.growingMethod;
      _cropTypeId = choice.cropTypeId;
      _nearLat = latitude;
      _nearLng = longitude;
      _locationUnavailable = unavailable;
      _locationNoFix = noFix;
    });
    await _reload(scrollToTop: true);
  }

  void _selectCategory(String? category) {
    setState(() => _category = category);
    unawaited(_reload(scrollToTop: true));
  }

  void _selectAll() {
    setState(() {
      _category = null;
      _cropTypeId = null;
    });
    unawaited(_reload(scrollToTop: true));
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final newest = _updates.isEmpty ? null : _updates.first;
    final showUpdates = !_updatesHidden && newest != null;
    return Column(
      children: [
        AppHeader(
          title: s.marketplace,
          trailing: const Row(
            mainAxisSize: MainAxisSize.min,
            children: [CartIconButton(), NotificationBellButton()],
          ),
        ),
        const UnverifiedEmailBanner(),
        Expanded(
          child: RefreshIndicator(
            onRefresh: _reload,
            child: CustomScrollView(
              controller: _scroll,
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverPersistentHeader(
                  pinned: true,
                  delegate: _SearchHeaderDelegate(
                    extent: 80,
                    child: SizedBox(
                      height: 80,
                      child: _SearchRow(
                        controller: _search,
                        filtersActive: _filtersActive,
                        onChanged: _onSearchChanged,
                        onSubmit: _submitSearch,
                        onClear: _clearSearch,
                        onFilter: _openFilters,
                      ),
                    ),
                  ),
                ),
                SliverToBoxAdapter(child: _chipRow(s)),
                if (_locating)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AniHowSpace.screen,
                        AniHowSpace.cardGap,
                        AniHowSpace.screen,
                        0,
                      ),
                      child: Text(
                        s.findingYourLocation,
                        key: const Key('finding-location'),
                      ),
                    ),
                  )
                else if (_locationUnavailable)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AniHowSpace.screen,
                        AniHowSpace.cardGap,
                        AniHowSpace.screen,
                        0,
                      ),
                      child: Text(
                        s.locationUnavailable,
                        key: const Key('location-unavailable'),
                      ),
                    ),
                  ),
                if (_locationNoFix)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(
                        AniHowSpace.screen,
                        AniHowSpace.cardGap,
                        AniHowSpace.screen,
                        0,
                      ),
                      child: Text(
                        s.locationNoFix,
                        key: const Key('location-no-fix'),
                      ),
                    ),
                  ),
                if (showUpdates)
                  SliverToBoxAdapter(
                    child: _UpdatesBanner(
                      count: _updates.length,
                      post: newest,
                      onOpen: () {
                        Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const AnnouncementsFeedScreen(),
                          ),
                        );
                      },
                      onClose: _dismissUpdates,
                    ),
                  ),
                ..._listingSlivers(s),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Widget _chipRow(AppStrings s) {
    return SizedBox(
      key: const Key('marketplace-chips'),
      height: 48,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
        children: [
          _chip(
            key: const ValueKey('category-all'),
            label: s.all,
            selected: _category == null && _cropTypeId == null,
            onSelected: _selectAll,
          ),
          _chip(
            key: const ValueKey('category-fresh_produce'),
            label: s.freshProduce,
            selected: _category == 'fresh_produce',
            onSelected: () => _selectCategory('fresh_produce'),
          ),
          _chip(
            key: const ValueKey('category-value_added'),
            label: s.valueAdded,
            selected: _category == 'value_added',
            onSelected: () => _selectCategory('value_added'),
          ),
        ],
      ),
    );
  }

  Widget _chip({
    required Key key,
    required String label,
    required bool selected,
    required VoidCallback onSelected,
    Widget? avatar,
  }) {
    return Padding(
      padding: const EdgeInsets.only(right: 8),
      child: FilterChip(
        key: key,
        avatar: avatar,
        label: Text(label),
        selected: selected,
        materialTapTargetSize: MaterialTapTargetSize.padded,
        onSelected: (_) => onSelected(),
      ),
    );
  }

  Widget _produceCard(ListingItem listing, ProduceCardStyle style) {
    return ProduceCard(
      listing: listing,
      style: style,
      showSeller: true,
      onTap: () {
        Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ListingDetailScreen(listingId: listing.id),
          ),
        );
      },
      onSellerTap: listing.sellerId == null
          ? null
          : () => openBuyerShop(context, listing.sellerId!),
    );
  }

  Widget _phoneList() {
    return SliverList.separated(
      itemCount: _items.length,
      separatorBuilder: (context, index) =>
          const SizedBox(height: AniHowSpace.cardGap),
      itemBuilder: (context, index) =>
          _produceCard(_items[index], ProduceCardStyle.feed),
    );
  }

  Widget _posterGrid() {
    return SliverGrid(
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisExtent: 328,
        crossAxisSpacing: AniHowSpace.cardGap,
        mainAxisSpacing: AniHowSpace.cardGap,
      ),
      delegate: SliverChildBuilderDelegate(
        (context, index) =>
            _produceCard(_items[index], ProduceCardStyle.poster),
        childCount: _items.length,
      ),
    );
  }

  List<Widget> _listingSlivers(AppStrings s) {
    if (_loading && _items.isEmpty) {
      return const [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(child: CircularProgressIndicator()),
        ),
      ];
    }
    if (_error != null && _items.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: AsyncViewError(onRetry: _reload),
        ),
      ];
    }
    if (_items.isEmpty) {
      return [
        SliverFillRemaining(
          hasScrollBody: false,
          child: Center(
            child: Padding(
              padding: AniHowSpace.screenPadding,
              child: Text(s.noListingsFound, textAlign: TextAlign.center),
            ),
          ),
        ),
      ];
    }
    return [
      SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            AniHowSpace.cardGap,
            AniHowSpace.screen,
            AniHowSpace.cardGap,
          ),
          child: Text(
            s.listingsCount(_items.length),
            key: const Key('marketplace-listing-count'),
            style: Theme.of(context).textTheme.titleSmall,
          ),
        ),
      ),
      SliverLayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.crossAxisExtent >= 600;
          return SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            sliver: wide ? _posterGrid() : _phoneList(),
          );
        },
      ),
      if (_loadingMore)
        const SliverToBoxAdapter(
          child: Padding(
            padding: EdgeInsets.all(16),
            child: Center(child: CircularProgressIndicator()),
          ),
        ),
    ];
  }
}

class _SearchRow extends StatelessWidget {
  const _SearchRow({
    required this.controller,
    required this.filtersActive,
    required this.onChanged,
    required this.onSubmit,
    required this.onClear,
    required this.onFilter,
  });

  final TextEditingController controller;
  final bool filtersActive;
  final ValueChanged<String> onChanged;
  final VoidCallback onSubmit;
  final VoidCallback onClear;
  final VoidCallback onFilter;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        8,
        AniHowSpace.screen,
        8,
      ),
      child: Row(
        children: [
          Expanded(
            child: TextField(
              key: const Key('marketplace-search'),
              controller: controller,
              decoration: InputDecoration(
                hintText: s.searchProduce,
                prefixIcon: const Icon(Icons.search),
                isDense: true,
                suffixIcon: controller.text.isEmpty
                    ? null
                    : IconButton(
                        key: const Key('marketplace-search-clear'),
                        tooltip: s.clear,
                        onPressed: onClear,
                        icon: const Icon(Icons.close),
                      ),
              ),
              onChanged: onChanged,
              onSubmitted: (_) => onSubmit(),
            ),
          ),
          const SizedBox(width: 8),
          IconButton(
            key: const Key('marketplace-filter'),
            tooltip: s.filter,
            onPressed: onFilter,
            style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
            icon: Badge(
              isLabelVisible: filtersActive,
              smallSize: 8,
              child: const Icon(Icons.tune),
            ),
          ),
        ],
      ),
    );
  }
}

class _SearchHeaderDelegate extends SliverPersistentHeaderDelegate {
  _SearchHeaderDelegate({required this.child, required this.extent});

  final Widget child;
  final double extent;

  @override
  double get minExtent => extent;

  @override
  double get maxExtent => extent;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Material(
      color: Theme.of(context).scaffoldBackgroundColor,
      child: child,
    );
  }

  @override
  bool shouldRebuild(covariant _SearchHeaderDelegate oldDelegate) => true;
}

class _UpdatesBanner extends StatelessWidget {
  const _UpdatesBanner({
    required this.count,
    required this.post,
    required this.onOpen,
    required this.onClose,
  });

  final int count;
  final BuyerFarmAnnouncement post;
  final VoidCallback onOpen;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.cardGap,
        AniHowSpace.screen,
        0,
      ),
      child: Material(
        color: theme.cardTheme.color ?? theme.colorScheme.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AniHowSpace.radius),
          side: BorderSide(color: theme.dividerColor),
        ),
        child: InkWell(
          key: const Key('marketplace-updates'),
          onTap: onOpen,
          borderRadius: BorderRadius.circular(AniHowSpace.radius),
          child: SizedBox(
            height: 64,
            child: Row(
              children: [
                const SizedBox(width: 12),
                const Icon(Icons.campaign_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        s.updatesFromFarmsCount(count),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.labelLarge,
                      ),
                      Text(
                        '${post.farmName} · ${post.title}',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                IconButton(
                  key: const Key('marketplace-updates-close'),
                  tooltip: s.hideUpdates,
                  onPressed: onClose,
                  icon: const Icon(Icons.close),
                  style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
                ),
                const Icon(Icons.chevron_right),
                const SizedBox(width: 4),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _MarketFilters {
  const _MarketFilters({
    required this.sort,
    required this.growingMethod,
    required this.cropTypeId,
  });

  final String sort;
  final String? growingMethod;
  final int? cropTypeId;
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.sort,
    required this.growingMethod,
    required this.cropTypeId,
    required this.cropTypes,
  });

  final String sort;
  final String? growingMethod;
  final int? cropTypeId;
  final List<CategoryItem> cropTypes;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  late String _sort;
  String? _growingMethod;
  int? _cropTypeId;

  @override
  void initState() {
    super.initState();
    _sort = widget.sort;
    _growingMethod = widget.growingMethod;
    _cropTypeId = widget.cropTypeId;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    return SafeArea(
      child: Padding(
        padding: AniHowSpace.screenPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(s.sort, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(
                          s.sortNewest,
                          _sort == 'freshest',
                          () => _sort = 'freshest',
                          key: const Key('sort-newest'),
                        ),
                        _chip(
                          s.priceLowHigh,
                          _sort == 'price_asc',
                          () => _sort = 'price_asc',
                        ),
                        _chip(
                          s.priceHighLow,
                          _sort == 'price_desc',
                          () => _sort = 'price_desc',
                        ),
                        _chip(
                          s.inStockFirst,
                          _sort == 'availability',
                          () => _sort = 'availability',
                        ),
                        _chip(
                          s.nearest,
                          _sort == 'nearest',
                          () => _sort = 'nearest',
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    Text(s.growingMethod, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(
                          s.any,
                          _growingMethod == null,
                          () => _growingMethod = null,
                        ),
                        _chip(
                          s.certifiedOrganicFilter,
                          _growingMethod == 'certified_organic',
                          () => _growingMethod = 'certified_organic',
                        ),
                        _chip(
                          s.naturallyGrown,
                          _growingMethod == 'naturally_grown',
                          () => _growingMethod = 'naturally_grown',
                        ),
                      ],
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    Text(s.cropFilter, style: theme.textTheme.titleMedium),
                    const SizedBox(height: 8),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(
                          s.any,
                          _cropTypeId == null,
                          () => _cropTypeId = null,
                        ),
                        for (final cropType in widget.cropTypes)
                          _chip(
                            cropType.labelFor(language),
                            _cropTypeId == cropType.id,
                            () => _cropTypeId = cropType.id,
                            key: ValueKey('crop-${cropType.id}'),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.cardGap),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton(
                    style: OutlinedButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    onPressed: () {
                      setState(() {
                        _sort = 'fair';
                        _growingMethod = null;
                        _cropTypeId = null;
                      });
                    },
                    child: Text(s.clear),
                  ),
                ),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  child: FilledButton(
                    style: FilledButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    onPressed: () {
                      Navigator.of(context).pop(
                        _MarketFilters(
                          sort: _sort,
                          growingMethod: _growingMethod,
                          cropTypeId: _cropTypeId,
                        ),
                      );
                    },
                    child: Text(s.apply),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label, bool selected, VoidCallback select, {Key? key}) {
    return ChoiceChip(
      key: key,
      label: Text(label),
      selected: selected,
      materialTapTargetSize: MaterialTapTargetSize.padded,
      onSelected: (chosen) {
        if (!chosen) {
          return;
        }
        setState(select);
      },
    );
  }
}
