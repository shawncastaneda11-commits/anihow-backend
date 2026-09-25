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
import '../../widgets/produce_card.dart';
import '../../widgets/shop_profile_parts.dart';
import '../../widgets/shop_review_tile.dart';
import '../farm/farm_profile_screen.dart';
import 'listing_detail_screen.dart';

void openBuyerShop(BuildContext context, int sellerId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => ShopProfileScreen(sellerId: sellerId)),
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
          SnackBar(content: Text(AppStrings.read(context).storeSavedToFavorites)),
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

  Future<void> _call(String number) async {
    final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      return;
    }
    final opened = await launchUrl(Uri(scheme: 'tel', path: digits));
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).couldNotOpenPhone)),
      );
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
                  icon: Icon(_isFavorited(shop) ? Icons.favorite : Icons.favorite_outline),
                  tooltip: _isFavorited(shop) ? s.removeStoreFromFavorites : s.addStoreToFavorites,
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
            builder: (context, shop) {
              final s = AppStrings.of(context);
              return RefreshIndicator(
                onRefresh: _reload,
                child: ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    Card(
                      child: Padding(
                        padding: AniHowSpace.cardPadding,
                        child: ShopIdentityHeader(shop: shop),
                      ),
                    ),
                    if (_canFavorite) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      OutlinedButton.icon(
                        onPressed: _favoriteBusy ? null : () => _toggleFavorite(shop),
                        icon: Icon(_isFavorited(shop) ? Icons.favorite : Icons.favorite_outline),
                        label: Text(
                          _isFavorited(shop) ? s.removeStoreFromFavorites : s.addStoreToFavorites,
                        ),
                      ),
                    ],
                    if (shop.farmId != null && shop.farmIsActive) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      FarmLinkChip(
                        farmId: shop.farmId!,
                        label: shop.farmName == null || shop.farmName!.isEmpty
                            ? s.farm
                            : s.farmLine(shop.farmName!),
                      ),
                    ] else if (shop.farmName != null && shop.farmName!.isNotEmpty) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      Text(s.farmLine(shop.farmName!), style: Theme.of(context).textTheme.bodyMedium),
                    ],
                    const SizedBox(height: AniHowSpace.section),
                    Text(
                      s.pickupOnly,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AniHowSpace.labelGap),
                    Text(
                      s.callToPickup,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    if (shop.contact != null && shop.contact!.isNotEmpty) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton.icon(
                          onPressed: () => _call(shop.contact!),
                          icon: const Icon(Icons.phone_outlined),
                          label: Text(shop.contact!),
                        ),
                      ),
                    ],
                    const SizedBox(height: AniHowSpace.section),
                    Text(
                      s.activeListings,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
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
                            onTap: () {
                              Navigator.of(context).push(
                                MaterialPageRoute<void>(
                                  builder: (_) => ListingDetailScreen(listingId: listing.id),
                                ),
                              );
                            },
                          ),
                        ),
                      ),
                    const SizedBox(height: AniHowSpace.section),
                    Text(
                      s.reviews,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: AniHowSpace.cardGap),
                    if (_loadingReviews)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: AniHowSpace.section),
                        child: Center(child: CircularProgressIndicator()),
                      )
                    else if (_reviewsError != null)
                      Text(_reviewsError!, style: Theme.of(context).textTheme.bodyMedium)
                    else if (_reviews.isEmpty)
                      const _EmptyNote(
                        icon: Icons.rate_review_outlined,
                        message: 'No reviews yet',
                      )
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
              );
            },
          );
        },
      ),
    );
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
