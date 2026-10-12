import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/id_verification_card.dart';
import '../../widgets/primary_button.dart';

/// Picks one photo. Swapped out in tests, where the platform picker is absent.
typedef IdPhotoPicker = Future<String?> Function(
  ImageSource source, {
  required bool selfie,
});

Future<String?> _pickWithImagePicker(
  ImageSource source, {
  required bool selfie,
}) async {
  final picked = await ImagePicker().pickImage(
    source: source,
    maxWidth: 2000,
    imageQuality: 85,
    preferredCameraDevice: selfie ? CameraDevice.front : CameraDevice.rear,
  );
  return picked?.path;
}

/// The buyer's ID check: pick the ID type, photograph the front, optionally
/// add a selfie, agree to how the photos are used, and send for review.
class VerifyIdScreen extends StatefulWidget {
  const VerifyIdScreen({
    super.key,
    this.afterSignUp = false,
    this.pickPhoto = _pickWithImagePicker,
  });

  /// Opened right after sign-up, so a "Skip for now" link is shown.
  final bool afterSignUp;
  final IdPhotoPicker pickPhoto;

  static const idTypes = [
    'national_id',
    'drivers_license',
    'passport',
    'umid',
    'postal_id',
    'other_government',
  ];

  @override
  State<VerifyIdScreen> createState() => _VerifyIdScreenState();
}

class _VerifyIdScreenState extends State<VerifyIdScreen> {
  String _type = VerifyIdScreen.idTypes.first;
  String? _front;
  String? _selfie;
  bool _consent = false;
  bool _busy = false;

  Future<void> _take({required bool selfie}) async {
    final s = AppStrings.read(context);
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (sheetContext) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              key: const ValueKey('id-source-camera'),
              leading: const Icon(Icons.photo_camera_outlined),
              title: Text(s.useCamera),
              onTap: () => Navigator.of(sheetContext).pop(ImageSource.camera),
            ),
            ListTile(
              key: const ValueKey('id-source-gallery'),
              leading: const Icon(Icons.photo_library_outlined),
              title: Text(s.useGallery),
              onTap: () => Navigator.of(sheetContext).pop(ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null || !mounted) {
      return;
    }
    final path = await widget.pickPhoto(source, selfie: selfie);
    if (path == null || !mounted) {
      return;
    }
    setState(() => selfie ? _selfie = path : _front = path);
  }

  Future<void> _submit() async {
    setState(() => _busy = true);
    final auth = context.read<AuthController>();
    final messenger = ScaffoldMessenger.of(context);
    final s = AppStrings.read(context);
    try {
      final account = await auth.api.submitIdVerification(
        idType: _type,
        frontPath: _front!,
        selfiePath: _selfie,
      );
      auth.applyAccount(account);
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(s.idSubmitted)));
      Navigator.of(context).pop();
    } catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _busy = false);
      messenger.showSnackBar(SnackBar(content: Text('$error')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.7);
    final info = context.watch<AuthController>().user?.idVerification;
    final optional = info != null && !info.required;
    final intro = IdToneColors.blue(context);
    final ready = _front != null && _consent;

    return Scaffold(
      appBar: AppBar(
        title: Text(s.verifyYourId),
        actions: [
          if (widget.afterSignUp)
            TextButton(
              key: const ValueKey('verify-id-skip'),
              onPressed: () => Navigator.of(context).pop(),
              style: TextButton.styleFrom(foregroundColor: Colors.white),
              child: Text(
                s.skipForNow,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: intro.background,
              borderRadius: BorderRadius.circular(18),
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Icon(Icons.verified_user_outlined, color: intro.foreground),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        optional ? s.getVerifiedBadge : s.verifiedBuyersCanOrder,
                        style: TextStyle(
                          color: intro.foreground,
                          fontSize: 14.5,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        optional ? s.verifyIdIntroOptional : s.verifyIdIntro,
                        style: TextStyle(
                          color: intro.foreground,
                          fontSize: 13.5,
                          height: 1.45,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _StepCard(
            title: s.whichIdStep,
            child: _IdTypeGrid(
              selected: _type,
              onSelected: _busy ? null : (type) => setState(() => _type = type),
            ),
          ),
          const SizedBox(height: 12),
          _StepCard(
            title: s.idFrontStep,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _PhotoSlot(
                  key: const ValueKey('id-front'),
                  path: _front,
                  height: 170,
                  icon: Icons.photo_camera_outlined,
                  label: s.takeAPhoto,
                  hint: s.orChooseFromGallery,
                  onTap: _busy ? null : () => _take(selfie: false),
                ),
                const SizedBox(height: 10),
                for (final tip in [s.idTipCorners, s.idTipReadable])
                  Padding(
                    padding: const EdgeInsets.only(bottom: 2),
                    child: Text(
                      '•  $tip',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: muted,
                        fontSize: 12.5,
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          _StepCard(
            title: s.idSelfieStep,
            subtitle: s.optionalFasterApproval,
            child: _PhotoSlot(
              key: const ValueKey('id-selfie'),
              path: _selfie,
              height: 96,
              icon: Icons.person_outline,
              label: s.takeASelfie,
              onTap: _busy ? null : () => _take(selfie: true),
            ),
          ),
          const SizedBox(height: 12),
          Card(
            margin: EdgeInsets.zero,
            child: CheckboxListTile(
              key: const ValueKey('id-consent'),
              value: _consent,
              onChanged: _busy
                  ? null
                  : (value) => setState(() => _consent = value ?? false),
              controlAffinity: ListTileControlAffinity.leading,
              contentPadding: const EdgeInsets.fromLTRB(8, 6, 14, 6),
              title: Text(
                s.idConsent,
                style: theme.textTheme.bodySmall?.copyWith(
                  fontSize: 13,
                  height: 1.5,
                ),
              ),
            ),
          ),
        ],
      ),
      bottomNavigationBar: DecoratedBox(
        decoration: BoxDecoration(
          color: theme.cardTheme.color ?? theme.colorScheme.surface,
          border: Border(top: BorderSide(color: theme.dividerColor)),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                PrimaryButton(
                  key: const ValueKey('id-submit'),
                  label: s.submitForReview,
                  busy: _busy,
                  onPressed: ready ? _submit : null,
                ),
                const SizedBox(height: 6),
                Text(
                  s.doThisLaterFromProfile,
                  textAlign: TextAlign.center,
                  style: theme.textTheme.bodySmall?.copyWith(color: muted),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.title, required this.child, this.subtitle});

  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: EdgeInsets.zero,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text.rich(
              TextSpan(
                text: title,
                style: theme.textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                  fontSize: 15.5,
                ),
                children: [
                  if (subtitle != null)
                    TextSpan(
                      text: ' $subtitle',
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(
                          alpha: 0.7,
                        ),
                        fontSize: 13,
                      ),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            child,
          ],
        ),
      ),
    );
  }
}

class _IdTypeGrid extends StatelessWidget {
  const _IdTypeGrid({required this.selected, required this.onSelected});

  final String selected;
  final ValueChanged<String>? onSelected;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final tone = IdToneColors.green(context);
    final line = theme.brightness == Brightness.dark
        ? AniHowColors.darkHairline
        : const Color(0xFFE3DFD5);
    final types = VerifyIdScreen.idTypes;

    Widget option(String type) {
      final on = type == selected;
      return Semantics(
        inMutuallyExclusiveGroup: true,
        selected: on,
        button: true,
        child: InkWell(
          key: ValueKey('id-type-$type'),
          onTap: onSelected == null ? null : () => onSelected!(type),
          borderRadius: BorderRadius.circular(12),
          child: Container(
            constraints: const BoxConstraints(minHeight: 48),
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
            decoration: BoxDecoration(
              color: on ? tone.background : null,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(
                color: on ? tone.foreground : line,
                width: 1.5,
              ),
            ),
            child: Row(
              children: [
                Icon(
                  on ? Icons.radio_button_checked : Icons.radio_button_off,
                  size: 18,
                  color: on ? tone.foreground : theme.colorScheme.onSurface,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    s.idTypeLabel(type),
                    style: TextStyle(
                      color: on ? tone.foreground : theme.colorScheme.onSurface,
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return Column(
      children: [
        for (var row = 0; row < types.length; row += 2) ...[
          if (row > 0) const SizedBox(height: 8),
          IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: option(types[row])),
                const SizedBox(width: 8),
                Expanded(child: option(types[row + 1])),
              ],
            ),
          ),
        ],
      ],
    );
  }
}

class _PhotoSlot extends StatelessWidget {
  const _PhotoSlot({
    super.key,
    required this.path,
    required this.height,
    required this.icon,
    required this.label,
    required this.onTap,
    this.hint,
  });

  final String? path;
  final double height;
  final IconData icon;
  final String label;
  final String? hint;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final theme = Theme.of(context);
    final dark = theme.brightness == Brightness.dark;
    final field = dark ? const Color(0xFF16201C) : const Color(0xFFFBFAF7);
    final line = dark ? AniHowColors.darkHairline : const Color(0xFFE3DFD5);
    final tone = IdToneColors.green(context);

    if (path != null) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: SizedBox(
          height: height < 150 ? 150 : height,
          child: Stack(
            fit: StackFit.expand,
            children: [
              ColoredBox(
                color: const Color(0xFF2C4A3B),
                child: Image.file(
                  File(path!),
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) => const Icon(
                    Icons.image_outlined,
                    color: Colors.white70,
                    size: 40,
                  ),
                ),
              ),
              Positioned(
                right: 10,
                bottom: 10,
                child: FilledButton.tonal(
                  onPressed: onTap,
                  style: FilledButton.styleFrom(
                    backgroundColor: Colors.black54,
                    foregroundColor: Colors.white,
                    minimumSize: const Size(48, 40),
                  ),
                  child: Text(s.retakePhoto),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Material(
      color: field,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: line, width: 2),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: SizedBox(
          height: height,
          child: hint == null
              ? Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(icon, color: theme.colorScheme.primary),
                    const SizedBox(width: 12),
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                )
              : Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 26,
                      backgroundColor: tone.background,
                      child: Icon(icon, color: tone.foreground),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 14.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      hint!,
                      style: theme.textTheme.bodySmall?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(
                          alpha: 0.7,
                        ),
                      ),
                    ),
                  ],
                ),
        ),
      ),
    );
  }
}
