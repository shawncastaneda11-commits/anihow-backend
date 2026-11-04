import 'package:anihow/models/models.dart';
import 'package:anihow/support/walk_in_quote.dart';
import 'package:flutter_test/flutter_test.dart';

/// The walk-in preview must show what the server records. It uses the
/// listing's price_guard, which the server sends already in the listing's
/// own unit, and judges the tawad per unit the same way the server does.
ListingItem _listing({
  required String unit,
  required num price,
  required Map<String, dynamic> tawad,
  Map<String, dynamic>? guard,
  num minOrder = 1,
}) {
  return ListingItem.fromJson({
    'id': 7,
    'title': 'Test listing',
    'unit': unit,
    'price_per_unit': price,
    'quantity_available': 100,
    'min_order_quantity': minOrder,
    'order_step': 1,
    'tawad': {'id': 3, 'is_active': true, ...tawad},
    'price_guard': ?guard,
  });
}

Map<String, dynamic> _flat(num amount) => {
  'type': 'flat',
  'discount_amount': amount,
};

Map<String, dynamic> _bulk(num amount, num minimum) => {
  'type': 'min_quantity',
  'discount_amount': amount,
  'min_quantity': minimum,
};

void main() {
  group('piece and dozen', () {
    test('a piece listing on a dozen-guarded crop applies its tawad', () {
      // Floor PHP 90 and max PHP 12 per dozen are PHP 7.50 and PHP 1 per piece.
      final listing = _listing(
        unit: 'piece',
        price: 10,
        tawad: _flat(1),
        guard: {
          'unit': 'piece',
          'floor_per_unit': 7.5,
          'max_tawad_per_unit': 1,
          'unit_allowed': true,
        },
      );

      final quote = WalkInQuote.forListing(listing, '1')!;

      expect(quote.tawad, 1);
      expect(quote.total, 9);
    });

    test('a dozen listing on a piece-guarded crop applies its tawad', () {
      // Floor PHP 8 and max PHP 2 per piece are PHP 96 and PHP 24 per dozen.
      final listing = _listing(
        unit: 'dozen',
        price: 110,
        tawad: _flat(12),
        guard: {
          'unit': 'dozen',
          'floor_per_unit': 96,
          'max_tawad_per_unit': 24,
          'unit_allowed': true,
        },
      );

      final quote = WalkInQuote.forListing(listing, '1')!;

      expect(quote.total, 98);
    });
  });

  group('bulk tawad', () {
    final guard = {
      'unit': 'bundle',
      'floor_per_unit': 15,
      'max_tawad_per_unit': 5,
      'unit_allowed': true,
    };

    test('PHP 15 off 3 bundles is PHP 5 per bundle and applies', () {
      final listing = _listing(
        unit: 'bundle',
        price: 20,
        tawad: _bulk(15, 3),
        guard: guard,
      );

      final quote = WalkInQuote.forListing(listing, '3')!;

      expect(quote.tawad, 15);
      expect(quote.total, 45);
    });

    test('PHP 18 off 3 bundles would go below the floor and is left off', () {
      final listing = _listing(
        unit: 'bundle',
        price: 20,
        tawad: _bulk(18, 3),
        guard: guard,
      );

      final quote = WalkInQuote.forListing(listing, '3')!;

      expect(quote.tawad, 0);
      expect(quote.total, 60);
    });
  });

  test('with no maximum, only the floor limits the tawad', () {
    final listing = _listing(
      unit: 'kg',
      price: 60,
      tawad: _flat(15),
      guard: {
        'unit': 'kg',
        'floor_per_unit': 40,
        'max_tawad_per_unit': null,
        'unit_allowed': true,
      },
    );

    expect(listing.priceGuard!.hasMaxTawad, isFalse);
    expect(WalkInQuote.forListing(listing, '1')!.total, 45);
  });

  test('a flat tawad is spread over the listing minimum order', () {
    // PHP 2 off 500 g is PHP 0.004 per g, within PHP 0.005 per g.
    final listing = _listing(
      unit: 'g',
      price: 0.05,
      tawad: _flat(2),
      minOrder: 500,
      guard: {
        'unit': 'g',
        'floor_per_unit': 0.04,
        'max_tawad_per_unit': 0.005,
        'unit_allowed': true,
      },
    );

    expect(WalkInQuote.forListing(listing, '500')!.tawad, 2);
  });

  test('an older server with no price guard keeps the crop values', () {
    final listing = _listing(unit: 'kg', price: 30, tawad: _flat(5));

    expect(listing.priceGuard, isNull);
    expect(WalkInQuote.forListing(listing, '1')!.tawad, 5);
  });
}
