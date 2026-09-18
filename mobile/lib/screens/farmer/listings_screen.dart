import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/produce_card.dart';
import 'listing_form_screen.dart';

class FarmerListingsScreen extends StatefulWidget {
  const FarmerListingsScreen({super.key});

  @override
  State<FarmerListingsScreen> createState() => _FarmerListingsScreenState();
}

class _FarmerListingsScreenState extends State<FarmerListingsScreen> {
  late Future<List<ListingItem>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.farmerListings();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.farmerListings();
    setState(() => _future = future);
    await future;
  }

  Future<void> _toggle(ListingItem listing) async {
    try {
      await context.read<AuthController>().api.toggleListingActive(listing.id);
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
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        floatingActionButton: FloatingActionButton(
          onPressed: () => _openForm(),
          child: const Icon(Icons.add),
        ),
        body: Column(
          children: [
            const TabBar(
              tabs: [
                Tab(height: AniHowSpace.tabHeight, text: 'All'),
                Tab(height: AniHowSpace.tabHeight, text: 'In stock'),
                Tab(height: AniHowSpace.tabHeight, text: 'Low'),
              ],
            ),
            Expanded(
              child: FutureBuilder<List<ListingItem>>(
                future: _future,
                builder: (context, snapshot) {
                  if (snapshot.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snapshot.hasError) {
                    return Center(child: Text('${snapshot.error}'));
                  }
                  final items = snapshot.data ?? const [];
                  return TabBarView(
                    children: [
                      _List(
                        items: _filter(items, 0),
                        emptyLabel: 'No listings yet.',
                        onReload: _reload,
                        onOpen: _openForm,
                        onToggle: _toggle,
                      ),
                      _List(
                        items: _filter(items, 1),
                        emptyLabel: 'No in-stock listings.',
                        onReload: _reload,
                        onOpen: _openForm,
                        onToggle: _toggle,
                      ),
                      _List(
                        items: _filter(items, 2),
                        emptyLabel: 'No low-stock listings.',
                        onReload: _reload,
                        onOpen: _openForm,
                        onToggle: _toggle,
                      ),
                    ],
                  );
                },
              ),
            ),
          ],
        ),
      ),
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
  final Future<void> Function(ListingItem) onToggle;

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
            trailing: Transform.scale(
              scale: AniHowSpace.switchScale,
              child: Switch(
                value: listing.isActive,
                onChanged: (_) => onToggle(listing),
              ),
            ),
          );
        },
      ),
    );
  }
}
