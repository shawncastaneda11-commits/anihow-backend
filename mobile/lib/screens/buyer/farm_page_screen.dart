import 'package:flutter/material.dart';
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
import '../../widgets/hint_card.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import 'shop_profile_screen.dart';

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

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 4, vsync: this);
    _farm = _loadFarm();
    _shops = _loadShops();
    _loadSaved();
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
    await Future.wait([_reloadFarm(), _reloadShops(), _loadSaved()]);
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
    if (!farm.hasPin) {
      return;
    }
    final uri = Uri.parse(
      FarmMapCard.urlFor(farm.latitude!, farm.longitude!),
    );
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
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

  String? _averageRating(List<ShopProfile> shops) {
    final rated = shops.where((shop) => shop.hasRating).toList();
    if (rated.isEmpty) {
      return null;
    }
    var sum = 0.0;
    for (final shop in rated) {
      sum += double.tryParse(shop.averageRating!) ?? 0;
    }
    return (sum / rated.length).toStringAsFixed(1);
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      body: AsyncView<FarmProfile>(
        future: _farm,
        onRetry: _reloadFarm,
        emptyMessage: s.farmNotFound,
        isEmpty: (_) => false,
        builder: (context, farm) {
          return RefreshIndicator(
            onRefresh: _reload,
            child: NestedScrollView(
              headerSliverBuilder: (context, innerBoxIsScrolled) {
                return [
                  SliverAppBar(
                    pinned: true,
                    expandedHeight: 220,
                    title: Text(
                      farm.name,
                      key: const Key('farm-name'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    flexibleSpace: FlexibleSpaceBar(
                      background: _FarmHeaderCover(farm: farm),
                    ),
                  ),
                  SliverToBoxAdapter(child: _headerActions(s, farm)),
                  SliverPersistentHeader(
                    pinned: true,
                    delegate: _FarmTabHeader(
                      TabBar(
                        controller: _tabs,
                        isScrollable: true,
                        tabAlignment: TabAlignment.start,
                        tabs: [
                          Tab(
                            key: const Key('farm-tab-shops'),
                            text: s.farmShopsTab,
                          ),
                          Tab(key: const Key('farm-tab-about'), text: s.about),
                          Tab(
                            key: const Key('farm-tab-updates'),
                            text: s.farmUpdates,
                          ),
                          Tab(
                            key: const Key('farm-tab-photos'),
                            text: s.farmPhotos,
                          ),
                        ],
                      ),
                    ),
                  ),
                ];
              },
              body: TabBarView(
                controller: _tabs,
                children: [
                  _shopsTab(s),
                  _aboutTab(s, farm),
                  _updatesTab(s, farm),
                  _photosTab(s, farm),
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _headerActions(AppStrings s, FarmProfile farm) {
    final followed = _followed ?? farm.isFavorited;
    final buyer = context.watch<AuthController>().user?.isBuyer ?? false;
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        8,
        AniHowSpace.screen,
        AniHowSpace.cardGap,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            height: 28,
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                Positioned(
                  left: 0,
                  top: -40,
                  child: _FarmLogo(farm: farm),
                ),
              ],
            ),
          ),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (buyer)
                _ActionChip(
                  key: const Key('farm-follow'),
                  icon: followed ? Icons.favorite : Icons.favorite_outline,
                  label: followed ? s.followingFarm : s.followFarm,
                  onPressed: () => _toggleFollow(farm),
                ),
              if (farm.hasPin)
                _ActionChip(
                  key: const Key('farm-directions'),
                  icon: Icons.directions_outlined,
                  label: s.directions,
                  onPressed: () => _directions(farm),
                ),
              _ActionChip(
                key: const Key('farm-updates-action'),
                icon: Icons.campaign_outlined,
                label: s.farmUpdates,
                onPressed: () => _tabs.animateTo(2),
              ),
            ],
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          FutureBuilder<List<ShopProfile>>(
            future: _shops,
            builder: (context, snapshot) {
              final rating = _averageRating(snapshot.data ?? const []);
              return Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  Chip(label: Text(s.shopsCount(farm.farmerSellersCount))),
                  if (rating != null) Chip(label: Text(rating)),
                ],
              );
            },
          ),
        ],
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
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.cardGap,
        AniHowSpace.screen,
        AniHowSpace.screen,
      ),
      children: [
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
          Text(s.noShopsAtFarm)
        else if (sellers.isEmpty)
          Text(s.noShopsMatch)
        else
          for (var index = 0; index < sellers.length; index++) ...[
            if (index > 0) const SizedBox(height: AniHowSpace.cardGap),
            _SellerCard(
              shop: sellers[index],
              saved: _saved.contains(sellers[index].id),
              onToggle: () => _toggle(sellers[index]),
            ),
          ],
      ],
    );
  }

  Widget _aboutTab(AppStrings s, FarmProfile farm) {
    final theme = Theme.of(context);
    final description = farm.description?.trim();
    final pickup = farm.pickupPoint?.trim();
    final contactPerson = farm.contactPerson?.trim();
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: AniHowSpace.screenPadding,
      children: [
        if (description != null && description.isNotEmpty)
          Text(description, style: theme.textTheme.bodyMedium),
        if (pickup != null && pickup.isNotEmpty) ...[
          const SizedBox(height: AniHowSpace.section),
          AniHowHintCard(
            icon: Icons.place_outlined,
            title: s.pickupPoint,
            body: pickup,
            tone: AniHowHintTone.brand,
          ),
        ],
        if (farm.hasPin) ...[
          const SizedBox(height: AniHowSpace.section),
          FarmMapCard(latitude: farm.latitude!, longitude: farm.longitude!),
        ],
        if (contactPerson != null && contactPerson.isNotEmpty) ...[
          const SizedBox(height: AniHowSpace.section),
          Text(s.farmContact, style: theme.textTheme.titleMedium),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(contactPerson, style: theme.textTheme.bodyMedium),
          const SizedBox(height: AniHowSpace.labelGap),
          Text(s.farmContactBuyerHint, style: theme.textTheme.bodyMedium),
        ],
      ],
    );
  }

  Widget _updatesTab(AppStrings s, FarmProfile farm) {
    if (farm.announcements.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Center(child: Text(s.noUpdatesYet)),
        ],
      );
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: AniHowSpace.screenPadding,
      itemCount: farm.announcements.length,
      separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
      itemBuilder: (context, index) =>
          _UpdateCard(announcement: farm.announcements[index]),
    );
  }

  Widget _photosTab(AppStrings s, FarmProfile farm) {
    if (farm.photos.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        children: [
          const SizedBox(height: 80),
          Center(child: Text(s.noFarmPhotos)),
        ],
      );
    }
    return GridView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: AniHowSpace.screenPadding,
      itemCount: farm.photos.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        mainAxisSpacing: AniHowSpace.cardGap,
        crossAxisSpacing: AniHowSpace.cardGap,
        childAspectRatio: 0.8,
      ),
      itemBuilder: (context, index) {
        final photo = farm.photos[index];
        final caption = photo.caption?.trim();
        return InkWell(
          onTap: () => _openPhotos(farm.photos, index),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(AniHowSpace.radius),
                  child: Image.network(
                    photo.gridUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => ColoredBox(
                      color: AniHowColors.brand.withValues(alpha: 0.12),
                      child: const Icon(Icons.image_outlined),
                    ),
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
      },
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

class _FarmHeaderCover extends StatelessWidget {
  const _FarmHeaderCover({required this.farm});

  final FarmProfile farm;

  @override
  Widget build(BuildContext context) {
    final place = farm.placeLabel;
    final cover = farm.coverPhotoUrl;
    return Stack(
      fit: StackFit.expand,
      children: [
        if (farm.hasCoverPhoto && cover != null)
          Image.network(
            cover,
            key: const Key('farm-cover'),
            fit: BoxFit.cover,
            errorBuilder: (_, _, _) => _CoverFallback(name: farm.name),
          )
        else
          _CoverFallback(name: farm.name),
        const DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [Color(0x00000000), Color(0xCC000000)],
            ),
          ),
        ),
        if (place.isNotEmpty)
          Positioned(
            left: 96,
            right: 16,
            bottom: 16,
            child: Text(
              place,
              key: const Key('farm-place'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
      ],
    );
  }
}

class _CoverFallback extends StatelessWidget {
  const _CoverFallback({required this.name});

  final String name;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      key: const Key('farm-cover-fallback'),
      color: AniHowColors.brand,
      child: Center(
        child: Text(
          _initials(name),
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
            color: Theme.of(context).colorScheme.onPrimary,
            fontWeight: FontWeight.w800,
          ),
        ),
      ),
    );
  }
}

class _FarmLogo extends StatelessWidget {
  const _FarmLogo({required this.farm});

  final FarmProfile farm;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(
          color: Theme.of(context).colorScheme.surface,
          width: 3,
        ),
      ),
      child: AniHowAvatar(
        name: farm.name,
        imageUrl: farm.coverPhotoUrl,
        radius: 32,
        backgroundColor: AniHowColors.brand,
        foregroundColor: Theme.of(context).colorScheme.onPrimary,
      ),
    );
  }
}

class _ActionChip extends StatelessWidget {
  const _ActionChip({
    super.key,
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 48,
      child: OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 20),
        label: Text(label),
      ),
    );
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

class _UpdateCard extends StatefulWidget {
  const _UpdateCard({required this.announcement});

  final FarmAnnouncement announcement;

  @override
  State<_UpdateCard> createState() => _UpdateCardState();
}

class _UpdateCardState extends State<_UpdateCard> {
  bool _open = false;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final item = widget.announcement;
    final body = item.body.trim();
    final long = body.length > 160;
    final shown = _open || !long ? body : '${body.substring(0, 160).trim()}…';
    final date = _dateLabel(item.createdAt);
    final image = item.imageUrl;
    return Card(
      clipBehavior: Clip.antiAlias,
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.title,
              style: const TextStyle(
                fontSize: AniHowSpace.title,
                fontWeight: FontWeight.w800,
              ),
            ),
            if (date != null) ...[
              const SizedBox(height: 4),
              Text(date, style: Theme.of(context).textTheme.bodySmall),
            ],
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
                borderRadius: BorderRadius.circular(AniHowSpace.radius),
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
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final person = shop.name.trim();
    final showPerson =
        person.isNotEmpty &&
        person.toLowerCase() != shop.shopName.trim().toLowerCase();

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => openBuyerShop(context, shop.id),
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            children: [
              AniHowAvatar(
                name: shop.shopName,
                imageUrl: shop.avatarUrl,
                radius: 26,
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
                      SizedBox(
                        height: 20,
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerLeft,
                          child: RatingLabel(
                            rating: shop.averageRating!,
                            count: shop.reviewsCount,
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
                style: IconButton.styleFrom(
                  minimumSize: const Size(48, 48),
                ),
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

String _initials(String name) {
  final parts = name
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2);
  if (parts.isEmpty) {
    return '';
  }
  return parts.map((part) => part[0].toUpperCase()).join();
}
