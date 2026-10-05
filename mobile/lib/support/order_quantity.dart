/// Seller minimum and step, matched in hundredths the same way as the API.
int orderHundredths(double amount) => (amount * 100).round();

String formatOrderAmount(double amount) {
  final hundredths = orderHundredths(amount);
  if (hundredths % 100 == 0) {
    return (hundredths ~/ 100).toString();
  }
  if (hundredths % 10 == 0) {
    return (hundredths / 100).toStringAsFixed(1);
  }
  return (hundredths / 100).toStringAsFixed(2);
}

bool sellsWhole(String? unit) {
  const whole = {'piece', 'dozen', 'bundle', 'sack', 'tray', 'pack', 'bottle'};
  return whole.contains(unit);
}

String wholeSaleName(String? unit) {
  return switch (unit) {
    'piece' => 'Pieces',
    'dozen' => 'Dozens',
    'bundle' => 'Bundles',
    'sack' => 'Sacks',
    'tray' => 'Trays',
    'pack' => 'Packs',
    'bottle' => 'Bottles',
    _ => unit ?? '',
  };
}

bool allowsOrderQuantity({
  required double quantity,
  required double min,
  required double step,
}) {
  final amount = orderHundredths(quantity);
  final minimum = orderHundredths(min);
  final increment = orderHundredths(step);
  if (increment < 1 || amount < minimum) {
    return false;
  }
  return (amount - minimum) % increment == 0;
}

double snapOrderQuantity({
  required double value,
  required double min,
  required double step,
  double? max,
}) {
  final increment = orderHundredths(step);
  final minimum = orderHundredths(min);
  if (increment < 1) {
    return min;
  }
  var amount = orderHundredths(value);
  if (amount < minimum) {
    amount = minimum;
  }
  final steps = ((amount - minimum) / increment).round();
  var snapped = minimum + steps * increment;
  if (max != null) {
    final ceiling = orderHundredths(max);
    while (snapped > ceiling && snapped - increment >= minimum) {
      snapped -= increment;
    }
    if (snapped > ceiling) {
      snapped = minimum <= ceiling ? minimum : ceiling;
    }
  }
  return snapped / 100;
}

List<double> stepChoices(String? unit) {
  if (unit == 'kg' || unit == 'liter') {
    return const [0.25, 0.5, 1];
  }
  if (unit == 'g' || unit == 'ml') {
    return const [50, 100, 250, 500];
  }
  return const [1];
}

String previewAmounts(double min, double step) {
  return [
    formatOrderAmount(min),
    formatOrderAmount(min + step),
    formatOrderAmount(min + (step * 2)),
  ].join(', ');
}

bool atMostTwoDecimals(String text) {
  final parts = text.trim().split('.');
  return parts.length < 2 || parts[1].length <= 2;
}

/// Null when the seller's minimum and step are allowed for [unit].
String? orderRuleCode({
  required String? unit,
  required String minText,
  required String stepText,
}) {
  if (!atMostTwoDecimals(minText) || !atMostTwoDecimals(stepText)) {
    return 'decimals';
  }
  final min = double.tryParse(minText.trim());
  final step = double.tryParse(stepText.trim());
  if (min == null || step == null || min <= 0 || step <= 0) {
    return 'positive';
  }
  if (orderHundredths(min) > 9999999) {
    return 'max';
  }
  if (sellsWhole(unit) && orderHundredths(step) % 100 != 0) {
    return 'whole-step';
  }
  if (sellsWhole(unit) && orderHundredths(min) % 100 != 0) {
    return 'whole-min';
  }
  if (orderHundredths(min) < orderHundredths(step)) {
    return 'min-step';
  }
  return null;
}
