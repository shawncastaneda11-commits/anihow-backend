import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../state/preferences_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/app_header.dart';
import '../../widgets/async_view.dart';
import '../../widgets/cart_icon_button.dart';
import '../../widgets/category_color.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/unverified_email_banner.dart';
import 'listing_detail_screen.dart';
import 'shop_profile_screen.dart';
import 'shops_screen.dart';

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
    _cropTypes = context.read<AuthController>().api.cropTypes();
    _listings = Completer<List<ListingItem>>().future;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      _reload();
    });
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
    setState(() {
      _listings = future;
    });
    await future;
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Column(
      children: [
        AppHeader(
          title: s.marketplace,
          trailing: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              IconButton(
                tooltip: s.shops,
                onPressed: () {
                  Navigator.of(context).push(
                    MaterialPageRoute(builder: (_) => const ShopsScreen()),
                  );
                },
                padding: EdgeInsets.zero,
                constraints: const BoxConstraints.tightFor(width: 48, height: 48),
                icon: Icon(
                  Icons.storefront,
                  size: 24,
                  color: Theme.of(context).colorScheme.onPrimary,
                ),
              ),
              const CartIconButton(),
              const NotificationBellButton(),
            ],
          ),
        ),
        const UnverifiedEmailBanner(),
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
              hintText: s.searchProduce,
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
                      label: Text(s.all),
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
                        label: Text(
                          cropType.labelFor(context.watch<PreferencesController>().language),
                        ),
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
            items: [
              DropdownMenuItem(value: 'freshest', child: Text(s.freshest)),
              DropdownMenuItem(value: 'price_asc', child: Text(s.priceLowHigh)),
              DropdownMenuItem(value: 'price_desc', child: Text(s.priceHighLow)),
              DropdownMenuItem(value: 'availability', child: Text(s.inStockFirst)),
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
          child: AsyncView<List<ListingItem>>(
            future: _listings,
            onRetry: _reload,
            emptyMessage: s.noListingsFound,
            builder: (context, items) {
              return RefreshIndicator(
                onRefresh: _reload,
                child: GridView.builder(
                  padding: AniHowSpace.screenPadding,
                  itemCount: items.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisExtent: 292,
                    crossAxisSpacing: AniHowSpace.cardGap,
                    mainAxisSpacing: AniHowSpace.cardGap,
                  ),
                  itemBuilder: (context, index) {
                    final listing = items[index];
                    return ProduceCard(
                      listing: listing,
                      style: ProduceCardStyle.poster,
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
