import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/app_header.dart';
import '../../widgets/form_label.dart';
import '../../widgets/hint_card.dart';
import '../../widgets/primary_button.dart';

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
  }

  void _fill(UserAccount user) {
    _name.text = user.name;
    _phone.text = user.phone ?? '';
    _location.text = user.location ?? '';
  }

  Future<void> _loadDeletion() async {
    if (_previewing) {
      return;
    }
    try {
      final request = await context.read<AuthController>().api.deletionRequest();
      if (!mounted) {
        return;
      }
      setState(() => _deletion = request);
    } on ApiException catch (error) {
      if (!mounted) {
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
      setState(() => _user = auth.user ?? user);
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
    final s = AppStrings.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(s.confirmDeletionTitle),
        content: Text(s.confirmDeletionBody),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(s.cancel),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(s.confirmDeletion),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await _requestDeletion();
    }
  }

  Future<void> _requestDeletion() async {
    if (_previewing) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() {
      _deletionBusy = true;
      _deletionError = null;
    });
    try {
      final request = await context.read<AuthController>().api.requestAccountDeletion();
      if (!mounted) {
        return;
      }
      setState(() => _deletion = request);
      messenger.showSnackBar(SnackBar(content: Text(AppStrings.read(context).deletionRequested)));
    } on ApiException catch (error) {
      if (!mounted) {
        return;
      }
      setState(() => _deletionError = error.message);
    } finally {
      if (mounted) {
        setState(() => _deletionBusy = false);
      }
    }
  }

  Future<void> _cancelDeletion() async {
    if (_previewing) {
      return;
    }
    final messenger = ScaffoldMessenger.of(context);
    setState(() => _deletionBusy = true);
    try {
      await context.read<AuthController>().api.cancelAccountDeletion();
      if (!mounted) {
        return;
      }
      setState(() => _deletion = null);
      messenger.showSnackBar(SnackBar(content: Text(AppStrings.read(context).deletionCancelled)));
    } on ApiException catch (error) {
      if (!mounted) {
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

    return Scaffold(
      appBar: AppHeader(title: s.myData),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          AniHowHintCard(
            icon: Icons.lock_outline,
            title: s.emailNotEditable,
          ),
          if (user?.isFarmerSeller == true) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            AniHowHintCard(
              icon: Icons.storefront_outlined,
              title: s.shopEditedSeparately,
            ),
          ],
          if (pending) ...[
            const SizedBox(height: AniHowSpace.cardGap),
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
          ],
          if (_deletionError != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text(
              _deletionError!,
              style: theme.textTheme.bodyMedium?.copyWith(color: theme.colorScheme.error),
            ),
          ],
          const SizedBox(height: AniHowSpace.section),
          AniHowField(
            label: s.fullName,
            child: TextField(
              controller: _name,
              enabled: !_busy,
              textInputAction: TextInputAction.next,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.phoneOptional,
            child: TextField(
              controller: _phone,
              enabled: !_busy,
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.next,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.location,
            child: TextField(
              controller: _location,
              enabled: !_busy,
              textInputAction: TextInputAction.done,
            ),
          ),
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.email,
            child: InputDecorator(
              decoration: const InputDecoration(enabled: false),
              child: Text(user?.email ?? '', style: theme.textTheme.bodyLarge),
            ),
          ),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(label: s.saveProfile, busy: _busy, onPressed: _saveProfile),
          const SizedBox(height: AniHowSpace.section),
          PrimaryButton(
            label: s.downloadMyData,
            busy: _exportBusy,
            onPressed: _download,
          ),
          if (_exportPath != null) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            Text('${s.dataSavedTo} $_exportPath', style: theme.textTheme.bodyMedium),
          ],
          const SizedBox(height: AniHowSpace.section),
          AniHowHintCard(
            icon: Icons.info_outline,
            title: s.requestAccountDeletion,
            body: s.deletionKeptHint,
          ),
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
                foregroundColor: theme.colorScheme.error,
                side: BorderSide(color: theme.colorScheme.error),
                minimumSize: const Size.fromHeight(48),
              ),
              child: Text(s.requestAccountDeletion),
            ),
          ],
        ],
      ),
    );
  }
}
