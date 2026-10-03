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
import '../../widgets/profile_avatar_button.dart';
import 'shop_profile_screen.dart';

class FavoritesPreview {
  const FavoritesPreview({this.stores = const []});

  final List<ShopFavoriteRecord> stores;
}

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key, this.preview, this.active = true});

  final FavoritesPreview? preview;

  /// The buyer shell keeps this page alive. Reload when the tab is opened.
  final bool active;

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late Future<List<ShopFavoriteRecord>> _stores;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    if (preview != null) {
      _stores = Future.value(preview.stores);
      return;
    }
    _stores = context.read<AuthController>().api.shopFavorites();
  }

  @override
  void didUpdateWidget(FavoritesScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (widget.active && !oldWidget.active && !_previewing) {
      _stores = context.read<AuthController>().api.shopFavorites();
    }
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    final stores = context.read<AuthController>().api.shopFavorites();
    setState(() => _stores = stores);
    await stores;
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

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return AsyncView<List<ShopFavoriteRecord>>(
      future: _stores,
      onRetry: _reload,
      emptyMessage: s.noFavoriteStores,
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: AniHowSpace.screenPadding,
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
                        AniHowAvatar(name: shop.shopName, radius: 26),
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
}
