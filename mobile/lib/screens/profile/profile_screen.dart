import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/form_label.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/profile_avatar_button.dart';
import '../buyer/favorites_screen.dart';
import '../buyer/order_history_screen.dart';
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
  late Future<ShopProfile> shopFuture;

  @override
  void initState() {
    super.initState();
    shopFuture = context.read<AuthController>().api.farmerShop();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthController>().user;

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
      body: FutureBuilder<ShopProfile>(
        future: shopFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text('${snapshot.error}'));
          }
          final shop = snapshot.data!;
          return ListView(
            padding: AniHowSpace.screenPadding,
            children: [
              Center(child: AniHowAvatar(name: shop.shopName, radius: 36)),
              const SizedBox(height: AniHowSpace.cardGap),
              Center(child: Text(shop.shopName, style: Theme.of(context).textTheme.titleMedium)),
              Center(child: Text(user?.email ?? '', style: Theme.of(context).textTheme.bodyMedium)),
              if (shop.averageRating != null)
                Center(child: Text('★ ${shop.averageRating} · ${shop.reviewsCount} reviews')),
              const SizedBox(height: AniHowSpace.section),
              _InfoCard(label: 'Bio', value: shop.bio ?? 'No bio yet'),
              _InfoCard(label: 'Location', value: shop.location ?? 'No location yet'),
              _InfoCard(label: 'Contact', value: shop.contact ?? 'No contact yet'),
              const SizedBox(height: AniHowSpace.section),
              PrimaryButton(
                label: 'Edit shop profile',
                onPressed: () async {
                  final saved = await Navigator.of(context).push<bool>(
                    MaterialPageRoute(builder: (_) => ShopEditScreen(shop: shop)),
                  );
                  if (saved == true && mounted) {
                    setState(() {
                      shopFuture = context.read<AuthController>().api.farmerShop();
                    });
                  }
                },
              ),
            ],
          );
        },
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
      child: ListTile(
        title: Text(label, style: Theme.of(context).textTheme.labelSmall),
        subtitle: Text(value, style: Theme.of(context).textTheme.bodyLarge),
      ),
    );
  }
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
