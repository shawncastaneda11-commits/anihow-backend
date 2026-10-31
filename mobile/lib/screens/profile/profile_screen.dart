import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../theme/readable_accent.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/id_verification_card.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../../widgets/shop_profile_parts.dart';
import '../../widgets/status_pill.dart';
import '../farmer/listing_form_screen.dart';
import '../farmer/shop_reviews_screen.dart';
import '../farmer/walk_in_sale_screen.dart';
import '../faq/faq_bot_screen.dart';
import '../auth/seller_info_screen.dart';
import '../farm/farm_profile_screen.dart';
import 'online_payment_section.dart';
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

    return Scaffold(
      appBar: AppBar(title: Text(s.profile)),
      body: ListView(
      padding: AniHowSpace.screenPadding,
      children: [
        Card(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 16, 14, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    AniHowAvatar(
                      name: user.name,
                      imageUrl: user.avatarUrl,
                      radius: 28,
                    ),
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
                              color: theme.colorScheme.onSurface.withValues(
                                alpha: 0.68,
                              ),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 6,
                            children: [
                              StatusPill(
                                label: role,
                                color: AniHowColors.brand,
                              ),
                              user.isVerified
                                  ? StatusPill(
                                      label: s.verified,
                                      color: AniHowColors.ready,
                                    )
                                  : StatusPill(
                                      label: s.unverified,
                                      color: AniHowColors.pending,
                                    ),
                              if (user.isIdVerified) const IdVerifiedBadge(),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                _PhotoActions(user: user),
              ],
            ),
          ),
        ),
        if (user.idVerification case final idCheck?) ...[
          const SizedBox(height: AniHowSpace.cardGap),
          IdVerificationCard(info: idCheck),
        ],
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
                MaterialPageRoute<void>(
                  builder: (_) => const VerifyEmailScreen(),
                ),
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
                  icon: Icons.storefront_outlined,
                  label: s.wantToSellOnAnihow,
                  onTap: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const SellerInfoScreen(),
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
                  MaterialPageRoute<void>(
                    builder: (_) => const SettingsScreen(),
                  ),
                ),
              ),
            ],
          ),
        ),
      ],
      ),
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
  const FarmerProfileScreen({super.key, this.preview});

  final ShopProfile? preview;

  @override
  State<FarmerProfileScreen> createState() => _FarmerProfileScreenState();
}

class _FarmerProfileScreenState extends State<FarmerProfileScreen> {
  _FarmerShopView? _view;
  bool _loading = true;
  Object? _error;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    if (preview != null) {
      _view = _FarmerShopView(
        shop: preview,
        activeListings: preview.listings
            .where((listing) => listing.isActive)
            .toList(),
        listingsCount: preview.listings
            .where((listing) => listing.isActive)
            .length,
      );
      _loading = false;
      return;
    }
    _reload();
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
      activeListings = pages.items
          .where((listing) => listing.isActive)
          .toList();
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

  void _applyShop(ShopProfile shop) {
    final current = _view;
    _view = _FarmerShopView(
      shop: shop,
      activeListings: current?.activeListings ?? const [],
      listingsCount: current?.listingsCount,
      listingsError: current?.listingsError,
    );
  }

  Future<void> _reload() async {
    if (_previewing) {
      return;
    }
    setState(() {
      _loading = _view == null;
      _error = null;
    });
    try {
      final next = await _load();
      if (!mounted) {
        return;
      }
      setState(() {
        _view = next;
        _loading = false;
        _error = null;
      });
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() {
        _loading = false;
        _error = error;
      });
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

  Future<void> _editShop(ShopProfile shop) async {
    final saved = await Navigator.of(context).push<ShopProfile>(
      MaterialPageRoute(
        builder: (_) => ShopEditScreen(shop: shop, preview: _previewing),
      ),
    );
    if (saved != null && mounted) {
      setState(() => _applyShop(saved));
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
    await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => const WalkInSaleScreen()));
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
            onPressed: () => Navigator.of(context)
                .push(MaterialPageRoute(builder: (_) => const FaqBotScreen())),
          ),
          IconButton(
            icon: const Icon(Icons.settings_outlined),
            onPressed: () => Navigator.of(
              context,
            ).push(MaterialPageRoute(builder: (_) => const SettingsScreen())),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(child: Text('$_error'))
          : _view == null
          ? Center(child: Text(s.shopNotFound))
          : _shopBody(context, s, _view!),
    );
  }

  void _openReviews(ShopProfile shop) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => ShopReviewsScreen(
          preview: _previewing
              ? PagedShopReviews(
                  reviews: const [],
                  currentPage: 1,
                  lastPage: 1,
                  averageRating: shop.averageRating,
                  reviewsCount: shop.reviewsCount,
                )
              : null,
        ),
      ),
    );
  }

  Widget _shopBody(BuildContext context, AppStrings s, _FarmerShopView view) {
    final shop = view.shop;
    final user = context.watch<AuthController>().user;
    final farmName = user?.farmName?.trim();
    final farmId = user?.farmId ?? shop.farmId;
    final theme = Theme.of(context);
    final walkIn = (user?.canRecordWalkInSales ?? false) && user!.farmFeatures.walkIn;
    final complete = _ShopChecklist.isComplete(shop);
    return RefreshIndicator(
      onRefresh: _reload,
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          _ShopHero(shop: shop, onCover: () => _editShop(shop)),
          Padding(
            padding: const EdgeInsets.fromLTRB(
              AniHowSpace.screen,
              8,
              AniHowSpace.screen,
              AniHowSpace.screen + AniHowSpace.section,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  shop.shopName,
                  key: const ValueKey('shop-profile-name'),
                  style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  runSpacing: 6,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    if (farmId != null &&
                        farmName != null &&
                        farmName.isNotEmpty &&
                        shop.farmIsActive)
                      FarmLinkChip(farmId: farmId, logoUrl: shop.farmLogoUrl, label: farmName)
                    else if (farmName != null && farmName.isNotEmpty)
                      Text(s.farmLine(farmName)),
                    if (shop.hasRating)
                      RatingLabel(rating: shop.averageRating!, count: shop.reviewsCount),
                  ],
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                _SellerStats(
                  listings: view.listingsCount,
                  rating: shop.hasRating ? shop.averageRating : null,
                  reviews: shop.reviewsCount,
                  onReviews: () => _openReviews(shop),
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        key: const ValueKey('shop-edit'),
                        onPressed: () => _editShop(shop),
                        icon: const Icon(Icons.edit_outlined, size: 18),
                        label: Text(s.editShop),
                      ),
                    ),
                    if (walkIn) ...[
                      const SizedBox(width: AniHowSpace.cardGap),
                      Expanded(
                        child: FilledButton.tonalIcon(
                          key: const ValueKey('shop-walk-in'),
                          style: FilledButton.styleFrom(
                            backgroundColor: accentTint(context),
                            foregroundColor: readableAccent(context),
                          ),
                          onPressed: _openWalkIn,
                          icon: const Icon(Icons.point_of_sale_outlined, size: 18),
                          label: Text(s.walkInSale),
                        ),
                      ),
                    ],
                  ],
                ),
                const SizedBox(height: AniHowSpace.cardGap),
                if (_ShopAbout.hasAny(shop)) ...[
                  _ShopAbout(shop: shop, onCall: _call),
                  const SizedBox(height: AniHowSpace.cardGap),
                ],
                if (!complete) ...[
                  _ShopChecklist(shop: shop, onFix: () => _editShop(shop)),
                  const SizedBox(height: AniHowSpace.cardGap),
                ],
                _ShopPaymentsCard(shop: shop, onManage: () => _editShop(shop)),
                const SizedBox(height: AniHowSpace.section),
                Text(s.yourListings, style: theme.textTheme.titleMedium),
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
          ),
        ],
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

class _PhotoActions extends StatefulWidget {
  const _PhotoActions({required this.user});

  final UserAccount user;

  @override
  State<_PhotoActions> createState() => _PhotoActionsState();
}

class _PhotoActionsState extends State<_PhotoActions> {
  bool _busy = false;

  Future<void> _change() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      imageQuality: 82,
    );
    if (picked == null || !mounted) {
      return;
    }
    setState(() => _busy = true);
    try {
      final auth = context.read<AuthController>();
      final account = await auth.api.uploadAvatar(picked.path);
      if (mounted) {
        auth.applyAccount(account);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _remove() async {
    setState(() => _busy = true);
    try {
      final auth = context.read<AuthController>();
      final account = await auth.api.deleteAvatar();
      if (mounted) {
        auth.applyAccount(account);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
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
    final hasPhoto =
        widget.user.avatarUrl != null && widget.user.avatarUrl!.isNotEmpty;
    final style = TextButton.styleFrom(minimumSize: const Size(48, 48));

    return Wrap(
      spacing: 8,
      children: [
        TextButton(
          key: const Key('change-profile-photo'),
          style: style,
          onPressed: _busy ? null : _change,
          child: Text(s.changePhoto),
        ),
        if (hasPhoto)
          TextButton(
            key: const Key('remove-profile-photo'),
            style: style,
            onPressed: _busy ? null : _remove,
            child: Text(s.removePhoto),
          ),
      ],
    );
  }
}

class ShopEditScreen extends StatefulWidget {
  const ShopEditScreen({super.key, required this.shop, this.preview = false});

  final ShopProfile shop;
  final bool preview;

  @override
  State<ShopEditScreen> createState() => _ShopEditScreenState();
}

class _ShopEditScreenState extends State<ShopEditScreen> {
  late final TextEditingController _name;
  late final TextEditingController _bio;
  late final TextEditingController _location;
  late final TextEditingController _contact;
  bool _busy = false;
  late String? _coverUrl;
  late bool _acceptsOnline;
  late int _hours;
  late List<PaymentQrCode> _qrs;
  String? _paymentError;

  @override
  void initState() {
    super.initState();
    _name = TextEditingController(text: widget.shop.shopName);
    _bio = TextEditingController(text: widget.shop.bio ?? '');
    _location = TextEditingController(text: widget.shop.location ?? '');
    _contact = TextEditingController(text: widget.shop.contact ?? '');
    _coverUrl = widget.shop.coverUrl;
    _acceptsOnline = widget.shop.acceptsOnlinePayment;
    _hours = widget.shop.paymentTimeLimitHours;
    _qrs = List<PaymentQrCode>.of(widget.shop.paymentQrs);
  }

  @override
  void dispose() {
    _name.dispose();
    _bio.dispose();
    _location.dispose();
    _contact.dispose();
    super.dispose();
  }

  ShopProfile _shopFromFields() {
    final bio = _bio.text.trim();
    final location = _location.text.trim();
    final contact = _contact.text.trim();
    return ShopProfile(
      id: widget.shop.id,
      shopName: _name.text.trim(),
      name: widget.shop.name,
      bio: bio.isEmpty ? null : bio,
      location: location.isEmpty ? null : location,
      contact: contact.isEmpty ? null : contact,
      avatarUrl: widget.shop.avatarUrl,
      coverUrl: _coverUrl,
      averageRating: widget.shop.averageRating,
      reviewsCount: widget.shop.reviewsCount,
      listings: widget.shop.listings,
      farmId: widget.shop.farmId,
      farmName: widget.shop.farmName,
      farmIsActive: widget.shop.farmIsActive,
      acceptsOnlinePayment: _acceptsOnline,
      paymentTimeLimitHours: _hours,
      paymentQrs: _qrs,
      isFavorited: widget.shop.isFavorited,
    );
  }

  Future<void> _changeCover() async {
    final picked = await ImagePicker().pickImage(
      source: ImageSource.gallery,
      maxWidth: 1600,
      imageQuality: 82,
    );
    if (picked == null || !mounted) {
      return;
    }
    if (widget.preview) {
      setState(() => _coverUrl = picked.path);
      return;
    }
    setState(() => _busy = true);
    try {
      final shop = await context.read<AuthController>().api.uploadShopCover(
        picked.path,
      );
      if (mounted) {
        setState(() => _coverUrl = shop.coverUrl);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _removeCover() async {
    if (widget.preview) {
      setState(() => _coverUrl = null);
      return;
    }
    setState(() => _busy = true);
    try {
      final shop = await context.read<AuthController>().api.deleteShopCover();
      if (mounted) {
        setState(() => _coverUrl = shop.coverUrl);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _save() async {
    if (widget.preview) {
      Navigator.of(context).pop(_shopFromFields());
      return;
    }
    setState(() {
      _busy = true;
      _paymentError = null;
    });
    try {
      final shop = await context.read<AuthController>().api.updateFarmerShop({
        'shop_name': _name.text.trim(),
        'bio': _bio.text.trim(),
        'location': _location.text.trim(),
        'contact': _contact.text.trim(),
        'accepts_online_payment': _qrs.isNotEmpty && _acceptsOnline,
        'payment_time_limit_hours': _hours,
      });
      if (mounted) {
        Navigator.of(context)
            .pop(shop.bio == _bio.text.trim() ? shop : _shopFromFields());
      }
    } on ApiException catch (error) {
      if (mounted) {
        setState(() => _paymentError = error.message);
      }
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context)
            .showSnackBar(SnackBar(content: Text('$error')));
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
      body: SingleChildScrollView(
        padding: AniHowSpace.screenPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
          AniHowFormCard(
            title: s.shopProfile,
            child: Column(
              children: [
                _CoverBanner(url: _coverUrl),
                const SizedBox(height: AniHowSpace.fieldGap),
                Wrap(
                  spacing: 8,
                  children: [
                    TextButton(
                      style: TextButton.styleFrom(
                        minimumSize: const Size(48, 48),
                      ),
                      onPressed: _busy ? null : _changeCover,
                      child: Text(s.changeCover),
                    ),
                    if (_coverUrl != null && _coverUrl!.isNotEmpty)
                      TextButton(
                        key: const Key('remove-shop-cover'),
                        style: TextButton.styleFrom(
                          minimumSize: const Size(48, 48),
                        ),
                        onPressed: _busy ? null : _removeCover,
                        child: Text(s.removeCover),
                      ),
                  ],
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(
                  label: s.shopName,
                  child: TextField(controller: _name),
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(
                  label: s.bio,
                  child: TextField(controller: _bio, maxLines: 4),
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(
                  label: s.location,
                  child: TextField(controller: _location),
                ),
                const SizedBox(height: AniHowSpace.fieldGap),
                AniHowField(
                  label: s.contact,
                  child: TextField(controller: _contact),
                ),
              ],
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          OnlinePaymentSection(
            qrs: _qrs,
            acceptsOnline: _acceptsOnline,
            hours: _hours,
            busy: _busy,
            error: _paymentError,
            onError: (message) => setState(() => _paymentError = message),
            onAccepts: (value) => setState(() => _acceptsOnline = value),
            onHours: (value) => setState(() => _hours = value),
            onQrs: (value) => setState(() => _qrs = value),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(
            label: s.saveShopProfile,
            busy: _busy,
            onPressed: _save,
          ),
          ],
        ),
      ),
    );
  }
}

class _CoverBanner extends StatelessWidget {
  const _CoverBanner({required this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    final photo = url?.trim();
    final hasPhoto = photo != null && photo.isNotEmpty;

    return ClipRRect(
      borderRadius: BorderRadius.circular(12),
      child: SizedBox(
        height: 120,
        width: double.infinity,
        child: hasPhoto
            ? photo.startsWith('http')
                  ? Image.network(photo, fit: BoxFit.cover)
                  : Image.file(File(photo), fit: BoxFit.cover)
            : ColoredBox(
                color: Theme.of(context).colorScheme.surfaceContainerHighest,
              ),
      ),
    );
  }
}

/// The cover banner with the shop photo overlapping its bottom edge. Without
/// a cover it stays brand green, with a button to add one.
class _ShopHero extends StatelessWidget {
  const _ShopHero({required this.shop, required this.onCover});

  final ShopProfile shop;
  final VoidCallback onCover;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final cover = shop.coverUrl?.trim();
    final hasCover = cover != null && cover.isNotEmpty;
    final background = Theme.of(context).scaffoldBackgroundColor;
    return SizedBox(
      height: 168,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          Positioned.fill(
            bottom: 40,
            child: hasCover
                ? Image.network(
                    cover,
                    key: const ValueKey('shop-cover-photo'),
                    fit: BoxFit.cover,
                    errorBuilder: (_, _, _) => const _GreenCover(),
                  )
                : const _GreenCover(),
          ),
          Positioned(
            top: 10,
            right: 10,
            child: Material(
              color: Colors.black.withValues(alpha: 0.38),
              shape: const StadiumBorder(),
              child: InkWell(
                key: const ValueKey('shop-cover-button'),
                customBorder: const StadiumBorder(),
                onTap: onCover,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.photo_camera_outlined, size: 16, color: Colors.white),
                      const SizedBox(width: 6),
                      Text(
                        hasCover ? s.changeCover : s.addCover,
                        style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          Positioned(
            left: AniHowSpace.screen,
            bottom: 0,
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: background, width: 4),
              ),
              child: AniHowAvatar(name: shop.shopName, imageUrl: shop.avatarUrl, radius: 36),
            ),
          ),
        ],
      ),
    );
  }
}

class _GreenCover extends StatelessWidget {
  const _GreenCover();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF2C6B4B), Color(0xFF1A4733), Color(0xFF173B2B)],
        ),
      ),
    );
  }
}

/// Listings, rating and reviews in one row; the reviews cell opens them.
class _SellerStats extends StatelessWidget {
  const _SellerStats({
    required this.listings,
    required this.rating,
    required this.reviews,
    required this.onReviews,
  });

  final int? listings;
  final String? rating;
  final int reviews;
  final VoidCallback onReviews;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    Widget cell(String value, String label, {String? link}) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 4),
        child: Column(
          children: [
            Text(value, style: theme.textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800)),
            Text(label, style: theme.textTheme.labelMedium),
            if (link != null)
              Text(
                link,
                style: theme.textTheme.labelSmall?.copyWith(
                  color: readableAccent(context),
                  fontWeight: FontWeight.w700,
                ),
              ),
          ],
        ),
      );
    }

    final divider = VerticalDivider(width: 1, thickness: 1, color: theme.dividerColor);
    return Card(
      child: IntrinsicHeight(
        child: Row(
          children: [
            Expanded(child: cell(listings == null ? '–' : '$listings', s.listings)),
            divider,
            Expanded(
              child: cell(rating == null ? '–' : '${AniHowMoney.rating(rating)}★', s.rating),
            ),
            divider,
            Expanded(
              child: InkWell(
                key: const ValueKey('farmer-shop-reviews'),
                onTap: onReviews,
                child: cell('$reviews', s.reviews, link: '${s.seeAll} ›'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Shown until the shop has a name, photo, bio, location and contact. Each
/// missing item opens the shop editor.
class _ShopChecklist extends StatelessWidget {
  const _ShopChecklist({required this.shop, required this.onFix});

  final ShopProfile shop;
  final VoidCallback onFix;

  static bool _filled(String? value) => value != null && value.trim().isNotEmpty;

  static bool isComplete(ShopProfile shop) =>
      _filled(shop.avatarUrl) && _filled(shop.bio) && _filled(shop.location) && _filled(shop.contact);

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final items = <(bool, IconData, String, String?)>[
      (true, Icons.storefront_outlined, s.shopNameDone, null),
      (_filled(shop.avatarUrl), Icons.photo_camera_outlined, s.addShopPhoto, s.addShopPhotoHint),
      (_filled(shop.bio), Icons.edit_note, s.addShortBio, s.addShortBioHint),
      (_filled(shop.location), Icons.location_on_outlined, s.addYourLocation, s.addYourLocationHint),
      (_filled(shop.contact), Icons.phone_outlined, s.addContactNumber, null),
    ];
    final done = items.where((item) => item.$1).length;
    return Card(
      key: const ValueKey('shop-checklist'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    s.finishYourShop,
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                Text(
                  s.finishProgress(done, items.length),
                  style: TextStyle(color: readableAccent(context), fontWeight: FontWeight.w700),
                ),
              ],
            ),
            const SizedBox(height: 8),
            ClipRRect(
              borderRadius: BorderRadius.circular(3),
              child: LinearProgressIndicator(
                value: done / items.length,
                minHeight: 6,
                color: AniHowColors.root,
                backgroundColor: theme.colorScheme.onSurface.withValues(alpha: 0.12),
              ),
            ),
            const SizedBox(height: 4),
            Text(
              s.finishShopHint,
              style: theme.textTheme.bodySmall?.copyWith(
                color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
              ),
            ),
            for (final item in items)
              ListTile(
                contentPadding: EdgeInsets.zero,
                minTileHeight: 48,
                enabled: !item.$1,
                onTap: item.$1 ? null : onFix,
                leading: CircleAvatar(
                  radius: 15,
                  backgroundColor: item.$1
                      ? readableAccent(context).withValues(alpha: 0.16)
                      : AniHowColors.root.withValues(alpha: 0.16),
                  child: Icon(
                    item.$1 ? Icons.check : item.$2,
                    size: 16,
                    color: item.$1 ? readableAccent(context) : AniHowColors.root,
                  ),
                ),
                title: Text(
                  item.$3,
                  style: item.$1
                      ? TextStyle(
                          decoration: TextDecoration.lineThrough,
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.6),
                        )
                      : null,
                ),
                subtitle: item.$1 || item.$4 == null ? null : Text(item.$4!),
                trailing: item.$1 ? null : const Icon(Icons.chevron_right),
              ),
          ],
        ),
      ),
    );
  }
}

class _ShopAbout extends StatelessWidget {
  const _ShopAbout({required this.shop, required this.onCall});

  final ShopProfile shop;
  final ValueChanged<String> onCall;

  static String? _text(String? value) {
    final trimmed = value?.trim();
    return trimmed == null || trimmed.isEmpty ? null : trimmed;
  }

  /// Only what the seller has filled in; the checklist covers the rest.
  static bool hasAny(ShopProfile shop) =>
      _text(shop.bio) != null || _text(shop.location) != null || _text(shop.contact) != null;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final bio = _text(shop.bio);
    final location = _text(shop.location);
    final contact = _text(shop.contact);
    Widget row(IconData icon, Widget child) => Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 18, color: theme.colorScheme.onSurface.withValues(alpha: 0.7)),
          const SizedBox(width: 10),
          Expanded(child: child),
        ],
      ),
    );
    return Card(
      key: const ValueKey('shop-about'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(s.aboutShop, style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700)),
            if (bio != null) row(Icons.eco_outlined, Text(bio)),
            if (location != null) row(Icons.location_on_outlined, Text(location)),
            if (contact != null)
              row(
                Icons.phone_outlined,
                InkWell(
                  onTap: () => onCall(contact),
                  child: Text(
                    contact,
                    style: TextStyle(color: readableAccent(context), fontWeight: FontWeight.w600),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Whether online payment is on, at a glance. Manage opens the shop editor,
/// where the QR codes and the time limit live.
class _ShopPaymentsCard extends StatelessWidget {
  const _ShopPaymentsCard({required this.shop, required this.onManage});

  final ShopProfile shop;
  final VoidCallback onManage;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final on = shop.acceptsOnlinePayment && shop.paymentQrs.isNotEmpty;
    return Card(
      key: const ValueKey('shop-payments'),
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    s.payments,
                    style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
                  ),
                ),
                TextButton(
                  key: const ValueKey('shop-payments-manage'),
                  onPressed: onManage,
                  child: Text('${s.manageLink} ›'),
                ),
              ],
            ),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Icon(
                    Icons.circle,
                    size: 10,
                    color: on ? readableAccent(context) : AniHowColors.cancelled,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        on ? s.onlinePaymentIsOn : s.onlinePaymentIsOff,
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        on
                            ? s.onlinePaymentDetail(shop.paymentQrs.length, shop.paymentTimeLimitHours)
                            : s.onlinePaymentOffDetail,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
