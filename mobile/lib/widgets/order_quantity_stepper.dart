import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../l10n/app_strings.dart';
import '../support/order_quantity.dart';

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
  });

  final TextEditingController controller;
  final double min;
  final double step;
  final String unit;
  final double? max;

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

  void _set(double value) {
    widget.controller.text = formatOrderAmount(value);
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

  @override
  Widget build(BuildContext context) {
    final s = AppStrings.of(context);
    final whole = sellsWhole(widget.unit);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            IconButton(
              key: const ValueKey('order-qty-minus'),
              onPressed: _canDecrease ? () => _nudge(-1) : null,
              icon: const Icon(Icons.remove),
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
            ),
            Expanded(
              child: TextField(
                key: const ValueKey('order-qty-field'),
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
              ),
            ),
            IconButton(
              key: const ValueKey('order-qty-plus'),
              onPressed: _canIncrease ? () => _nudge(1) : null,
              icon: const Icon(Icons.add),
              style: IconButton.styleFrom(minimumSize: const Size(48, 48)),
            ),
          ],
        ),
        const SizedBox(height: 4),
        Text(
          key: const ValueKey('order-qty-hint'),
          s.quantityStepHint(
            formatOrderAmount(widget.min),
            formatOrderAmount(widget.step),
            widget.unit,
          ),
        ),
      ],
    );
  }
}
