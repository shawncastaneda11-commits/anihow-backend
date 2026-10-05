import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../farm/farm_profile_screen.dart';
import 'shop_profile_screen.dart';

void openBuyerFarmPage(BuildContext context, int farmId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => FarmPageScreen(farmId: farmId)),
  );
}

class FarmPageScreen extends StatefulWidget {
  const FarmPageScreen({super.key, required this.farmId});

  final int farmId;

  @override
  State<FarmPageScreen> createState() => _FarmPageScreenState();
}

class _FarmPageScreenState extends State<FarmPageScreen> {
  final _search = TextEditingController();
  final Set<int> _saved = {};
  final Set<int> _busy = {};
  late Future<FarmProfile> _farm;
  late Future<List<ShopProfile>> _shops;

  @override
  void initState() {
    super.initState();
    _farm = _loadFarm();
    _shops = _loadShops();
    _loadSaved();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<FarmProfile> _loadFarm() {
    return context.read<AuthController>().api.farm(widget.farmId);
  }

  Future<List<ShopProfile>> _loadShops() async {
    final shops = await context.read<AuthController>().api.buyerShops();
    return shops.where((shop) => shop.farmId == widget.farmId).toList();
  }

  Future<void> _reloadFarm() async {
    final farm = _loadFarm();
    setState(() => _farm = farm);
    await farm;
  }

  Future<void> _reloadShops() async {
    final shops = _loadShops();
    setState(() => _shops = shops);
    await shops;
  }

  Future<void> _reload() async {
    await Future.wait([_reloadFarm(), _reloadShops(), _loadSaved()]);
  }

  Future<void> _loadSaved() async {
    try {
      final saved = await context.read<AuthController>().api.shopFavorites();
      if (!mounted) {
        return;
      }
      setState(() {
        _saved
          ..clear()
          ..addAll(saved.map((favorite) => favorite.sellerId));
      });
    } on ApiException {
      // Hearts from the shop list still show. A tap tries again.
    }
  }

  Future<void> _toggle(ShopProfile shop) async {
    if (_busy.contains(shop.id)) {
      return;
    }
    final saved = _saved.contains(shop.id);
    setState(() {
      _busy.add(shop.id);
      if (saved) {
        _saved.remove(shop.id);
      } else {
        _saved.add(shop.id);
      }
    });
    try {
      final api = context.read<AuthController>().api;
      if (saved) {
        await api.removeShopFavorite(shop.id);
      } else {
        await api.addShopFavorite(shop.id);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(AppStrings.read(context).storeSavedToFavorites),
            ),
          );
        }
      }
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        if (saved) {
          _saved.add(shop.id);
        } else {
          _saved.remove(shop.id);
        }
      });
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _busy.remove(shop.id));
      }
    }
  }

  Future<void> _call(String number) async {
    final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      return;
    }
    final opened = await launchUrl(Uri(scheme: 'tel', path: digits));
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AppStrings.read(context).couldNotOpenPhone)),
      );
    }
  }

  List<ShopProfile> _visible(List<ShopProfile> shops) {
    final query = _search.text.trim().toLowerCase();
    final sellers = [...shops]
      ..sort(
        (a, b) => a.shopName.toLowerCase().compareTo(b.shopName.toLowerCase()),
      );
    if (query.isEmpty) {
      return sellers;
    }
    return sellers.where((shop) {
      return '${shop.shopName} ${shop.name} ${shop.location ?? ''}'
          .toLowerCase()
          .contains(query);
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: FutureBuilder<FarmProfile>(
          future: _farm,
          builder: (context, snapshot) {
            final name = snapshot.data?.name.trim();
            return Text(name == null || name.isEmpty ? s.farm : name);
          },
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: AsyncView<FarmProfile>(
                future: _farm,
                onRetry: _reloadFarm,
                emptyMessage: s.farmNotFound,
                isEmpty: (_) => false,
                builder: (context, farm) => FarmProfileContent(
                  farm: farm,
                  onCall: _call,
                  includeStorefronts: false,
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: AsyncView<List<ShopProfile>>(
                future: _shops,
                onRetry: _reloadShops,
                isEmpty: (_) => false,
                builder: (context, shops) => _shopSection(s, shops),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _shopSection(AppStrings s, List<ShopProfile> shops) {
    final sellers = _visible(shops);
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        AniHowSpace.screen,
        0,
        AniHowSpace.screen,
        AniHowSpace.screen,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            s.shopsAtThisFarm(shops.length),
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          TextField(
            controller: _search,
            decoration: InputDecoration(
              hintText: s.searchShops,
              prefixIcon: const Icon(Icons.search),
            ),
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: AniHowSpace.cardGap),
          if (shops.isEmpty)
            Text(s.noShopsAtFarm)
          else if (sellers.isEmpty)
            Text(s.noShopsMatch)
          else
            for (var index = 0; index < sellers.length; index++) ...[
              if (index > 0) const SizedBox(height: AniHowSpace.cardGap),
              _SellerCard(
                shop: sellers[index],
                saved: _saved.contains(sellers[index].id),
                onToggle: () => _toggle(sellers[index]),
              ),
            ],
        ],
      ),
    );
  }
}

class _SellerCard extends StatelessWidget {
  const _SellerCard({
    required this.shop,
    required this.saved,
    required this.onToggle,
  });

  final ShopProfile shop;
  final bool saved;
  final VoidCallback onToggle;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final location = shop.location?.trim();
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final person = shop.name.trim();
    final showPerson =
        person.isNotEmpty &&
        person.toLowerCase() != shop.shopName.trim().toLowerCase();

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => openBuyerShop(context, shop.id),
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            children: [
              AniHowAvatar(
                name: shop.shopName,
                imageUrl: shop.avatarUrl,
                radius: 26,
              ),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      shop.shopName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: AniHowSpace.title,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (showPerson) ...[
                      const SizedBox(height: 2),
                      Text(
                        person,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: AniHowColors.sage,
                        ),
                      ),
                    ],
                    if (shop.distanceKm != null) ...[
                      const SizedBox(height: 4),
                      Text(
                        AppStrings.of(context).kilometersAway(shop.distanceKm!),
                      ),
                    ],
                    if (location != null && location.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 16,
                            color: muted,
                          ),
                          const SizedBox(width: 4),
                          Expanded(
                            child: Text(
                              location,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: theme.textTheme.bodyMedium?.copyWith(
                                color: muted,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ],
                    if (shop.hasRating) ...[
                      const SizedBox(height: 6),
                      SizedBox(
                        height: 20,
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerLeft,
                          child: RatingLabel(
                            rating: shop.averageRating!,
                            count: shop.reviewsCount,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              IconButton(
                tooltip: saved
                    ? AppStrings.of(context).removeStoreFromFavorites
                    : AppStrings.of(context).addStoreToFavorites,
                onPressed: onToggle,
                style: IconButton.styleFrom(
                  minimumSize: const Size(48, 48),
                ),
                icon: Icon(saved ? Icons.favorite : Icons.favorite_outline),
                color: saved ? theme.colorScheme.error : muted,
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}
