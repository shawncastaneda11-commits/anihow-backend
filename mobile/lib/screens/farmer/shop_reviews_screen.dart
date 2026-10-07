import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/shop_review_tile.dart';

class ShopReviewsScreen extends StatefulWidget {
  const ShopReviewsScreen({super.key, this.preview});

  final PagedShopReviews? preview;

  @override
  State<ShopReviewsScreen> createState() => _ShopReviewsScreenState();
}

class _ShopReviewsScreenState extends State<ShopReviewsScreen> {
  late Future<PagedShopReviews> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<PagedShopReviews> _load({int page = 1}) {
    final preview = widget.preview;
    if (preview != null) {
      return Future.value(preview);
    }
    return context.read<AuthController>().api.farmerShopReviews(page: page);
  }

  void _reload() {
    setState(() => _future = _load());
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.reviews)),
      body: AsyncView<PagedShopReviews>(
        future: _future,
        onRetry: _reload,
        isEmpty: (page) => page.reviews.isEmpty,
        emptyMessage: s.noReviewsYet,
        builder: (context, firstPage) => _ReviewsBody(
          firstPage: firstPage,
          previewing: widget.preview != null,
        ),
      ),
    );
  }
}

class _ReviewsBody extends StatefulWidget {
  const _ReviewsBody({required this.firstPage, required this.previewing});

  final PagedShopReviews firstPage;
  final bool previewing;

  @override
  State<_ReviewsBody> createState() => _ReviewsBodyState();
}

class _ReviewsBodyState extends State<_ReviewsBody> {
  late List<ShopReview> _reviews;
  late int _page;
  late int _lastPage;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _reviews = List<ShopReview>.from(widget.firstPage.reviews);
    _page = widget.firstPage.currentPage;
    _lastPage = widget.firstPage.lastPage;
  }

  Future<void> _loadMore() async {
    if (widget.previewing || _loadingMore || _page >= _lastPage) {
      return;
    }
    setState(() => _loadingMore = true);
    try {
      final result = await context.read<AuthController>().api.farmerShopReviews(page: _page + 1);
      if (!mounted) {
        return;
      }
      setState(() {
        _reviews.addAll(result.reviews);
        _page = result.currentPage;
        _lastPage = result.lastPage;
      });
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _loadingMore = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final first = widget.firstPage;
    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        if (first.averageRating != null && first.averageRating!.isNotEmpty)
          Padding(
            padding: const EdgeInsets.only(bottom: AniHowSpace.section),
            child: RatingLabel(
              rating: first.averageRating!,
              count: first.reviewsCount,
            ),
          )
        else
          Padding(
            padding: const EdgeInsets.only(bottom: AniHowSpace.section),
            child: Text(s.reviewsCount(first.reviewsCount)),
          ),
        ..._reviews.map(
          (review) => Padding(
            padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
            child: ShopReviewTile(review: review, showReport: true),
          ),
        ),
        if (_page < _lastPage)
          TextButton(
            style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
            onPressed: _loadingMore ? null : _loadMore,
            child: Text(_loadingMore ? s.loading : s.showMore),
          ),
      ],
    );
  }
}
