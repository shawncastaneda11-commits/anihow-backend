import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../l10n/app_strings.dart';
import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../support/phone_link.dart';
import '../../support/seller_mailto.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/primary_button.dart';

enum SellerPath { farmer, farm }

class SellerInfoScreen extends StatefulWidget {
  const SellerInfoScreen({super.key});

  @override
  State<SellerInfoScreen> createState() => _SellerInfoScreenState();
}

class _SellerInfoScreenState extends State<SellerInfoScreen> {
  SellerPath _path = SellerPath.farmer;
  SellerHelp? _help;
  bool _loading = true;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _failed = false;
    });
    try {
      final help = await context.read<AuthController>().api.sellerHelp();
      if (!mounted) {
        return;
      }
      setState(() {
        _help = help;
        _loading = false;
      });
    } on ApiException {
      if (!mounted) {
        return;
      }
      setState(() {
        _help = null;
        _loading = false;
        _failed = true;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }
      setState(() {
        _help = null;
        _loading = false;
        _failed = true;
      });
    }
  }

  String get _subject =>
      _path == SellerPath.farmer ? sellerAccountSubject : farmAccountSubject;

  String get _body =>
      _path == SellerPath.farmer ? sellerAccountBody : farmAccountBody;

  Future<void> _copy(String email, String message) async {
    await Clipboard.setData(ClipboardData(text: email));
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _email(String email) async {
    final s = AppStrings.read(context);
    final mailto = sellerMailto(email: email, subject: _subject, body: _body);
    try {
      final opened = await launchUrl(
        Uri.parse(mailto),
        mode: LaunchMode.externalApplication,
      );
      if (!opened) {
        await _copy(email, s.noEmailApp);
      }
    } catch (_) {
      await _copy(email, s.noEmailApp);
    }
  }

  Future<void> _dial(String number) async {
    final digits = dialablePhone(number);
    if (digits == null) {
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
    final theme = Theme.of(context);
    final help = _help;
    final ready = help != null && help.email.isNotEmpty && !_failed;
    final office = ready ? help.office : s.sellerHelpAdmin;
    final days = help?.temporaryPasswordDays;
    final steps = _path == SellerPath.farmer
        ? [
            s.sellerStepFarmerFarm,
            s.sellerStepFarmerEmail(office),
            if (days != null) s.sellerStepFarmerPassword(days),
          ]
        : [
            s.sellerStepFarmEmail(office),
            s.sellerStepFarmCms,
            if (days != null) s.sellerStepFarmPassword(days),
          ];
    final muted = theme.textTheme.bodyMedium?.copyWith(
      color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
    );

    return Scaffold(
      appBar: AppBar(title: Text(s.wantToBeASeller)),
      body: ListView(
        padding: AniHowSpace.screenPadding,
        children: [
          Text(s.sellerInfoIntro, style: theme.textTheme.bodyLarge),
          const SizedBox(height: AniHowSpace.section),
          SegmentedButton<SellerPath>(
            expandedInsets: EdgeInsets.zero,
            showSelectedIcon: false,
            segments: [
              ButtonSegment(
                value: SellerPath.farmer,
                label: Text(
                  s.imAFarmer,
                  key: const Key('seller-path-farmer'),
                  textAlign: TextAlign.center,
                ),
              ),
              ButtonSegment(
                value: SellerPath.farm,
                label: Text(
                  s.myFarmWantsToJoin,
                  key: const Key('seller-path-farm'),
                  textAlign: TextAlign.center,
                ),
              ),
            ],
            selected: {_path},
            onSelectionChanged: (value) => setState(() => _path = value.first),
          ),
          const SizedBox(height: AniHowSpace.section),
          Card(
            child: Padding(
              padding: AniHowSpace.cardPadding,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  for (var i = 0; i < steps.length; i++) ...[
                    if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
                    _NumberedStep(number: i + 1, text: steps[i]),
                  ],
                  const Divider(height: 28),
                  PrimaryButton(
                    key: const Key('seller-email'),
                    label: s.emailOffice(office),
                    onPressed: ready ? () => _email(help.email) : null,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    children: [
                      if (ready)
                        Expanded(child: SelectableText(help.email, style: muted)),
                      TextButton(
                        key: const Key('seller-copy'),
                        onPressed: ready ? () => _copy(help.email, s.emailCopied) : null,
                        child: Text(s.copyEmail),
                      ),
                    ],
                  ),
                  if (ready && help.phone != null)
                    Align(
                      alignment: Alignment.centerLeft,
                      child: TextButton(
                        onPressed: () => _dial(help.phone!),
                        child: Text(s.callOffice(office)),
                      ),
                    ),
                  Text(s.emailAppHint(_subject), style: muted),
                ],
              ),
            ),
          ),
          if (_failed) ...[
            const SizedBox(height: AniHowSpace.cardGap),
            TextButton(
              onPressed: _load,
              child: Text(s.sellerHelpLoadError),
            ),
          ],
          const SizedBox(height: AniHowSpace.section),
          Text(s.partnerFarms, style: theme.textTheme.titleMedium),
          const SizedBox(height: 2),
          Text(s.farmsAlreadyOnAnihow, style: muted),
          const SizedBox(height: AniHowSpace.cardGap),
          if (_loading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 24),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (help == null || help.farms.isEmpty)
            Text(s.noPartnerFarms, style: muted)
          else
            Card(
              child: Column(
                children: [
                  for (var i = 0; i < help.farms.length; i++) ...[
                    if (i > 0) const Divider(height: 1),
                    _FarmRow(farm: help.farms[i], onCall: _dial),
                  ],
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _NumberedStep extends StatelessWidget {
  const _NumberedStep({required this.number, required this.text});

  final int number;
  final String text;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 28,
          height: 28,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: theme.colorScheme.primary.withValues(alpha: 0.12),
            shape: BoxShape.circle,
          ),
          child: Text(
            '$number',
            style: theme.textTheme.labelLarge?.copyWith(
              color: theme.colorScheme.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(child: Text(text)),
      ],
    );
  }
}

class _FarmRow extends StatelessWidget {
  const _FarmRow({required this.farm, required this.onCall});

  final SellerHelpFarm farm;
  final Future<void> Function(String number) onCall;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final meta = [
      farm.municipality,
      farm.contactPerson,
    ].whereType<String>().where((part) => part.isNotEmpty).join(' · ');
    final digits = dialablePhone(farm.contactNumber);

    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 56),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        child: Row(
          children: [
            Icon(Icons.eco_outlined, color: theme.colorScheme.primary),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(farm.name, style: theme.textTheme.titleMedium),
                  if (meta.isNotEmpty)
                    Text(
                      meta,
                      style: theme.textTheme.bodyMedium?.copyWith(
                        color: theme.colorScheme.onSurface.withValues(alpha: 0.68),
                      ),
                    ),
                ],
              ),
            ),
            if (digits != null)
              SizedBox(
                width: 44,
                height: 44,
                child: IconButton(
                  key: Key('seller-farm-call-${farm.id}'),
                  padding: EdgeInsets.zero,
                  tooltip: farm.contactNumber,
                  onPressed: () => onCall(farm.contactNumber!),
                  icon: const Icon(Icons.phone_outlined),
                ),
              ),
          ],
        ),
      ),
    );
  }
}
