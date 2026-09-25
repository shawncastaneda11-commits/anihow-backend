import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../state/cart_controller.dart';
import '../../state/preferences_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/produce_photo.dart';
import '../../widgets/report_sheet.dart';
import '../../widgets/status_pill.dart';
import 'cart_screen.dart';
import 'shop_profile_screen.dart';

class ListingDetailScreen extends StatefulWidget {
  const ListingDetailScreen({super.key, required this.listingId, this.preview});

  final int listingId;
  final ListingItem? preview;

  @override
  State<ListingDetailScreen> createState() => _ListingDetailScreenState();
}

class _ListingDetailScreenState extends State<ListingDetailScreen> {
  final _quantity = TextEditingController(text: '1');
  late Future<ListingItem> _future;
  bool _adding = false;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    _future = preview != null
        ? Future.value(preview)
        : context.read<AuthController>().api.marketplaceShow(widget.listingId);
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _addToCart() async {
    final quantity = _quantity.text.trim();
    if (quantity.isEmpty || (double.tryParse(quantity) ?? 0) <= 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).enterQuantity)),
      );
      return;
    }
    if (_adding) {
      return;
    }
    setState(() => _adding = true);
    try {
      await context.read<CartController>().add(
            listingId: widget.listingId,
            quantity: quantity,
          );
      if (!mounted) {
        return;
      }
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AppStrings.read(context).addedToCart),
          action: SnackBarAction(
            label: AppStrings.read(context).viewCart,
            onPressed: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const CartScreen()),
              );
            },
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _adding = false);
      }
    }
  }

  Future<void> _favorite() async {
    try {
      await context.read<AuthController>().api.addFavorite(widget.listingId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(AppStrings.read(context).t('Saved to favorites.', 'Nasave sa mga paborito.'))),
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
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.t('Listing', 'Listing')),
        actions: [
          FutureBuilder<ListingItem>(
            future: _future,
            builder: (context, snapshot) {
              final listing = snapshot.data;
              final userId = context.watch<AuthController>().user?.id;
              final ownListing = listing != null && listing.sellerId != null && listing.sellerId == userId;
              if (listing == null || ownListing) {
                return const SizedBox.shrink();
              }
              return TextButton(
                key: const ValueKey('listing-report'),
                style: TextButton.styleFrom(
                  foregroundColor: Theme.of(context).colorScheme.onPrimary,
                  minimumSize: const Size(48, 48),
                ),
                onPressed: () => showReportSheet(
                  context,
                  targetType: 'listing',
                  targetId: listing.id,
                ),
                child: Text(s.report),
              );
            },
          ),
          const CartIconButton(),
        ],
      ),
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
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              AspectRatio(
                aspectRatio: 4 / 3,
                child: ProducePhoto(
                  listing: listing,
                  borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
                  iconSize: 64,
                  preferThumbnail: false,
                ),
              ),
              const SizedBox(height: AniHowSpace.section),
              Row(
                children: [
                  Expanded(child: Text(listing.name, style: Theme.of(context).textTheme.titleMedium)),
                  if (listing.isLowStock) StatusPill.lowStock(strings: s) else StatusPill.inStock(strings: s),
                ],
              ),
              if (listing.category != null) ...[
                const SizedBox(height: 2),
                Text(
                  listing.category!.labelFor(context.watch<PreferencesController>().language),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
                      ),
                ),
              ],
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
                              listing.sellerName ?? s.t('Farm stall', 'Tindahan'),
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
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
              DecoratedBox(
                decoration: BoxDecoration(
                  color: Theme.of(context).brightness == Brightness.dark
                      ? const Color(0xFF1A2A22)
                      : const Color(0xFFEDF6F0),
                  borderRadius: BorderRadius.circular(AniHowSpace.radius),
                ),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          '${AniHowMoney.peso(listing.pricePerUnit)} / ${listing.unitLabel ?? listing.unit ?? ''}',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                color: AniHowColors.brand,
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                      ),
                      Text(
                        '${listing.quantityAvailable} ${s.t('available', 'available')}',
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                    ],
                  ),
                ),
              ),
              if (listing.tawad != null && listing.tawad!.isActive) ...[
                const SizedBox(height: AniHowSpace.labelGap),
                Text(
                  key: const ValueKey('listing-tawad-summary'),
                  listing.tawad!.displaySummary(
                    offThisOrder: s.tawadOffThisOrder,
                    offAtMin: s.tawadOffAtMin,
                  ),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AniHowColors.brand,
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ],
              if (listing.description != null && listing.description!.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(listing.description!),
              ],
              const SizedBox(height: AniHowSpace.section),
              AniHowField(
                label: s.quantity,
                child: TextField(
                  controller: _quantity,
                  keyboardType: const TextInputType.numberWithOptions(decimal: true),
                ),
              ),
              const SizedBox(height: AniHowSpace.fieldGap),
              PrimaryButton(label: s.addToCart, onPressed: _addToCart, busy: _adding),
              const SizedBox(height: AniHowSpace.cardGap),
              OutlinedButton(onPressed: _favorite, child: Text(s.t('Add to favorites', 'Idagdag sa paborito'))),
            ],
          );
        },
      ),
    );
  }
}
