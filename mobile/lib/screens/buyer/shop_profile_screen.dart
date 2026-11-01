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
import '../../widgets/shop_profile_parts.dart';
import '../../widgets/chat_with_stall_button.dart';
import '../../widgets/shop_review_tile.dart';
import '../farm/farm_profile_screen.dart';
import 'listing_detail_screen.dart';

void openBuyerShop(BuildContext context, int sellerId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(
      builder: (_) => ShopProfileScreen(sellerId: sellerId),
    ),
  );
}

class ShopProfileScreen extends StatefulWidget {
  const ShopProfileScreen({super.key, required this.sellerId, this.preview});

  final int sellerId;
  final ShopProfile? preview;

  @override
  State<ShopProfileScreen> createState() => _ShopProfileScreenState();
}

class _ShopProfileScreenState extends State<ShopProfileScreen> {
  late Future<ShopProfile> _shop;
  bool? _favorited;
  bool _favoriteBusy = false;
  final List<ShopReview> _reviews = [];
  int _page = 0;
  int _lastPage = 1;
  bool _loadingReviews = true;
  bool _loadingMore = false;
  String? _reviewsError;

  final _reviewsKey = GlobalKey();

  bool get _previewing => widget.preview != null;

  bool get _canFavorite {
    if (_previewing) {
      return true;
    }
    try {
      return context.read<AuthController>().user?.isBuyer ?? false;
    } on ProviderNotFoundException {
      return false;
    }
  }

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    if (preview != null) {
      _shop = Future.value(preview);
      _loadingReviews = false;
      return;
    }
    _shop = context.read<AuthController>().api.buyerShop(widget.sellerId);
    _loadReviews();
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    final shop = context.read<AuthController>().api.buyerShop(widget.sellerId);
    setState(() {
      _shop = shop;
      _favorited = null;
      _reviews.clear();
      _page = 0;
      _lastPage = 1;
      _reviewsError = null;
    });
    await Future.wait([shop, _loadReviews()]);
  }

  Future<void> _loadReviews({bool more = false}) async {
    if (more && (_loadingMore || _page >= _lastPage)) {
      return;
    }
    setState(() {
      if (more) {
        _loadingMore = true;
      } else {
        _loadingReviews = true;
      }
    });
    try {
      final page = more ? _page + 1 : 1;
      final result = await context.read<AuthController>().api.shopReviews(
        widget.sellerId,
        page: page,
      );
      if (!mounted) {
        return;
      }
      setState(() {
        if (!more) {
          _reviews.clear();
        }
        _reviews.addAll(result.reviews);
        _page = result.currentPage;
        _lastPage = result.lastPage;
        _reviewsError = null;
      });
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _reviewsError = error.message);
      }
    } finally {
      if (mounted) {
        setState(() {
          _loadingReviews = false;
          _loadingMore = false;
        });
      }
    }
  }

  bool _isFavorited(ShopProfile shop) => _favorited ?? shop.isFavorited;

  Future<void> _toggleFavorite(ShopProfile shop) async {
    final messenger = ScaffoldMessenger.of(context);
    final wasFavorited = _isFavorited(shop);
    setState(() => _favorited = !wasFavorited);
    if (_previewing) {
      return;
    }
    setState(() => _favoriteBusy = true);
    try {
      if (wasFavorited) {
        await context.read<AuthController>().api.removeShopFavorite(shop.id);
      } else {
        await context.read<AuthController>().api.addShopFavorite(shop.id);
      }
      if (!mounted) {
        return;
      }
      if (!wasFavorited) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(AppStrings.read(context).storeSavedToFavorites),
          ),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _favorited = wasFavorited);
        messenger.showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _favoriteBusy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(AppStrings.of(context).shop),
        actions: [
          if (_canFavorite)
            FutureBuilder<ShopProfile>(
              future: _shop,
              builder: (context, snapshot) {
                final shop = snapshot.data;
                if (shop == null) {
                  return const SizedBox(width: 48, height: 48);
                }
                final s = AppStrings.of(context);
                return IconButton(
                  icon: Icon(
                    _isFavorited(shop)
                        ? Icons.favorite
                        : Icons.favorite_outline,
                  ),
                  tooltip: _isFavorited(shop)
                      ? s.removeStoreFromFavorites
                      : s.addStoreToFavorites,
                  onPressed: _favoriteBusy ? null : () => _toggleFavorite(shop),
                  style: IconButton.styleFrom(
                    minimumSize: const Size(48, 48),
                    foregroundColor: Colors.white,
                  ),
                );
              },
            ),
        ],
      ),
      body: FutureBuilder<ShopProfile>(
        future: _shop,
        builder: (context, snapshot) {
          return AsyncView<ShopProfile>.snapshot(
            snapshot: snapshot,
            onRetry: _reload,
            emptyMessage: AppStrings.of(context).shopNotFound,
            builder: (context, shop) => _body(context, shop),
          );
        },
      ),
    );
  }

  Widget _body(BuildContext context, ShopProfile shop) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final farmName = shop.farmName?.trim();
    return RefreshIndicator(
      onRefresh: _reload,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          ShopHero(shop: shop),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              8,
              AniHowSpace.screen,
              AniHowSpace.screen + AniHowSpace.section,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  shop.shopName,
                  key: const ValueKey('shop-profile-name'),
                  style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    if (shop.farmId != null && shop.farmIsActive)
                      FarmLinkChip(
                        farmId: shop.farmId!,
                        openCombinedPage: true,
                        logoUrl: shop.farmLogoUrl,
                        label: farmName == null || farmName.isEmpty ? s.farm : s.farmLine(farmName),
                      )
                    else if (farmName != null && farmName.isNotEmpty)
                      Text(s.farmLine(farmName), style: theme.textTheme.bodyMedium),
                    if (shop.hasRating) RatingLabel(rating: shop.averageRating!, count: shop.reviewsCount),
                  ],
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                ShopStats(
                  listings: shop.listings.length,
                  rating: shop.hasRating ? shop.averageRating : null,
                  reviews: shop.reviewsCount,
                  onReviews: _showReviews,
                ),
                if (_canFavorite) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          key: const ValueKey('shop-favorite'),
                          onPressed: _favoriteBusy ? null : () => _toggleFavorite(shop),
                          icon: Icon(
                            _isFavorited(shop) ? Icons.favorite : Icons.favorite_outline,
                            size: 18,
                          ),
                          label: Text(
                            _isFavorited(shop) ? s.removeStoreFromFavorites : s.addStoreToFavorites,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ),
                      if (!_previewing) ...[
                        const SizedBox(width: AniHowSpace.cardGap),
                        Expanded(child: ChatWithStallButton(sellerId: shop.id)),
                      ],
                    ],
                  ),
                ],
                const SizedBox(height: AniHowSpace.cardGap),
                if (ShopAbout.hasAny(shop, showContact: false)) ...[
                  ShopAbout(shop: shop),
                  const SizedBox(height: AniHowSpace.cardGap),
                ],
                ShopPaymentsCard(shop: shop),
                const SizedBox(height: AniHowSpace.section),
                Text(s.activeListings, style: theme.textTheme.titleMedium),
                const SizedBox(height: AniHowSpace.cardGap),
                if (shop.listings.isEmpty)
                  const ShopListingsEmpty()
                else
                  ...shop.listings.map(
                    (listing) => Padding(
                      padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                      child: ProduceCard(
                        listing: listing,
                        showSeller: false,
                        onTap: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => ListingDetailScreen(listingId: listing.id),
                          ),
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: AniHowSpace.section),
                Text(s.reviews, key: _reviewsKey, style: theme.textTheme.titleMedium),
                const SizedBox(height: AniHowSpace.cardGap),
                if (_loadingReviews)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: AniHowSpace.section),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_reviewsError != null)
                  Text(_reviewsError!, style: theme.textTheme.bodyMedium)
                else if (_reviews.isEmpty)
                  _EmptyNote(icon: Icons.rate_review_outlined, message: s.noReviewsShort)
                else ...[
                  ..._reviews.map(
                    (review) => Padding(
                      padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                      child: ShopReviewTile(review: review),
                    ),
                  ),
                  if (_page < _lastPage)
                    TextButton(
                      onPressed: _loadingMore ? null : () => _loadReviews(more: true),
                      child: Text(_loadingMore ? 'Loading…' : 'Show more'),
                    ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  /// The stats card's reviews cell jumps down to the reviews on this page.
  void _showReviews() {
    final target = _reviewsKey.currentContext;
    if (target != null) {
      Scrollable.ensureVisible(target, duration: const Duration(milliseconds: 300));
    }
  }
}

class _EmptyNote extends StatelessWidget {
  const _EmptyNote({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: AniHowSpace.section),
      child: Column(
        children: [
          Icon(icon, size: 40, color: AniHowColors.brand),
          const SizedBox(height: AniHowSpace.cardGap),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
      ),
    );
  }
}
