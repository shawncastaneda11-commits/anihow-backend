import 'package:flutter/material.dart';

import '../../l10n/app_strings.dart';
import '../../theme/anihow_space.dart';
import '../../widgets/form_label.dart';

const freshRejectionReasons = [
  'pests',
  'bruised_damaged',
  'undersized',
  'spoiled',
  'other',
];

const valueAddedRejectionReasons = [
  'defective',
  'packaging_damaged',
  'spoiled',
  'other',
];

const costCategories = [
  'seeds',
  'fertilizer',
  'pesticide',
  'labor',
  'transport',
  'packaging',
  'other',
];

const removalReasons = ['spoiled', 'damaged', 'sold_outside', 'correction'];

String formatGoodQuantity(double value) {
  if (value == value.roundToDouble()) {
    return value.toStringAsFixed(0);
  }
  return value.toStringAsFixed(2);
}

double goodQuantity(String harvested, String rejected) {
  final total = double.tryParse(harvested.trim()) ?? 0;
  final lost = double.tryParse(rejected.trim()) ?? 0;
  final good = total - lost;
  return good < 0 ? 0 : good;
}

double breakdownTotal(Map<String, TextEditingController> costs) {
  var sum = 0.0;
  for (final controller in costs.values) {
    sum += double.tryParse(controller.text.trim()) ?? 0;
  }
  return sum;
}

bool breakdownHasAmount(Map<String, TextEditingController> costs) {
  for (final controller in costs.values) {
    if (controller.text.trim().isNotEmpty) {
      return true;
    }
  }
  return false;
}

class HarvestFields extends StatelessWidget {
  const HarvestFields({
    super.key,
    required this.valueAdded,
    required this.unit,
    required this.harvestedOnLabel,
    required this.harvested,
    required this.rejected,
    required this.reason,
    required this.note,
    required this.cost,
    required this.costs,
    required this.breakdownOpen,
    required this.onChanged,
    required this.onPickDate,
    required this.onReason,
    required this.onToggleBreakdown,
  });

  final bool valueAdded;
  final String unit;
  final String harvestedOnLabel;
  final TextEditingController harvested;
  final TextEditingController rejected;
  final String? reason;
  final TextEditingController note;
  final TextEditingController cost;
  final Map<String, TextEditingController> costs;
  final bool breakdownOpen;
  final VoidCallback onChanged;
  final VoidCallback onPickDate;
  final ValueChanged<String?> onReason;
  final VoidCallback onToggleBreakdown;

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final reasons = valueAdded
        ? valueAddedRejectionReasons
        : freshRejectionReasons;
    final rejectedAmount = double.tryParse(rejected.text.trim()) ?? 0;
    final good = goodQuantity(harvested.text, rejected.text);
    final unitLabel = unit.isEmpty ? '' : unit;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(s.harvestSection, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: AniHowSpace.fieldGap),
        AniHowField(
          label: valueAdded ? s.dateMade : s.harvestDate,
          child: OutlinedButton(
            key: ValueKey(valueAdded ? 'date-made' : 'harvest-date'),
            onPressed: onPickDate,
            child: Align(
              alignment: Alignment.centerLeft,
              child: Text(
                harvestedOnLabel.isEmpty ? s.dateNotSet : harvestedOnLabel,
              ),
            ),
          ),
        ),
        const SizedBox(height: AniHowSpace.fieldGap),
        AniHowField(
          label: valueAdded ? s.quantityMade : s.harvestedQuantity,
          child: TextField(
            key: const ValueKey('harvest-quantity'),
            controller: harvested,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => onChanged(),
          ),
        ),
        const SizedBox(height: AniHowSpace.fieldGap),
        AniHowField(
          label: valueAdded ? s.defectiveQuantity : s.rejectedQuantity,
          child: TextField(
            key: const ValueKey('rejected-quantity'),
            controller: rejected,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            onChanged: (_) => onChanged(),
          ),
        ),
        if (rejectedAmount > 0) ...[
          const SizedBox(height: AniHowSpace.fieldGap),
          AniHowField(
            label: s.rejectionReason,
            child: DropdownButtonFormField<String>(
              key: const ValueKey('rejection-reason'),
              isExpanded: true,
              initialValue: reasons.contains(reason) ? reason : null,
              items: [
                for (final value in reasons)
                  DropdownMenuItem(
                    value: value,
                    child: Text(s.rejectionReasonLabel(value)),
                  ),
              ],
              onChanged: onReason,
            ),
          ),
          if (reason == 'other') ...[
            const SizedBox(height: AniHowSpace.fieldGap),
            AniHowField(
              label: s.rejectionNote,
              child: TextField(
                key: const ValueKey('rejection-note'),
                controller: note,
                onChanged: (_) => onChanged(),
              ),
            ),
          ],
        ],
        const SizedBox(height: AniHowSpace.cardGap),
        Text(
          s.goodToSell(formatGoodQuantity(good), unitLabel).trim(),
          key: const ValueKey('good-to-sell'),
          style: Theme.of(context).textTheme.titleSmall,
        ),
        const SizedBox(height: AniHowSpace.fieldGap),
        AniHowField(
          label: s.productionCost,
          child: TextField(
            key: const ValueKey('production-cost'),
            controller: cost,
            readOnly: breakdownOpen,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: const InputDecoration(prefixText: '₱ '),
            onChanged: (_) => onChanged(),
          ),
        ),
        Text(s.costHint, style: Theme.of(context).textTheme.bodySmall),
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton(
            key: const ValueKey('break-down-costs'),
            onPressed: onToggleBreakdown,
            child: Text(s.breakDownCosts),
          ),
        ),
        if (breakdownOpen)
          for (final category in costCategories) ...[
            AniHowField(
              label: s.costCategory(category),
              child: TextField(
                key: ValueKey('cost-$category'),
                controller: costs[category],
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: const InputDecoration(prefixText: '₱ '),
                onChanged: (_) => onChanged(),
              ),
            ),
            const SizedBox(height: AniHowSpace.fieldGap),
          ],
      ],
    );
  }
}
