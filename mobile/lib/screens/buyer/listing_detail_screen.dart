import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../services/cart_requests.dart';
import '../../push/push_permission.dart';
import '../../state/auth_controller.dart';
import '../../state/cart_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/order_quantity.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/chat_with_stall_button.dart';
import '../../widgets/form_label.dart';
import '../../widgets/growing_badge.dart';
import '../../widgets/order_quantity_stepper.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/produce_photo.dart';
import '../../widgets/promo_badge.dart';
import '../../widgets/report_sheet.dart';
import '../../widgets/status_pill.dart';
import 'cart_screen.dart';
import 'pay_now_screen.dart';
import 'shop_profile_screen.dart';

String? _reserveBlockReason(ListingItem listing, AppStrings s) {
  if (!listing.isUpcoming || listing.canReserve != false) {
    return null;
  }
  if (!listing.acceptsOnlinePayment) {
    return s.sellerNoReservations;
  }
  final opens = listing.availableFrom;
  if (opens != null && !opens.isAfter(DateTime.now().add(const Duration(hours: 1)))) {
    return s.reservationsClosed;
  }
  return null;
}

double? _available(ListingItem listing) {
  return double.tryParse(listing.sellableQuantity ?? listing.quantityAvailable);
}

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
  bool _seeded = false;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    if (preview != null) {
      _quantity.text = formatOrderAmount(preview.minOrderQuantity);
      _seeded = true;
    }
    _future = preview != null
        ? Future.value(preview)
        : context.read<AuthController>().api.marketplaceShow(widget.listingId);
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _openReserve(ListingItem listing) async {
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        return ReserveHarvestSheet(
          listing: listing,
          onReserve: (quantity, preference) async {
            final reservation = await sheetContext
                .read<AuthController>()
                .api
                .reserveListing(
                  listingId: listing.id,
                  quantity: quantity,
                  fulfillmentPreference: preference,
                );
            if (sheetContext.mounted) {
              Navigator.of(sheetContext).pop();
            }
            if (!mounted) {
              return;
            }
            await offerPushPermission(context);
            if (!mounted) {
              return;
            }
            await Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => PayNowScreen(reservation: reservation),
              ),
            );
          },
        );
      },
    );
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
              Navigator.of(context)
                  .push(MaterialPageRoute(builder: (_) => const CartScreen()));
            },
          ),
        ),
      );
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _adding = false);
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
              final ownListing =
                  listing != null &&
                  listing.sellerId != null &&
                  listing.sellerId == userId;
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
          if (!_seeded) {
            _seeded = true;
            final next = formatOrderAmount(listing.minOrderQuantity);
            if (_quantity.text != next) {
              WidgetsBinding.instance.addPostFrameCallback((_) {
                if (mounted) {
                  _quantity.text = next;
                }
              });
            }
          }
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
                  Expanded(
                    child: Text(
                      listing.name,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                  ),
                  if (listing.isLowStock)
                    StatusPill.lowStock(strings: s)
                  else
                    StatusPill.inStock(strings: s),
                ],
              ),
              if (listing.category != null) ...[
                const SizedBox(height: 2),
                Text(
                  listing.category!.labelFor(
                    context.watch<PreferencesController>().language,
                  ),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.onSurface
                        .withValues(alpha: 0.7),
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
                              listing.sellerName ??
                                  s.t('Farm stall', 'Tindahan'),
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
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
              if (widget.preview == null &&
                  context.watch<AuthController>().user?.isBuyer == true &&
                  listing.sellerId != null &&
                  listing.sellerId != context.watch<AuthController>().user?.id)
                ChatWithStallButton(
                  sellerId: listing.sellerId!,
                  compact: true,
                  listingId: listing.id,
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
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              listing.priceLabel,
                              style: Theme.of(context).textTheme.titleMedium
                                  ?.copyWith(
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
                      GrowingBadge(
                        badge: listing.organicBadge,
                        certifier: listing.organicCertifier,
                      ),
                      if (listing.harvestedOn != null)
                        Text(
                          s.harvestedLine(
                            s.shortDate(listing.harvestedOn!.toLocal()),
                          ),
                        ),
                      if (listing.isUpcoming && listing.availableFrom != null)
                        Text(
                          key: const ValueKey('upcoming-badge'),
                          s.availableFromBadge(
                            s.shortDate(listing.availableFrom!.toLocal()),
                          ),
                        ),
                    ],
                  ),
                ),
              ),
              if (promoBadgeLabel(s, listing.tawad, unit: listing.unit) !=
                  null) ...[
                const SizedBox(height: AniHowSpace.labelGap),
                PromoBadge(rule: listing.tawad, unit: listing.unit),
              ],
              if (listing.description != null &&
                  listing.description!.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(listing.description!),
              ],
              const SizedBox(height: AniHowSpace.section),
              if (_reserveBlockReason(listing, s) case final reason?)
                Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FilledButton(
                      key: const ValueKey('reserve-harvest'),
                      style: FilledButton.styleFrom(
                        minimumSize: const Size.fromHeight(48),
                      ),
                      onPressed: null,
                      child: Text(s.reserve),
                    ),
                    const SizedBox(height: 8),
                    Text(reason, key: const ValueKey('reserve-unavailable')),
                  ],
                )
              else if (listing.showComingSoon)
                Text(
                  s.comingSoon,
                  key: const ValueKey('coming-soon'),
                  style: Theme.of(context).textTheme.titleMedium,
                )
              else if (listing.showReserveButton)
                FilledButton(
                  key: const ValueKey('reserve-harvest'),
                  style: FilledButton.styleFrom(
                    minimumSize: const Size.fromHeight(48),
                  ),
                  onPressed: () => _openReserve(listing),
                  child: Text(s.reserve),
                )
              else ...[
                AniHowField(
                  label: s.quantity,
                  child: OrderQuantityStepper(
                    controller: _quantity,
                    min: listing.minOrderQuantity,
                    step: listing.orderStep,
                    unit: listing.unit ?? '',
                    max: _available(listing),
                  ),
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                PrimaryButton(
                  label: s.addToCart,
                  onPressed: _addToCart,
                  busy: _adding,
                ),
              ],
            ],
          );
        },
      ),
    );
  }
}

class ReserveHarvestSheet extends StatefulWidget {
  const ReserveHarvestSheet({
    super.key,
    required this.listing,
    required this.onReserve,
  });

  final ListingItem listing;
  final Future<void> Function(String quantity, String preference) onReserve;

  @override
  State<ReserveHarvestSheet> createState() => _ReserveHarvestSheetState();
}

class _ReserveHarvestSheetState extends State<ReserveHarvestSheet> {
  late final TextEditingController _quantity;
  String _preference = CartRequests.buyerPickup;
  bool _busy = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _quantity = TextEditingController(
      text: formatOrderAmount(widget.listing.minOrderQuantity),
    );
  }

  @override
  void dispose() {
    _quantity.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final quantity = _quantity.text.trim();
    if (quantity.isEmpty || (double.tryParse(quantity) ?? 0) <= 0) {
      setState(() => _error = AppStrings.read(context).enterQuantity);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onReserve(quantity, _preference);
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _error = error.message);
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final listing = widget.listing;
    final price = double.tryParse(listing.pricePerUnit) ?? 0;
    final bottom = MediaQuery.viewInsetsOf(context).bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(20, 16, 20, 20 + bottom),
      child: ListenableBuilder(
        listenable: _quantity,
        builder: (context, _) {
          final quantity = double.tryParse(_quantity.text.trim()) ?? 0;
          final estimate = price * quantity;
          return Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(s.reserve, style: Theme.of(context).textTheme.titleLarge),
              if (listing.availableFrom != null) ...[
                const SizedBox(height: 8),
                Text(
                  s.availableFromBadge(
                    s.shortDate(listing.availableFrom!.toLocal()),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              Text('${s.lockedPrice}: ${listing.priceLabel}'),
              const SizedBox(height: 12),
              AniHowField(
                label: s.quantity,
                child: OrderQuantityStepper(
                  controller: _quantity,
                  min: listing.minOrderQuantity,
                  step: listing.orderStep,
                  unit: listing.unit ?? '',
                  max: _available(listing),
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '${s.estimatedTotal}: ${AniHowMoney.peso(estimate)}',
                key: const ValueKey('reserve-estimate'),
              ),
              const SizedBox(height: 8),
              Text(s.payWithSellerQr),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: _ReserveChoice(
                      label: s.pickupShort,
                      selected: _preference == CartRequests.buyerPickup,
                      onTap: () => setState(
                        () => _preference = CartRequests.buyerPickup,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _ReserveChoice(
                      label: s.deliverShort,
                      selected: _preference == CartRequests.sellerDelivers,
                      onTap: () => setState(
                        () => _preference = CartRequests.sellerDelivers,
                      ),
                    ),
                  ),
                ],
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  key: const ValueKey('reserve-error'),
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
              const SizedBox(height: 16),
              FilledButton(
                style: FilledButton.styleFrom(
                  minimumSize: const Size.fromHeight(48),
                ),
                onPressed: _busy ? null : _submit,
                child: Text(s.reserve),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _ReserveChoice extends StatelessWidget {
  const _ReserveChoice({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return OutlinedButton(
      style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
      onPressed: onTap,
      child: Text(
        label,
        style: TextStyle(
          fontWeight: selected ? FontWeight.w800 : FontWeight.w500,
        ),
      ),
    );
  }
}
