import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/listing_active_badge.dart';
import '../../widgets/produce_card.dart';
import 'farm_announcements_screen.dart';
import 'listing_form_screen.dart';

class FarmerListingsScreen extends StatefulWidget {
  const FarmerListingsScreen({super.key});

  @override
  State<FarmerListingsScreen> createState() => _FarmerListingsScreenState();
}

class _FarmerListingsScreenState extends State<FarmerListingsScreen> {
  List<ListingItem> _items = const [];
  List<FarmAnnouncement> _announcements = const [];
  int? _dismissedAnnouncementId;
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
      final api = context.read<AuthController>().api;
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

    _toggling.add(listing.id);
    _replace(listing.copyWith(isActive: isActive));

    try {
      final updated = await context.read<AuthController>().api.toggleListingActive(
            listing.id,
            isActive: isActive,
          );
      if (mounted) {
        _replace(updated);
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      _replace(listing);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      _toggling.remove(listing.id);
    }
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

  FarmAnnouncement? get _bannerAnnouncement {
    for (final item in _announcements) {
      if (item.id != _dismissedAnnouncementId) {
        return item;
      }
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final banner = _bannerAnnouncement;

    return DefaultTabController(
      length: 3,
      child: Scaffold(
        floatingActionButton: FloatingActionButton(
          onPressed: () => _openForm(),
          child: const Icon(Icons.add),
        ),
        body: Column(
          children: [
            if (banner != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AniHowSpace.screen,
                  AniHowSpace.cardGap,
                  AniHowSpace.screen,
                  0,
                ),
                child: FarmAnnouncementBanner(
                  announcement: banner,
                  onOpen: () => openFarmAnnouncements(context),
                  onDismiss: () => setState(() => _dismissedAnnouncementId = banner.id),
                ),
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
            onTap: () => onOpen(listing),
            trailing: ListingActiveBadge(
              isActive: listing.isActive,
              onTap: () => onToggle(listing, !listing.isActive),
            ),
          );
        },
      ),
    );
  }
}
