import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/farm_map_card.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

/// Space under the tab divider once pinned chrome is already cleared.
const double _tabScrollTop = AniHowSpace.cardGap;

/// The pinned toolbar covers the body only during the last part of the
/// header scroll. Add that overlap on top of the 12px gap.
double _tabClearance(BuildContext context) {
  final toolbar = MediaQuery.paddingOf(context).top + kToolbarHeight;
  final nested = context.findAncestorStateOfType<NestedScrollViewState>();
  final controller = nested?.outerController;
  if (controller == null ||
      !controller.hasClients ||
      !controller.position.hasContentDimensions) {
    return _tabScrollTop;
  }
  final uncovered =
      controller.position.maxScrollExtent - controller.position.pixels;
  final covered = (toolbar - uncovered).clamp(0.0, toolbar);
  return _tabScrollTop + covered;
}

/// Cover body plus the half of the logo that hangs below it.
const double _expandedBody = 240 + 42;

/// Opens a farm directions link outside the app. Tests replace this.
Future<bool> Function(Uri uri) launchFarmDirections = (Uri uri) {
  return launchUrl(uri, mode: LaunchMode.externalApplication);
};

/// Review-weighted rating across a farm's shops.
///
/// A shop with a rating and no review count counts as one review.
class FarmRatingSummary {
  const FarmRatingSummary({required this.rating, required this.reviewsCount});

  final String rating;
  final int reviewsCount;
}

FarmRatingSummary? weightedFarmRating(List<ShopProfile> shops) {
  var weightedSum = 0.0;
  var totalReviews = 0;
  for (final shop in shops) {
    final raw = shop.averageRating?.trim();
    if (raw == null || raw.isEmpty) {
      continue;
    }
    final rating = double.tryParse(raw);
    if (rating == null) {
      continue;
    }
    final count = shop.reviewsCount > 0 ? shop.reviewsCount : 1;
    weightedSum += rating * count;
    totalReviews += count;
  }
  if (totalReviews == 0) {
    return null;
  }
  return FarmRatingSummary(
    rating: (weightedSum / totalReviews).toStringAsFixed(1),
    reviewsCount: totalReviews,
  );
}

void openBuyerFarmPage(BuildContext context, int farmId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => FarmPageScreen(farmId: farmId)),
  );
}

class FarmPageScreen extends StatefulWidget {
  const FarmPageScreen({super.key, required this.farmId});

  final int farmId;

  @override
  State<FarmPageScreen> createState() => _FarmPageScreenState();
}

class _FarmPageScreenState extends State<FarmPageScreen>
    with TickerProviderStateMixin {
  final _search = TextEditingController();
  final Set<int> _saved = {};
  final Set<int> _busy = {};
  late final TabController _tabs;
  late Future<FarmProfile> _farm;
  late Future<List<ShopProfile>> _shops;
  bool? _followed;
  bool _followBusy = false;
  final List<ListingItem> _products = [];
  int? _productsTotal;
  int _productsPage = 0;
  int _productToken = 0;
  bool _productsBusy = false;
  bool _productsLoading = true;
  bool _productsLoadingMore = false;
  bool _productsHasMore = true;
  String? _productsError;

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 5, vsync: this);
    _farm = _loadFarm();
    _shops = _loadShops();
    _loadSaved();
    _productsBusy = true;
    unawaited(_fetchProducts(reset: true, notify: false));
  }

  @override
  void dispose() {
    _tabs.dispose();
    _search.dispose();
    super.dispose();
  }

  bool get _isBuyer {
    try {
      return context.read<AuthController>().user?.isBuyer ?? false;
    } on ProviderNotFoundException {
      return false;
    }
  }

  Future<FarmProfile> _loadFarm() {
    return context.read<AuthController>().api.farm(widget.farmId);
  }

  Future<List<ShopProfile>> _loadShops() async {
    final shops = await context.read<AuthController>().api.buyerShops();
    return shops.where((shop) => shop.farmId == widget.farmId).toList();
  }

  Future<void> _reloadFarm() async {
    final farm = _loadFarm();
    setState(() => _farm = farm);
    await farm;
  }

  Future<void> _reloadShops() async {
    final shops = _loadShops();
    setState(() => _shops = shops);
    await shops;
  }

  Future<void> _reload() async {
    await Future.wait([
      _reloadFarm(),
      _reloadShops(),
      _loadSaved(),
      _fetchProducts(reset: true),
    ]);
  }

  Future<void> _loadSaved() async {
    try {
      final saved = await context.read<AuthController>().api.shopFavorites();
      if (!mounted) {
        return;
      }
      setState(() {
        _saved
          ..clear()
          ..addAll(saved.map((favorite) => favorite.sellerId));
      });
    } on ApiException {
      // Hearts from the shop list still show. A tap tries again.
    }
  }

  Future<void> _fetchProducts({required bool reset, bool notify = true}) async {
    if (!reset && (_productsBusy || !_productsHasMore)) {
      return;
    }
    final token = reset ? ++_productToken : _productToken;
    final page = reset ? 1 : _productsPage + 1;
    void markLoading() {
      _productsBusy = true;
      _productsError = null;
      if (reset) {
        _products.clear();
        _productsPage = 0;
        _productsHasMore = true;
        _productsLoading = true;
        _productsLoadingMore = false;
      } else {
        _productsLoadingMore = _products.isNotEmpty;
        _productsLoading = _products.isEmpty;
      }
    }

    if (notify) {
      setState(markLoading);
    } else {
      markLoading();
    }
    try {
      final feed = await context.read<AuthController>().api.marketplace(
        farmId: widget.farmId,
        page: page,
        sort: 'freshest',
      );
      if (!mounted || token != _productToken) {
        return;
      }
      setState(() {
        if (reset) {
          _productsTotal = feed.total;
        }
        if (feed.items.isEmpty) {
          _productsHasMore = false;
          if (reset) {
            _productsPage = 1;
          }
        } else if (reset) {
          _products
            ..clear()
            ..addAll(feed.items);
          _productsPage = 1;
          _productsHasMore = true;
        } else {
          _products.addAll(feed.items);
          _productsPage = page;
          _productsHasMore = true;
        }
        _productsLoading = false;
        _productsLoadingMore = false;
        _productsBusy = false;
      });
    } on ApiException catch (error) {
      if (!mounted || token != _productToken) {
        return;
      }
      setState(() {
        _productsError = error.message;
        _productsLoading = false;
        _productsLoadingMore = false;
        _productsBusy = false;
      });
    } catch (_) {
      if (!mounted || token != _productToken) {
        return;
      }
      setState(() {
        _productsError = AppStrings.read(context).somethingWentWrong;
        _productsLoading = false;
        _productsLoadingMore = false;
        _productsBusy = false;
      });
    }
  }

  bool _onProductsScroll(ScrollNotification notification) {
    if (notification.metrics.axis != Axis.vertical) {
      return false;
    }
    if (notification is! ScrollUpdateNotification) {
      return false;
    }
    final metrics = notification.metrics;
    if (metrics.maxScrollExtent <= 0 || metrics.pixels <= 0) {
      return false;
    }
    if (metrics.pixels >= metrics.maxScrollExtent - 280) {
      unawaited(_fetchProducts(reset: false));
    }
    return false;
  }

  Future<void> _toggle(ShopProfile shop) async {
    if (_busy.contains(shop.id)) {
      return;
    }
    final saved = _saved.contains(shop.id);
    setState(() {
      _busy.add(shop.id);
      if (saved) {
        _saved.remove(shop.id);
      } else {
        _saved.add(shop.id);
      }
    });
    try {
      final api = context.read<AuthController>().api;
      if (saved) {
        await api.removeShopFavorite(shop.id);
      } else {
        await api.addShopFavorite(shop.id);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(AppStrings.read(context).storeSavedToFavorites),
            ),
          );
        }
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        if (saved) {
          _saved.add(shop.id);
        } else {
          _saved.remove(shop.id);
        }
      });
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _busy.remove(shop.id));
      }
    }
  }

  Future<void> _toggleFollow(FarmProfile farm) async {
    if (_followBusy || !_isBuyer) {
      return;
    }
    final followed = _followed ?? farm.isFavorited;
    setState(() {
      _followBusy = true;
      _followed = !followed;
    });
    try {
      final api = context.read<AuthController>().api;
      if (followed) {
        await api.removeFarmFavorite(farm.id);
      } else {
        await api.addFarmFavorite(farm.id);
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _followed = followed);
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _followBusy = false);
      }
    }
  }

  Future<void> _directions(FarmProfile farm) async {
    final uri = farm.directionsUri;
    if (uri == null) {
      return;
    }
    final opened = await launchFarmDirections(uri);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).couldNotOpenLink)),
      );
    }
  }

  List<ShopProfile> _visible(List<ShopProfile> shops) {
    final query = _search.text.trim().toLowerCase();
    final sellers = [...shops]
      ..sort(
        (a, b) => a.shopName.toLowerCase().compareTo(b.shopName.toLowerCase()),
      );
    if (query.isEmpty) {
      return sellers;
    }
    return sellers.where((shop) {
      return '${shop.shopName} ${shop.name} ${shop.location ?? ''}'
          .toLowerCase()
          .contains(query);
    }).toList();
  }

  FarmRatingSummary? _averageRating(List<ShopProfile> shops) {
    return weightedFarmRating(shops);
  }

  void _openListing(ListingItem listing) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ListingDetailScreen(listingId: listing.id),
      ),
    );
  }

  int _productColumns(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = MediaQuery.textScalerOf(context).scale(1);
    if (width < 340 || scale >= 1.3) {
      return 1;
    }
    return 2;
  }

  bool _tabsScroll(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final scale = MediaQuery.textScalerOf(context).scale(1);
    return width < 360 || scale >= 1.15;
  }

  Tab _farmTab(Key key, String label) {
    return Tab(
      key: key,
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        softWrap: false,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      body: FutureBuilder<FarmProfile>(
        future: _farm,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const _FarmLoading();
          }
          if (snapshot.hasError || snapshot.data == null) {
            return AsyncViewError(onRetry: _reloadFarm);
          }
          return _page(s, snapshot.data!);
        },
      ),
    );
  }

  Widget _page(AppStrings s, FarmProfile farm) {
    final top = MediaQuery.paddingOf(context).top;
    final scrollTabs = _tabsScroll(context);
    final theme = Theme.of(context);
    return RefreshIndicator(
      onRefresh: _reload,
      child: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) {
          return [
            SliverAppBar(
              pinned: true,
              elevation: 0,
              scrolledUnderElevation: 0,
              expandedHeight: _expandedBody,
              backgroundColor: theme.colorScheme.surface,
              surfaceTintColor: Colors.transparent,
              leading: const _CircleBackButton(),
              flexibleSpace: _FarmFlexibleHeader(
                farm: farm,
                maxHeight: top + _expandedBody,
              ),
            ),
            SliverToBoxAdapter(child: _belowHeader(s, farm)),
            SliverOverlapAbsorber(
              handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
              sliver: SliverPersistentHeader(
                pinned: true,
                delegate: _FarmTabHeader(
                  TabBar(
                    controller: _tabs,
                    isScrollable: scrollTabs,
                    tabAlignment: scrollTabs
                        ? TabAlignment.center
                        : TabAlignment.fill,
                    labelPadding: const EdgeInsets.symmetric(horizontal: 4),
                    indicatorColor: AniHowColors.brand,
                    indicatorWeight: 3,
                    dividerColor: theme.dividerColor,
                    dividerHeight: 1,
                    labelColor: AniHowColors.brand,
                    tabs: [
                      _farmTab(
                        const Key('farm-tab-products'),
                        s.farmProductsTab,
                      ),
                      _farmTab(const Key('farm-tab-shops'), s.farmShopsTab),
                      _farmTab(const Key('farm-tab-about'), s.about),
                      _farmTab(const Key('farm-tab-updates'), s.farmUpdates),
                      _farmTab(const Key('farm-tab-photos'), s.farmPhotos),
                    ],
                  ),
                ),
              ),
            ),
          ];
        },
        body: TabBarView(
          controller: _tabs,
          children: [
            _productsTab(s),
            _shopsTab(s),
            _aboutTab(s, farm),
            _updatesTab(s, farm),
            _photosTab(s, farm),
          ],
        ),
      ),
    );
  }

  Widget _belowHeader(AppStrings s, FarmProfile farm) {
    final latest = farm.announcements.isEmpty ? null : farm.announcements.first;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.cardGap,
        AniHowSpace.screen,
        AniHowSpace.cardGap,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _FarmIdentity(farm: farm, strings: s),
          const SizedBox(height: AniHowSpace.cardGap),
          FutureBuilder<List<ShopProfile>>(
            future: _shops,
            builder: (context, snapshot) {
              return _StatsStrip(
                farm: farm,
                rating: _averageRating(snapshot.data ?? const []),
                onRating: () => _tabs.animateTo(1),
              );
            },
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          _actions(s, farm),
          if (latest != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            _LatestUpdateCard(
              announcement: latest,
              onSeeAll: () => _tabs.animateTo(3),
            ),
          ],
        ],
      ),
    );
  }

  Widget _actions(AppStrings s, FarmProfile farm) {
    final followed = _followed ?? farm.isFavorited;
    final buyer = context.watch<AuthController>().user?.isBuyer ?? false;
    final follow = followed
        ? FilledButton.tonalIcon(
            key: const Key('farm-follow'),
            onPressed: _followBusy ? null : () => _toggleFollow(farm),
            icon: const Icon(Icons.favorite),
            label: Text(
              s.followingFarm,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          )
        : FilledButton.icon(
            key: const Key('farm-follow'),
            onPressed: _followBusy ? null : () => _toggleFollow(farm),
            icon: const Icon(Icons.favorite_outline),
            label: Text(
              s.followFarm,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          );
    final showDirections = farm.canGetDirections;
    return Row(
      children: [
        if (buyer) Expanded(child: follow),
        if (buyer && showDirections) const SizedBox(width: AniHowSpace.cardGap),
        if (showDirections)
          Expanded(
            child: OutlinedButton.icon(
              key: const Key('farm-directions'),
              onPressed: () => _directions(farm),
              icon: const Icon(Icons.directions_outlined),
              label: Text(
                s.directions,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ),
      ],
    );
  }

  Widget _productsTab(AppStrings s) {
    return Builder(
      builder: (context) {
        final columns = _productColumns(context);
        return NotificationListener<ScrollNotification>(
          onNotification: _onProductsScroll,
          child: CustomScrollView(
            key: const PageStorageKey<String>('farm-products'),
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverOverlapInjector(
                handle: NestedScrollView.sliverOverlapAbsorberHandleFor(
                  context,
                ),
              ),
              if (_productsLoading && _products.isEmpty)
                SliverPadding(
                  padding: AniHowSpace.screenPadding,
                  sliver: _placeholderGrid(columns),
                )
              else if (_productsError != null && _products.isEmpty)
                SliverFillRemaining(
                  hasScrollBody: false,
                  child: _CenteredStatus(
                    icon: Icons.cloud_off_outlined,
                    message: _productsError!,
                    action: FilledButton(
                      key: const Key('farm-products-retry'),
                      onPressed: () => _fetchProducts(reset: true),
                      child: Text(s.retry),
                    ),
                  ),
                )
              else ...[
                const SliverToBoxAdapter(child: _PinnedTabClearance()),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(
                      AniHowSpace.screen,
                      0,
                      AniHowSpace.screen,
                      AniHowSpace.cardGap,
                    ),
                    child: Text(
                      s.productsFromFarm(_productsTotal ?? _products.length),
                      key: const Key('farm-products-count'),
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurfaceVariant,
                      ),
                    ),
                  ),
                ),
                if (_products.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: _CenteredStatus(
                      icon: Icons.shopping_basket_outlined,
                      message: s.noProduceListed,
                    ),
                  )
                else
                  SliverPadding(
                    padding: const EdgeInsets.fromLTRB(
                      AniHowSpace.screen,
                      0,
                      AniHowSpace.screen,
                      AniHowSpace.screen,
                    ),
                    sliver: SliverList(
                      delegate: SliverChildBuilderDelegate((context, row) {
                        final start = row * columns;
                        return Padding(
                          padding: const EdgeInsets.only(
                            bottom: AniHowSpace.cardGap,
                          ),
                          child: _ShareRowHeight(
                            children: [
                              for (var column = 0; column < columns; column++)
                                start + column < _products.length
                                    ? _productCard(_products[start + column])
                                    : const SizedBox.shrink(),
                            ],
                          ),
                        );
                      }, childCount: (_products.length / columns).ceil()),
                    ),
                  ),
                if (_productsLoadingMore)
                  const SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Center(
                        child: SizedBox(
                          width: 24,
                          height: 24,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                      ),
                    ),
                  ),
              ],
            ],
          ),
        );
      },
    );
  }

  Widget _productCard(ListingItem listing) {
    return ProduceCard(
      listing: listing,
      style: ProduceCardStyle.poster,
      showSeller: true,
      stackCropAndSeller: true,
      expandPhoto: true,
      onTap: () => _openListing(listing),
      onSellerTap: listing.sellerId == null
          ? null
          : () => openBuyerShop(context, listing.sellerId!),
    );
  }

  Widget _placeholderGrid(int columns) {
    return SliverList(
      delegate: SliverChildBuilderDelegate(
        (context, row) => Padding(
          padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
          child: Row(
            children: [
              for (var column = 0; column < columns; column++) ...[
                if (column > 0) const SizedBox(width: AniHowSpace.cardGap),
                const Expanded(
                  child: AspectRatio(aspectRatio: 4 / 3, child: _GreyBlock()),
                ),
              ],
            ],
          ),
        ),
        childCount: 2,
      ),
    );
  }

  Widget _shopsTab(AppStrings s) {
    return AsyncView<List<ShopProfile>>(
      future: _shops,
      onRetry: _reloadShops,
      isEmpty: (_) => false,
      builder: (context, shops) => _shopSection(s, shops),
    );
  }

  Widget _shopSection(AppStrings s, List<ShopProfile> shops) {
    final sellers = _visible(shops);
    return Builder(
      builder: (context) => CustomScrollView(
        key: const PageStorageKey<String>('farm-shops'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverOverlapInjector(
            handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
          ),
          const SliverToBoxAdapter(child: _PinnedTabClearance()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                Text(
                  s.shopsAtThisFarm(shops.length),
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                TextField(
                  controller: _search,
                  decoration: InputDecoration(
                    hintText: s.searchShops,
                    prefixIcon: const Icon(Icons.search),
                  ),
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                if (shops.isEmpty)
                  _CenteredStatus(
                    icon: Icons.storefront_outlined,
                    message: s.noShopsAtFarm,
                  )
                else if (sellers.isEmpty)
                  _CenteredStatus(
                    icon: Icons.search_off,
                    message: s.noShopsMatch,
                  )
                else
                  for (var index = 0; index < sellers.length; index++) ...[
                    if (index > 0) const SizedBox(height: AniHowSpace.cardGap),
                    _SellerCard(
                      shop: sellers[index],
                      saved: _saved.contains(sellers[index].id),
                      onToggle: () => _toggle(sellers[index]),
                    ),
                  ],
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _aboutTab(AppStrings s, FarmProfile farm) {
    final description = farm.description?.trim();
    final pickup = farm.pickupPoint?.trim();
    final contactPerson = farm.contactPerson?.trim();
    final certifier = farm.organicCertifier?.trim();
    final until = farm.organicCertifiedUntil?.trim();
    return Builder(
      builder: (context) => CustomScrollView(
        key: const PageStorageKey<String>('farm-about'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverOverlapInjector(
            handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
          ),
          const SliverToBoxAdapter(child: _PinnedTabClearance()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                _AboutCard(
                  icon: Icons.info_outline,
                  title: s.about,
                  body: description == null || description.isEmpty
                      ? s.noDescriptionYet
                      : description,
                ),
                if (pickup != null && pickup.isNotEmpty) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  _AboutCard(
                    icon: Icons.place_outlined,
                    title: s.pickupPoint,
                    body: pickup,
                  ),
                ],
                if (farm.canGetDirections) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  _SurfaceCard(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _AboutHeading(
                          icon: Icons.map_outlined,
                          title: s.location,
                        ),
                        const SizedBox(height: AniHowSpace.cardGap),
                        if (farm.hasPin)
                          FarmMapCard(
                            latitude: farm.latitude!,
                            longitude: farm.longitude!,
                          )
                        else
                          Text(farm.placeLabel),
                        const SizedBox(height: AniHowSpace.cardGap),
                        OutlinedButton.icon(
                          onPressed: () => _directions(farm),
                          icon: const Icon(Icons.directions_outlined),
                          label: Text(s.directions),
                        ),
                      ],
                    ),
                  ),
                ],
                if (contactPerson != null && contactPerson.isNotEmpty) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  _AboutCard(
                    icon: Icons.person_outline,
                    title: s.farmContact,
                    body: contactPerson,
                    extra: s.farmContactBuyerHint,
                  ),
                ],
                if (farm.isCertified) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  _AboutCard(
                    key: const Key('farm-organic'),
                    icon: Icons.eco_outlined,
                    title: s.organicCertified,
                    body: [
                      if (certifier != null && certifier.isNotEmpty)
                        s.certifiedBy(certifier),
                      if (until != null && until.isNotEmpty)
                        s.validUntil(until),
                    ].join('\n'),
                  ),
                ],
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _updatesTab(AppStrings s, FarmProfile farm) {
    if (farm.announcements.isEmpty) {
      return _statusScroll(
        icon: Icons.campaign_outlined,
        message: s.noUpdatesYet,
        storageKey: 'farm-updates',
      );
    }
    return Builder(
      builder: (context) => CustomScrollView(
        key: const PageStorageKey<String>('farm-updates'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverOverlapInjector(
            handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
          ),
          const SliverToBoxAdapter(child: _PinnedTabClearance()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            sliver: SliverList.separated(
              itemCount: farm.announcements.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(height: AniHowSpace.section),
              itemBuilder: (context, index) => _UpdateTimeline(
                announcement: farm.announcements[index],
                showLine: index < farm.announcements.length - 1,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _photosTab(AppStrings s, FarmProfile farm) {
    if (farm.photos.isEmpty) {
      return _statusScroll(
        icon: Icons.photo_library_outlined,
        message: s.noFarmPhotos,
        storageKey: 'farm-photos',
      );
    }
    return Builder(
      builder: (context) => CustomScrollView(
        key: const PageStorageKey<String>('farm-photos'),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverOverlapInjector(
            handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
          ),
          const SliverToBoxAdapter(child: _PinnedTabClearance()),
          SliverPadding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            sliver: SliverGrid(
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                mainAxisSpacing: AniHowSpace.cardGap,
                crossAxisSpacing: AniHowSpace.cardGap,
                childAspectRatio: 0.78,
              ),
              delegate: SliverChildBuilderDelegate((context, index) {
                final photo = farm.photos[index];
                final caption = photo.caption?.trim();
                return InkWell(
                  onTap: () => _openPhotos(farm.photos, index),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Expanded(
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(12),
                          child: Image.network(
                            photo.gridUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => const _GreyBlock(),
                          ),
                        ),
                      ),
                      if (caption != null && caption.isNotEmpty)
                        Padding(
                          padding: const EdgeInsets.only(top: 4),
                          child: Text(
                            caption,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.labelSmall,
                          ),
                        ),
                    ],
                  ),
                );
              }, childCount: farm.photos.length),
            ),
          ),
        ],
      ),
    );
  }

  Widget _statusScroll({
    required IconData icon,
    required String message,
    required String storageKey,
  }) {
    return Builder(
      builder: (context) => CustomScrollView(
        key: PageStorageKey<String>(storageKey),
        physics: const AlwaysScrollableScrollPhysics(),
        slivers: [
          SliverOverlapInjector(
            handle: NestedScrollView.sliverOverlapAbsorberHandleFor(context),
          ),
          SliverFillRemaining(
            hasScrollBody: false,
            child: _CenteredStatus(icon: icon, message: message),
          ),
        ],
      ),
    );
  }

  void _openPhotos(List<FarmPhotoItem> photos, int index) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => _PhotoViewer(photos: photos, initialIndex: index),
      ),
    );
  }
}

class _CircleBackButton extends StatelessWidget {
  const _CircleBackButton();

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.all(8),
      child: Material(
        color: theme.colorScheme.surface.withValues(alpha: 0.92),
        shape: const CircleBorder(),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          customBorder: const CircleBorder(),
          onTap: () => Navigator.of(context).maybePop(),
          child: Icon(Icons.arrow_back, color: theme.colorScheme.onSurface),
        ),
      ),
    );
  }
}

class _FarmFlexibleHeader extends StatelessWidget {
  const _FarmFlexibleHeader({required this.farm, required this.maxHeight});

  final FarmProfile farm;
  final double maxHeight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final top = MediaQuery.paddingOf(context).top;
    const coverBody = 240.0;
    const logoSize = 84.0;
    final coverHeight = top + coverBody;
    return LayoutBuilder(
      builder: (context, constraints) {
        final current = constraints.maxHeight;
        final collapsed = top + kToolbarHeight;
        final span = math.max(1.0, maxHeight - collapsed);
        final t = ((maxHeight - current) / span).clamp(0.0, 1.0);
        final titleOpacity = ((t - 0.72) / 0.28).clamp(0.0, 1.0);
        return ClipRect(
          child: Stack(
            fit: StackFit.expand,
            children: [
              Positioned(
                top: 0,
                left: 0,
                right: 0,
                height: math.min(coverHeight, current),
                child: _FarmCover(farm: farm),
              ),
              if (current > coverHeight)
                Positioned(
                  top: coverHeight - (logoSize / 2),
                  left: AniHowSpace.screen,
                  child: _FarmLogo(farm: farm),
                ),
              Positioned(
                left: 64,
                right: AniHowSpace.screen,
                bottom: 0,
                height: kToolbarHeight,
                child: IgnorePointer(
                  child: Opacity(
                    opacity: titleOpacity,
                    child: Align(
                      alignment: Alignment.centerLeft,
                      child: Text(
                        farm.name,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: theme.colorScheme.onSurface,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _FarmIdentity extends StatelessWidget {
  const _FarmIdentity({required this.farm, required this.strings});

  final FarmProfile farm;
  final AppStrings strings;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final place = farm.placeLabel;
    final accent = theme.brightness == Brightness.dark
        ? AniHowColors.sage
        : AniHowColors.inStock;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          farm.name,
          key: const Key('farm-name'),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
          style: theme.textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        if (farm.isCertified) ...[
          const SizedBox(height: 8),
          DecoratedBox(
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(Icons.eco, size: 16, color: accent),
                  const SizedBox(width: 4),
                  Flexible(
                    child: Text(
                      strings.organicCertified,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.labelLarge?.copyWith(
                        color: accent,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
        if (place.isNotEmpty) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              Icon(
                Icons.location_on_outlined,
                size: 18,
                color: theme.colorScheme.onSurfaceVariant,
              ),
              const SizedBox(width: 4),
              Expanded(
                child: Text(
                  place,
                  key: const Key('farm-place'),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: theme.colorScheme.onSurfaceVariant,
                  ),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }
}

class _FarmCover extends StatelessWidget {
  const _FarmCover({required this.farm});

  final FarmProfile farm;

  @override
  Widget build(BuildContext context) {
    final cover = farm.coverPhotoUrl;
    return Stack(
      key: const Key('farm-header-cover'),
      fit: StackFit.expand,
      children: [
        if (farm.hasCoverPhoto && cover != null)
          Image.network(
            cover,
            key: const Key('farm-cover'),
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => const _CoverFallback(),
          )
        else
          const _CoverFallback(),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x00000000), Color(0x99000000)],
              stops: [0.45, 1],
            ),
          ),
        ),
      ],
    );
  }
}

class _CoverFallback extends StatelessWidget {
  const _CoverFallback();

  @override
  Widget build(BuildContext context) {
    const spots = <(double, double, IconData)>[
      (24, 36, Icons.eco_outlined),
      (120, 18, Icons.grass),
      (220, 70, Icons.spa_outlined),
      (300, 28, Icons.eco_outlined),
      (70, 120, Icons.spa_outlined),
      (180, 150, Icons.grass),
      (280, 130, Icons.eco_outlined),
    ];
    return DecoratedBox(
      key: const Key('farm-cover-fallback'),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF3C8F62), AniHowColors.brand, Color(0xFF143C28)],
        ),
      ),
      child: Stack(
        children: [
          for (final spot in spots)
            Positioned(
              left: spot.$1,
              top: spot.$2,
              child: Icon(
                spot.$3,
                size: 42,
                color: Colors.white.withValues(alpha: 0.14),
              ),
            ),
        ],
      ),
    );
  }
}

class _FarmLogo extends StatelessWidget {
  const _FarmLogo({required this.farm});

  final FarmProfile farm;

  @override
  Widget build(BuildContext context) {
    final surface = Theme.of(context).colorScheme.surface;
    return Container(
      key: const Key('farm-logo'),
      width: 84,
      height: 84,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: surface,
        border: Border.all(color: surface, width: 4),
      ),
      child: AniHowAvatar(
        name: farm.name,
        imageUrl: farm.logoImageUrl,
        radius: 38,
        backgroundColor: AniHowColors.brand,
        foregroundColor: Theme.of(context).colorScheme.onPrimary,
      ),
    );
  }
}

class _StatsStrip extends StatelessWidget {
  const _StatsStrip({
    required this.farm,
    required this.rating,
    required this.onRating,
  });

  final FarmProfile farm;
  final FarmRatingSummary? rating;
  final VoidCallback onRating;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return _SurfaceCard(
      padding: EdgeInsets.zero,
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(
              child: _StatCell(
                key: const Key('farm-rating'),
                tooltip: s.farmRating,
                onTap: onRating,
                value: rating == null
                    ? null
                    : Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            size: 18,
                            color: AniHowColors.pending,
                          ),
                          const SizedBox(width: 2),
                          Text(rating!.rating),
                        ],
                      ),
                label: rating == null
                    ? s.farmNoReviews
                    : s.farmReviewCount(rating!.reviewsCount),
              ),
            ),
            const _StatDivider(),
            Expanded(
              child: _StatCell(
                value: Text('${farm.farmerSellersCount}'),
                label: s.farmShopsTab,
              ),
            ),
            const _StatDivider(),
            Expanded(
              child: _StatCell(
                value: Text('${farm.favoritesCount}'),
                label: s.followers,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _StatDivider extends StatelessWidget {
  const _StatDivider();

  @override
  Widget build(BuildContext context) {
    return VerticalDivider(
      width: 1,
      thickness: 1,
      color: Theme.of(context).colorScheme.outlineVariant,
    );
  }
}

class _StatCell extends StatelessWidget {
  const _StatCell({
    super.key,
    required this.label,
    this.value,
    this.onTap,
    this.tooltip,
  });

  final String label;
  final Widget? value;
  final VoidCallback? onTap;
  final String? tooltip;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final child = Padding(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 12),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (value != null)
            FittedBox(
              fit: BoxFit.scaleDown,
              child: DefaultTextStyle.merge(
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
                child: value!,
              ),
            ),
          Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodySmall?.copyWith(
              color: theme.colorScheme.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
    final tappable = onTap == null
        ? child
        : InkWell(onTap: onTap, child: child);
    final message = tooltip;
    if (message == null) {
      return tappable;
    }
    return Tooltip(message: message, child: tappable);
  }
}

class _LatestUpdateCard extends StatelessWidget {
  const _LatestUpdateCard({required this.announcement, required this.onSeeAll});

  final FarmAnnouncement announcement;
  final VoidCallback onSeeAll;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final when = DateTime.tryParse(announcement.createdAt ?? '');
    final accent = theme.brightness == Brightness.dark
        ? AniHowColors.sage
        : AniHowColors.brand;
    return _SurfaceCard(
      key: const Key('farm-latest-update'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.campaign_outlined, color: accent),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  announcement.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          if (announcement.body.trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              announcement.body.trim(),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          const SizedBox(height: 8),
          Row(
            children: [
              if (when != null)
                Expanded(
                  child: Text(
                    s.timeAgo(when.toLocal()),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                )
              else
                const Spacer(),
              TextButton(
                key: const Key('farm-updates-see-all'),
                onPressed: onSeeAll,
                child: Text(s.seeAll),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SurfaceCard extends StatelessWidget {
  const _SurfaceCard({super.key, required this.child, this.padding});

  final Widget child;
  final EdgeInsetsGeometry? padding;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    return Material(
      color: theme.colorScheme.surfaceContainerLow,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(
          color: dark ? theme.colorScheme.outlineVariant : Colors.transparent,
        ),
      ),
      clipBehavior: Clip.antiAlias,
      child: Padding(padding: padding ?? AniHowSpace.cardPadding, child: child),
    );
  }
}

class _AboutCard extends StatelessWidget {
  const _AboutCard({
    super.key,
    required this.icon,
    required this.title,
    required this.body,
    this.extra,
  });

  final IconData icon;
  final String title;
  final String body;
  final String? extra;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return _SurfaceCard(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _IconTile(icon: icon),
          const SizedBox(width: AniHowSpace.cardGap),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                for (final line in body.split('\n'))
                  if (line.trim().isNotEmpty) Text(line),
                if (extra != null && extra!.trim().isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    extra!,
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AboutHeading extends StatelessWidget {
  const _AboutHeading({required this.icon, required this.title});

  final IconData icon;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        _IconTile(icon: icon),
        const SizedBox(width: AniHowSpace.cardGap),
        Expanded(
          child: Text(
            title,
            style: Theme.of(context).textTheme.titleMedium
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
        ),
      ],
    );
  }
}

class _IconTile extends StatelessWidget {
  const _IconTile({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = theme.brightness == Brightness.dark
        ? AniHowColors.sage
        : AniHowColors.brand;
    return Container(
      width: 40,
      height: 40,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Icon(icon, color: accent, size: 22),
    );
  }
}

/// Lays each card out at its own height, then again at the row's tallest
/// height, so the two cards line up and spare space stays in the photo.
class _PinnedTabClearance extends StatelessWidget {
  const _PinnedTabClearance();

  @override
  Widget build(BuildContext context) {
    final controller = context
        .findAncestorStateOfType<NestedScrollViewState>()
        ?.outerController;
    if (controller == null) {
      return const SizedBox(height: _tabScrollTop);
    }
    return ListenableBuilder(
      listenable: controller,
      builder: (context, _) => SizedBox(height: _tabClearance(context)),
    );
  }
}

class _ShareRowHeight extends MultiChildRenderObjectWidget {
  const _ShareRowHeight({required super.children});

  @override
  RenderObject createRenderObject(BuildContext context) {
    return _RenderShareRowHeight();
  }
}

class _ShareRowParentData extends ContainerBoxParentData<RenderBox> {}

class _RenderShareRowHeight extends RenderBox
    with
        ContainerRenderObjectMixin<RenderBox, _ShareRowParentData>,
        RenderBoxContainerDefaultsMixin<RenderBox, _ShareRowParentData> {
  @override
  void setupParentData(RenderBox child) {
    if (child.parentData is! _ShareRowParentData) {
      child.parentData = _ShareRowParentData();
    }
  }

  @override
  void performLayout() {
    final children = getChildrenAsList();
    if (children.isEmpty) {
      size = constraints.constrain(Size.zero);
      return;
    }
    final gap = AniHowSpace.cardGap;
    final width =
        (constraints.maxWidth - gap * (children.length - 1)) / children.length;
    var rowHeight = 0.0;
    final loose = BoxConstraints(minWidth: width, maxWidth: width);
    for (final child in children) {
      child.layout(loose, parentUsesSize: true);
      if (child.size.height > rowHeight) {
        rowHeight = child.size.height;
      }
    }
    final tight = BoxConstraints.tightFor(width: width, height: rowHeight);
    var x = 0.0;
    for (final child in children) {
      child.layout(tight);
      (child.parentData! as _ShareRowParentData).offset = Offset(x, 0);
      x += width + gap;
    }
    size = constraints.constrain(Size(constraints.maxWidth, rowHeight));
  }

  @override
  void paint(PaintingContext context, Offset offset) {
    defaultPaint(context, offset);
  }

  @override
  bool hitTestChildren(BoxHitTestResult result, {required Offset position}) {
    return defaultHitTestChildren(result, position: position);
  }
}

class _FarmTabHeader extends SliverPersistentHeaderDelegate {
  _FarmTabHeader(this.tabBar);

  final TabBar tabBar;

  @override
  double get minExtent => tabBar.preferredSize.height;

  @override
  double get maxExtent => tabBar.preferredSize.height;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Material(
      color: Theme.of(context).colorScheme.surface,
      child: tabBar,
    );
  }

  @override
  bool shouldRebuild(covariant _FarmTabHeader oldDelegate) {
    return tabBar != oldDelegate.tabBar;
  }
}

class _UpdateTimeline extends StatefulWidget {
  const _UpdateTimeline({required this.announcement, required this.showLine});

  final FarmAnnouncement announcement;
  final bool showLine;

  @override
  State<_UpdateTimeline> createState() => _UpdateTimelineState();
}

class _UpdateTimelineState extends State<_UpdateTimeline> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final item = widget.announcement;
    final body = item.body.trim();
    final long = body.length > 160;
    final shown = _open || !long ? body : '${body.substring(0, 160).trim()}…';
    final date = _dateLabel(item.createdAt);
    final image = item.imageUrl;
    final accent = theme.brightness == Brightness.dark
        ? AniHowColors.sage
        : AniHowColors.brand;
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                Container(
                  width: 12,
                  height: 12,
                  decoration: BoxDecoration(
                    color: accent,
                    shape: BoxShape.circle,
                  ),
                ),
                if (widget.showLine)
                  Expanded(
                    child: Container(
                      width: 2,
                      color: theme.colorScheme.outlineVariant,
                    ),
                  ),
              ],
            ),
          ),
          Expanded(
            child: _SurfaceCard(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (date != null)
                    DecoratedBox(
                      decoration: BoxDecoration(
                        color: theme.colorScheme.surfaceContainerHighest,
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 2,
                        ),
                        child: Text(date, style: theme.textTheme.labelMedium),
                      ),
                    ),
                  if (date != null) const SizedBox(height: 8),
                  Text(
                    item.title,
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (shown.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(shown),
                  ],
                  if (long && !_open)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton(
                        onPressed: () => setState(() => _open = true),
                        child: Text(s.readMore),
                      ),
                    ),
                  if (image != null && image.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(12),
                      child: Image.network(
                        image,
                        height: 140,
                        width: double.infinity,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => const SizedBox.shrink(),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  String? _dateLabel(String? value) {
    if (value == null || value.length < 10) {
      return null;
    }
    return value.substring(0, 10);
  }
}

class _PhotoViewer extends StatefulWidget {
  const _PhotoViewer({required this.photos, required this.initialIndex});

  final List<FarmPhotoItem> photos;
  final int initialIndex;

  @override
  State<_PhotoViewer> createState() => _PhotoViewerState();
}

class _PhotoViewerState extends State<_PhotoViewer> {
  late final PageController _pages;

  @override
  void initState() {
    super.initState();
    _pages = PageController(initialPage: widget.initialIndex);
  }

  @override
  void dispose() {
    _pages.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
      ),
      body: PageView.builder(
        controller: _pages,
        itemCount: widget.photos.length,
        itemBuilder: (context, index) {
          final photo = widget.photos[index];
          final caption = photo.caption?.trim();
          return Column(
            children: [
              Expanded(
                child: InteractiveViewer(
                  child: Image.network(
                    photo.url,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) => const Icon(
                      Icons.image_outlined,
                      color: Colors.white,
                      size: 48,
                    ),
                  ),
                ),
              ),
              if (caption != null && caption.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: Text(
                    caption,
                    style: const TextStyle(color: Colors.white),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }
}

class _SellerCard extends StatelessWidget {
  const _SellerCard({
    required this.shop,
    required this.saved,
    required this.onToggle,
  });

  final ShopProfile shop;
  final bool saved;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final location = shop.location?.trim();
    final muted = theme.colorScheme.onSurfaceVariant;
    final person = shop.name.trim();
    final showPerson =
        person.isNotEmpty &&
        person.toLowerCase() != shop.shopName.trim().toLowerCase();

    return _SurfaceCard(
      padding: EdgeInsets.zero,
      child: InkWell(
        onTap: () => openBuyerShop(context, shop.id),
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            children: [
              Container(
                padding: const EdgeInsets.all(2),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: theme.colorScheme.outlineVariant),
                ),
                child: AniHowAvatar(
                  name: shop.shopName,
                  imageUrl: shop.avatarUrl,
                  radius: 26,
                ),
              ),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shop.shopName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: AniHowSpace.title,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (showPerson) ...[
                      const SizedBox(height: 2),
                      Text(
                        person,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AniHowColors.sage,
                        ),
                      ),
                    ],
                    if (shop.distanceKm != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        AppStrings.of(context).kilometersAway(shop.distanceKm!),
                      ),
                    ],
                    if (location != null && location.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 16,
                            color: muted,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              location,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: muted,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (shop.hasRating) ...[
                      const SizedBox(height: 6),
                      DecoratedBox(
                        decoration: BoxDecoration(
                          color: AniHowColors.pending.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Padding(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 2,
                          ),
                          child: FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: RatingLabel(
                              rating: shop.averageRating!,
                              count: shop.reviewsCount,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              IconButton(
                tooltip: saved
                    ? AppStrings.of(context).removeStoreFromFavorites
                    : AppStrings.of(context).addStoreToFavorites,
                onPressed: onToggle,
                style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
                icon: Icon(saved ? Icons.favorite : Icons.favorite_outline),
                color: saved ? theme.colorScheme.error : muted,
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}

class _FarmLoading extends StatelessWidget {
  const _FarmLoading();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.all(AniHowSpace.screen),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          SizedBox(height: 24),
          _GreyBlock(height: 220),
          SizedBox(height: AniHowSpace.cardGap),
          _GreyBlock(height: 72),
          SizedBox(height: AniHowSpace.cardGap),
          _GreyBlock(height: 48),
        ],
      ),
    );
  }
}

class _GreyBlock extends StatelessWidget {
  const _GreyBlock({this.height});

  final double? height;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final color = theme.brightness == Brightness.dark
        ? AniHowColors.darkHairline
        : AniHowColors.photoPlaceholder;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(16),
      ),
      child: SizedBox(height: height, width: double.infinity),
    );
  }
}

class _CenteredStatus extends StatelessWidget {
  const _CenteredStatus({
    required this.icon,
    required this.message,
    this.action,
  });

  final IconData icon;
  final String message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: AniHowSpace.screenPadding,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 40, color: theme.colorScheme.onSurfaceVariant),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: theme.textTheme.bodyLarge,
          ),
          if (action != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            action!,
          ],
        ],
      ),
    );
  }
}
