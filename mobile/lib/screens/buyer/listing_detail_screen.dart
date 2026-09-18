import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/category_color.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/status_pill.dart';
import 'shop_profile_screen.dart';

class ListingDetailScreen extends StatefulWidget {
  const ListingDetailScreen({super.key, required this.listingId});

  final int listingId;

  @override
  State<ListingDetailScreen> createState() => _ListingDetailScreenState();
}

class _ListingDetailScreenState extends State<ListingDetailScreen> {
  final _quantity = TextEditingController(text: '1');
  late Future<ListingItem> _future;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.marketplaceShow(widget.listingId);
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _reserve() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.createReservation(
            listingId: widget.listingId,
            quantity: _quantity.text.trim(),
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Reservation placed. Pick up when marked ready.')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _favorite() async {
    try {
      await context.read<AuthController>().api.addFavorite(widget.listingId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Saved to favorites.')),
        );
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Listing')),
      body: FutureBuilder<ListingItem>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final listing = snapshot.data!;
          final accent = CategoryColor.of(listing.category, listingName: listing.name);
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              AspectRatio(
                aspectRatio: 16 / 9,
                child: DecoratedBox(
                  decoration: BoxDecoration(
                    color: accent,
                    borderRadius: BorderRadius.circular(AniHowSpace.radius),
                  ),
                  child: listing.imageUrl != null
                      ? ClipRRect(
                          borderRadius: BorderRadius.circular(AniHowSpace.radius),
                          child: Image.network(
                            listing.imageUrl!,
                            fit: BoxFit.cover,
                            errorBuilder: (_, _, _) => Center(
                              child: Text(
                                listing.name,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w800,
                                  fontSize: AniHowSpace.title,
                                ),
                              ),
                            ),
                          ),
                        )
                      : Center(
                          child: Text(
                            listing.name,
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: AniHowSpace.title,
                            ),
                          ),
                        ),
                ),
              ),
              const SizedBox(height: AniHowSpace.section),
              Row(
                children: [
                  Expanded(child: Text(listing.name, style: Theme.of(context).textTheme.titleMedium)),
                  if (listing.isLowStock) StatusPill.lowStock() else StatusPill.inStock(),
                ],
              ),
              const SizedBox(height: AniHowSpace.labelGap),
              InkWell(
                onTap: listing.sellerId == null
                    ? null
                    : () => openBuyerShop(context, listing.sellerId!),
                borderRadius: BorderRadius.circular(AniHowSpace.radius),
                child: Padding(
                  padding: const EdgeInsets.symmetric(vertical: 4),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              listing.sellerName ?? 'Farm stall',
                              style: TextStyle(
                                fontSize: AniHowSpace.body,
                                fontWeight: FontWeight.w700,
                                color: listing.sellerId == null
                                    ? null
                                    : AniHowColors.deepGreen,
                              ),
                            ),
                            if (listing.sellerLocation != null)
                              Text(
                                listing.sellerLocation!,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                            if (listing.hasRating) ...[
                              const SizedBox(height: AniHowSpace.labelGap),
                              RatingLabel(
                                rating: listing.averageRating!,
                                count: listing.reviewsCount,
                              ),
                            ],
                          ],
                        ),
                      ),
                      if (listing.sellerId != null)
                        const Icon(Icons.chevron_right),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              Text(
                '${AniHowMoney.peso(listing.pricePerUnit)} / ${listing.unitLabel ?? listing.unit ?? ''}',
                style: Theme.of(context).textTheme.labelLarge?.copyWith(color: AniHowColors.deepGreen),
              ),
              Text('${listing.quantityAvailable} available', style: Theme.of(context).textTheme.bodyMedium),
              if (listing.description != null && listing.description!.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(listing.description!),
              ],
              const SizedBox(height: AniHowSpace.section),
              AniHowField(
                label: 'Quantity to reserve',
                child: TextField(
                  controller: _quantity,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              PrimaryButton(label: 'Reserve for pickup', busy: _busy, onPressed: _reserve),
              const SizedBox(height: AniHowSpace.cardGap),
              OutlinedButton(onPressed: _favorite, child: const Text('Add to favorites')),
            ],
          );
        },
      ),
    );
  }
}
