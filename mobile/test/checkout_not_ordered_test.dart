import 'package:anihow/models/models.dart';
import 'package:flutter_test/flutter_test.dart';

CartLine _line(int listingId, String title) => CartLine(
      id: listingId,
      quantity: '1',
      listedPrice: '40',
      lineSubtotal: '40',
      tawadAmount: '0',
      lineTotal: '40',
      listing: ListingItem(
        id: listingId,
        title: title,
        pricePerUnit: '40',
        quantityAvailable: '10',
        unit: 'kg',
      ),
    );

void main() {
  test('an item checkout left out is named, ordered items are not', () {
    final cart = CartSnapshot(items: [_line(1, 'Pechay'), _line(2, 'Talong'), _line(3, 'Kamatis')]);
    final orders = [
      OrderRecord(
        id: 9,
        status: 'placed',
        total: '80',
        items: const [
          OrderItemRow(listingName: 'Talong', quantity: '1', listedPrice: '40', lineSubtotal: '40', listingId: 2),
          OrderItemRow(listingName: 'Kamatis', quantity: '1', listedPrice: '40', lineSubtotal: '40', listingId: 3),
        ],
      ),
    ];

    expect(itemsNotOrdered(cart, orders), ['Pechay']);
  });

  test('nothing is named when every item was ordered', () {
    final cart = CartSnapshot(items: [_line(1, 'Pechay')]);
    final orders = [
      OrderRecord(
        id: 9,
        status: 'placed',
        total: '40',
        items: const [
          OrderItemRow(listingName: 'Pechay', quantity: '1', listedPrice: '40', lineSubtotal: '40', listingId: 1),
        ],
      ),
    ];

    expect(itemsNotOrdered(cart, orders), isEmpty);
  });

  test('the order item reads its listing id', () {
    final row = OrderItemRow.fromJson({'listing_name': 'Pechay', 'quantity': '1', 'listed_price': '40', 'line_subtotal': '40', 'listing_id': 5});
    expect(row.listingId, 5);
  });
}
