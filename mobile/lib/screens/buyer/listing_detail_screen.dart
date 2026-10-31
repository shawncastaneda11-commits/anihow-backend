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
import '../../theme/readable_accent.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/chat_with_stall_button.dart';
import '../../widgets/form_label.dart';
import '../../widgets/growing_badge.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/id_verification_card.dart';
import '../../widgets/order_quantity_stepper.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
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
    if (!await ensureIdForOrdering(context) || !mounted) {
      return;
    }
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
          return Column(
            children: [
              Expanded(
                child: ListView(
                  padding: AniHowSpace.screenPadding,
                  children: _details(context, listing, s),
                ),
              ),
              _ListingActionBar(
                listing: listing,
                quantity: _quantity,
                adding: _adding,
                onAddToCart: _addToCart,
                onReserve: () => _openReserve(listing),
              ),
            ],
          );
        },
      ),
    );
  }

  List<Widget> _details(BuildContext context, ListingItem listing, AppStrings s) {
    final theme = Theme.of(context);
    final user = context.watch<AuthController>().user;
    final canMessage = widget.preview == null &&
        user?.isBuyer == true &&
        listing.sellerId != null &&
        listing.sellerId != user?.id;
    final reserveBlocked = _reserveBlockReason(listing, s);
    final opens = listing.availableFrom?.toLocal();
    final unit = listing.unit ?? listing.unitLabel ?? '';
    final amount = formatOrderAmount(double.tryParse(listing.quantityAvailable) ?? 0);
    return [
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(listing.name, style: theme.textTheme.titleLarge),
                if (listing.category != null)
                  Text(
                    listing.category!.labelFor(context.watch<PreferencesController>().language),
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: theme.colorScheme.onSurface.withValues(alpha: 0.7),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: AniHowSpace.cardGap),
          _statusPill(listing, s),
        ],
      ),
      const SizedBox(height: AniHowSpace.cardGap),
      _SellerCard(listing: listing, canMessage: canMessage),
      const SizedBox(height: AniHowSpace.section),
      Text(
        listing.priceLabel,
        key: const ValueKey('listing-price'),
        style: theme.textTheme.headlineSmall?.copyWith(
          color: readableAccent(context),
          fontWeight: FontWeight.w800,
        ),
      ),
      const SizedBox(height: AniHowSpace.cardGap),
      Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          if (listing.isUpcoming) ...[
            if (opens != null)
              _InfoChip(
                key: const ValueKey('upcoming-badge'),
                label: s.readyOn(s.shortDate(opens)),
                tone: _ChipTone.preorder,
              ),
            _InfoChip(label: s.expectedAmount(amount, unit)),
          ] else
            _InfoChip(
              key: const ValueKey('listing-left'),
              label: s.amountLeft(amount, unit),
              tone: _ChipTone.stock,
            ),
          if (listing.harvestedOn != null && !listing.isUpcoming)
            _InfoChip(label: s.harvestedLine(s.shortDate(listing.harvestedOn!.toLocal()))),
          if (listing.acceptsOnlinePayment) _InfoChip(label: s.onlinePaymentChip),
        ],
      ),
      GrowingBadge(badge: listing.organicBadge, certifier: listing.organicCertifier),
      if (promoBadgeLabel(s, listing.tawad, unit: listing.unit) != null) ...[
        const SizedBox(height: AniHowSpace.labelGap),
        PromoBadge(rule: listing.tawad, unit: listing.unit),
      ],
      if (listing.description != null && listing.description!.isNotEmpty) ...[
        const SizedBox(height: AniHowSpace.cardGap),
        Text(listing.description!),
      ],
      const SizedBox(height: AniHowSpace.section),
      if (reserveBlocked != null)
        Text(reserveBlocked, key: const ValueKey('reserve-unavailable'))
      else if (listing.showComingSoon)
        Text(
          s.comingSoon,
          key: const ValueKey('coming-soon'),
          style: theme.textTheme.titleMedium,
        )
      else if (listing.showReserveButton)
        AniHowHintCard(
          key: const ValueKey('preorder-note'),
          icon: Icons.event_outlined,
          title: opens == null ? s.preorderNoteTitleNoDate : s.preorderNoteTitle(s.shortDate(opens)),
          body: s.preorderNoteBody,
          tone: AniHowHintTone.cash,
        )
      else
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
    ];
  }

  Widget _statusPill(ListingItem listing, AppStrings s) {
    if (listing.isUpcoming) {
      return StatusPill(
        key: const ValueKey('listing-status-pill'),
        label: listing.showComingSoon ? s.comingSoon : s.preorderPill,
        color: AniHowColors.pending,
        background: const Color(0xFFFFF1DA),
        icon: Icons.event,
      );
    }
    return KeyedSubtree(
      key: const ValueKey('listing-status-pill'),
      child: listing.isLowStock ? StatusPill.lowStock(strings: s) : StatusPill.inStock(strings: s),
    );
  }
}

/// Seller row: who sells it, their rating, a message button and the way to
/// their shop.
class _SellerCard extends StatelessWidget {
  const _SellerCard({required this.listing, required this.canMessage});

  final ListingItem listing;
  final bool canMessage;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final name = listing.sellerName ?? s.t('Farm stall', 'Tindahan');
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    return Card(
      key: const ValueKey('listing-seller-card'),
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: listing.sellerId == null ? null : () => openBuyerShop(context, listing.sellerId!),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          child: Row(
            children: [
              AniHowAvatar(name: name, radius: 20),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                    ),
                    const SizedBox(height: 2),
                    if (listing.hasRating)
                      RatingLabel(rating: listing.averageRating!, count: listing.reviewsCount, size: 14)
                    else
                      Text(s.noReviewsShort, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
                    if (listing.sellerLocation != null && listing.sellerLocation!.isNotEmpty)
                      Text(
                        listing.sellerLocation!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodySmall?.copyWith(color: muted),
                      ),
                  ],
                ),
              ),
              if (canMessage)
                ChatWithStallButton(sellerId: listing.sellerId!, iconOnly: true, listingId: listing.id),
              if (listing.sellerId != null) Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}

enum _ChipTone { plain, stock, preorder }

class _InfoChip extends StatelessWidget {
  const _InfoChip({super.key, required this.label, this.tone = _ChipTone.plain});

  final String label;
  final _ChipTone tone;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final (Color fg, Color bg) = switch (tone) {
      _ChipTone.stock => (readableAccent(context), accentTint(context)),
      _ChipTone.preorder => (
        dark ? const Color(0xFFE9B566) : AniHowColors.pending,
        dark ? const Color(0x26E9B566) : const Color(0xFFFFF1DA),
      ),
      _ChipTone.plain => (
        theme.colorScheme.onSurface.withValues(alpha: 0.85),
        theme.colorScheme.onSurface.withValues(alpha: 0.07),
      ),
    };
    return DecoratedBox(
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(999)),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
        child: Text(
          label,
          style: theme.textTheme.labelMedium?.copyWith(color: fg, fontWeight: FontWeight.w600),
        ),
      ),
    );
  }
}

/// Stays at the bottom of the screen: the total and Add to cart, or the
/// ready date and Reserve for a pre-order.
class _ListingActionBar extends StatelessWidget {
  const _ListingActionBar({
    required this.listing,
    required this.quantity,
    required this.adding,
    required this.onAddToCart,
    required this.onReserve,
  });

  final ListingItem listing;
  final TextEditingController quantity;
  final bool adding;
  final VoidCallback onAddToCart;
  final VoidCallback onReserve;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    final opens = listing.availableFrom?.toLocal();
    final upcoming = listing.isUpcoming;
    final blocked = _reserveBlockReason(listing, s) != null || listing.showComingSoon;

    Widget? leading;
    Widget button;
    if (upcoming) {
      if (opens != null) {
        leading = _BarFigure(label: s.readyLabel, value: s.shortDate(opens), muted: muted);
      }
      final comingSoonOnly = listing.showComingSoon && _reserveBlockReason(listing, s) == null;
      button = FilledButton(
        key: comingSoonOnly ? null : const ValueKey('reserve-harvest'),
        style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
        onPressed: blocked || !listing.showReserveButton ? null : onReserve,
        child: Text(comingSoonOnly ? s.comingSoon : s.reserve),
      );
    } else {
      leading = ValueListenableBuilder<TextEditingValue>(
        valueListenable: quantity,
        builder: (context, value, _) {
          final amount = double.tryParse(value.text.trim()) ?? 0;
          final price = double.tryParse(listing.pricePerUnit) ?? 0;
          return _BarFigure(
            key: const ValueKey('listing-total'),
            label: s.total,
            value: AniHowMoney.peso((amount * price).toStringAsFixed(2)),
            muted: muted,
          );
        },
      );
      button = PrimaryButton(label: s.addToCart, onPressed: onAddToCart, busy: adding);
    }
    return DecoratedBox(
      key: const ValueKey('listing-action-bar'),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        border: Border(top: BorderSide(color: theme.dividerColor)),
      ),
      child: SafeArea(
        top: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(AniHowSpace.screen, 10, AniHowSpace.screen, 12),
          child: Row(
            children: [
              if (leading != null) ...[leading, const SizedBox(width: AniHowSpace.section)],
              Expanded(child: button),
            ],
          ),
        ),
      ),
    );
  }
}

class _BarFigure extends StatelessWidget {
  const _BarFigure({super.key, required this.label, required this.value, required this.muted});

  final String label;
  final String value;
  final Color muted;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
        Text(value, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800)),
      ],
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

  /// Nothing is chosen at first: the buyer has to pick one.
  String? _preference;
  bool _busy = false;
  bool _showChoiceError = false;
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
    final preference = _preference;
    if (preference == null) {
      setState(() => _showChoiceError = true);
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onReserve(quantity, preference);
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
              const SizedBox(height: 16),
              Text(
                s.howWillYouGetIt,
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: _ReserveChoice(
                      key: const ValueKey('reserve-pickup'),
                      label: s.pickupShort,
                      icon: Icons.storefront_outlined,
                      selected: _preference == CartRequests.buyerPickup,
                      onTap: () => setState(() {
                        _preference = CartRequests.buyerPickup;
                        _showChoiceError = false;
                      }),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _ReserveChoice(
                      key: const ValueKey('reserve-deliver'),
                      label: s.deliverShort,
                      icon: Icons.local_shipping_outlined,
                      selected: _preference == CartRequests.sellerDelivers,
                      onTap: () => setState(() {
                        _preference = CartRequests.sellerDelivers;
                        _showChoiceError = false;
                      }),
                    ),
                  ),
                ],
              ),
              if (_showChoiceError) ...[
                const SizedBox(height: 6),
                Text(
                  s.chooseHowToGetIt,
                  key: const ValueKey('reserve-choice-error'),
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
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

/// One of the two pickup or delivery cards. The chosen one is filled, has a
/// thicker border and a check, so the choice is plain at a glance.
class _ReserveChoice extends StatelessWidget {
  const _ReserveChoice({
    super.key,
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final accent = readableAccent(context);
    final border = selected
        ? accent
        : theme.colorScheme.onSurface.withValues(alpha: 0.28);
    return Semantics(
      selected: selected,
      button: true,
      child: Material(
        color: selected ? accentTint(context) : Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: border, width: selected ? 2 : 1),
        ),
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 56),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(
                    selected ? Icons.check_circle : icon,
                    size: 20,
                    color: selected ? accent : theme.colorScheme.onSurface,
                  ),
                  const SizedBox(width: 8),
                  Flexible(
                    child: Text(
                      label,
                      textAlign: TextAlign.center,
                      style: theme.textTheme.labelLarge?.copyWith(
                        fontWeight: selected
                            ? FontWeight.w700
                            : FontWeight.w500,
                        color: selected ? accent : null,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
