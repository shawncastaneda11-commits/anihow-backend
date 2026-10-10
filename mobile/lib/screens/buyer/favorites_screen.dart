import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/brand_tab_bar.dart';
import '../../widgets/main_tab_app_bar.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/unverified_email_banner.dart';
import '../../widgets/profile_avatar_button.dart';
import 'farm_page_screen.dart';
import 'shop_profile_screen.dart';

class FavoritesPreview {
  const FavoritesPreview({this.stores = const [], this.farms = const []});

  final List<ShopFavoriteRecord> stores;
  final List<FarmFavoriteRecord> farms;
}

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({
    super.key,
    this.preview,
    this.active = true,
    this.showAccountMenu = false,
  });

  final FavoritesPreview? preview;

  /// The buyer shell keeps this page alive. Reload when the tab is opened.
  final bool active;

  /// Set by [BuyerShell] only. Pushed copies of this screen omit the menu.
  final bool showAccountMenu;

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen>
    with SingleTickerProviderStateMixin {
  late Future<List<ShopFavoriteRecord>> _stores;
  late Future<List<FarmFavoriteRecord>> _farms;
  late final TabController _tabs;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    _tabs = TabController(length: 2, vsync: this);
    if (preview != null) {
      _stores = Future.value(preview.stores);
      _farms = Future.value(preview.farms);
      return;
    }
    _stores = context.read<AuthController>().api.shopFavorites();
    _farms = context.read<AuthController>().api.farmFavorites();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(FavoritesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !_previewing) {
      _stores = context.read<AuthController>().api.shopFavorites();
      _farms = context.read<AuthController>().api.farmFavorites();
    }
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    final stores = context.read<AuthController>().api.shopFavorites();
    final farms = context.read<AuthController>().api.farmFavorites();
    setState(() {
      _stores = stores;
      _farms = farms;
    });
    await Future.wait([stores, farms]);
  }

  Future<void> _remove(int sellerId) async {
    if (_previewing) {
      return;
    }
    try {
      await context.read<AuthController>().api.removeShopFavorite(sellerId);
      if (mounted) {
        await _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _unfollowFarm(int farmId) async {
    if (_previewing) {
      return;
    }
    try {
      await context.read<AuthController>().api.removeFarmFavorite(farmId);
      if (mounted) {
        await _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: mainTabAppBar(
        title: s.favorites,
        showAccountMenu: widget.showAccountMenu,
        bottom: onBrandTabBar(
          controller: _tabs,
          tabs: [
            Tab(text: s.favoriteStores),
            Tab(key: const Key('favorite-farms-tab'), text: s.favoriteFarms),
          ],
        ),
      ),
      body: Column(
        children: [
          mainTabBodyGap,
          const UnverifiedEmailBanner(),
          Expanded(
            child: TabBarView(
              controller: _tabs,
              children: [
                _storesTab(s),
                _farmsTab(s),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _storesTab(AppStrings s) {
    return AsyncView<List<ShopFavoriteRecord>>(
      future: _stores,
      onRetry: _reload,
      emptyMessage: s.noFavoriteStores,
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            itemCount: items.length,
            separatorBuilder: (_, _) =>
                const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final favorite = items[index];
              final shop = favorite.shop;
              if (shop == null) {
                return ListTile(title: Text(s.shop));
              }
              final person = shop.name.trim();
              final showPerson =
                  person.isNotEmpty &&
                  person.toLowerCase() != shop.shopName.trim().toLowerCase();
              final place = shop.location?.trim();
              return Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => openBuyerShop(context, favorite.sellerId),
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
                                  style: Theme.of(context).textTheme.bodyMedium
                                      ?.copyWith(color: AniHowColors.sage),
                                ),
                              ],
                              if (place != null && place.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  place,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodyMedium,
                                ),
                              ],
                              if (shop.hasRating) ...[
                                const SizedBox(height: 6),
                                RatingLabel(
                                  rating: shop.averageRating!,
                                  count: shop.reviewsCount,
                                ),
                              ],
                            ],
                          ),
                        ),
                        IconButton(
                          tooltip: s.removeStoreFromFavorites,
                          icon: const Icon(Icons.delete_outline),
                          onPressed: () => _remove(favorite.sellerId),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }

  Widget _farmsTab(AppStrings s) {
    return AsyncView<List<FarmFavoriteRecord>>(
      future: _farms,
      onRetry: _reload,
      emptyMessage: s.noFavoriteFarms,
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              0,
              AniHowSpace.screen,
              AniHowSpace.screen,
            ),
            itemCount: items.length,
            separatorBuilder: (_, _) =>
                const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final favorite = items[index];
              final place = favorite.place?.trim();
              return Card(
                clipBehavior: Clip.antiAlias,
                child: InkWell(
                  onTap: () => openBuyerFarmPage(context, favorite.farmId),
                  child: Padding(
                    padding: AniHowSpace.cardPadding,
                    child: Row(
                      children: [
                        AniHowAvatar(
                          name: favorite.name,
                          imageUrl: favorite.coverUrl,
                          radius: 26,
                        ),
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                favorite.name,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  fontSize: AniHowSpace.title,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              if (place != null && place.isNotEmpty) ...[
                                const SizedBox(height: 4),
                                Text(
                                  place,
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ],
                            ],
                          ),
                        ),
                        IconButton(
                          key: Key('unfollow-farm-${favorite.farmId}'),
                          tooltip: s.followingFarm,
                          onPressed: () => _unfollowFarm(favorite.farmId),
                          style: IconButton.styleFrom(
                            minimumSize: const Size(48, 48),
                          ),
                          icon: Icon(
                            Icons.favorite,
                            color: Theme.of(context).colorScheme.error,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
