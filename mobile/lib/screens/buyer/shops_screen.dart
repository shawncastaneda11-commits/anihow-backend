import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/buyer_location.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import '../../widgets/notification_bell.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/unverified_email_banner.dart';
import 'farm_page_screen.dart';

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
    this.distanceKm,
  });

  final int id;
  final String name;
  final String place;
  final List<ShopProfile> sellers;
  final double? distanceKm;
}

class _ShopsScreenState extends State<ShopsScreen> {
  final _search = TextEditingController();
  String _sort = 'name';
  double? _nearLat;
  double? _nearLng;
  bool _locationUnavailable = false;
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
    final future = context.read<AuthController>().api.buyerShops(
      sort: _sort == 'nearest' ? 'nearest' : null,
      nearLat: _sort == 'nearest' ? _nearLat : null,
      nearLng: _sort == 'nearest' ? _nearLng : null,
    );
    setState(() {
      _future = future;
    });
    await future;
  }

  Future<void> _applySort(String value) async {
    double? latitude;
    double? longitude;
    var unavailable = false;
    if (value == 'nearest') {
      final point = await BuyerLocation.read();
      if (!mounted) {
        return;
      }
      if (point == null) {
        unavailable = true;
      } else {
        latitude = point.latitude;
        longitude = point.longitude;
      }
    }
    setState(() {
      _sort = value;
      _nearLat = latitude;
      _nearLng = longitude;
      _locationUnavailable = unavailable;
    });
    await _reload();
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
        distanceKm: sellers
            .map((shop) => shop.distanceKm)
            .whereType<double>()
            .firstOrNull,
      );
    }).toList();

    final query = _search.text.trim().toLowerCase();
    final visible = query.isEmpty
        ? rows
        : rows.where((farm) {
            return '${farm.name} ${farm.place}'.toLowerCase().contains(query);
          }).toList();

    if (_sort != 'nearest') {
      visible.sort(
        (a, b) => a.name.toLowerCase().compareTo(b.name.toLowerCase()),
      );
    }
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
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AniHowSpace.screen,
                0,
                AniHowSpace.screen,
                AniHowSpace.cardGap,
              ),
              child: Align(
                alignment: Alignment.centerLeft,
                child: SizedBox(
                  height: 48,
                  child: FilterChip(
                    label: Text(s.nearest),
                    selected: _sort == 'nearest',
                    onSelected: (selected) {
                      _applySort(selected ? 'nearest' : 'name');
                    },
                  ),
                ),
              ),
            ),
            if (_locationUnavailable)
              Padding(
                padding: const EdgeInsets.fromLTRB(
                  AniHowSpace.screen,
                  0,
                  AniHowSpace.screen,
                  AniHowSpace.cardGap,
                ),
                child: Text(
                  s.locationUnavailable,
                  key: const Key('location-unavailable'),
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
        onTap: () => openBuyerFarmPage(context, farm.id),
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
                    if (farm.distanceKm != null) ...[
                      const SizedBox(height: 4),
                      Text(s.kilometersAway(farm.distanceKm!)),
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
