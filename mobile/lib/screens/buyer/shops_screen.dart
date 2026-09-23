import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/async_view.dart';
import 'shop_profile_screen.dart';

class ShopsScreen extends StatefulWidget {
  const ShopsScreen({super.key});

  @override
  State<ShopsScreen> createState() => _ShopsScreenState();
}

class _ShopsScreenState extends State<ShopsScreen> {
  late Future<List<ShopProfile>> _future;

  @override
  void initState() {
    super.initState();
    _future = context.read<AuthController>().api.buyerShops();
  }

  Future<void> _reload() async {
    final future = context.read<AuthController>().api.buyerShops();
    setState(() {
      _future = future;
    });
    await future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Shops')),
      body: AsyncView<List<ShopProfile>>(
        future: _future,
        onRetry: _reload,
        emptyMessage: 'No shops yet.',
        builder: (context, shops) {
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView.separated(
              padding: AniHowSpace.screenPadding,
              itemCount: shops.length,
              separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
              itemBuilder: (context, index) {
                final shop = shops[index];
                final location = shop.location?.trim();
                final rating = shop.averageRating?.trim();
                return Card(
                  child: ListTile(
                    contentPadding: AniHowSpace.cardPadding,
                    title: Text(shop.shopName, style: Theme.of(context).textTheme.titleMedium),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (location != null && location.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(location, style: Theme.of(context).textTheme.bodyMedium),
                        ],
                        if (rating != null && rating.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            '★ $rating (${shop.reviewsCount})',
                            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: AniHowColors.sage,
                                ),
                          ),
                        ],
                      ],
                    ),
                    onTap: () => openBuyerShop(context, shop.id),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}
