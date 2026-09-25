import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

enum FavoriteKind { crops, stores }

class FavoritesPreview {
  const FavoritesPreview({
    this.crops = const [],
    this.stores = const [],
    this.kind = FavoriteKind.crops,
  });

  final List<FavoriteRecord> crops;
  final List<ShopFavoriteRecord> stores;
  final FavoriteKind kind;
}

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key, this.preview});

  final FavoritesPreview? preview;

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late FavoriteKind _kind;
  late Future<List<FavoriteRecord>> _crops;
  late Future<List<ShopFavoriteRecord>> _stores;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    _kind = preview?.kind ?? FavoriteKind.crops;
    if (preview != null) {
      _crops = Future.value(preview.crops);
      _stores = Future.value(preview.stores);
      return;
    }
    _crops = context.read<AuthController>().api.favorites();
    _stores = context.read<AuthController>().api.shopFavorites();
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    final api = context.read<AuthController>().api;
    final crops = api.favorites();
    final stores = api.shopFavorites();
    setState(() {
      _crops = crops;
      _stores = stores;
    });
    await Future.wait([crops, stores]);
  }

  Future<void> _removeCrop(int listingId) async {
    if (_previewing) {
      return;
    }
    try {
      await context.read<AuthController>().api.removeFavorite(listingId);
      if (mounted) {
        await _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  Future<void> _removeStore(int sellerId) async {
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
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            AniHowSpace.screen,
            AniHowSpace.screen,
            AniHowSpace.cardGap,
          ),
          child: SizedBox(
            width: double.infinity,
            child: SegmentedButton<FavoriteKind>(
              showSelectedIcon: false,
              segments: [
                ButtonSegment(
                  value: FavoriteKind.crops,
                  label: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(s.favoriteCrops, key: const ValueKey('favorite-crops-tab')),
                  ),
                ),
                ButtonSegment(
                  value: FavoriteKind.stores,
                  label: FittedBox(
                    fit: BoxFit.scaleDown,
                    child: Text(s.favoriteStores, key: const ValueKey('favorite-stores-tab')),
                  ),
                ),
              ],
              selected: {_kind},
              onSelectionChanged: (next) {
                if (next.isEmpty) {
                  return;
                }
                setState(() => _kind = next.first);
              },
            ),
          ),
        ),
        Expanded(
          child: _kind == FavoriteKind.crops ? _cropList(s) : _storeList(s),
        ),
      ],
    );
  }

  Widget _cropList(AppStrings s) {
    return AsyncView<List<FavoriteRecord>>(
      future: _crops,
      onRetry: _reload,
      emptyMessage: s.noFavorites,
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: AniHowSpace.screenPadding.copyWith(top: 0),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final favorite = items[index];
              final listing = favorite.listing;
              if (listing == null) {
                return ListTile(title: Text(s.listingNumber(favorite.listingId)));
              }
              return ProduceCard(
                listing: listing,
                style: ProduceCardStyle.poster,
                onTap: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ListingDetailScreen(listingId: favorite.listingId),
                    ),
                  );
                },
                onSellerTap: listing.sellerId == null
                    ? null
                    : () => openBuyerShop(context, listing.sellerId!),
                trailing: IconButton(
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () => _removeCrop(favorite.listingId),
                ),
              );
            },
          ),
        );
      },
    );
  }

  Widget _storeList(AppStrings s) {
    return AsyncView<List<ShopFavoriteRecord>>(
      future: _stores,
      onRetry: _reload,
      emptyMessage: s.noFavoriteStores,
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: AniHowSpace.screenPadding.copyWith(top: 0),
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final favorite = items[index];
              final shop = favorite.shop;
              if (shop == null) {
                return ListTile(title: Text(s.shop));
              }
              final location = shop.location?.trim();
              final rating = shop.averageRating?.trim();
              return Card(
                child: ListTile(
                  contentPadding: AniHowSpace.cardPadding,
                  title: Text(shop.shopName, style: Theme.of(context).textTheme.titleMedium),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      if (location != null && location.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(location, style: Theme.of(context).textTheme.bodyMedium),
                      ],
                      if (rating != null && rating.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          '★ $rating (${shop.reviewsCount})',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                color: AniHowColors.sage,
                              ),
                        ),
                      ],
                    ],
                  ),
                  trailing: IconButton(
                    icon: const Icon(Icons.delete_outline),
                    onPressed: () => _removeStore(favorite.sellerId),
                  ),
                  onTap: () => openBuyerShop(context, favorite.sellerId),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
