import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/availability_chip.dart';
import '../../widgets/listing_active_badge.dart';
import '../../widgets/produce_card.dart';
import 'cancel_reservations_dialog.dart';
import 'farm_announcements_screen.dart';
import 'listing_form_screen.dart';
import 'listing_reservations_screen.dart';
import 'stock_sheets.dart';

class FarmerListingsScreen extends StatefulWidget {
  const FarmerListingsScreen({super.key});

  @override
  State<FarmerListingsScreen> createState() => _FarmerListingsScreenState();
}

class _FarmerListingsScreenState extends State<FarmerListingsScreen> {
  List<ListingItem> _items = const [];
  List<FarmAnnouncement> _announcements = const [];
  bool _loading = true;
  Object? _error;
  final Set<int> _toggling = {};

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    setState(() {
      _loading = _items.isEmpty;
      _error = null;
    });
    try {
      final auth = context.read<AuthController>();
      if (auth.user != null) {
        try {
          await auth.refreshUser();
        } catch (_) {}
      }
      final api = auth.api;
      final items = await api.farmerListings();
      var announcements = const <FarmAnnouncement>[];
      try {
        announcements = await api.farmerAnnouncements();
      } on ApiException {
        announcements = _announcements;
      }
      if (!mounted) {
        return;
      }
      setState(() {
        _items = items;
        _announcements = announcements;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
    }
  }

  void _replace(ListingItem listing) {
    setState(() {
      _items = [
        for (final item in _items)
          if (item.id == listing.id) listing else item,
      ];
    });
  }

  Future<void> _toggle(ListingItem listing, bool isActive) async {
    if (listing.isActive == isActive || _toggling.contains(listing.id)) {
      return;
    }

    var confirm = false;
    final reserved = listing.activeReservationsCount ?? 0;
    if (!isActive && reserved > 0) {
      final accepted = await confirmCancelReservations(
        context,
        count: reserved,
        quantity: formatReservedQuantity(listing.reservedQuantity),
        unit: listing.unit ?? '',
        deleting: false,
      );
      if (!accepted || !mounted) {
        return;
      }
      confirm = true;
    }

    _toggling.add(listing.id);
    _replace(listing.copyWith(isActive: isActive));

    try {
      final updated = await _setActive(listing, isActive, confirm);
      if (mounted) {
        _replace(updated);
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      _replace(listing);
      final conflict = ReservationConflict.fromException(error);
      if (conflict != null && !confirm) {
        final accepted = await confirmCancelReservations(
          context,
          count: conflict.count,
          quantity: conflict.quantity,
          unit: listing.unit ?? '',
          deleting: false,
        );
        if (!accepted || !mounted) {
          return;
        }
        _replace(listing.copyWith(isActive: isActive));
        try {
          final updated = await _setActive(listing, isActive, true);
          if (mounted) {
            _replace(updated);
          }
        } on ApiException catch (again) {
          if (!mounted) {
            return;
          }
          _replace(listing);
          ScaffoldMessenger.of(context)
              .showSnackBar(SnackBar(content: Text(again.message)));
        }
        return;
      }
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      _toggling.remove(listing.id);
    }
  }

  Future<ListingItem> _setActive(
    ListingItem listing,
    bool isActive,
    bool confirm,
  ) {
    return context.read<AuthController>().api.toggleListingActive(
      listing.id,
      isActive: isActive,
      confirmCancelReservations: confirm,
    );
  }

  Future<void> _openForm([ListingItem? listing]) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ListingFormScreen(listing: listing)),
    );
    if (!mounted || changed != true) {
      return;
    }
    await _reload();
  }

  List<ListingItem> _filter(List<ListingItem> items, int tab) {
    return switch (tab) {
      1 => items.where((item) => item.isInStock).toList(),
      2 => items.where((item) => item.isLowStock).toList(),
      _ => items,
    };
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final userId = context.watch<AuthController>().user?.id;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        floatingActionButton: FloatingActionButton(
          heroTag: 'farmer-add-listing',
          tooltip: s.newListing,
          onPressed: () => _openForm(),
          child: const Icon(Icons.add),
        ),
        floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
        body: Column(
          children: [
            if (userId != null && _announcements.isNotEmpty)
              FarmerAnnouncementHomeBanner(
                userId: userId,
                announcements: _announcements,
              ),
            Align(
              alignment: Alignment.centerLeft,
              child: ConstrainedBox(
                constraints: const BoxConstraints(minHeight: 48),
                child: TextButton.icon(
                  onPressed: () => openFarmAnnouncements(context),
                  icon: const Icon(Icons.campaign_outlined),
                  label: Text(s.viewAnnouncements),
                ),
              ),
            ),
            const TabBar(
              tabs: [
                Tab(height: AniHowSpace.tabHeight, text: 'All'),
                Tab(height: AniHowSpace.tabHeight, text: 'In stock'),
                Tab(height: AniHowSpace.tabHeight, text: 'Low'),
              ],
            ),
            Expanded(child: _body()),
          ],
        ),
      ),
    );
  }

  Widget _body() {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    return TabBarView(
      children: [
        _List(
          items: _filter(_items, 0),
          emptyLabel: 'No listings yet.',
          onReload: _reload,
          onOpen: _openForm,
          onToggle: _toggle,
        ),
        _List(
          items: _filter(_items, 1),
          emptyLabel: 'No in-stock listings.',
          onReload: _reload,
          onOpen: _openForm,
          onToggle: _toggle,
        ),
        _List(
          items: _filter(_items, 2),
          emptyLabel: 'No low-stock listings.',
          onReload: _reload,
          onOpen: _openForm,
          onToggle: _toggle,
        ),
      ],
    );
  }
}

class _ExpiredStockBanner extends StatelessWidget {
  const _ExpiredStockBanner({required this.listing, required this.onExtend});

  final ListingItem listing;
  final VoidCallback onExtend;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final unit = listing.unit ?? '';
    return Column(
      crossAxisAlignment: CrossAxisAlignment.end,
      children: [
        Text(
          s.leftAfterExpiry(listing.quantityAvailable, unit),
          key: ValueKey('expired-left-${listing.id}'),
        ),
        TextButton(
          key: ValueKey('remove-spoiled-${listing.id}'),
          onPressed: () => showRemoveStockSheet(
            context,
            listing,
            prefilledReason: 'spoiled',
          ),
          child: Text(s.removeAsSpoiled),
        ),
        TextButton(
          key: ValueKey('extend-${listing.id}'),
          onPressed: onExtend,
          child: Text(s.extendListing),
        ),
      ],
    );
  }
}

class _List extends StatelessWidget {
  const _List({
    required this.items,
    required this.emptyLabel,
    required this.onReload,
    required this.onOpen,
    required this.onToggle,
  });

  final List<ListingItem> items;
  final String emptyLabel;
  final Future<void> Function() onReload;
  final Future<void> Function([ListingItem?]) onOpen;
  final Future<void> Function(ListingItem, bool) onToggle;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return Center(child: Text(emptyLabel));
    }
    return RefreshIndicator(
      onRefresh: onReload,
      child: ListView.separated(
        padding: AniHowSpace.screenPadding,
        itemCount: items.length,
        separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
        itemBuilder: (context, index) {
          final listing = items[index];
          return ProduceCard(
            listing: listing,
            showSeller: false,
            showStock: true,
            showPromo: false,
            onTap: () => onOpen(listing),
            trailing: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.end,
              children: [
                if (listing.tawadPaused)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Chip(
                      key: ValueKey('listing-discount-${listing.id}'),
                      visualDensity: VisualDensity.compact,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      label: Text(AppStrings.of(context).discountPaused),
                      labelStyle: Theme.of(context).textTheme.labelSmall,
                    ),
                  )
                else if (listing.tawad?.isActive == true)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 4),
                    child: Chip(
                      key: ValueKey('listing-discount-${listing.id}'),
                      visualDensity: VisualDensity.compact,
                      materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                      label: Text(AppStrings.of(context).discountChip),
                      labelStyle: Theme.of(context).textTheme.labelSmall,
                    ),
                  ),
                AvailabilityChip(state: listing.availabilityState),
                if (listing.harvestDueSoon)
                  ActionChip(
                    key: ValueKey('record-harvest-${listing.id}'),
                    label: Text(AppStrings.of(context).recordHarvest),
                    onPressed: () => onOpen(listing),
                  ),
                if (listing.expiredWithStock)
                  _ExpiredStockBanner(
                    listing: listing,
                    onExtend: () => onOpen(listing),
                  ),
                ReservedHarvestLabel(listing: listing),
                if (listing.isUpcoming)
                  TextButton(
                    style: TextButton.styleFrom(
                      minimumSize: const Size(48, 48),
                    ),
                    onPressed: () {
                      Navigator.of(context).push(
                        MaterialPageRoute<void>(
                          builder: (_) =>
                              ListingReservationsScreen(listing: listing),
                        ),
                      );
                    },
                    child: Text(AppStrings.of(context).reservationsTab),
                  ),
                ListingActiveBadge(
                  isActive: listing.isSellerActive,
                  onTap: listing.isTakenDown
                      ? null
                      : () => onToggle(listing, !listing.isActive),
                ),
              ],
            ),
          );
        },
      ),
    );
  }
}
