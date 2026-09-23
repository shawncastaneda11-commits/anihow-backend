import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/relative_time.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/shop_profile_parts.dart';
import 'listing_detail_screen.dart';

void openBuyerShop(BuildContext context, int sellerId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => ShopProfileScreen(sellerId: sellerId)),
  );
}

class ShopProfileScreen extends StatefulWidget {
  const ShopProfileScreen({super.key, required this.sellerId});

  final int sellerId;

  @override
  State<ShopProfileScreen> createState() => _ShopProfileScreenState();
}

class _ShopProfileScreenState extends State<ShopProfileScreen> {
  late Future<ShopProfile> _shop;
  final List<ShopReview> _reviews = [];
  int _page = 0;
  int _lastPage = 1;
  bool _loadingReviews = true;
  bool _loadingMore = false;
  String? _reviewsError;

  @override
  void initState() {
    super.initState();
    _shop = context.read<AuthController>().api.buyerShop(widget.sellerId);
    _loadReviews();
  }

  Future<void> _reload() async {
    final shop = context.read<AuthController>().api.buyerShop(widget.sellerId);
    setState(() {
      _shop = shop;
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

  Future<void> _call(String number) async {
    final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      return;
    }
    final opened = await launchUrl(Uri(scheme: 'tel', path: digits));
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the phone app.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(AppStrings.of(context).shop)),
      body: FutureBuilder<ShopProfile>(
        future: _shop,
        builder: (context, snapshot) {
          return AsyncView<ShopProfile>.snapshot(
            snapshot: snapshot,
            onRetry: _reload,
            emptyMessage: 'Shop not found.',
            builder: (context, shop) {
              return RefreshIndicator(
                onRefresh: _reload,
                child: ListView(
                  padding: AniHowSpace.screenPadding,
                  children: [
                    ShopIdentityHeader(shop: shop),
                    const SizedBox(height: AniHowSpace.section),
                    Text(
                      'Pickup only',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: AniHowSpace.labelGap),
                    Text(
                      'Call to coordinate pickup at the stall.',
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
                      'Active listings',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
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
                      'Reviews',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
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
                          child: _ReviewCard(review: review),
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

class _ReviewCard extends StatelessWidget {
  const _ReviewCard({required this.review});

  final ShopReview review;

  @override
  Widget build(BuildContext context) {
    final time = relativeTime(review.createdAt);
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    review.reviewerName,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                if (time.isNotEmpty)
                  Text(
                    time,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          fontWeight: FontWeight.w400,
                          color: Theme.of(context).colorScheme.onSurface,
                        ),
                  ),
              ],
            ),
            const SizedBox(height: AniHowSpace.labelGap),
            Row(
              children: [
                for (var index = 1; index <= 5; index++)
                  Icon(
                    index <= review.rating ? Icons.star_rounded : Icons.star_outline_rounded,
                    size: 16,
                    color: AniHowColors.pending,
                  ),
              ],
            ),
            if (review.comment != null && review.comment!.isNotEmpty) ...[
              const SizedBox(height: AniHowSpace.labelGap),
              Text(review.comment!, style: Theme.of(context).textTheme.bodyMedium),
            ],
          ],
        ),
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
