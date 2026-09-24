import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/app_header.dart';
import '../../widgets/async_view.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/profile_avatar_button.dart';
import '../buyer/shop_profile_screen.dart';

void openFarmProfile(BuildContext context, int farmId) {
  Navigator.of(context).push(
    MaterialPageRoute<void>(builder: (_) => FarmProfileScreen(farmId: farmId)),
  );
}

class FarmLinkChip extends StatelessWidget {
  const FarmLinkChip({
    super.key,
    required this.farmId,
    this.label,
  });

  final int farmId;
  final String? label;

  @override
  Widget build(BuildContext context) {
    final text = label ?? AppStrings.of(context).farm;
    return Align(
      alignment: Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(minHeight: 48),
        child: ActionChip(
          avatar: const Icon(Icons.agriculture_outlined, size: 18),
          label: Text(text),
          onPressed: () => openFarmProfile(context, farmId),
        ),
      ),
    );
  }
}

class FarmProfileScreen extends StatefulWidget {
  const FarmProfileScreen({
    super.key,
    required this.farmId,
    this.preview,
  });

  final int farmId;
  final FarmProfile? preview;

  @override
  State<FarmProfileScreen> createState() => _FarmProfileScreenState();
}

class _FarmProfileScreenState extends State<FarmProfileScreen> {
  late Future<FarmProfile> _farm;

  @override
  void initState() {
    super.initState();
    _farm = _load();
  }

  Future<FarmProfile> _load() {
    final preview = widget.preview;
    if (preview != null) {
      return Future.value(preview);
    }
    return context.read<AuthController>().api.farm(widget.farmId);
  }

  Future<void> _reload() async {
    final farm = _load();
    setState(() => _farm = farm);
    await farm;
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

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    return Scaffold(
      appBar: AppHeader(title: s.farmProfile),
      body: FutureBuilder<FarmProfile>(
        future: _farm,
        builder: (context, snapshot) {
          return AsyncView<FarmProfile>.snapshot(
            snapshot: snapshot,
            onRetry: _reload,
            emptyMessage: s.farmNotFound,
            builder: (context, farm) {
              return RefreshIndicator(
                onRefresh: _reload,
                child: FarmProfileView(
                  farm: farm,
                  onCall: _call,
                  onStorefront: (sellerId) => openBuyerShop(context, sellerId),
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class FarmProfileView extends StatelessWidget {
  const FarmProfileView({
    super.key,
    required this.farm,
    this.onCall,
    this.onStorefront,
  });

  final FarmProfile farm;
  final Future<void> Function(String number)? onCall;
  final ValueChanged<int>? onStorefront;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final place = farm.placeLabel;
    final description = farm.description?.trim();
    final pickup = farm.pickupPoint?.trim();
    final contactPerson = farm.contactPerson?.trim();
    final contactNumber = farm.contactNumber?.trim();

    return ListView(
      children: [
        _Cover(farm: farm),
        Padding(
          padding: AniHowSpace.screenPadding,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(farm.name, style: theme.textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800)),
              if (place.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.labelGap),
                Text(place, style: theme.textTheme.bodyMedium),
              ],
              if (description != null && description.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.cardGap),
                Text(description, style: theme.textTheme.bodyMedium),
              ],
              if (pickup != null && pickup.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.section),
                AniHowHintCard(
                  icon: Icons.place_outlined,
                  title: s.pickupPoint,
                  body: pickup,
                  tone: AniHowHintTone.brand,
                ),
              ],
              if (farm.announcements.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.section),
                Text(s.announcements, style: theme.textTheme.titleMedium),
                const SizedBox(height: AniHowSpace.cardGap),
                ...farm.announcements.map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                    child: AniHowHintCard(
                      icon: item.isPinned ? Icons.push_pin_outlined : Icons.campaign_outlined,
                      title: item.title,
                      body: item.body,
                      tone: AniHowHintTone.brand,
                    ),
                  ),
                ),
              ],
              if (contactPerson != null && contactPerson.isNotEmpty) ...[
                const SizedBox(height: AniHowSpace.section),
                Text(s.farmContact, style: theme.textTheme.titleMedium),
                const SizedBox(height: AniHowSpace.labelGap),
                if (contactNumber != null && contactNumber.isNotEmpty)
                  Align(
                    alignment: Alignment.centerLeft,
                    child: TextButton.icon(
                      onPressed: onCall == null ? null : () => onCall!(contactNumber),
                      icon: const Icon(Icons.phone_outlined),
                      label: Text('$contactPerson · $contactNumber'),
                    ),
                  )
                else
                  SelectableText(contactPerson, style: theme.textTheme.bodyMedium),
                if (_isBuyer(context)) ...[
                  const SizedBox(height: AniHowSpace.labelGap),
                  Text(s.farmContactBuyerHint, style: theme.textTheme.bodyMedium),
                ],
              ],
              const SizedBox(height: AniHowSpace.section),
              Text(s.farmPhotos, style: theme.textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.cardGap),
              if (farm.photos.isEmpty)
                Text(s.noFarmPhotos, style: theme.textTheme.bodyMedium)
              else
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: farm.photos.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: AniHowSpace.cardGap,
                    crossAxisSpacing: AniHowSpace.cardGap,
                    childAspectRatio: 1,
                  ),
                  itemBuilder: (context, index) {
                    final photo = farm.photos[index];
                    return _PhotoTile(photo: photo);
                  },
                ),
              const SizedBox(height: AniHowSpace.section),
              Text(s.farmStorefronts, style: theme.textTheme.titleMedium),
              const SizedBox(height: AniHowSpace.cardGap),
              if (farm.storefronts.isEmpty)
                Text(s.noFarmStorefronts, style: theme.textTheme.bodyMedium)
              else
                ...farm.storefronts.map(
                  (storefront) => Padding(
                    padding: const EdgeInsets.only(bottom: AniHowSpace.cardGap),
                    child: _StorefrontTile(
                      storefront: storefront,
                      onTap: onStorefront == null ? null : () => onStorefront!(storefront.id),
                    ),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }

  bool _isBuyer(BuildContext context) {
    try {
      return context.watch<AuthController>().user?.isBuyer ?? false;
    } on ProviderNotFoundException {
      return false;
    }
  }
}

class _Cover extends StatelessWidget {
  const _Cover({required this.farm});

  final FarmProfile farm;

  @override
  Widget build(BuildContext context) {
    final url = farm.coverPhotoUrl;
    return SizedBox(
      key: Key(farm.hasCoverPhoto ? 'farm-cover' : 'farm-cover-fallback'),
      height: 180,
      width: double.infinity,
      child: farm.hasCoverPhoto
          ? Image.network(
              url!,
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
              filterQuality: FilterQuality.high,
              errorBuilder: (_, _, _) => const _CoverFallback(),
            )
          : const _CoverFallback(),
    );
  }
}

class _CoverFallback extends StatelessWidget {
  const _CoverFallback();

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: AniHowColors.brand,
      child: Center(
        child: Icon(
          Icons.agriculture_outlined,
          size: 56,
          color: Theme.of(context).colorScheme.onPrimary.withValues(alpha: 0.88),
        ),
      ),
    );
  }
}

class _PhotoTile extends StatelessWidget {
  const _PhotoTile({required this.photo});

  final FarmPhotoItem photo;

  @override
  Widget build(BuildContext context) {
    final caption = photo.caption?.trim();
    return ClipRRect(
      borderRadius: BorderRadius.circular(AniHowSpace.radius),
      child: Stack(
        fit: StackFit.expand,
        children: [
          Image.network(
            photo.url,
            fit: BoxFit.cover,
            filterQuality: FilterQuality.high,
            errorBuilder: (_, _, _) => ColoredBox(
              color: AniHowColors.brand.withValues(alpha: 0.12),
              child: const Icon(Icons.image_outlined, color: AniHowColors.brand),
            ),
          ),
          if (caption != null && caption.isNotEmpty)
            Align(
              alignment: Alignment.bottomCenter,
              child: ColoredBox(
                color: Colors.black.withValues(alpha: 0.45),
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(8, 6, 8, 6),
                  child: Text(
                    caption,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: Colors.white,
                        ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _StorefrontTile extends StatelessWidget {
  const _StorefrontTile({
    required this.storefront,
    this.onTap,
  });

  final FarmStorefront storefront;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AniHowTheme.cardRadius),
        child: ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 48),
          child: Padding(
            padding: AniHowSpace.cardPadding,
            child: Row(
              children: [
                AniHowAvatar(name: storefront.shopName, radius: 22),
                const SizedBox(width: AniHowSpace.cardGap),
                Expanded(
                  child: Text(
                    storefront.shopName,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                const Icon(Icons.chevron_right),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
