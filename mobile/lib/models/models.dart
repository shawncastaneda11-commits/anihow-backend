import '../config/api_config.dart';
import '../support/crop_language.dart';
import '../theme/anihow_space.dart';

class UserAccount {
  const UserAccount({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    this.permissions = const [],
    this.phone,
    this.shopName,
    this.emailVerifiedAt,
    this.farmId,
    this.farmName,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;
  final String? phone;
  final String? shopName;
  final String? emailVerifiedAt;
  final int? farmId;
  final String? farmName;

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    final farmJson = json['farm'];
    final farmMap = farmJson is Map ? Map<String, dynamic>.from(farmJson) : null;
    return UserAccount(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roles: ((json['roles'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(),
      permissions: ((json['permissions'] as List?) ?? const [])
          .map((permission) => permission.toString())
          .toList(),
      phone: json['phone'] as String?,
      shopName: json['shop_name'] as String?,
      emailVerifiedAt: json['email_verified_at'] as String?,
      farmId: ListingItem._asCount(farmMap?['id']),
      farmName: farmMap?['name'] as String?,
    );
  }

  bool get isBuyer => roles.contains('buyer');
  bool get isFarmerSeller => roles.contains('farmer_seller');
  bool get canRecordWalkInSales =>
      isFarmerSeller && permissions.contains('record_walk_in_sales');
  bool get isSuperAdmin => roles.contains('super_admin');
  bool get isVerified => emailVerifiedAt != null && emailVerifiedAt!.isNotEmpty;
  String get roleLabel =>
      roles.isEmpty ? 'unknown' : roles.first.replaceAll('_', ' ');
}

class CategoryItem {
  const CategoryItem({
    required this.id,
    required this.name,
    this.slug,
    this.labelEn,
    this.labelFil,
    this.unit,
    this.unitLabel,
    this.floorPrice,
    this.maxDiscount,
    this.effectiveFloorPrice,
    this.effectiveMaxDiscount,
  });

  final int id;
  final String name;
  final String? slug;
  final String? labelEn;
  final String? labelFil;
  final String? unit;
  final String? unitLabel;
  final String? floorPrice;
  final String? maxDiscount;
  final String? effectiveFloorPrice;
  final String? effectiveMaxDiscount;

  factory CategoryItem.fromJson(Map<String, dynamic> json) {
    return CategoryItem(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      labelEn: json['label_en'] as String?,
      labelFil: json['label_fil'] as String?,
      unit: json['unit_of_measure'] as String?,
      unitLabel: json['unit_label'] as String?,
      floorPrice: json['floor_price']?.toString(),
      maxDiscount: json['max_discount']?.toString(),
      effectiveFloorPrice: json['effective_floor_price']?.toString(),
      effectiveMaxDiscount: json['effective_max_discount']?.toString(),
    );
  }

  /// Filipino crop label, then English, then taxonomy `name`.
  String get displayLabel => labelFor(CropLanguage.filipino);

  /// English or Filipino crop label from Settings → Language.
  String labelFor(CropLanguage language) {
    final fil = labelFil?.trim();
    final en = labelEn?.trim();
    final hasFil = fil != null && fil.isNotEmpty;
    final hasEn = en != null && en.isNotEmpty;

    switch (language) {
      case CropLanguage.english:
        if (hasEn) {
          return en;
        }
        return hasFil ? fil : name;
      case CropLanguage.filipino:
        if (hasFil) {
          return fil;
        }
        return hasEn ? en : name;
    }
  }

  /// The floor this farmer-seller is held to. Falls back to the system
  /// value when talking to an API that does not send the farm's.
  String? get sellerFloorPrice => effectiveFloorPrice ?? floorPrice;

  /// The largest tawad this farmer-seller may set. Same fallback.
  String? get sellerMaxDiscount => effectiveMaxDiscount ?? maxDiscount;
}

class TawadRule {
  const TawadRule({
    required this.id,
    required this.type,
    required this.discountAmount,
    this.typeLabel,
    this.minQuantity,
    this.isActive = true,
  });

  final int id;
  final String type;
  final String? typeLabel;
  final String discountAmount;
  final String? minQuantity;
  final bool isActive;

  bool get isFlat => type == 'flat';
  bool get isMinQuantity => type == 'min_quantity';

  String get summary {
    final amount = AniHowMoney.peso(discountAmount);
    if (isMinQuantity && minQuantity != null && minQuantity!.isNotEmpty) {
      return '$amount off at $minQuantity and above';
    }
    return '$amount off this order';
  }

  factory TawadRule.fromJson(Map<String, dynamic> json) {
    return TawadRule(
      id: json['id'] as int,
      type: json['type'] as String? ?? '',
      typeLabel: json['type_label'] as String?,
      discountAmount: '${json['discount_amount'] ?? '0'}',
      minQuantity: json['min_quantity']?.toString(),
      isActive: json['is_active'] == true || json['is_active'] == 1,
    );
  }
}

class ListingItem {
  const ListingItem({
    required this.id,
    required this.title,
    required this.pricePerUnit,
    required this.quantityAvailable,
    this.unit,
    this.unitLabel,
    this.description,
    this.imageUrl,
    this.isActive = true,
    this.category,
    this.sellerName,
    this.sellerLocation,
    this.sellerId,
    this.averageRating,
    this.reviewsCount = 0,
    this.tawad,
    this.status,
  });

  final int id;
  final String title;
  final String? unit;
  final String? unitLabel;
  final String pricePerUnit;
  final String quantityAvailable;
  final String? description;
  final String? imageUrl;
  final bool isActive;
  final String? status;
  final CategoryItem? category;
  final String? sellerName;
  final String? sellerLocation;
  final int? sellerId;
  final String? averageRating;
  final int reviewsCount;
  final TawadRule? tawad;

  String get name => title;

  bool get hasRating => reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  bool get isLowStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity > 0 && quantity < 5;
  }

  bool get isInStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity >= 5;
  }

  bool get isTakenDown => status == 'taken_down';

  ListingItem copyWith({bool? isActive}) {
    return ListingItem(
      id: id,
      title: title,
      pricePerUnit: pricePerUnit,
      quantityAvailable: quantityAvailable,
      unit: unit,
      unitLabel: unitLabel,
      description: description,
      imageUrl: imageUrl,
      isActive: isActive ?? this.isActive,
      status: status,
      category: category,
      sellerName: sellerName,
      sellerLocation: sellerLocation,
      sellerId: sellerId,
      averageRating: averageRating,
      reviewsCount: reviewsCount,
      tawad: tawad,
    );
  }

  factory ListingItem.fromJson(Map<String, dynamic> json) {
    final cropTypeJson = json['crop_type'];
    final cropTypeMap = cropTypeJson is Map ? Map<String, dynamic>.from(cropTypeJson) : null;
    final sellerJson = json['seller'];
    final sellerMap = sellerJson is Map ? Map<String, dynamic>.from(sellerJson) : null;
    final tawadJson = json['tawad'];
    return ListingItem(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      unit: cropTypeMap?['unit_of_measure'] as String?,
      unitLabel: cropTypeMap?['unit_label'] as String?,
      pricePerUnit: '${json['price_per_unit'] ?? '0'}',
      quantityAvailable: '${json['quantity_available'] ?? '0'}',
      description: json['description'] as String?,
      imageUrl: ApiConfig.mediaUrl(json['image_url'] as String?),
      isActive: json['is_active'] == true || json['is_active'] == 1,
      status: json['status'] as String?,
      category: cropTypeMap == null ? null : CategoryItem.fromJson(cropTypeMap),
      sellerName: sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      sellerLocation: sellerMap?['location'] as String?,
      sellerId: _asCount(sellerMap?['id']),
      averageRating: json['average_rating']?.toString() ?? sellerMap?['average_rating']?.toString(),
      reviewsCount: _asCount(json['reviews_count']) ?? _asCount(sellerMap?['reviews_count']) ?? 0,
      tawad: tawadJson is Map && tawadJson['id'] != null
          ? TawadRule.fromJson(Map<String, dynamic>.from(tawadJson))
          : null,
    );
  }

  static int? _asCount(Object? value) {
    if (value is int) {
      return value;
    }
    if (value is num) {
      return value.toInt();
    }
    return int.tryParse('$value');
  }
}

class OrderItemRow {
  const OrderItemRow({
    required this.listingName,
    required this.quantity,
    required this.listedPrice,
    required this.lineSubtotal,
    this.unit,
    this.tawadAmount,
    this.lineTotal,
  });

  final String listingName;
  final String quantity;
  final String listedPrice;
  final String lineSubtotal;
  final String? unit;
  final String? tawadAmount;
  final String? lineTotal;

  String get quantityLabel {
    final unit = this.unit;
    if (unit == null || unit.isEmpty) {
      return quantity;
    }
    return '$quantity $unit';
  }

  factory OrderItemRow.fromJson(Map<String, dynamic> json) {
    return OrderItemRow(
      listingName: json['listing_name'] as String? ?? 'Item',
      quantity: '${json['quantity'] ?? ''}',
      listedPrice: '${json['listed_price'] ?? ''}',
      lineSubtotal: '${json['line_subtotal'] ?? ''}',
      unit: json['unit'] as String?,
      tawadAmount: json['tawad_amount']?.toString(),
      lineTotal: json['line_total']?.toString(),
    );
  }
}

class OrderRecord {
  const OrderRecord({
    required this.id,
    required this.status,
    required this.total,
    required this.items,
    this.orderNumber,
    this.statusLabel,
    this.fulfillmentNote,
    this.counterpartyName,
    this.shopName,
    this.location,
    this.contact,
    this.sellerId,
    this.placedAt,
    this.canBeReviewed = false,
    this.reviewRating,
    this.cancellationReason,
    this.cancellationLabel,
    this.allowedNext = const [],
    this.fulfillmentPreference,
    this.fulfillmentLabel,
    this.amountReceived,
    this.subtotal,
    this.tawadTotal,
    this.paymentMethod,
    this.source,
    this.isWalkIn = false,
    this.walkInBuyerName,
  });

  final int id;
  final String? orderNumber;
  final String status;
  final String? statusLabel;
  final String total;
  final String? subtotal;
  final String? tawadTotal;
  final String? paymentMethod;
  final String? fulfillmentNote;
  final String? fulfillmentPreference;
  final String? fulfillmentLabel;
  final String? counterpartyName;
  final String? shopName;
  final String? location;
  final String? contact;
  final int? sellerId;
  final String? placedAt;
  final bool canBeReviewed;
  final int? reviewRating;
  final String? cancellationReason;
  final String? cancellationLabel;
  final String? amountReceived;
  final String? source;
  final bool isWalkIn;
  final String? walkInBuyerName;
  final List<String> allowedNext;
  final List<OrderItemRow> items;

  factory OrderRecord.fromJson(Map<String, dynamic> json) {
    final buyer = json['buyer'];
    final seller = json['seller'];
    final farm = json['farm'];
    final review = json['review'];
    final sellerMap = seller is Map ? Map<String, dynamic>.from(seller) : null;
    final buyerMap = buyer is Map ? Map<String, dynamic>.from(buyer) : null;
    final farmMap = farm is Map ? Map<String, dynamic>.from(farm) : null;
    final reviewMap = review is Map ? Map<String, dynamic>.from(review) : null;
    final locationParts = [
      farmMap?['barangay'] as String?,
      farmMap?['municipality'] as String?,
    ].whereType<String>().where((part) => part.isNotEmpty).toList();
    return OrderRecord(
      id: json['id'] as int,
      orderNumber: json['order_number'] as String?,
      status: json['status'] as String? ?? '',
      statusLabel: json['status_label'] as String?,
      total: '${json['total'] ?? '0'}',
      subtotal: json['subtotal']?.toString(),
      tawadTotal: json['tawad_total']?.toString(),
      paymentMethod: json['payment_method'] as String?,
      fulfillmentNote: json['fulfillment_note'] as String?,
      fulfillmentPreference: json['fulfillment_preference'] as String?,
      fulfillmentLabel: json['fulfillment_label'] as String?,
      counterpartyName: buyerMap?['name'] as String? ??
          sellerMap?['shop_name'] as String? ??
          sellerMap?['name'] as String?,
      shopName: sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      location: locationParts.isEmpty ? null : locationParts.join(', '),
      contact: sellerMap?['contact'] as String? ??
          sellerMap?['phone'] as String? ??
          buyerMap?['contact'] as String? ??
          buyerMap?['phone'] as String?,
      sellerId: ListingItem._asCount(sellerMap?['id']),
      placedAt: json['placed_at'] as String?,
      canBeReviewed: json['can_be_reviewed'] == true,
      reviewRating: reviewMap == null ? null : ListingItem._asCount(reviewMap['rating']),
      cancellationReason: json['cancellation_reason'] as String?,
      cancellationLabel: json['cancellation_label'] as String?,
      amountReceived: json['amount_received']?.toString(),
      source: json['source'] as String?,
      isWalkIn: json['is_walk_in'] == true || json['is_walk_in'] == 1,
      walkInBuyerName: json['walk_in_buyer_name'] as String?,
      allowedNext: ((json['allowed_next'] as List?) ?? const [])
          .map((item) => item.toString())
          .toList(),
      items: ((json['items'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => OrderItemRow.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }

  String get stallName => shopName ?? counterpartyName ?? 'Stall';

  String get itemSummary {
    if (items.isEmpty) {
      return orderNumber ?? 'Order #$id';
    }
    return items.map((item) => item.listingName).join(', ');
  }

  bool get isPlaced => status == 'placed';
  bool get isConfirmed => status == 'confirmed';
  bool get isReady => status == 'ready';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';

  bool get hasCancellationReason =>
      isCancelled && ((cancellationReason != null && cancellationReason!.isNotEmpty) ||
          (cancellationLabel != null && cancellationLabel!.isNotEmpty));

  String get buyerName {
    if (isWalkIn) {
      final name = walkInBuyerName?.trim();
      if (name != null && name.isNotEmpty) {
        return name;
      }
      return 'Walk-in customer';
    }
    return counterpartyName ?? 'Buyer';
  }

  String get listedTotal => subtotal ?? total;

  String get tawadDisplay => tawadTotal ?? '0';

  String get paymentLabel =>
      paymentMethod == null || paymentMethod == 'cash_on_handover'
          ? 'Cash on handover'
          : paymentMethod!;

  bool canAdvanceTo(String next) {
    if (isWalkIn) {
      return false;
    }
    if (allowedNext.isNotEmpty) {
      return allowedNext.contains(next);
    }
    return switch (next) {
      'confirmed' => isPlaced,
      'ready' => isConfirmed,
      'completed' => isReady,
      'cancelled' => isPlaced || isConfirmed || isReady,
      _ => false,
    };
  }

  OrderRecord copyWith({
    String? status,
    String? statusLabel,
    List<String>? allowedNext,
    bool? canBeReviewed,
    String? cancellationReason,
    String? cancellationLabel,
    String? amountReceived,
  }) {
    return OrderRecord(
      id: id,
      orderNumber: orderNumber,
      status: status ?? this.status,
      statusLabel: statusLabel ?? this.statusLabel,
      total: total,
      subtotal: subtotal,
      tawadTotal: tawadTotal,
      paymentMethod: paymentMethod,
      items: items,
      fulfillmentNote: fulfillmentNote,
      fulfillmentPreference: fulfillmentPreference,
      fulfillmentLabel: fulfillmentLabel,
      counterpartyName: counterpartyName,
      shopName: shopName,
      location: location,
      contact: contact,
      sellerId: sellerId,
      placedAt: placedAt,
      canBeReviewed: canBeReviewed ?? this.canBeReviewed,
      reviewRating: reviewRating,
      cancellationReason: cancellationReason ?? this.cancellationReason,
      cancellationLabel: cancellationLabel ?? this.cancellationLabel,
      amountReceived: amountReceived ?? this.amountReceived,
      source: source,
      isWalkIn: isWalkIn,
      walkInBuyerName: walkInBuyerName,
      allowedNext: allowedNext ?? this.allowedNext,
    );
  }
}

class CartLine {
  const CartLine({
    required this.id,
    required this.quantity,
    required this.listedPrice,
    required this.lineSubtotal,
    required this.tawadAmount,
    required this.lineTotal,
    this.listing,
  });

  final int id;
  final String quantity;
  final String listedPrice;
  final String lineSubtotal;
  final String tawadAmount;
  final String lineTotal;
  final ListingItem? listing;

  int get sellerId => listing?.sellerId ?? 0;

  String get sellerName => listing?.sellerName ?? 'Seller';

  String get listingName => listing?.name ?? 'Item';

  /// Filipino crop label when the cart line embeds a crop type.
  String? get cropDisplayLabel => cropLabel(CropLanguage.filipino);

  String? cropLabel(CropLanguage language) {
    final label = listing?.category?.labelFor(language).trim();
    if (label == null || label.isEmpty) {
      return null;
    }
    return label;
  }

  String get unitLabel => listing?.unitLabel ?? listing?.unit ?? '';

  factory CartLine.fromJson(Map<String, dynamic> json) {
    final listingJson = json['listing'];
    return CartLine(
      id: json['id'] as int,
      quantity: '${json['quantity'] ?? ''}',
      listedPrice: '${json['listed_price'] ?? '0'}',
      lineSubtotal: '${json['line_subtotal'] ?? '0'}',
      tawadAmount: '${json['tawad_amount'] ?? '0'}',
      lineTotal: '${json['line_total'] ?? '0'}',
      listing: listingJson is Map
          ? ListingItem.fromJson(Map<String, dynamic>.from(listingJson))
          : null,
    );
  }
}

class SellerCartGroup {
  const SellerCartGroup({
    required this.sellerId,
    required this.sellerName,
    required this.items,
  });

  final int sellerId;
  final String sellerName;
  final List<CartLine> items;

  double get listedSubtotal => _sum((item) => item.lineSubtotal);

  double get tawadTotal => _sum((item) => item.tawadAmount);

  double get total => _sum((item) => item.lineTotal);

  double _sum(String Function(CartLine) read) {
    return items.fold<double>(0, (sum, item) => sum + (double.tryParse(read(item)) ?? 0));
  }
}

class CartSnapshot {
  const CartSnapshot({this.items = const []});

  final List<CartLine> items;

  bool get isEmpty => items.isEmpty;

  List<SellerCartGroup> get groupsBySeller {
    final order = <int>[];
    final buckets = <int, List<CartLine>>{};
    final names = <int, String>{};
    for (final item in items) {
      final id = item.sellerId;
      if (!buckets.containsKey(id)) {
        order.add(id);
        buckets[id] = [];
        names[id] = item.sellerName;
      }
      buckets[id]!.add(item);
    }
    return [
      for (final id in order)
        SellerCartGroup(
          sellerId: id,
          sellerName: names[id]!,
          items: buckets[id]!,
        ),
    ];
  }

  int get upcomingOrderCount => groupsBySeller.length;

  String get splitMessage {
    final count = upcomingOrderCount;
    if (count <= 1) {
      final name = groupsBySeller.isEmpty ? 'this seller' : groupsBySeller.first.sellerName;
      return 'This will be one order with $name.';
    }
    return 'This cart will become $count orders, one per seller.';
  }
}

class FavoriteRecord {
  const FavoriteRecord({
    required this.id,
    required this.listingId,
    this.listing,
  });

  final int id;
  final int listingId;
  final ListingItem? listing;

  factory FavoriteRecord.fromJson(Map<String, dynamic> json) {
    final listingJson = json['listing'];
    return FavoriteRecord(
      id: json['id'] as int,
      listingId: json['listing_id'] as int,
      listing: listingJson is Map<String, dynamic>
          ? ListingItem.fromJson(listingJson)
          : null,
    );
  }
}

class CropCareArticle {
  const CropCareArticle({
    required this.id,
    required this.title,
    this.body = '',
    this.summary = '',
    this.slug,
    this.category = '',
    this.categoryLabel = '',
    this.authorName,
    this.imageUrl,
    this.publishedAt,
    this.farmId,
    this.farmName,
    this.cropTypes = const [],
  });

  final int id;
  final String title;
  final String body;
  final String summary;
  final String? slug;
  final String category;
  final String categoryLabel;
  final String? authorName;
  final String? imageUrl;
  final String? publishedAt;
  final int? farmId;
  final String? farmName;
  final List<CategoryItem> cropTypes;

  CategoryItem get categoryChip => CategoryItem(
        id: 0,
        name: categoryLabel.isNotEmpty ? categoryLabel : category,
        slug: category,
      );

  String get authorLabel {
    final author = authorName?.trim();
    if (author != null && author.isNotEmpty) {
      return author;
    }
    final farm = farmName?.trim();
    if (farm != null && farm.isNotEmpty) {
      return farm;
    }
    return 'Farm';
  }

  factory CropCareArticle.fromJson(Map<String, dynamic> json) {
    final farmJson = json['farm'];
    final farmMap = farmJson is Map ? Map<String, dynamic>.from(farmJson) : null;
    return CropCareArticle(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      summary: json['summary'] as String? ?? '',
      slug: json['slug'] as String?,
      category: json['category'] as String? ?? '',
      categoryLabel: json['category_label'] as String? ?? '',
      authorName: json['author_name'] as String?,
      imageUrl: ApiConfig.mediaUrl(json['image_url'] as String?),
      publishedAt: json['published_at'] as String?,
      farmId: ListingItem._asCount(farmMap?['id']),
      farmName: farmMap?['name'] as String?,
      cropTypes: ((json['crop_types'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => CategoryItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class CropCareCategory {
  const CropCareCategory({required this.value, required this.label});

  final String value;
  final String label;

  static const cropCare = CropCareCategory(value: 'crop_care', label: 'Crop care');
  static const pestManagement = CropCareCategory(value: 'pest_management', label: 'Pest management');
  static const filters = [cropCare, pestManagement];
}

class ShopProfile {
  const ShopProfile({
    required this.id,
    required this.shopName,
    required this.name,
    this.bio,
    this.location,
    this.contact,
    this.averageRating,
    this.reviewsCount = 0,
    this.listings = const [],
    this.farmId,
    this.farmName,
    this.farmIsActive = false,
  });

  final int id;
  final String shopName;
  final String name;
  final String? bio;
  final String? location;
  final String? contact;
  final String? averageRating;
  final int reviewsCount;
  final List<ListingItem> listings;
  final int? farmId;
  final String? farmName;
  final bool farmIsActive;

  bool get hasRating => reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  factory ShopProfile.fromJson(Map<String, dynamic> json) {
    final farmJson = json['farm'];
    final farmMap = farmJson is Map ? Map<String, dynamic>.from(farmJson) : null;
    return ShopProfile(
      id: json['id'] as int,
      shopName: json['shop_name'] as String? ?? json['name'] as String? ?? '',
      name: json['name'] as String? ?? '',
      bio: json['bio'] as String?,
      location: json['location'] as String?,
      contact: json['contact'] as String?,
      averageRating: json['average_rating']?.toString(),
      reviewsCount: ListingItem._asCount(json['reviews_count']) ?? 0,
      listings: ((json['listings'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => ListingItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      farmId: ListingItem._asCount(farmMap?['id']),
      farmName: farmMap?['name'] as String?,
      farmIsActive: farmMap != null &&
          (farmMap['is_active'] == true || farmMap['is_active'] == 1 || farmMap['is_active'] == '1'),
    );
  }
}

class FarmAnnouncement {
  const FarmAnnouncement({
    required this.id,
    required this.title,
    required this.body,
    this.audience,
    this.startsAt,
    this.endsAt,
    this.isPinned = false,
    this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final String? audience;
  final String? startsAt;
  final String? endsAt;
  final bool isPinned;
  final String? createdAt;

  factory FarmAnnouncement.fromJson(Map<String, dynamic> json) {
    return FarmAnnouncement(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      audience: json['audience'] as String?,
      startsAt: json['starts_at'] as String?,
      endsAt: json['ends_at'] as String?,
      isPinned: json['is_pinned'] == true || json['is_pinned'] == 1 || json['is_pinned'] == '1',
      createdAt: json['created_at'] as String?,
    );
  }
}

class FarmPhotoItem {
  const FarmPhotoItem({
    required this.id,
    required this.url,
    this.caption,
  });

  final int id;
  final String url;
  final String? caption;

  factory FarmPhotoItem.fromJson(Map<String, dynamic> json) {
    return FarmPhotoItem(
      id: json['id'] as int,
      url: ApiConfig.mediaUrl(json['url'] as String?) ?? '',
      caption: json['caption'] as String?,
    );
  }
}

class FarmStorefront {
  const FarmStorefront({
    required this.id,
    required this.shopName,
    this.avatar,
  });

  final int id;
  final String shopName;
  final String? avatar;

  factory FarmStorefront.fromJson(Map<String, dynamic> json) {
    return FarmStorefront(
      id: json['id'] as int,
      shopName: json['shop_name'] as String? ?? json['name'] as String? ?? '',
      avatar: ApiConfig.mediaUrl(json['avatar'] as String?),
    );
  }
}

class FarmProfile {
  const FarmProfile({
    required this.id,
    required this.name,
    this.slug,
    this.description,
    this.contactPerson,
    this.contactNumber,
    this.barangay,
    this.municipality,
    this.pickupPoint,
    this.coverPhotoUrl,
    this.isActive = true,
    this.photos = const [],
    this.farmerSellersCount = 0,
    this.storefronts = const [],
    this.announcements = const [],
  });

  final int id;
  final String name;
  final String? slug;
  final String? description;
  final String? contactPerson;
  final String? contactNumber;
  final String? barangay;
  final String? municipality;
  final String? pickupPoint;
  final String? coverPhotoUrl;
  final bool isActive;
  final List<FarmPhotoItem> photos;
  final int farmerSellersCount;
  final List<FarmStorefront> storefronts;
  final List<FarmAnnouncement> announcements;

  bool get hasCoverPhoto => coverPhotoUrl != null && coverPhotoUrl!.isNotEmpty;

  String get placeLabel {
    final parts = [barangay, municipality]
        .map((part) => part?.trim() ?? '')
        .where((part) => part.isNotEmpty)
        .toList();
    return parts.join(', ');
  }

  factory FarmProfile.fromJson(Map<String, dynamic> json) {
    return FarmProfile(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      description: json['description'] as String?,
      contactPerson: json['contact_person'] as String?,
      contactNumber: json['contact_number'] as String?,
      barangay: json['barangay'] as String?,
      municipality: json['municipality'] as String?,
      pickupPoint: json['pickup_point'] as String?,
      coverPhotoUrl: ApiConfig.mediaUrl(json['cover_photo_url'] as String?),
      isActive: json['is_active'] == true || json['is_active'] == 1 || json['is_active'] == '1',
      photos: ((json['photos'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmPhotoItem.fromJson(Map<String, dynamic>.from(item)))
          .where((photo) => photo.url.isNotEmpty)
          .toList(),
      farmerSellersCount: ListingItem._asCount(json['farmer_sellers_count']) ?? 0,
      storefronts: ((json['storefronts'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmStorefront.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      announcements: ((json['announcements'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmAnnouncement.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class ShopReview {
  const ShopReview({
    required this.id,
    required this.rating,
    required this.reviewerName,
    this.comment,
    this.createdAt,
  });

  final int id;
  final int rating;
  final String reviewerName;
  final String? comment;
  final String? createdAt;

  factory ShopReview.fromJson(Map<String, dynamic> json) {
    return ShopReview(
      id: json['id'] as int,
      rating: ListingItem._asCount(json['rating']) ?? 0,
      reviewerName: json['buyer_name'] as String? ?? 'Buyer',
      comment: json['comment'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class PagedShopReviews {
  const PagedShopReviews({
    required this.reviews,
    required this.currentPage,
    required this.lastPage,
    this.averageRating,
    this.reviewsCount = 0,
  });

  final List<ShopReview> reviews;
  final int currentPage;
  final int lastPage;
  final String? averageRating;
  final int reviewsCount;

  bool get hasMore => currentPage < lastPage;
}

class AppNotification {
  const AppNotification({
    required this.id,
    required this.title,
    required this.body,
    this.type,
    this.relatedId,
    this.relatedType,
    this.readAt,
    this.createdAt,
  });

  final int id;
  final String title;
  final String body;
  final String? type;
  final int? relatedId;
  final String? relatedType;
  final String? readAt;
  final String? createdAt;

  bool get isUnread => readAt == null || readAt!.isEmpty;

  AppNotification copyWith({String? readAt}) {
    return AppNotification(
      id: id,
      title: title,
      body: body,
      type: type,
      relatedId: relatedId,
      relatedType: relatedType,
      readAt: readAt ?? this.readAt,
      createdAt: createdAt,
    );
  }

  bool get pointsToListing {
    final related = relatedType ?? '';
    return type == 'listing_low_stock' ||
        related == 'listing' ||
        related.endsWith('Listing');
  }

  bool get pointsToAnnouncement {
    final related = relatedType ?? '';
    return type == 'farm_announcement' ||
        related == 'farm_announcement' ||
        related.endsWith('FarmAnnouncement');
  }

  bool get pointsToOrder {
    final related = relatedType ?? '';
    return type == 'order_placed' ||
        type == 'order_awaiting_confirmation' ||
        type == 'order_confirmed' ||
        type == 'order_ready' ||
        type == 'order_completed' ||
        type == 'order_cancelled' ||
        type == 'order_message' ||
        related == 'order' ||
        related.endsWith('Order');
  }

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      type: json['type'] as String?,
      relatedId: ListingItem._asCount(json['related_id']),
      relatedType: json['related_type'] as String?,
      readAt: json['read_at'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class OrderMessage {
  const OrderMessage({
    required this.id,
    required this.body,
    required this.authorId,
    required this.authorName,
    this.authorRole,
    this.createdAt,
  });

  final int id;
  final String body;
  final int authorId;
  final String authorName;
  final String? authorRole;
  final String? createdAt;

  factory OrderMessage.fromJson(Map<String, dynamic> json) {
    final author = json['author'];
    final authorMap = author is Map ? Map<String, dynamic>.from(author) : null;
    return OrderMessage(
      id: json['id'] as int,
      body: json['body'] as String? ?? '',
      authorId: ListingItem._asCount(authorMap?['id']) ?? 0,
      authorName: authorMap?['name'] as String? ?? 'Someone',
      authorRole: authorMap?['role'] as String?,
      createdAt: json['created_at'] as String?,
    );
  }
}

class FaqSuggestion {
  const FaqSuggestion({required this.id, required this.label});

  final String id;
  final String label;

  factory FaqSuggestion.fromJson(Map<String, dynamic> json) {
    return FaqSuggestion(
      id: json['id'] as String? ?? '',
      label: json['label'] as String? ?? '',
    );
  }
}

class FarmerAnalyticsSummary {
  const FarmerAnalyticsSummary({
    required this.completedOrders,
    required this.unitsSold,
    required this.grossSales,
    required this.averageDiscount,
  });

  final int completedOrders;
  final double unitsSold;
  final double grossSales;
  final double averageDiscount;

  bool get isEmpty => completedOrders == 0 && unitsSold == 0 && grossSales == 0;

  factory FarmerAnalyticsSummary.fromJson(Map<String, dynamic> json) {
    return FarmerAnalyticsSummary(
      completedOrders: ListingItem._asCount(json['completed_orders']) ?? 0,
      unitsSold: _asDouble(json['units_sold']),
      grossSales: _asDouble(json['gross_sales']),
      averageDiscount: _asDouble(json['average_discount']),
    );
  }
}

class FarmerSalesPoint {
  const FarmerSalesPoint({
    required this.period,
    required this.orders,
    required this.revenue,
  });

  final String period;
  final int orders;
  final double revenue;

  factory FarmerSalesPoint.fromJson(Map<String, dynamic> json) {
    return FarmerSalesPoint(
      period: json['period'] as String? ?? '',
      orders: ListingItem._asCount(json['orders']) ?? 0,
      revenue: _asDouble(json['revenue']),
    );
  }
}

class FarmerCropSales {
  const FarmerCropSales({
    required this.crop,
    required this.units,
    required this.revenue,
    this.unit,
  });

  final String crop;
  final String? unit;
  final double units;
  final double revenue;

  factory FarmerCropSales.fromJson(Map<String, dynamic> json) {
    return FarmerCropSales(
      crop: json['crop'] as String? ?? '',
      unit: json['unit'] as String?,
      units: _asDouble(json['units']),
      revenue: _asDouble(json['revenue']),
    );
  }
}

class FarmerWalkInShare {
  const FarmerWalkInShare({
    required this.walkInOrders,
    required this.walkInSales,
    required this.appOrders,
    required this.appSales,
  });

  final int walkInOrders;
  final double walkInSales;
  final int appOrders;
  final double appSales;

  factory FarmerWalkInShare.fromJson(Map<String, dynamic> json) {
    return FarmerWalkInShare(
      walkInOrders: ListingItem._asCount(json['walk_in_orders']) ?? 0,
      walkInSales: _asDouble(json['walk_in_sales']),
      appOrders: ListingItem._asCount(json['app_orders']) ?? 0,
      appSales: _asDouble(json['app_sales']),
    );
  }
}

class FarmerAnalytics {
  const FarmerAnalytics({
    required this.period,
    required this.summary,
    required this.salesPerPeriod,
    required this.unitsPerCropType,
    required this.bestSelling,
    required this.walkInShare,
    this.windowStart,
    this.windowEnd,
  });

  final String period;
  final String? windowStart;
  final String? windowEnd;
  final FarmerAnalyticsSummary summary;
  final List<FarmerSalesPoint> salesPerPeriod;
  final List<FarmerCropSales> unitsPerCropType;
  final List<FarmerCropSales> bestSelling;
  final FarmerWalkInShare walkInShare;

  bool get isEmpty => summary.isEmpty;

  factory FarmerAnalytics.fromJson(Map<String, dynamic> json) {
    return FarmerAnalytics(
      period: json['period'] as String? ?? 'week',
      windowStart: json['window_start'] as String?,
      windowEnd: json['window_end'] as String?,
      summary: FarmerAnalyticsSummary.fromJson(
        json['summary'] is Map ? Map<String, dynamic>.from(json['summary'] as Map) : <String, dynamic>{},
      ),
      salesPerPeriod: ((json['sales_per_period'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmerSalesPoint.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      unitsPerCropType: ((json['units_per_crop_type'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmerCropSales.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      bestSelling: ((json['best_selling'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmerCropSales.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      walkInShare: FarmerWalkInShare.fromJson(
        json['walk_in_share'] is Map ? Map<String, dynamic>.from(json['walk_in_share'] as Map) : <String, dynamic>{},
      ),
    );
  }
}

double _asDouble(Object? value) {
  if (value is num) {
    return value.toDouble();
  }
  return double.tryParse('$value') ?? 0;
}

class FaqAnswer {
  const FaqAnswer({
    required this.answer,
    required this.suggestions,
    this.matchedId,
  });

  final String answer;
  final String? matchedId;
  final List<FaqSuggestion> suggestions;

  factory FaqAnswer.fromJson(Map<String, dynamic> json) {
    return FaqAnswer(
      answer: json['answer'] as String? ?? '',
      matchedId: json['matched_id'] as String?,
      suggestions: ((json['suggestions'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FaqSuggestion.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

