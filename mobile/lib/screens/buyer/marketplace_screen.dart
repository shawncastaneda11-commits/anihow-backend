import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/app_header.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/category_color.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/produce_card.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';

class MarketplaceScreen extends StatefulWidget {
  const MarketplaceScreen({super.key});

  @override
  State<MarketplaceScreen> createState() => _MarketplaceScreenState();
}

class _MarketplaceScreenState extends State<MarketplaceScreen> {
  final _search = TextEditingController();
  int? _cropTypeId;
  String _sort = 'freshest';
  late Future<List<ListingItem>> _listings;
  late Future<List<CategoryItem>> _cropTypes;

  @override
  void initState() {
    super.initState();
    final api = context.read<AuthController>().api;
    _cropTypes = api.cropTypes();
    _listings = api.marketplace();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.marketplace(
          search: _search.text.trim(),
          cropTypeId: _cropTypeId,
          sort: _sort,
        );
    setState(() => _listings = future);
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        const AppHeader(
          title: 'Marketplace',
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              CartIconButton(),
              NotificationBellButton(),
            ],
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            AniHowSpace.screen,
            AniHowSpace.screen,
            AniHowSpace.cardGap,
          ),
          child: TextField(
            controller: _search,
            decoration: InputDecoration(
              hintText: 'Search produce',
              prefixIcon: const Icon(Icons.search),
              suffixIcon: IconButton(onPressed: _reload, icon: const Icon(Icons.arrow_forward)),
            ),
            onSubmitted: (_) => _reload(),
          ),
        ),
        SizedBox(
          height: 48,
          child: FutureBuilder<List<CategoryItem>>(
            future: _cropTypes,
            builder: (context, snapshot) {
              final cropTypes = snapshot.data ?? const <CategoryItem>[];
              return ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: AniHowSpace.screen),
                children: [
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      label: const Text('All'),
                      selected: _cropTypeId == null,
                      onSelected: (_) {
                        setState(() => _cropTypeId = null);
                        _reload();
                      },
                    ),
                  ),
                  ...cropTypes.map(
                    (cropType) => Padding(
                      padding: const EdgeInsets.only(right: 8),
                      child: FilterChip(
                        avatar: CircleAvatar(
                          backgroundColor: CategoryColor.of(cropType),
                          radius: 8,
                        ),
                        label: Text(cropType.name),
                        selected: _cropTypeId == cropType.id,
                        onSelected: (_) {
                          setState(() => _cropTypeId = cropType.id);
                          _reload();
                        },
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AniHowSpace.screen,
            0,
            AniHowSpace.screen,
            AniHowSpace.cardGap,
          ),
          child: DropdownButtonFormField<String>(
            initialValue: _sort,
            decoration: const InputDecoration(
              isDense: true,
              contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            ),
            items: const [
              DropdownMenuItem(value: 'freshest', child: Text('Freshest')),
              DropdownMenuItem(value: 'price_asc', child: Text('Price: low to high')),
              DropdownMenuItem(value: 'price_desc', child: Text('Price: high to low')),
              DropdownMenuItem(value: 'availability', child: Text('In stock first')),
            ],
            onChanged: (value) {
              if (value == null) {
                return;
              }
              setState(() => _sort = value);
              _reload();
            },
          ),
        ),
        Expanded(
          child: FutureBuilder<List<ListingItem>>(
            future: _listings,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Center(child: CircularProgressIndicator());
              }
              if (snapshot.hasError) {
                return Center(child: Text('${snapshot.error}'));
              }
              final items = snapshot.data ?? const [];
              if (items.isEmpty) {
                return const Center(child: Text('No listings found.'));
              }
              return RefreshIndicator(
                onRefresh: _reload,
                child: ListView.separated(
                  padding: AniHowSpace.screenPadding,
                  itemCount: items.length,
                  separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
                  itemBuilder: (context, index) {
                    final listing = items[index];
                    return ProduceCard(
                      listing: listing,
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => ListingDetailScreen(listingId: listing.id),
                          ),
                        );
                      },
                      onSellerTap: listing.sellerId == null
                          ? null
                          : () => openBuyerShop(context, listing.sellerId!),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }
}
