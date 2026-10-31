import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/produce_photo.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/unverified_email_banner.dart';
import 'farm_page_screen.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

class FavoritesPreview {
  const FavoritesPreview({
    this.stores = const [],
    this.farms = const [],
    this.fresh = const [],
  });

  final List<ShopFavoriteRecord> stores;
  final List<FarmFavoriteRecord> farms;
  final List<ListingItem> fresh;
}

class _FavoritesData {
  const _FavoritesData({
    required this.stores,
    required this.farms,
    required this.fresh,
    this.freshTotal,
  });

  final List<ShopFavoriteRecord> stores;
  final List<FarmFavoriteRecord> farms;
  final List<ListingItem> fresh;
  final int? freshTotal;

  bool get isEmpty => stores.isEmpty && farms.isEmpty;
}

/// One page: produce in stock from what the buyer follows, then their
/// favorite shops and followed farms.
class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({
    super.key,
    this.preview,
    this.active = true,
    this.showAccountMenu = false,
    this.onOpenTab,
  });

  final FavoritesPreview? preview;

  /// The buyer shell keeps this page alive. Reload when the tab is opened.
  final bool active;

  /// Set by [BuyerShell] only. Pushed copies of this screen omit the menu.
  final bool showAccountMenu;

  /// Switches the buyer shell tab: 0 is Farms, 1 is Market.
  final ValueChanged<int>? onOpenTab;

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late Future<_FavoritesData> _future;
  _FavoritesData? _data;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  @override
  void didUpdateWidget(FavoritesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !_previewing) {
      _future = _load();
    }
  }

  Future<_FavoritesData> _load() async {
    final preview = widget.preview;
    if (preview != null) {
      return _data = _FavoritesData(stores: preview.stores, farms: preview.farms, fresh: preview.fresh);
    }
    final api = context.read<AuthController>().api;
    final stores = api.shopFavorites();
    final farms = api.farmFavorites();
    MarketplaceFeed? fresh;
    try {
      fresh = await api.favoritesInStock();
    } on ApiException {
      fresh = null;
    }
    return _data = _FavoritesData(
      stores: await stores,
      farms: await farms,
      fresh: fresh?.items ?? const [],
      freshTotal: fresh?.total,
    );
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  void _showUndo(String name, Future<void> Function() undo) {
    final s = AppStrings.read(context);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(s.removedFavorite(name)),
          action: SnackBarAction(
            label: s.undo,
            onPressed: () async {
              try {
                await undo();
              } on ApiException catch (_) {
                // The reload below shows what the server kept.
              }
              if (mounted) {
                await _reload();
              }
            },
          ),
        ),
      );
  }

  Future<void> _removeStore(ShopFavoriteRecord favorite) async {
    final data = _data;
    if (_previewing || data == null) {
      return;
    }
    final api = context.read<AuthController>().api;
    final name = favorite.shop?.shopName ?? AppStrings.read(context).shop;
    setState(() {
      _data = _FavoritesData(
        stores: data.stores.where((item) => item.sellerId != favorite.sellerId).toList(),
        farms: data.farms,
        fresh: data.fresh,
        freshTotal: data.freshTotal,
      );
      _future = Future.value(_data);
    });
    try {
      await api.removeShopFavorite(favorite.sellerId);
      if (mounted) {
        _showUndo(name, () => api.addShopFavorite(favorite.sellerId));
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
        await _reload();
      }
    }
  }

  Future<void> _unfollowFarm(FarmFavoriteRecord favorite) async {
    final data = _data;
    if (_previewing || data == null) {
      return;
    }
    final api = context.read<AuthController>().api;
    setState(() {
      _data = _FavoritesData(
        stores: data.stores,
        farms: data.farms.where((item) => item.farmId != favorite.farmId).toList(),
        fresh: data.fresh,
        freshTotal: data.freshTotal,
      );
      _future = Future.value(_data);
    });
    try {
      await api.removeFarmFavorite(favorite.farmId);
      if (mounted) {
        _showUndo(favorite.name, () => api.addFarmFavorite(favorite.farmId));
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
        await _reload();
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: mainTabAppBar(title: s.favorites, showAccountMenu: widget.showAccountMenu),
      body: Column(
        children: [
          mainTabBodyGap,
          const UnverifiedEmailBanner(),
          Expanded(
            child: FutureBuilder<_FavoritesData>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState != ConnectionState.done && _data == null) {
                  return const Center(child: CircularProgressIndicator());
                }
                if (snapshot.hasError && _data == null) {
                  return Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text('${snapshot.error}', textAlign: TextAlign.center),
                        TextButton(onPressed: _reload, child: Text(s.retry)),
                      ],
                    ),
                  );
                }
                final data = snapshot.data ?? _data!;
                return RefreshIndicator(
                  onRefresh: _reload,
                  child: data.isEmpty ? _empty(s) : _page(s, data),
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _empty(AppStrings s) {
    final theme = Theme.of(context);
    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        const SizedBox(height: AniHowSpace.section),
        Container(
          key: const ValueKey('favorites-empty'),
          padding: const EdgeInsets.all(AniHowSpace.section),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: readableAccent(context).withValues(alpha: 0.4)),
          ),
          child: Column(
            children: [
              Icon(Icons.favorite_border, size: 36, color: readableAccent(context)),
              const SizedBox(height: AniHowSpace.cardGap),
              Text(s.noFavoritesYet, style: theme.textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.labelGap),
              Text(s.noFavoritesBody, textAlign: TextAlign.center),
              if (widget.onOpenTab != null) ...[
                const SizedBox(height: AniHowSpace.section),
                Wrap(
                  spacing: AniHowSpace.cardGap,
                  runSpacing: AniHowSpace.cardGap,
                  alignment: WrapAlignment.center,
                  children: [
                    FilledButton(
                      key: const ValueKey('favorites-browse-market'),
                      onPressed: () => widget.onOpenTab!(1),
                      child: Text(s.browseMarket),
                    ),
                    OutlinedButton(
                      key: const ValueKey('favorites-browse-farms'),
                      onPressed: () => widget.onOpenTab!(0),
                      child: Text(s.browseFarms),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }

  Widget _page(AppStrings s, _FavoritesData data) {
    return ListView(
      padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 0, AniHowSpace.screen, AniHowSpace.screen),
      children: [
        if (data.fresh.isNotEmpty) ...[
          _SectionHeader(
            title: s.freshFromFavorites,
            count: data.freshTotal ?? data.fresh.length,
            action: TextButton(
              key: const ValueKey('favorites-fresh-see-all'),
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const _FreshFromFavoritesScreen()),
              ),
              child: Text(s.seeAll),
            ),
          ),
          SizedBox(
            height: 200,
            child: ListView.separated(
              key: const ValueKey('favorites-fresh-strip'),
              scrollDirection: Axis.horizontal,
              itemCount: data.fresh.length,
              separatorBuilder: (_, _) => const SizedBox(width: AniHowSpace.cardGap),
              itemBuilder: (context, index) => _FreshCard(listing: data.fresh[index]),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
        ],
        _SectionHeader(title: s.favoriteStores, count: data.stores.length),
        if (data.stores.isEmpty)
          _EmptyLine(text: s.noFavoriteStores)
        else
          Card(
            margin: EdgeInsets.zero,
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (final (index, favorite) in data.stores.indexed) ...[
                  if (index > 0) const Divider(height: 1),
                  _StoreRow(favorite: favorite, onRemove: () => _removeStore(favorite)),
                ],
              ],
            ),
          ),
        const SizedBox(height: AniHowSpace.section),
        _SectionHeader(title: s.favoriteFarms, count: data.farms.length),
        if (data.farms.isEmpty)
          _EmptyLine(text: s.noFavoriteFarms)
        else
          Card(
            margin: EdgeInsets.zero,
            clipBehavior: Clip.antiAlias,
            child: Column(
              children: [
                for (final (index, favorite) in data.farms.indexed) ...[
                  if (index > 0) const Divider(height: 1),
                  _FarmRow(favorite: favorite, onUnfollow: () => _unfollowFarm(favorite)),
                ],
              ],
            ),
          ),
      ],
    );
  }
}

class _SectionHeader extends StatelessWidget {
  const _SectionHeader({required this.title, required this.count, this.action});

  final String title;
  final int count;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SizedBox(
        height: 40,
        child: Row(
          children: [
            Expanded(
              child: Text.rich(
                TextSpan(
                  text: title,
                  style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  children: [
                    TextSpan(
                      text: '  $count',
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                      ),
                    ),
                  ],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            ?action,
          ],
        ),
      ),
    );
  }
}

class _EmptyLine extends StatelessWidget {
  const _EmptyLine({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Text(
        text,
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.68),
        ),
      ),
    );
  }
}

class _FreshCard extends StatelessWidget {
  const _FreshCard({required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return SizedBox(
      width: 140,
      child: Card(
        key: ValueKey('favorites-fresh-${listing.id}'),
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute<void>(builder: (_) => ListingDetailScreen(listingId: listing.id)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SizedBox(height: 96, width: double.infinity, child: ProducePhoto(listing: listing, iconSize: 32)),
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 8),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      listing.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      listing.priceLabel,
                      maxLines: 1,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: readableAccent(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (listing.sellerName != null)
                      Text(
                        listing.sellerName!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StoreRow extends StatelessWidget {
  const _StoreRow({required this.favorite, required this.onRemove});

  final ShopFavoriteRecord favorite;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final shop = favorite.shop;
    final name = shop?.shopName ?? s.shop;
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    final place = shop?.location?.trim();
    final parts = <InlineSpan>[
      if (shop != null && shop.hasRating) ...[
        TextSpan(text: '★ ', style: const TextStyle(color: Color(0xFFE9A23B))),
        TextSpan(text: shop.averageRating!),
      ] else
        TextSpan(text: s.noReviewsShort),
      if (place != null && place.isNotEmpty) TextSpan(text: ' · $place'),
      if (favorite.inStockCount > 0)
        TextSpan(
          text: ' · ${s.inStockCount(favorite.inStockCount)}',
          style: TextStyle(color: readableAccent(context), fontWeight: FontWeight.w600),
        ),
    ];
    return InkWell(
      key: ValueKey('favorite-store-${favorite.sellerId}'),
      onTap: () => openBuyerShop(context, favorite.sellerId),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
        child: Row(
          children: [
            AniHowAvatar(name: name, imageUrl: shop?.avatarUrl, radius: 22),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text.rich(
                    TextSpan(children: parts),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(color: muted),
                  ),
                ],
              ),
            ),
            IconButton(
              key: ValueKey('unfavorite-store-${favorite.sellerId}'),
              tooltip: s.removeStoreFromFavorites,
              onPressed: onRemove,
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
              icon: Icon(Icons.favorite, color: theme.colorScheme.error),
            ),
          ],
        ),
      ),
    );
  }
}

class _FarmRow extends StatelessWidget {
  const _FarmRow({required this.favorite, required this.onUnfollow});

  final FarmFavoriteRecord favorite;
  final VoidCallback onUnfollow;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final place = favorite.place?.trim();
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    return InkWell(
      onTap: () => openBuyerFarmPage(context, favorite.farmId),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 4, 10),
        child: Row(
          children: [
            AniHowAvatar(name: favorite.name, imageUrl: favorite.coverUrl, radius: 22),
            const SizedBox(width: AniHowSpace.cardGap),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    favorite.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 2),
                  Text.rich(
                    TextSpan(
                      children: [
                        if (place != null && place.isNotEmpty) TextSpan(text: place),
                        if (favorite.sellersCount > 0)
                          TextSpan(
                            text: '${place != null && place.isNotEmpty ? ' · ' : ''}${s.storesCount(favorite.sellersCount)}',
                            style: TextStyle(color: readableAccent(context), fontWeight: FontWeight.w600),
                          ),
                      ],
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: theme.textTheme.bodySmall?.copyWith(color: muted),
                  ),
                ],
              ),
            ),
            IconButton(
              key: Key('unfollow-farm-${favorite.farmId}'),
              tooltip: s.followingFarm,
              onPressed: onUnfollow,
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
              icon: Icon(Icons.favorite, color: theme.colorScheme.error),
            ),
          ],
        ),
      ),
    );
  }
}

/// "See all" for the fresh strip: everything in stock from favorites.
class _FreshFromFavoritesScreen extends StatefulWidget {
  const _FreshFromFavoritesScreen();

  @override
  State<_FreshFromFavoritesScreen> createState() => _FreshFromFavoritesScreenState();
}

class _FreshFromFavoritesScreenState extends State<_FreshFromFavoritesScreen> {
  late final Future<MarketplaceFeed> _feed = context.read<AuthController>().api.favoritesInStock();

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.freshFromFavorites)),
      body: FutureBuilder<MarketplaceFeed>(
        future: _feed,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final items = snapshot.data?.items ?? const <ListingItem>[];
          return ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) => ProduceCard(
              listing: items[index],
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => ListingDetailScreen(listingId: items[index].id)),
              ),
            ),
          );
        },
      ),
    );
  }
}
