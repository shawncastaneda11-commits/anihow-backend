import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/shop_profile_parts.dart';
import '../buyer/favorites_screen.dart';
import '../buyer/order_history_screen.dart';
import '../farmer/listing_form_screen.dart';
import 'settings_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    if (user == null) {
      return const SizedBox.shrink();
    }

    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Center(child: AniHowAvatar(name: user.name, radius: 36)),
        const SizedBox(height: AniHowSpace.cardGap),
        Center(child: Text(user.name, style: Theme.of(context).textTheme.titleMedium)),
        Center(child: Text(user.email, style: Theme.of(context).textTheme.bodyMedium)),
        const SizedBox(height: AniHowSpace.labelGap),
        Center(
          child: Text(
            user.roleLabel,
            style: const TextStyle(color: AniHowColors.brand, fontWeight: FontWeight.w700, fontSize: AniHowSpace.meta),
          ),
        ),
        const SizedBox(height: AniHowSpace.section),
        if (user.isBuyer) ...[
          ListTile(
            leading: const Icon(Icons.receipt_long_outlined),
            title: const Text('Order history'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const OrderHistoryScreen()),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.favorite_outline),
            title: const Text('Favorites'),
            trailing: const Icon(Icons.chevron_right),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute(
                builder: (_) => Scaffold(
                  appBar: AppBar(title: const Text('Favorites')),
                  body: const FavoritesScreen(),
                ),
              ),
            ),
          ),
        ],
        ListTile(
          leading: const Icon(Icons.settings_outlined),
          title: const Text('Settings'),
          trailing: const Icon(Icons.chevron_right),
          onTap: () => Navigator.of(context).push(
            MaterialPageRoute(builder: (_) => const SettingsScreen()),
          ),
        ),
      ],
    );
  }
}

class FarmerProfileScreen extends StatefulWidget {
  const FarmerProfileScreen({super.key});

  @override
  State<FarmerProfileScreen> createState() => _FarmerProfileScreenState();
}

class _FarmerProfileScreenState extends State<FarmerProfileScreen> {
  late Future<_FarmerShopView> _view;

  @override
  void initState() {
    super.initState();
    _view = _load();
  }

  Future<_FarmerShopView> _load() async {
    final api = context.read<AuthController>().api;
    final shopFuture = api.farmerShop();
    final listingsFuture = api.farmerListingsPaged();

    final shop = await shopFuture;

    List<ListingItem> activeListings = const [];
    int? listingsCount;
    String? listingsError;
    try {
      final pages = await listingsFuture;
      activeListings = pages.items.where((listing) => listing.isActive).toList();
      if (pages.complete) {
        listingsCount = activeListings.length;
      }
    } on ApiException catch (error) {
      listingsError = error.message;
    }

    return _FarmerShopView(
      shop: shop,
      activeListings: activeListings,
      listingsCount: listingsCount,
      listingsError: listingsError,
    );
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _view = next);
    await next;
  }

  Future<void> _call(String number) async {
    final digits = number.replaceAll(RegExp(r'[^\d+]'), '');
    if (digits.isEmpty) {
      return;
    }
    final opened = await launchUrl(Uri(scheme: 'tel', path: digits));
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the phone app.')),
      );
    }
  }

  Future<void> _editShop(ShopProfile shop) async {
    final saved = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ShopEditScreen(shop: shop)),
    );
    if (saved == true && mounted) {
      await _reload();
    }
  }

  Future<void> _openListing(ListingItem listing) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => ListingFormScreen(listing: listing)),
    );
    if (changed == true && mounted) {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Shop profile'),
        actions: [
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const SettingsScreen()),
            ),
          ),
        ],
      ),
      body: FutureBuilder<_FarmerShopView>(
        future: _view,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final view = snapshot.data;
          if (view == null) {
            return const Center(child: Text('Shop not found.'));
          }
          final shop = view.shop;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                ShopIdentityHeader(shop: shop),
                if (view.listingsCount != null || shop.hasRating) ...[
                  const SizedBox(height: AniHowSpace.section),
                  ShopStatRow(
                    listings: view.listingsCount,
                    rating: shop.hasRating ? shop.averageRating : null,
                  ),
                ],
                const SizedBox(height: AniHowSpace.section),
                ShopAboutCard(shop: shop, onCall: _call),
                const SizedBox(height: AniHowSpace.section),
                OutlinedButton(
                  onPressed: () => _editShop(shop),
                  child: const Text('Edit shop profile'),
                ),
                const SizedBox(height: AniHowSpace.section),
                const Text(
                  'Active listings',
                  style: TextStyle(fontSize: AniHowSpace.name, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                if (view.listingsError != null)
                  Text(view.listingsError!, style: const TextStyle(fontSize: AniHowSpace.body))
                else if (view.activeListings.isEmpty)
                  const ShopListingsEmpty()
                else
                  ...view.activeListings.map(
                    (listing) => Padding(
                      padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                      child: ProduceCard(
                        listing: listing,
                        showSeller: false,
                        showStock: true,
                        onTap: () => _openListing(listing),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _FarmerShopView {
  const _FarmerShopView({
    required this.shop,
    required this.activeListings,
    this.listingsCount,
    this.listingsError,
  });

  final ShopProfile shop;
  final List<ListingItem> activeListings;
  final int? listingsCount;
  final String? listingsError;
}

class ShopEditScreen extends StatefulWidget {
  const ShopEditScreen({super.key, required this.shop});

  final ShopProfile shop;

  @override
  State<ShopEditScreen> createState() => _ShopEditScreenState();
}

class _ShopEditScreenState extends State<ShopEditScreen> {
  late final TextEditingController _name;
  late final TextEditingController _bio;
  late final TextEditingController _location;
  late final TextEditingController _contact;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.shop.shopName);
    _bio = TextEditingController(text: widget.shop.bio ?? '');
    _location = TextEditingController(text: widget.shop.location ?? '');
    _contact = TextEditingController(text: widget.shop.contact ?? '');
  }

  @override
  void dispose() {
    _name.dispose();
    _bio.dispose();
    _location.dispose();
    _contact.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _busy = true);
    try {
      await context.read<AuthController>().api.updateFarmerShop({
        'shop_name': _name.text.trim(),
        'bio': _bio.text.trim(),
        'location': _location.text.trim(),
        'contact': _contact.text.trim(),
      });
      if (mounted) {
        Navigator.of(context).pop(true);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit shop')),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowField(label: 'Shop name', child: TextField(controller: _name)),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(label: 'Bio', child: TextField(controller: _bio, maxLines: 4)),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(label: 'Location', child: TextField(controller: _location)),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(label: 'Contact', child: TextField(controller: _contact)),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: 'Save shop profile', busy: _busy, onPressed: _save),
        ],
      ),
    );
  }
}
