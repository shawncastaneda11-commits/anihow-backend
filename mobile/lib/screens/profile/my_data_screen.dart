import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/app_header.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';
import '../../widgets/status_pill.dart';

class MyDataPreview {
  const MyDataPreview({
    required this.user,
    this.deletion,
    this.deletionError,
    this.exportPath,
  });

  final UserAccount user;
  final AccountDeletionRequest? deletion;
  final String? deletionError;
  final String? exportPath;
}

class MyDataScreen extends StatefulWidget {
  const MyDataScreen({super.key, this.preview});

  final MyDataPreview? preview;

  @override
  State<MyDataScreen> createState() => _MyDataScreenState();
}

class _MyDataScreenState extends State<MyDataScreen> {
  final _name = TextEditingController();
  final _phone = TextEditingController();
  final _location = TextEditingController();
  bool _busy = false;
  bool _exportBusy = false;
  bool _deletionBusy = false;
  String? _exportPath;
  String? _deletionError;
  AccountDeletionRequest? _deletion;
  UserAccount? _user;
  int _deletionLoadToken = 0;
  bool _dirty = false;

  bool get _previewing => widget.preview != null;

  @override
  void initState() {
    super.initState();
    final preview = widget.preview;
    if (preview != null) {
      _user = preview.user;
      _deletion = preview.deletion;
      _deletionError = preview.deletionError;
      _exportPath = preview.exportPath;
      _fill(preview.user);
      return;
    }
    _user = context.read<AuthController>().user;
    if (_user != null) {
      _fill(_user!);
    }
    _loadDeletion();
    for (final controller in [_name, _phone, _location]) {
      controller.addListener(_trackChanges);
    }
  }

  void _fill(UserAccount user) {
    _name.text = user.name;
    _phone.text = user.phone ?? '';
    _location.text = user.location ?? '';
    _dirty = false;
  }

  /// Save lights up only after the buyer changes something.
  void _trackChanges() {
    final user = _user;
    final dirty = user == null ||
        _name.text.trim() != user.name ||
        _phone.text.trim() != (user.phone ?? '') ||
        _location.text.trim() != (user.location ?? '');
    if (dirty != _dirty) {
      setState(() => _dirty = dirty);
    }
  }

  Future<void> _loadDeletion() async {
    if (_previewing) {
      return;
    }
    final token = ++_deletionLoadToken;
    try {
      final request = await context.read<AuthController>().api.deletionRequest();
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      setState(() => _deletion = request);
    } on ApiException catch (error) {
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      setState(() => _deletionError = error.message);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    _phone.dispose();
    _location.dispose();
    super.dispose();
  }

  Future<void> _saveProfile() async {
    if (_previewing) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final auth = context.read<AuthController>();
    setState(() => _busy = true);
    try {
      final user = await auth.api.updateProfile(
        name: _name.text.trim(),
        phone: _phone.text.trim().isEmpty ? null : _phone.text.trim(),
        location: _location.text.trim().isEmpty ? null : _location.text.trim(),
      );
      await auth.refreshUser();
      if (!mounted) {
        return;
      }
      setState(() {
        _user = auth.user ?? user;
        _dirty = false;
      });
      messenger.showSnackBar(SnackBar(content: Text(AppStrings.read(context).profileUpdated)));
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  Future<void> _download() async {
    if (_previewing) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _exportBusy = true);
    try {
      final path = await context.read<AuthController>().api.saveOwnDataExport();
      if (!mounted) {
        return;
      }
      setState(() => _exportPath = path);
      messenger.showSnackBar(
        SnackBar(content: Text('${AppStrings.read(context).dataSavedTo} $path')),
      );
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _exportBusy = false);
      }
    }
  }

  Future<void> _confirmDeletion() async {
    if (_deletionBusy) {
      return;
    }

    final s = AppStrings.read(context);
    bool? confirmed;
    try {
      confirmed = await showDialog<bool>(
        context: context,
        useRootNavigator: true,
        barrierDismissible: false,
        builder: (dialogContext) => AlertDialog(
          title: Text(s.confirmDeletionTitle),
          content: Text(s.confirmDeletionBody),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext, rootNavigator: true).pop(false),
              child: Text(s.cancel),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext, rootNavigator: true).pop(true),
              child: Text(s.confirmDeletion),
            ),
          ],
        ),
      );
    } catch (error, stack) {
      debugPrint('AniHow deletion confirm dialog failed: $error\n$stack');
      if (mounted) {
        await _showDeletionBlocked(error.toString());
      }
      return;
    }

    if (confirmed == true && mounted) {
      await _requestDeletion();
    }
  }

  Future<void> _requestDeletion() async {
    if (_previewing || _deletionBusy) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final token = ++_deletionLoadToken;
    setState(() {
      _deletionBusy = true;
      _deletionError = null;
    });
    try {
      final request = await context.read<AuthController>().api.requestAccountDeletion();
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      setState(() => _deletion = request);
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(content: Text(AppStrings.read(context).deletionRequested)));
    } on ApiException catch (error) {
      debugPrint('AniHow deletion blocked: ${error.message}');
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      setState(() => _deletionError = error.message);
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(error.message),
            duration: const Duration(seconds: 8),
            backgroundColor: Theme.of(context).colorScheme.error,
          ),
        );
      await _showDeletionBlocked(error.message);
    } catch (error, stack) {
      debugPrint('AniHow deletion failed: $error\n$stack');
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      final message = error.toString();
      setState(() => _deletionError = message);
      messenger
        ..hideCurrentSnackBar()
        ..showSnackBar(
          SnackBar(
            content: Text(message),
            duration: const Duration(seconds: 8),
            backgroundColor: Theme.of(context).colorScheme.error,
          ),
        );
      await _showDeletionBlocked(message);
    } finally {
      if (mounted) {
        setState(() => _deletionBusy = false);
      }
    }
  }

  Future<void> _showDeletionBlocked(String reason) async {
    if (!mounted) {
      return;
    }
    final s = AppStrings.read(context);
    await showDialog<void>(
      context: context,
      useRootNavigator: true,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        icon: Icon(Icons.error_outline, color: Theme.of(dialogContext).colorScheme.error),
        title: Text(s.deletionBlockedTitle),
        content: Text(reason),
        actions: [
          FilledButton(
            onPressed: () => Navigator.of(dialogContext, rootNavigator: true).pop(),
            child: Text(s.ok),
          ),
        ],
      ),
    );
  }

  Future<void> _cancelDeletion() async {
    if (_previewing) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    final token = ++_deletionLoadToken;
    setState(() => _deletionBusy = true);
    try {
      await context.read<AuthController>().api.cancelAccountDeletion();
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      setState(() => _deletion = null);
      messenger.showSnackBar(SnackBar(content: Text(AppStrings.read(context).deletionCancelled)));
    } on ApiException catch (error) {
      if (!mounted || token != _deletionLoadToken) {
        return;
      }
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) {
        setState(() => _deletionBusy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final user = _previewing ? _user : context.watch<AuthController>().user ?? _user;
    final theme = Theme.of(context);
    final pending = _deletion?.isPending == true;

    final muted = theme.colorScheme.onSurface.withValues(alpha: 0.68);
    final danger = theme.colorScheme.error;

    return Scaffold(
      appBar: AppHeader(title: s.myData),
      body: SingleChildScrollView(
        padding: AniHowSpace.screenPadding,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (pending) ...[
              AniHowHintCard(
                icon: Icons.hourglass_empty,
                title: s.deletionRequested,
                tone: AniHowHintTone.brand,
              ),
              const SizedBox(height: AniHowSpace.cardGap),
              OutlinedButton(
                onPressed: _deletionBusy ? null : _cancelDeletion,
                style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(48)),
                child: Text(s.cancelDeletionRequest),
              ),
              const SizedBox(height: AniHowSpace.section),
            ],
            if (user?.isFarmerSeller == true) ...[
              AniHowHintCard(icon: Icons.storefront_outlined, title: s.shopEditedSeparately),
              const SizedBox(height: AniHowSpace.section),
            ],
            AniHowField(
              label: s.fullName,
              child: TextField(
                key: const ValueKey('my-data-name'),
                controller: _name,
                enabled: !_busy,
                textInputAction: TextInputAction.next,
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.phoneOptional,
              child: TextField(
                key: const ValueKey('my-data-phone'),
                controller: _phone,
                enabled: !_busy,
                keyboardType: TextInputType.phone,
                textInputAction: TextInputAction.next,
                decoration: InputDecoration(hintText: s.phoneHint),
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.location,
              child: TextField(
                key: const ValueKey('my-data-location'),
                controller: _location,
                enabled: !_busy,
                textInputAction: TextInputAction.done,
                decoration: InputDecoration(hintText: s.locationHint),
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.email,
              child: Container(
                key: const ValueKey('my-data-email'),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(AniHowSpace.radius),
                  border: Border.all(color: theme.dividerColor),
                ),
                child: Row(
                  children: [
                    Icon(Icons.lock_outline, size: 18, color: muted),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        user?.email ?? '',
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: theme.textTheme.bodyLarge,
                      ),
                    ),
                    if (user?.isVerified == true)
                      StatusPill(label: s.verified, color: AniHowColors.ready),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(s.emailNotEditable, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(
              key: const ValueKey('my-data-save'),
              label: s.saveChanges,
              busy: _busy,
              onPressed: _dirty ? _saveProfile : null,
            ),
            const SizedBox(height: AniHowSpace.section),
            Text(
              s.yourData.toUpperCase(),
              style: theme.textTheme.labelMedium?.copyWith(
                color: muted,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 8),
            Card(
              margin: EdgeInsets.zero,
              child: Padding(
                padding: AniHowSpace.cardPadding,
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            s.downloadMyData,
                            style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 2),
                          Text(s.downloadMyDataBody, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
                        ],
                      ),
                    ),
                    const SizedBox(width: AniHowSpace.cardGap),
                    OutlinedButton(
                      key: const ValueKey('my-data-download'),
                      onPressed: _exportBusy ? null : _download,
                      style: OutlinedButton.styleFrom(minimumSize: const Size(96, 44)),
                      child: _exportBusy
                          ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2))
                          : Text(s.downloadAction),
                    ),
                  ],
                ),
              ),
            ),
            if (_exportPath != null) ...[
              const SizedBox(height: AniHowSpace.cardGap),
              Text('${s.dataSavedTo} $_exportPath', style: theme.textTheme.bodyMedium),
            ],
            const SizedBox(height: AniHowSpace.section),
            Container(
              key: const ValueKey('my-data-danger'),
              padding: AniHowSpace.cardPadding,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(AniHowSpace.radius),
                border: Border.all(color: danger.withValues(alpha: 0.45)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    s.deleteMyAccount,
                    style: theme.textTheme.titleSmall?.copyWith(color: danger, fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(s.deletionKeptHint, style: theme.textTheme.bodySmall?.copyWith(color: muted)),
                  if (_deletionError != null) ...[
                    const SizedBox(height: AniHowSpace.cardGap),
                    AniHowHintCard(
                      icon: Icons.error_outline,
                      title: s.deletionBlockedTitle,
                      body: _deletionError,
                      tone: AniHowHintTone.danger,
                    ),
                  ],
                  if (!pending) ...[
                    if (_deletion?.isRejected == true) ...[
                      const SizedBox(height: AniHowSpace.cardGap),
                      AniHowHintCard(
                        icon: Icons.block,
                        title: s.deletionRejected,
                        body: _deletion?.rejectionNote,
                      ),
                    ],
                    const SizedBox(height: AniHowSpace.cardGap),
                    OutlinedButton(
                      onPressed: _deletionBusy ? null : _confirmDeletion,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: danger,
                        side: BorderSide(color: danger),
                        minimumSize: const Size.fromHeight(48),
                      ),
                      child: _deletionBusy
                          ? const SizedBox(
                              width: 22,
                              height: 22,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : Text(s.requestAccountDeletion),
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
