import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/buyer_location.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/crop_language.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/unverified_email_banner.dart';
import 'announcements_feed_screen.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

const _updatesHiddenKey = 'marketplace_updates_hidden_on';
const _pageSize = 15;

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key, this.showAccountMenu = false});

  /// Set by [BuyerShell] only. Pushed copies of this screen omit the menu.
  final bool showAccountMenu;

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
  int? _farmId;
  MarketplaceFlags _flags = const MarketplaceFlags();
  Future<List<FarmOption>>? _farmsFuture;
  List<FarmOption>? _farms;
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

  /// Every listing that matches, from the server. Null until a page says so.
  int? _total;
  Object? _error;
  List<ListingItem> _items = const [];
  List<BuyerFarmAnnouncement> _updates = const [];
  late Future<List<CategoryItem>> _cropTypes;
  List<CategoryItem> _loadedCrops = const [];

  @override
  void initState() {
    super.initState();
    _cropTypes = context.read<AuthController>().api.cropTypes();
    _cropTypes.then((crops) => _loadedCrops = crops, onError: (_) {});
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

  /// How many filters the sheet has on; shown on the filter button.
  int get _activeFilterCount =>
      (_sort != 'fair' ? 1 : 0) +
      (_growingMethod != null ? 1 : 0) +
      (_cropTypeId != null ? 1 : 0) +
      (_farmId != null ? 1 : 0) +
      _flags.count;

  bool get _filtersActive => _activeFilterCount > 0;

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
      _total = null;
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
        farmId: _farmId,
        flags: _flags,
      );
      if (!mounted || request != _requestId) {
        return;
      }
      setState(() {
        _items = feed.items;
        _mixDay = feed.mixDay;
        _total = feed.total;
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
        farmId: _farmId,
        flags: _flags,
      );
      if (!mounted || request != _requestId) {
        return;
      }
      setState(() {
        _page = nextPage;
        _items = [..._items, ...feed.items];
        _total = feed.total ?? _total;
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
    final api = context.read<AuthController>().api;
    final cropTypes = await _cropTypes;
    // The farm filter is optional: the sheet opens at once and the farm
    // chips appear when the list arrives.
    _farmsFuture ??= api.farms().then((farms) => _farms = farms).catchError(
      (Object _) => const <FarmOption>[],
    );
    if (!mounted) {
      return;
    }
    final choice = await showModalBottomSheet<_MarketFilters>(
      context: context,
      isScrollControlled: true,
      builder: (context) => _FilterSheet(
        current: _MarketFilters(
          sort: _sort,
          growingMethod: _growingMethod,
          cropTypeId: _cropTypeId,
          farmId: _farmId,
          flags: _flags,
        ),
        cropTypes: cropTypes,
        farms: _farmsFuture!,
        countFor: _countFor,
      ),
    );
    if (!mounted || choice == null) {
      return;
    }
    await _applyFilters(choice);
  }

  /// How many listings a set of filters would show, for the sheet's button.
  Future<int?> _countFor(_MarketFilters filters) async {
    final feed = await context.read<AuthController>().api.marketplace(
      search: _search.text.trim(),
      cropTypeId: filters.cropTypeId,
      // The order does not change the count; nearest needs a location.
      sort: filters.sort == 'nearest' ? 'fair' : filters.sort,
      category: _category,
      growingMethod: filters.growingMethod,
      farmId: filters.farmId,
      flags: filters.flags,
    );
    return feed.total ?? feed.items.length;
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
      _farmId = choice.farmId;
      _flags = choice.flags;
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
    return Scaffold(
      appBar: mainTabAppBar(
        title: s.marketplace,
        showCart: true,
        showAccountMenu: widget.showAccountMenu,
      ),
      body: Column(
      children: [
        mainTabBodyGap,
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
                        filterCount: _activeFilterCount,
                        onChanged: _onSearchChanged,
                        onSubmit: _submitSearch,
                        onClear: _clearSearch,
                        onFilter: _openFilters,
                      ),
                    ),
                  ),
                ),
                SliverToBoxAdapter(child: _chipRow(s)),
                if (_filtersActive)
                  SliverToBoxAdapter(child: _activeFilterRow(s)),
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
      ),
    );
  }

  /// Each filter that is on, as a chip whose x turns it off.
  Widget _activeFilterRow(AppStrings s) {
    final language = context.read<PreferencesController>().language;
    final chips = <(String, String, VoidCallback)>[
      if (_sort != 'fair')
        ('sort', _sortLabel(s, _sort), () => _sort = 'fair'),
      if (_flags.inStock)
        ('in-stock', s.inStockNow, () => _flags = _flags.copyWith(inStock: false)),
      if (_flags.upcoming)
        ('upcoming', s.preorderFilter, () => _flags = _flags.copyWith(upcoming: false)),
      if (_flags.onlinePayment)
        ('online', s.onlinePaymentFilter, () => _flags = _flags.copyWith(onlinePayment: false)),
      if (_flags.tawad) ('tawad', s.tawadFilter, () => _flags = _flags.copyWith(tawad: false)),
      if (_flags.topRated)
        ('top-rated', s.topRatedFilter, () => _flags = _flags.copyWith(topRated: false)),
      if (_growingMethod != null)
        (
          'growing',
          _growingMethod == 'certified_organic' ? s.certifiedOrganicFilter : s.naturallyGrown,
          () => _growingMethod = null,
        ),
      if (_farmId != null)
        (
          'farm',
          _farms?.where((farm) => farm.id == _farmId).firstOrNull?.name ?? s.farmFilter,
          () => _farmId = null,
        ),
      if (_cropTypeId != null)
        ('crop', _cropName(language) ?? s.cropFilter, () => _cropTypeId = null),
    ];
    return SizedBox(
      key: const Key('marketplace-active-filters'),
      height: 44,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
        children: [
          for (final chip in chips)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: InputChip(
                key: ValueKey('active-filter-${chip.$1}'),
                label: Text(chip.$2),
                onDeleted: () {
                  setState(chip.$3);
                  unawaited(_reload(scrollToTop: true));
                },
              ),
            ),
        ],
      ),
    );
  }

  String? _cropName(CropLanguage language) {
    for (final crop in _loadedCrops) {
      if (crop.id == _cropTypeId) {
        return crop.labelFor(language);
      }
    }
    return null;
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
            s.listingsCount(math.max(_total ?? 0, _items.length)),
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
    required this.filterCount,
    required this.onChanged,
    required this.onSubmit,
    required this.onClear,
    required this.onFilter,
  });

  final TextEditingController controller;
  final int filterCount;
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
              key: const Key('marketplace-filter-count'),
              isLabelVisible: filterCount > 0,
              label: Text('$filterCount'),
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

String _sortLabel(AppStrings s, String sort) => switch (sort) {
  'freshest' => s.sortNewest,
  'price_asc' => s.priceLowHigh,
  'price_desc' => s.priceHighLow,
  'availability' => s.inStockFirst,
  'nearest' => s.nearest,
  _ => s.sortDefault,
};

class _MarketFilters {
  const _MarketFilters({
    required this.sort,
    required this.growingMethod,
    required this.cropTypeId,
    this.farmId,
    this.flags = const MarketplaceFlags(),
  });

  final String sort;
  final String? growingMethod;
  final int? cropTypeId;
  final int? farmId;
  final MarketplaceFlags flags;

  _MarketFilters copyWith({
    String? sort,
    String? Function()? growingMethod,
    int? Function()? cropTypeId,
    int? Function()? farmId,
    MarketplaceFlags? flags,
  }) {
    return _MarketFilters(
      sort: sort ?? this.sort,
      growingMethod: growingMethod == null ? this.growingMethod : growingMethod(),
      cropTypeId: cropTypeId == null ? this.cropTypeId : cropTypeId(),
      farmId: farmId == null ? this.farmId : farmId(),
      flags: flags ?? this.flags,
    );
  }
}

class _FilterSheet extends StatefulWidget {
  const _FilterSheet({
    required this.current,
    required this.cropTypes,
    required this.farms,
    required this.countFor,
  });

  final _MarketFilters current;
  final List<CategoryItem> cropTypes;
  final Future<List<FarmOption>> farms;

  /// How many listings the chosen filters would show.
  final Future<int?> Function(_MarketFilters filters) countFor;

  @override
  State<_FilterSheet> createState() => _FilterSheetState();
}

class _FilterSheetState extends State<_FilterSheet> {
  static const _cropsShown = 8;

  late _MarketFilters _filters;
  bool _allCrops = false;
  int? _count;
  int _countRequest = 0;
  Timer? _countTimer;
  List<FarmOption> _farms = const [];

  @override
  void initState() {
    super.initState();
    _filters = widget.current;
    _recount();
    widget.farms.then((farms) {
      if (mounted) {
        setState(() => _farms = farms);
      }
    });
  }

  @override
  void dispose() {
    _countTimer?.cancel();
    super.dispose();
  }

  void _set(_MarketFilters next) {
    setState(() => _filters = next);
    _recount();
  }

  void _recount() {
    _countTimer?.cancel();
    _countTimer = Timer(const Duration(milliseconds: 300), () async {
      final request = ++_countRequest;
      try {
        final count = await widget.countFor(_filters);
        if (mounted && request == _countRequest) {
          setState(() => _count = count);
        }
      } catch (_) {
        if (mounted && request == _countRequest) {
          setState(() => _count = null);
        }
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final language = context.watch<PreferencesController>().language;
    final flags = _filters.flags;
    final crops = widget.cropTypes;
    final selectedIndex = crops.indexWhere((crop) => crop.id == _filters.cropTypeId);
    final hidden = crops.length - _cropsShown;
    final shownCrops = _allCrops || hidden <= 0 || selectedIndex >= _cropsShown
        ? crops
        : crops.take(_cropsShown).toList();

    Widget heading(String text) => Padding(
      padding: const EdgeInsets.only(top: AniHowSpace.cardGap, bottom: 8),
      child: Text(
        text.toUpperCase(),
        style: theme.textTheme.labelSmall?.copyWith(
          color: theme.colorScheme.onSurface.withValues(alpha: 0.72),
          fontWeight: FontWeight.w700,
          letterSpacing: 0.8,
        ),
      ),
    );

    Widget toggle(
      String key,
      IconData icon,
      String title,
      String hint,
      bool value,
      MarketplaceFlags Function(bool on) change,
    ) {
      return SwitchListTile(
        key: ValueKey('filter-$key'),
        contentPadding: EdgeInsets.zero,
        secondary: Icon(icon),
        title: Text(title),
        subtitle: Text(hint),
        value: value,
        onChanged: (on) => _set(_filters.copyWith(flags: change(on))),
      );
    }

    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 4, AniHowSpace.screen, AniHowSpace.screen),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    s.filtersTitle,
                    style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                TextButton(
                  key: const Key('filters-reset'),
                  onPressed: () => _set(
                    const _MarketFilters(sort: 'fair', growingMethod: null, cropTypeId: null),
                  ),
                  child: Text(s.resetAll),
                ),
              ],
            ),
            const Divider(height: 1),
            Flexible(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    heading(s.sort),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        for (final sort in const ['fair', 'freshest', 'price_asc', 'price_desc', 'availability', 'nearest'])
                          _chip(
                            _sortLabel(s, sort),
                            _filters.sort == sort,
                            () => _set(_filters.copyWith(sort: sort)),
                            key: Key(sort == 'fair' ? 'sort-default' : sort == 'freshest' ? 'sort-newest' : 'sort-$sort'),
                          ),
                      ],
                    ),
                    heading(s.showFilters),
                    toggle('in-stock', Icons.check_circle_outline, s.inStockNow, s.inStockNowHint,
                        flags.inStock, (on) => flags.copyWith(inStock: on)),
                    toggle('upcoming', Icons.event_outlined, s.preorderFilter, s.preorderFilterHint,
                        flags.upcoming, (on) => flags.copyWith(upcoming: on)),
                    toggle('online', Icons.qr_code_2, s.onlinePaymentFilter, s.onlinePaymentFilterHint,
                        flags.onlinePayment, (on) => flags.copyWith(onlinePayment: on)),
                    toggle('tawad', Icons.local_offer_outlined, s.tawadFilter, s.tawadFilterHint,
                        flags.tawad, (on) => flags.copyWith(tawad: on)),
                    toggle('top-rated', Icons.star_outline, s.topRatedFilter, s.topRatedFilterHint,
                        flags.topRated, (on) => flags.copyWith(topRated: on)),
                    heading(s.growingMethod),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(s.any, _filters.growingMethod == null,
                            () => _set(_filters.copyWith(growingMethod: () => null))),
                        _chip(s.certifiedOrganicFilter, _filters.growingMethod == 'certified_organic',
                            () => _set(_filters.copyWith(growingMethod: () => 'certified_organic'))),
                        _chip(s.naturallyGrown, _filters.growingMethod == 'naturally_grown',
                            () => _set(_filters.copyWith(growingMethod: () => 'naturally_grown'))),
                      ],
                    ),
                    if (_farms.isNotEmpty) ...[
                      heading(s.farmFilter),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          _chip(s.allFarms, _filters.farmId == null,
                              () => _set(_filters.copyWith(farmId: () => null))),
                          for (final farm in _farms)
                            _chip(
                              farm.name,
                              _filters.farmId == farm.id,
                              () => _set(_filters.copyWith(farmId: () => farm.id)),
                              key: ValueKey('farm-${farm.id}'),
                            ),
                        ],
                      ),
                    ],
                    heading(s.cropFilter),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _chip(s.any, _filters.cropTypeId == null,
                            () => _set(_filters.copyWith(cropTypeId: () => null))),
                        for (final cropType in shownCrops)
                          _chip(
                            cropType.labelFor(language),
                            _filters.cropTypeId == cropType.id,
                            () => _set(_filters.copyWith(cropTypeId: () => cropType.id)),
                            key: ValueKey('crop-${cropType.id}'),
                          ),
                        if (shownCrops.length < crops.length)
                          ActionChip(
                            key: const Key('crops-more'),
                            label: Text(s.moreItems(crops.length - shownCrops.length)),
                            onPressed: () => setState(() => _allCrops = true),
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
                    style: OutlinedButton.styleFrom(minimumSize: const Size(48, 48)),
                    onPressed: () => _set(
                      const _MarketFilters(sort: 'fair', growingMethod: null, cropTypeId: null),
                    ),
                    child: Text(s.clear),
                  ),
                ),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  flex: 2,
                  child: FilledButton(
                    key: const Key('filters-apply'),
                    style: FilledButton.styleFrom(minimumSize: const Size(48, 48)),
                    onPressed: () {
                      // No count request may land after the real reload.
                      _countTimer?.cancel();
                      _countRequest++;
                      Navigator.of(context).pop(_filters);
                    },
                    child: Text(s.showResultsCount(_count)),
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
        select();
      },
    );
  }
}
