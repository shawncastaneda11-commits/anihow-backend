import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../support/crop_language.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/main_tab_app_bar.dart';
import 'cancel_reservations_dialog.dart';
import 'farm_announcements_screen.dart';
import 'harvest_form.dart';
import 'listing_delete.dart';
import 'listing_form_screen.dart';
import 'listing_reservations_screen.dart';
import 'stock_history_screen.dart';
import 'stock_sheets.dart';
import 'tawad_form_screen.dart';

enum _ListingFilter { all, inStock, low, hidden }

class FarmerListingsScreen extends StatefulWidget {
  const FarmerListingsScreen({super.key, this.showAccountMenu = false});

  /// Set by [FarmerShell] only.
  final bool showAccountMenu;

  @override
  State<FarmerListingsScreen> createState() => _FarmerListingsScreenState();
}

class _FarmerListingsScreenState extends State<FarmerListingsScreen> {
  List<ListingItem> _items = const [];
  List<FarmAnnouncement> _announcements = const [];
  bool _loading = true;
  Object? _error;
  final Set<int> _toggling = {};
  _ListingFilter _filter = _ListingFilter.all;

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

  Future<void> _openNews() async {
    final userId = context.read<AuthController>().user?.id;
    await openFarmAnnouncements(context);
    if (!mounted || userId == null) {
      return;
    }
    for (final item in _announcements) {
      DismissedAnnouncementStore.remember(userId, item.id);
    }
    setState(() {});
  }

  Future<void> _menu(String action, ListingItem listing) async {
    switch (action) {
      case 'edit':
        await _openForm(listing);
      case 'actual':
        final changed = await showAddStockSheet(context, listing, actual: true);
        if (changed && mounted) {
          await _reload();
        }
      case 'add':
        final changed = await showAddStockSheet(context, listing);
        if (changed && mounted) {
          await _reload();
        }
      case 'remove':
        final changed = await showRemoveStockSheet(context, listing);
        if (changed && mounted) {
          await _reload();
        }
      case 'history':
        if (!mounted) {
          return;
        }
        await Navigator.of(context).push<void>(
          MaterialPageRoute(
            builder: (_) => StockHistoryScreen(
              listingId: listing.id,
              listingTitle: listing.title,
            ),
          ),
        );
      case 'tawad':
        if (!mounted) {
          return;
        }
        final changed = await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => TawadFormScreen(listing: listing),
          ),
        );
        if (changed == true && mounted) {
          await _reload();
        }
      case 'reservations':
        if (!mounted) {
          return;
        }
        await Navigator.of(context).push<void>(
          MaterialPageRoute(
            builder: (_) => ListingReservationsScreen(listing: listing),
          ),
        );
      case 'delete':
        final deleted = await deleteListingFlow(context, listing);
        if (deleted && mounted) {
          await _reload();
        }
    }
  }

  List<ListingItem> _visible() {
    return switch (_filter) {
      _ListingFilter.all => _items,
      _ListingFilter.inStock => _items
          .where((item) => item.isInStock && item.isSellerActive)
          .toList(),
      _ListingFilter.low => _items.where((item) => item.isLowStock).toList(),
      _ListingFilter.hidden =>
        _items.where((item) => !item.isSellerActive).toList(),
    };
  }

  int _count(_ListingFilter filter) {
    return switch (filter) {
      _ListingFilter.all => _items.length,
      _ListingFilter.inStock => _items
          .where((item) => item.isInStock && item.isSellerActive)
          .length,
      _ListingFilter.low => _items.where((item) => item.isLowStock).length,
      _ListingFilter.hidden =>
        _items.where((item) => !item.isSellerActive).length,
    };
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final userId = context.watch<AuthController>().user?.id;
    final language = context.watch<PreferencesController>().language;
    final seen = userId == null
        ? const <int>{}
        : DismissedAnnouncementStore.load(userId);
    final unseen = _announcements.where((item) => !seen.contains(item.id)).length;

    return Scaffold(
      appBar: widget.showAccountMenu
          ? mainTabAppBar(title: s.myListings, showAccountMenu: true)
          : null,
      floatingActionButton: FloatingActionButton.extended(
        heroTag: 'farmer-add-listing',
        tooltip: s.newListing,
        backgroundColor: AniHowColors.brand,
        foregroundColor: Colors.white,
        shape: const StadiumBorder(),
        extendedIconLabelSpacing: 8,
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: Text(s.newListing),
      ),
      body: Column(
        children: [
          if (widget.showAccountMenu) mainTabBodyGap,
          if (!_loading && _error == null) ...[
            _FarmNewsRow(
              title: _announcements.isEmpty ? null : _announcements.first.title,
              unseen: unseen,
              onTap: _openNews,
            ),
            _FilterChips(
              selected: _filter,
              count: _count,
              onSelected: (filter) => setState(() => _filter = filter),
            ),
          ],
          Expanded(child: _body(s, language)),
        ],
      ),
    );
  }

  Widget _body(AppStrings s, CropLanguage language) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(child: Text('$_error'));
    }
    final items = _visible();
    final empty = switch (_filter) {
      _ListingFilter.all => s.noListingsYet,
      _ListingFilter.inStock => s.noInStockListings,
      _ListingFilter.low => s.noLowStockListings,
      _ListingFilter.hidden => s.noHiddenListings,
    };
    return RefreshIndicator(
      onRefresh: _reload,
      child: items.isEmpty
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: 240,
                  child: Center(child: Text(empty)),
                ),
              ],
            )
          : ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(
                AniHowSpace.screen,
                0,
                AniHowSpace.screen,
                96,
              ),
              itemCount: items.length,
              separatorBuilder: (_, _) =>
                  const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                final listing = items[index];
                return _SellerListingCard(
                  listing: listing,
                  language: language,
                  onOpen: () => _openForm(listing),
                  onToggle: (active) => _toggle(listing, active),
                  onMenu: (action) => _menu(action, listing),
                  onChanged: _reload,
                );
              },
            ),
    );
  }
}

class _FarmNewsRow extends StatelessWidget {
  const _FarmNewsRow({
    required this.title,
    required this.unseen,
    required this.onTap,
  });

  final String? title;
  final int unseen;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final amberBg = dark ? const Color(0xFF5A431C) : const Color(0xFFFBF3DC);
    final amberFg = dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C);
    final greenBg = dark ? const Color(0xFF245C42) : const Color(0xFFE5F4EB);
    final greenFg = dark ? const Color(0xFFB7E4C7) : const Color(0xFF145C38);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        AniHowSpace.cardGap,
        AniHowSpace.screen,
        0,
      ),
      child: Card(
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          key: const ValueKey('farm-news-row'),
          onTap: onTap,
          child: ConstrainedBox(
            constraints: const BoxConstraints(minHeight: 48),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              child: Row(
                children: [
                  DecoratedBox(
                    decoration: BoxDecoration(
                      color: amberBg,
                      shape: BoxShape.circle,
                    ),
                    child: SizedBox(
                      width: 36,
                      height: 36,
                      child: Icon(Icons.campaign_outlined, color: amberFg, size: 20),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Flexible(
                              child: Text(
                                s.farmNews,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: theme.textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            if (unseen > 0) ...[
                              const SizedBox(width: 8),
                              DecoratedBox(
                                decoration: BoxDecoration(
                                  color: greenBg,
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Padding(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 8,
                                    vertical: 2,
                                  ),
                                  child: Text(
                                    s.newsCount(unseen),
                                    style: theme.textTheme.labelSmall?.copyWith(
                                      color: greenFg,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                        Text(
                          title ?? s.noFarmNewsYet,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: theme.textTheme.bodySmall?.copyWith(color: muted),
                        ),
                      ],
                    ),
                  ),
                  Icon(Icons.chevron_right, color: muted),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _FilterChips extends StatelessWidget {
  const _FilterChips({
    required this.selected,
    required this.count,
    required this.onSelected,
  });

  final _ListingFilter selected;
  final int Function(_ListingFilter filter) count;
  final ValueChanged<_ListingFilter> onSelected;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final chips = <(_ListingFilter, String, Key)>[
      (_ListingFilter.all, s.filterAll, const ValueKey('listing-filter-all')),
      (
        _ListingFilter.inStock,
        s.filterInStock,
        const ValueKey('listing-filter-stock'),
      ),
      (_ListingFilter.low, s.filterLow, const ValueKey('listing-filter-low')),
      (
        _ListingFilter.hidden,
        s.filterHidden,
        const ValueKey('listing-filter-hidden'),
      ),
    ];
    return SizedBox(
      height: 48,
      child: ListView(
        key: const ValueKey('listing-filters'),
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
        children: [
          for (final chip in chips) ...[
            _FilterChip(
              key: chip.$3,
              label: s.filterWithCount(chip.$2, count(chip.$1)),
              selected: selected == chip.$1,
              onTap: () => onSelected(chip.$1),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final fill = dark ? const Color(0xFF2A3330) : const Color(0xFFF1EFE8);
    final background = selected ? AniHowColors.brand : fill;
    final foreground = selected
        ? Colors.white
        : theme.colorScheme.onSurface;
    return Center(
      child: Semantics(
        button: true,
        selected: selected,
        child: Material(
          color: background,
          borderRadius: BorderRadius.circular(999),
          child: InkWell(
            onTap: onTap,
            customBorder: const StadiumBorder(),
            child: ConstrainedBox(
              constraints: const BoxConstraints(minHeight: 36),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                child: Center(
                  child: Text(
                    label,
                    style: theme.textTheme.labelLarge?.copyWith(
                      color: foreground,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _SellerListingCard extends StatelessWidget {
  const _SellerListingCard({
    required this.listing,
    required this.language,
    required this.onOpen,
    required this.onToggle,
    required this.onMenu,
    required this.onChanged,
  });

  final ListingItem listing;
  final CropLanguage language;
  final VoidCallback onOpen;
  final ValueChanged<bool> onToggle;
  final ValueChanged<String> onMenu;
  final Future<void> Function() onChanged;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.72);
    final crop = listing.category?.labelFor(language).trim();
    final meta = crop == null || crop.isEmpty
        ? listing.priceLabel
        : '$crop · ${listing.priceLabel}';
    return Card(
      margin: EdgeInsets.zero,
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            onTap: onOpen,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(12, 12, 4, 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _ListingThumb(listing: listing),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              listing.title,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                            Text(
                              meta,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodySmall?.copyWith(
                                color: muted,
                              ),
                            ),
                            const SizedBox(height: 6),
                            _StatusPill(listing: listing),
                          ],
                        ),
                      ),
                      _MoreMenu(listing: listing, onSelected: onMenu),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    _stockLine(s),
                    style: theme.textTheme.bodySmall?.copyWith(
                      color: muted,
                      fontSize: 13,
                    ),
                  ),
                  _NoticeChips(listing: listing),
                ],
              ),
            ),
          ),
          _ActionRow(
            listing: listing,
            onOpen: onOpen,
            onChanged: onChanged,
          ),
          const Divider(height: 1),
          _VisibilityFooter(listing: listing, onToggle: onToggle),
        ],
      ),
    );
  }

  String _stockLine(AppStrings s) {
    final reserved = listing.reservedQuantity ?? 0;
    final count = listing.activeReservationsCount ?? 0;
    if (listing.isUpcoming && (reserved > 0 || count > 0)) {
      final shown = reserved == reserved.roundToDouble()
          ? reserved.toStringAsFixed(0)
          : reserved.toString();
      return s.reservedHarvest(shown, listing.unit ?? '', count);
    }
    final amount = formatGoodQuantity(
      double.tryParse(listing.quantityAvailable) ?? 0,
    );
    return s.quantityLeft(amount, listing.unit ?? '');
  }
}

class _ListingThumb extends StatelessWidget {
  const _ListingThumb({required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    final url = listing.thumbnailUrl ?? listing.imageUrl;
    final fallback = ColoredBox(
      key: ValueKey('listing-thumb-fallback-${listing.id}'),
      color: accentTint(context),
      child: Icon(Icons.eco_outlined, color: readableAccent(context)),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        width: 64,
        height: 64,
        child: url == null || url.isEmpty
            ? fallback
            : Image.network(
                url,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => fallback,
              ),
      ),
    );
  }
}

class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final dark = Theme.of(context).brightness == Brightness.dark;
    final (label, background, foreground) = _colors(s, dark);
    return Align(
      alignment: Alignment.centerLeft,
      child: DecoratedBox(
        key: ValueKey('listing-status-${listing.id}'),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(999),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
          child: Text(
            label,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
              color: foreground,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  (String, Color, Color) _colors(AppStrings s, bool dark) {
    if (listing.isTakenDown) {
      return (
        s.takenDown,
        dark ? const Color(0xFF5C2A2A) : const Color(0xFFFDECEC),
        dark ? const Color(0xFFF5C2C0) : const Color(0xFF8C1D18),
      );
    }
    if (!listing.isActive) {
      return _grey(s.hiddenStatus, dark);
    }
    if (listing.expiredWithStock) {
      return _grey(s.expiredStatus, dark);
    }
    if (listing.isUpcoming) {
      return (
        s.upcomingStatus,
        dark ? const Color(0xFF1E3A5C) : const Color(0xFFE7F0FA),
        dark ? const Color(0xFFC5DDF5) : const Color(0xFF1A4E86),
      );
    }
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    if (quantity <= 0) {
      return _grey(s.outOfStockStatus, dark);
    }
    if (listing.isLowStock) {
      return (
        s.lowStock,
        dark ? const Color(0xFF5A431C) : const Color(0xFFFBF3DC),
        dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C),
      );
    }
    return (
      s.onTheMarket,
      dark ? const Color(0xFF245C42) : const Color(0xFFE5F4EB),
      dark ? const Color(0xFFB7E4C7) : const Color(0xFF145C38),
    );
  }

  (String, Color, Color) _grey(String label, bool dark) {
    return (
      label,
      dark ? const Color(0xFF2E3834) : const Color(0xFFEEEDE8),
      dark ? const Color(0xFFD5D1C8) : const Color(0xFF3E4642),
    );
  }
}

class _NoticeChips extends StatelessWidget {
  const _NoticeChips({required this.listing});

  final ListingItem listing;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final dark = Theme.of(context).brightness == Brightness.dark;
    final chips = <Widget>[];
    if (listing.tawadPaused) {
      chips.add(
        _MiniChip(
          key: ValueKey('listing-discount-${listing.id}'),
          label: s.tawadPausedChip,
          background: dark ? const Color(0xFF2E3834) : const Color(0xFFEEEDE8),
          foreground: dark ? const Color(0xFFD5D1C8) : const Color(0xFF3E4642),
        ),
      );
    } else if (listing.tawad?.isActive == true) {
      chips.add(
        _MiniChip(
          key: ValueKey('listing-discount-${listing.id}'),
          label: s.tawadOn,
          background: dark ? const Color(0xFF245C42) : const Color(0xFFE5F4EB),
          foreground: dark ? const Color(0xFFB7E4C7) : const Color(0xFF145C38),
        ),
      );
    }
    final reserved = listing.activeReservationsCount ?? 0;
    if (reserved > 0) {
      chips.add(
        _MiniChip(
          label: s.reservedCount(reserved),
          background: dark ? const Color(0xFF1E3A5C) : const Color(0xFFE7F0FA),
          foreground: dark ? const Color(0xFFC5DDF5) : const Color(0xFF1A4E86),
        ),
      );
    }
    final until = listing.availableUntil;
    if (until != null && _withinThreeDays(until)) {
      chips.add(
        _MiniChip(
          label: s.untilDate(s.shortDate(until)),
          background: dark ? const Color(0xFF2E3834) : const Color(0xFFEEEDE8),
          foreground: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.72),
        ),
      );
    }
    if (chips.isEmpty) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Wrap(spacing: 6, runSpacing: 6, children: chips),
    );
  }

  bool _withinThreeDays(DateTime until) {
    final now = DateTime.now();
    final end = DateTime(until.year, until.month, until.day);
    final today = DateTime(now.year, now.month, now.day);
    final days = end.difference(today).inDays;
    return days >= 0 && days <= 3;
  }
}

class _MiniChip extends StatelessWidget {
  const _MiniChip({
    super.key,
    required this.label,
    required this.background,
    required this.foreground,
  });

  final String label;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
        child: Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: foreground,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.listing,
    required this.onOpen,
    required this.onChanged,
  });

  final ListingItem listing;
  final VoidCallback onOpen;
  final Future<void> Function() onChanged;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    if (listing.harvestDueSoon) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
        child: _AmberButton(
          key: ValueKey('record-harvest-${listing.id}'),
          label: s.recordHarvest,
          onPressed: () async {
            final changed = await showAddStockSheet(
              context,
              listing,
              actual: true,
            );
            if (changed) {
              await onChanged();
            }
          },
        ),
      );
    }
    if (listing.expiredWithStock) {
      return _ExpiredActions(listing: listing, onExtend: onOpen, onChanged: onChanged);
    }
    if (_needsStock) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
        child: _AmberButton(
          label: s.addStock,
          onPressed: () async {
            final changed = await showAddStockSheet(context, listing);
            if (changed) {
              await onChanged();
            }
          },
        ),
      );
    }
    return const SizedBox.shrink();
  }

  /// Low or sold out, for listings that take normal stock (not a pending harvest).
  bool get _needsStock {
    if (listing.needsActualHarvest ||
        listing.isUpcoming ||
        listing.isTakenDown) {
      return false;
    }
    final quantity = double.tryParse(listing.quantityAvailable) ?? 0;
    return listing.isLowStock || quantity <= 0;
  }
}

class _AmberButton extends StatelessWidget {
  const _AmberButton({
    super.key,
    required this.label,
    required this.onPressed,
  });

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final dark = Theme.of(context).brightness == Brightness.dark;
    return FilledButton(
      onPressed: onPressed,
      style: FilledButton.styleFrom(
        backgroundColor: dark ? const Color(0xFF5A431C) : const Color(0xFFFBF3DC),
        foregroundColor: dark ? const Color(0xFFF6D48A) : const Color(0xFF7A4E0C),
        minimumSize: const Size.fromHeight(40),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        shape: const StadiumBorder(),
        elevation: 0,
      ),
      child: Text(label),
    );
  }
}

class _ExpiredActions extends StatelessWidget {
  const _ExpiredActions({
    required this.listing,
    required this.onExtend,
    required this.onChanged,
  });

  final ListingItem listing;
  final VoidCallback onExtend;
  final Future<void> Function() onChanged;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final muted = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.72);
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 0, 12, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            s.leftAfterExpiry(listing.quantityAvailable, listing.unit ?? ''),
            key: ValueKey('expired-left-${listing.id}'),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(color: muted),
          ),
          const SizedBox(height: 6),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  key: ValueKey('remove-spoiled-${listing.id}'),
                  onPressed: () async {
                    final changed = await showRemoveStockSheet(
                      context,
                      listing,
                      prefilledReason: 'spoiled',
                    );
                    if (changed) {
                      await onChanged();
                    }
                  },
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(48, 40),
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    shape: const StadiumBorder(),
                  ),
                  child: Text(s.removeAsSpoiled),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: OutlinedButton(
                  key: ValueKey('extend-${listing.id}'),
                  onPressed: onExtend,
                  style: OutlinedButton.styleFrom(
                    minimumSize: const Size(48, 40),
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                    shape: const StadiumBorder(),
                  ),
                  child: Text(s.extendListing),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _VisibilityFooter extends StatelessWidget {
  const _VisibilityFooter({required this.listing, required this.onToggle});

  final ListingItem listing;
  final ValueChanged<bool> onToggle;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final muted = Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.72);
    final takenDown = listing.isTakenDown;
    final label = takenDown
        ? s.takenDownByAdmin
        : listing.isSellerActive
        ? s.buyersCanSeeThis
        : s.hiddenFromBuyers;
    return SizedBox(
      height: 48,
      child: Padding(
        padding: const EdgeInsets.only(left: 12, right: 4),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: muted,
                ),
              ),
            ),
            Semantics(
              label: s.showListingInMarket(listing.title),
              child: Switch(
                key: ValueKey('listing-visible-${listing.id}'),
                value: takenDown ? false : listing.isActive,
                onChanged: takenDown ? null : onToggle,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MoreMenu extends StatelessWidget {
  const _MoreMenu({required this.listing, required this.onSelected});

  final ListingItem listing;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final error = theme.colorScheme.error;
    return PopupMenuButton<String>(
      key: ValueKey('listing-menu-${listing.id}'),
      tooltip: s.moreActions,
      padding: EdgeInsets.zero,
      icon: const Icon(Icons.more_vert),
      style: IconButton.styleFrom(
        minimumSize: const Size(44, 44),
        fixedSize: const Size(44, 44),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
        padding: EdgeInsets.zero,
      ),
      color: theme.cardTheme.color ?? theme.colorScheme.surface,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      onSelected: onSelected,
      itemBuilder: (context) => [
        PopupMenuItem(value: 'edit', child: Text(s.editListing)),
        if (listing.needsActualHarvest)
          PopupMenuItem(value: 'actual', child: Text(s.recordActualHarvest))
        else ...[
          PopupMenuItem(value: 'add', child: Text(s.addStock)),
          PopupMenuItem(value: 'remove', child: Text(s.removeStock)),
        ],
        PopupMenuItem(value: 'history', child: Text(s.stockHistory)),
        PopupMenuItem(value: 'tawad', child: Text(s.tawadDiscount)),
        if (listing.isUpcoming)
          PopupMenuItem(value: 'reservations', child: Text(s.reservationsTab)),
        const PopupMenuDivider(),
        PopupMenuItem(
          value: 'delete',
          child: Text(s.deleteListing, style: TextStyle(color: error)),
        ),
      ],
    );
  }
}
