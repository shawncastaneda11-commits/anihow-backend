import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../models/models.dart';
import '../../services/api_client.dart';
import '../../state/auth_controller.dart';
import '../../theme/anihow_space.dart';
import '../../theme/anihow_theme.dart';
import '../../widgets/produce_card.dart';
import '../../widgets/primary_button.dart';

class PosScreen extends StatefulWidget {
  const PosScreen({super.key});

  @override
  State<PosScreen> createState() => _PosScreenState();
}

class _PosScreenState extends State<PosScreen> {
  late Future<List<ListingItem>> _listingsFuture;
  late Future<List<SaleRecord>> _salesFuture;
  ListingItem? _selected;
  double _quantity = 1;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    final api = context.read<AuthController>().api;
    _listingsFuture = api.farmerListings();
    _salesFuture = api.farmerSales();
    _bindSelected();
  }

  void _reload() {
    final api = context.read<AuthController>().api;
    setState(() {
      _listingsFuture = api.farmerListings();
      _salesFuture = api.farmerSales();
    });
    _bindSelected();
  }

  void _bindSelected() {
    _listingsFuture.then((items) {
      if (!mounted) {
        return;
      }
      if (_selected == null && items.isNotEmpty) {
        setState(() => _selected = items.first);
      } else if (_selected != null) {
        final match = items.where((item) => item.id == _selected!.id);
        setState(() => _selected = match.isEmpty ? (items.isNotEmpty ? items.first : null) : match.first);
      }
    }, onError: (_) {});
  }

  double get _unitPrice => double.tryParse(_selected?.pricePerUnit ?? '') ?? 0;

  double get _total => _unitPrice * _quantity;

  double get _step {
    final unit = _selected?.unit ?? 'kg';
    return const {'piece', 'bundle', 'sack', 'tray'}.contains(unit) ? 1 : 0.5;
  }

  String get _quantityLabel {
    final value = _quantity == _quantity.roundToDouble()
        ? '${_quantity.toInt()}'
        : _quantity.toStringAsFixed(1);
    final unit = _selected?.unit ?? '';
    return unit.isEmpty ? value : '$value $unit';
  }

  Future<void> _pickListing(List<ListingItem> listings) async {
    final chosen = await showModalBottomSheet<ListingItem>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return ListView.separated(
          padding: AniHowSpace.screenPadding,
          itemCount: listings.length,
          separatorBuilder: (_, _) => const SizedBox(height: AniHowSpace.cardGap),
          itemBuilder: (context, index) {
            final listing = listings[index];
            return ProduceCard(
              listing: listing,
              showSeller: false,
              showStock: true,
              onTap: () => Navigator.of(context).pop(listing),
            );
          },
        );
      },
    );
    if (chosen != null && mounted) {
      setState(() {
        _selected = chosen;
        _quantity = _step;
      });
    }
  }

  void _nudge(double delta) {
    setState(() {
      _quantity = (_quantity + delta).clamp(_step, 9999);
    });
  }

  Future<void> _save() async {
    final listing = _selected;
    if (listing == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a listing.')),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final qty = _quantity == _quantity.roundToDouble()
          ? '${_quantity.toInt()}'
          : _quantity.toStringAsFixed(1);
      final sale = await context.read<AuthController>().api.recordSale(
            listingId: listing.id,
            quantity: qty,
          );
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Sale #${sale.id} recorded · ${AniHowMoney.peso(sale.total)}')),
        );
        _quantity = _step;
        _reload();
      }
    } on ApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return FutureBuilder<List<ListingItem>>(
      future: _listingsFuture,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(child: Text('${snapshot.error}'));
        }
        final listings = snapshot.data ?? const [];
        final selected = _selected ?? (listings.isNotEmpty ? listings.first : null);

        return ListView(
          padding: AniHowSpace.screenPadding,
          children: [
            if (selected == null)
              const Card(
                child: Padding(
                  padding: AniHowSpace.cardPadding,
                  child: Text('No listings to sell yet.'),
                ),
              )
            else
              ProduceCard(
                listing: selected,
                showSeller: false,
                onTap: () => _pickListing(listings),
              ),
            const SizedBox(height: AniHowSpace.section),
            Text('Quantity', style: theme.textTheme.labelSmall),
            const SizedBox(height: AniHowSpace.labelGap),
            Card(
              child: Padding(
                padding: AniHowSpace.cardPadding,
                child: Row(
                  children: [
                    _StepButton(icon: Icons.remove, onPressed: () => _nudge(-_step)),
                    Expanded(
                      child: Text(
                        _quantityLabel,
                        textAlign: TextAlign.center,
                        style: theme.textTheme.titleMedium,
                      ),
                    ),
                    _StepButton(icon: Icons.add, onPressed: () => _nudge(_step)),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
            Card(
              color: AniHowColors.brand.withValues(alpha: 0.12),
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Row(
                  children: [
                    Text('TOTAL', style: theme.textTheme.labelLarge),
                    const Spacer(),
                    Text(
                      AniHowMoney.peso(_total),
                      style: theme.textTheme.titleLarge?.copyWith(color: AniHowColors.deepGreen),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: AniHowSpace.section),
            PrimaryButton(label: 'Record sale', busy: _busy, onPressed: _save),
            const SizedBox(height: AniHowSpace.section),
            Text('Today at the stall', style: theme.textTheme.titleMedium),
            const SizedBox(height: AniHowSpace.cardGap),
            FutureBuilder<List<SaleRecord>>(
              future: _salesFuture,
              builder: (context, salesSnapshot) {
                if (salesSnapshot.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.symmetric(vertical: 12),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (salesSnapshot.hasError) {
                  return Text('${salesSnapshot.error}', style: theme.textTheme.bodyMedium);
                }
                final today = (salesSnapshot.data ?? const []).where((sale) => sale.isToday).toList();
                if (today.isEmpty) {
                  return Text('No walk-in sales recorded today.', style: theme.textTheme.bodyMedium);
                }
                return Column(
                  children: [
                    for (var i = 0; i < today.length; i++) ...[
                      if (i > 0) const SizedBox(height: AniHowSpace.cardGap),
                      _SaleRow(sale: today[i]),
                    ],
                  ],
                );
              },
            ),
          ],
        );
      },
    );
  }
}

class _StepButton extends StatelessWidget {
  const _StepButton({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return IconButton.filledTonal(
      onPressed: onPressed,
      icon: Icon(icon),
    );
  }
}

class _SaleRow extends StatelessWidget {
  const _SaleRow({required this.sale});

  final SaleRecord sale;

  @override
  Widget build(BuildContext context) {
    final name = sale.items.isEmpty ? 'Walk-in sale' : sale.items.first.listingName;
    final extra = sale.items.length > 1 ? ' +${sale.items.length - 1}' : '';
    return Card(
      child: Padding(
        padding: AniHowSpace.cardPadding,
        child: Row(
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('$name$extra', style: Theme.of(context).textTheme.titleMedium),
                  Text('#${sale.id}', style: Theme.of(context).textTheme.bodyMedium),
                ],
              ),
            ),
            Text(
              AniHowMoney.peso(sale.total),
              style: Theme.of(context).textTheme.labelLarge,
            ),
          ],
        ),
      ),
    );
  }
}
