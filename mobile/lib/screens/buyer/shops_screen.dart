import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/unverified_email_banner.dart';
import 'shop_profile_screen.dart';

class ShopsScreen extends StatefulWidget {
  const ShopsScreen({super.key});

  @override
  State<ShopsScreen> createState() => _ShopsScreenState();
}

class _FarmRow {
  const _FarmRow({
    required this.id,
    required this.name,
    required this.place,
    required this.sellers,
  });

  final int id;
  final String name;
  final String place;
  final List<ShopProfile> sellers;
}

class _ShopsScreenState extends State<ShopsScreen> {
  final _search = TextEditingController();
  late Future<List<ShopProfile>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerShops();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.buyerShops();
    setState(() {
      _future = future;
    });
    await future;
  }

  List<_FarmRow> _farms(List<ShopProfile> shops) {
    final grouped = <int, List<ShopProfile>>{};
    for (final shop in shops) {
      final farmId = shop.farmId;
      if (farmId == null || !shop.farmIsActive) {
        continue;
      }
      grouped.putIfAbsent(farmId, () => []).add(shop);
    }

    final rows = grouped.entries.map((entry) {
      final sellers = entry.value;
      final name = sellers
          .map((shop) => shop.farmName?.trim() ?? '')
          .firstWhere((value) => value.isNotEmpty, orElse: () => 'Farm');
      final place = sellers
          .map(_place)
          .firstWhere((value) => value.isNotEmpty, orElse: () => '');
      return _FarmRow(
        id: entry.key,
        name: name,
        place: place,
        sellers: sellers,
      );
    }).toList();

    final query = _search.text.trim().toLowerCase();
    final visible = query.isEmpty
        ? rows
        : rows.where((farm) {
            return '${farm.name} ${farm.place}'.toLowerCase().contains(query);
          }).toList();

    visible.sort(
      (a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()),
    );
    return visible;
  }

  String _place(ShopProfile shop) {
    final parts = [shop.farmBarangay, shop.farmMunicipality]
        .map((part) => part?.trim() ?? '')
        .where((part) => part.isNotEmpty)
        .toList();
    if (parts.isNotEmpty) {
      return parts.join(', ');
    }
    return shop.location?.trim() ?? '';
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(s.shops),
        actions: const [NotificationBellButton()],
      ),
      body: Column(
        children: [
          const UnverifiedEmailBanner(),
          Expanded(child: _farmList(s)),
        ],
      ),
    );
  }

  Widget _farmList(AppStrings s) {
    return AsyncView<List<ShopProfile>>(
      future: _future,
      onRetry: _reload,
      emptyMessage: s.noFarmsYet,
      builder: (context, shops) {
        final farms = _farms(shops);

        return Column(
          children: [
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
                  hintText: s.searchFarms,
                  prefixIcon: const Icon(Icons.search),
                ),
                onChanged: (_) => setState(() {}),
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                onRefresh: _reload,
                child: farms.isEmpty
                    ? ListView(
                        children: [
                          const SizedBox(height: 80),
                          Center(
                            child: Text(
                              _search.text.trim().isEmpty
                                  ? s.noFarmsYet
                                  : s.noFarmsMatch,
                            ),
                          ),
                        ],
                      )
                    : ListView.separated(
                        padding: const EdgeInsets.fromLTRB(
                          AniHowSpace.screen,
                          0,
                          AniHowSpace.screen,
                          AniHowSpace.screen,
                        ),
                        itemCount: farms.length,
                        separatorBuilder: (_, _) =>
                            const SizedBox(height: AniHowSpace.cardGap),
                        itemBuilder: (context, index) =>
                            _FarmCard(farm: farms[index]),
                      ),
              ),
            ),
          ],
        );
      },
    );
  }
}

class _FarmCard extends StatelessWidget {
  const _FarmCard({required this.farm});

  final _FarmRow farm;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final s = AppStrings.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () {
          Navigator.of(context).push(
            MaterialPageRoute<void>(
              builder: (_) =>
                  FarmSellersScreen(farmName: farm.name, sellers: farm.sellers),
            ),
          );
        },
        child: Padding(
          padding: AniHowSpace.cardPadding,
          child: Row(
            children: [
              AniHowAvatar(name: farm.name, radius: 26),
              const SizedBox(width: AniHowSpace.cardGap),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      farm.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: AniHowSpace.title,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      s.sellersCount(farm.sellers.length),
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: AniHowColors.sage,
                      ),
                    ),
                    if (farm.place.isNotEmpty) ...[
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
                              farm.place,
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
                  ],
                ),
              ),
              Icon(Icons.chevron_right, color: muted),
            ],
          ),
        ),
      ),
    );
  }
}

class FarmSellersScreen extends StatefulWidget {
  const FarmSellersScreen({
    super.key,
    required this.farmName,
    required this.sellers,
  });

  final String farmName;
  final List<ShopProfile> sellers;

  @override
  State<FarmSellersScreen> createState() => _FarmSellersScreenState();
}

class _FarmSellersScreenState extends State<FarmSellersScreen> {
  final _search = TextEditingController();
  final Set<int> _saved = {};
  final Set<int> _busy = {};

  @override
  void initState() {
    super.initState();
    _saved.addAll(
      widget.sellers.where((shop) => shop.isFavorited).map((shop) => shop.id),
    );
    _loadSaved();
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
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

  List<ShopProfile> get _visible {
    final query = _search.text.trim().toLowerCase();
    final sellers = [...widget.sellers]
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
    final sellers = _visible;

    return Scaffold(
      appBar: AppBar(title: Text(widget.farmName)),
      body: Column(
        children: [
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
                hintText: s.searchShops,
                prefixIcon: const Icon(Icons.search),
              ),
              onChanged: (_) => setState(() {}),
            ),
          ),
          Expanded(
            child: sellers.isEmpty
                ? Center(child: Text(s.noShopsMatch))
                : ListView.separated(
                    padding: const EdgeInsets.fromLTRB(
                      AniHowSpace.screen,
                      0,
                      AniHowSpace.screen,
                      AniHowSpace.screen,
                    ),
                    itemCount: sellers.length,
                    separatorBuilder: (_, _) =>
                        const SizedBox(height: AniHowSpace.cardGap),
                    itemBuilder: (context, index) {
                      final shop = sellers[index];
                      return _SellerCard(
                        shop: shop,
                        saved: _saved.contains(shop.id),
                        onToggle: () => _toggle(shop),
                      );
                    },
                  ),
          ),
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
                      RatingLabel(
                        rating: shop.averageRating!,
                        count: shop.reviewsCount,
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
