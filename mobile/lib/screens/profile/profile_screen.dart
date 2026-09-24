import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/order_look.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/shop_profile_parts.dart';
import '../../widgets/status_pill.dart';
import '../buyer/favorites_screen.dart';
import '../buyer/order_history_screen.dart';
import '../farmer/listing_form_screen.dart';
import '../farmer/walk_in_sale_screen.dart';
import '../faq/faq_bot_screen.dart';
import '../farm/farm_profile_screen.dart';
import 'settings_screen.dart';
import 'verify_email_screen.dart';

class ProfileScreen extends StatelessWidget {
  const ProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;
    final s = AppStrings.of(context);
    if (user == null) {
      return const SizedBox.shrink();
    }
    final theme = Theme.of(context);
    final role = user.isFarmerSeller
        ? s.roleFarmer
        : user.isBuyer
            ? s.roleBuyer
            : user.roleLabel;

    return ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
            child: Row(
              children: [
                AniHowAvatar(name: user.name, radius: 28),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(user.name, style: theme.textTheme.titleMedium),
                      const SizedBox(height: 2),
                      Text(
                        user.email,
                        style: theme.textTheme.bodyMedium?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 8,
                        runSpacing: 6,
                        children: [
                          StatusPill(label: role, color: AniHowColors.brand),
                          user.isVerified
                              ? StatusPill(label: s.verified, color: AniHowColors.ready)
                              : StatusPill(label: s.unverified, color: AniHowColors.pending),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
        if (!user.isVerified) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          AniHowHintCard(
            icon: Icons.mail_outline,
            title: s.verifyBanner,
            tone: AniHowHintTone.cash,
          ),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute<void>(builder: (_) => const VerifyEmailScreen()),
              ),
              child: Text(s.verifyNow),
            ),
          ),
        ],
        const SizedBox(height: AniHowSpace.cardGap),
        Card(
          child: Column(
            children: [
              if (user.isBuyer) ...[
                _ProfileLink(
                  icon: Icons.receipt_long_outlined,
                  label: s.orderHistory,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(builder: (_) => const OrderHistoryScreen()),
                  ),
                ),
                const Divider(height: 1),
                _ProfileLink(
                  icon: Icons.favorite_outline,
                  label: s.favorites,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => Scaffold(
                        appBar: AppBar(title: Text(s.favorites)),
                        body: const FavoritesScreen(),
                      ),
                    ),
                  ),
                ),
                const Divider(height: 1),
              ],
              _ProfileLink(
                icon: Icons.help_outline,
                label: s.faq,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => const FaqBotScreen()),
                ),
              ),
              const Divider(height: 1),
              _ProfileLink(
                icon: Icons.settings_outlined,
                label: s.settings,
                onTap: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(builder: (_) => const SettingsScreen()),
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ProfileLink extends StatelessWidget {
  const _ProfileLink({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    return ListTile(
      leading: DecoratedBox(
        decoration: BoxDecoration(
          color: dark ? const Color(0xFF24382D) : const Color(0xFFD7EADF),
          shape: BoxShape.circle,
        ),
        child: Padding(
          padding: const EdgeInsets.all(8),
          child: Icon(icon, size: 20, color: AniHowColors.brand),
        ),
      ),
      title: Text(label, style: theme.textTheme.titleMedium),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
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
        SnackBar(content: Text(AppStrings.read(context).couldNotOpenPhone)),
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

  Future<void> _openWalkIn() async {
    await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => const WalkInSaleScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(s.shopProfile),
        actions: [
          IconButton(
            tooltip: s.faq,
            icon: const Icon(Icons.help_outline),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute(builder: (_) => const FaqBotScreen()),
            ),
          ),
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
            return Center(child: Text(s.shopNotFound));
          }
          final shop = view.shop;
          final user = context.watch<AuthController>().user;
          final farmName = user?.farmName?.trim();
          final farmId = user?.farmId ?? shop.farmId;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              padding: AniHowSpace.screenPadding,
              children: [
                Card(
                  child: Padding(
                    padding: AniHowSpace.cardPadding,
                    child: ShopIdentityHeader(shop: shop),
                  ),
                ),
                if (farmId != null && farmName != null && farmName.isNotEmpty && shop.farmIsActive)
                  FarmLinkChip(farmId: farmId, label: s.farmLine(farmName))
                else if (farmName != null && farmName.isNotEmpty)
                  OrderMetaRow(icon: Icons.agriculture_outlined, text: s.farmLine(farmName)),
                if (view.listingsCount != null || shop.hasRating) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  ShopStatRow(
                    listings: view.listingsCount,
                    rating: shop.hasRating ? shop.averageRating : null,
                  ),
                ],
                const SizedBox(height: AniHowSpace.cardGap),
                ShopAboutCard(shop: shop, onCall: _call),
                const SizedBox(height: AniHowSpace.cardGap),
                OutlinedButton.icon(
                  onPressed: () => _editShop(shop),
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(s.editShopProfile),
                ),
                if (context.watch<AuthController>().user?.canRecordWalkInSales ?? false) ...[
                  const SizedBox(height: AniHowSpace.cardGap),
                  OutlinedButton.icon(
                    onPressed: _openWalkIn,
                    icon: const Icon(Icons.point_of_sale_outlined),
                    label: Text(s.recordWalkIn),
                  ),
                ],
                const SizedBox(height: AniHowSpace.section),
                Text(s.activeListings, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: AniHowSpace.cardGap),
                if (view.listingsError != null)
                  Text(view.listingsError!)
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
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppBar(title: Text(s.editShop)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowFormCard(
            title: s.shopProfile,
            child: Column(
              children: [
                AniHowField(label: s.shopName, child: TextField(controller: _name)),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(label: s.bio, child: TextField(controller: _bio, maxLines: 4)),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(label: s.location, child: TextField(controller: _location)),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(label: s.contact, child: TextField(controller: _contact)),
              ],
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: s.saveShopProfile, busy: _busy, onPressed: _save),
        ],
      ),
    );
  }
}
