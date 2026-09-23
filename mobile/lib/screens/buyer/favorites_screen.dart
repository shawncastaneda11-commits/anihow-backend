import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

class FavoritesScreen extends StatefulWidget {
  const FavoritesScreen({super.key});

  @override
  State<FavoritesScreen> createState() => _FavoritesScreenState();
}

class _FavoritesScreenState extends State<FavoritesScreen> {
  late Future<List<FavoriteRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.favorites();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.favorites();
    setState(() => _future = future);
    await future;
  }

  Future<void> _remove(int listingId) async {
    try {
      await context.read<AuthController>().api.removeFavorite(listingId);
      if (!mounted) {
        return;
      }
      await _reload();
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return AsyncView<List<FavoriteRecord>>(
      future: _future,
      onRetry: _reload,
      emptyMessage: 'No favorites yet.',
      builder: (context, items) {
        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView.separated(
            padding: AniHowSpace.screenPadding,
            itemCount: items.length,
            separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
            itemBuilder: (context, index) {
              final favorite = items[index];
              final listing = favorite.listing;
              if (listing == null) {
                return ListTile(title: Text('Listing #${favorite.listingId}'));
              }
              return ProduceCard(
                listing: listing,
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
                  onPressed: () => _remove(favorite.listingId),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
