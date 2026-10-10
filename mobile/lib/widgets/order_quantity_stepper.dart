import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../l10n/app_strings.dart';
import '../support/order_quantity.dart';
import '../theme/anihow_theme.dart';

/// Minus and plus move by the seller's step, starting from the minimum
/// and stopping at what is still available.
class OrderQuantityStepper extends StatefulWidget {
  const OrderQuantityStepper({
    super.key,
    required this.controller,
    required this.min,
    required this.step,
    required this.unit,
    this.max,
    this.lineId,
    this.onChanged,
    this.onValueTap,
    this.pill = false,
  });

  final TextEditingController controller;
  final double min;
  final double step;
  final String unit;
  final double? max;
  final int? lineId;
  final ValueChanged<String>? onChanged;
  final VoidCallback? onValueTap;

  /// Compact cart stepper. Hides the gram chip and the rule line.
  final bool pill;

  @override
  State<OrderQuantityStepper> createState() => _OrderQuantityStepperState();
}

class _OrderQuantityStepperState extends State<OrderQuantityStepper> {
  @override
  void initState() {
    super.initState();
    widget.controller.addListener(_rebuild);
  }

  @override
  void didUpdateWidget(OrderQuantityStepper oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      oldWidget.controller.removeListener(_rebuild);
      widget.controller.addListener(_rebuild);
    }
  }

  @override
  void dispose() {
    widget.controller.removeListener(_rebuild);
    super.dispose();
  }

  void _rebuild() {
    if (mounted) {
      setState(() {});
    }
  }

  double get _current =>
      double.tryParse(widget.controller.text.trim()) ?? widget.min;

  String? get _liveSmallUnit {
    final parsed = double.tryParse(widget.controller.text.trim());
    if (parsed == null) {
      return null;
    }
    return orderQuantitySmallUnit(parsed, widget.unit);
  }

  void _set(double value) {
    final text = formatOrderAmount(value);
    if (widget.controller.text == text) {
      return;
    }
    widget.controller.text = text;
    widget.onChanged?.call(text);
  }

  void _nudge(int direction) {
    final next = snapOrderQuantity(
      value: _current + (widget.step * direction),
      min: widget.min,
      step: widget.step,
      max: widget.max,
    );
    _set(next);
  }

  void _snap() {
    final parsed = double.tryParse(widget.controller.text.trim());
    if (parsed == null) {
      _set(widget.min);
      return;
    }
    _set(
      snapOrderQuantity(
        value: parsed,
        min: widget.min,
        step: widget.step,
        max: widget.max,
      ),
    );
  }

  bool get _canDecrease {
    final next = orderHundredths(_current) - orderHundredths(widget.step);
    return next >= orderHundredths(widget.min);
  }

  bool get _canIncrease {
    final next = orderHundredths(_current) + orderHundredths(widget.step);
    final ceiling = widget.max;
    if (ceiling == null) {
      return true;
    }
    return next <= orderHundredths(ceiling);
  }

  Widget _valueField() {
    final whole = sellsWhole(widget.unit);
    final valueKey = ValueKey(
      'order-qty-field${widget.lineId == null ? '' : '-${widget.lineId}'}',
    );
    if (widget.onValueTap != null) {
      return TextButton(
        key: valueKey,
        onPressed: widget.onValueTap,
        style: TextButton.styleFrom(minimumSize: const Size(48, 48)),
        child: Text(formatOrderAmount(_current)),
      );
    }
    return TextField(
      key: valueKey,
      controller: widget.controller,
      textAlign: TextAlign.center,
      keyboardType: whole
          ? TextInputType.number
          : const TextInputType.numberWithOptions(decimal: true),
      inputFormatters: whole
          ? [FilteringTextInputFormatter.digitsOnly]
          : const [],
      decoration: const InputDecoration(isDense: true),
      onTapOutside: (_) => _snap(),
      onEditingComplete: _snap,
      onSubmitted: (_) => _snap(),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.pill) {
      return _pillStepper(context);
    }
    final s = AppStrings.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            IconButton(
              key: ValueKey('order-qty-minus${widget.lineId == null ? '' : '-${widget.lineId}'}'),
              onPressed: _canDecrease ? () => _nudge(-1) : null,
              icon: const Icon(Icons.remove),
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
            ),
            Expanded(child: _valueField()),
            IconButton(
              key: ValueKey('order-qty-plus${widget.lineId == null ? '' : '-${widget.lineId}'}'),
              onPressed: _canIncrease ? () => _nudge(1) : null,
              icon: const Icon(Icons.add),
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
            ),
          ],
        ),
        const SizedBox(height: 4),
        if (_liveSmallUnit != null) ...[
          Align(
            alignment: Alignment.center,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: AniHowColors.inStockBg,
                borderRadius: BorderRadius.circular(999),
              ),
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                child: Text(
                  key: const ValueKey('order-qty-equivalent'),
                  s.quantitySmallUnitHint(_liveSmallUnit!),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    color: AniHowColors.inStock,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 4),
        ],
        Text(
          key: const ValueKey('order-qty-hint'),
          s.quantityStepHint(
            formatOrderAmount(widget.min),
            formatOrderAmount(widget.step),
            widget.unit,
            minEquivalent: orderQuantitySmallUnit(widget.min, widget.unit),
            stepEquivalent: orderQuantitySmallUnit(widget.step, widget.unit),
          ),
        ),
      ],
    );
  }

  Widget _pillStepper(BuildContext context) {
    final theme = Theme.of(context);
    final fill = theme.brightness == Brightness.dark
        ? const Color(0xFF2A3330)
        : const Color(0xFFF1EFE8);
    final amount = formatOrderAmount(_current);
    final unit = widget.unit.trim();
    final label = unit.isEmpty ? amount : '$amount $unit';
    final suffix = widget.lineId == null ? '' : '-${widget.lineId}';
    return DecoratedBox(
      decoration: BoxDecoration(
        color: fill,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          IconButton(
            key: ValueKey('order-qty-minus$suffix'),
            onPressed: _canDecrease ? () => _nudge(-1) : null,
            icon: const Icon(Icons.remove, size: 18),
            style: IconButton.styleFrom(
              minimumSize: const Size(40, 40),
              fixedSize: const Size(40, 40),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              padding: EdgeInsets.zero,
            ),
          ),
          TextButton(
            key: ValueKey('order-qty-field$suffix'),
            onPressed: widget.onValueTap,
            style: TextButton.styleFrom(
              minimumSize: const Size(40, 40),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              padding: const EdgeInsets.symmetric(horizontal: 4),
              foregroundColor: theme.colorScheme.onSurface,
            ),
            child: Text(label),
          ),
          IconButton(
            key: ValueKey('order-qty-plus$suffix'),
            onPressed: _canIncrease ? () => _nudge(1) : null,
            icon: const Icon(Icons.add, size: 18),
            style: IconButton.styleFrom(
              minimumSize: const Size(40, 40),
              fixedSize: const Size(40, 40),
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              padding: EdgeInsets.zero,
            ),
          ),
        ],
      ),
    );
  }
}
