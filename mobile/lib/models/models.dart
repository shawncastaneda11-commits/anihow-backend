class UserAccount {
  const UserAccount({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    this.phone,
    this.shopName,
    this.emailVerifiedAt,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final String? phone;
  final String? shopName;
  final String? emailVerifiedAt;

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    return UserAccount(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roles: ((json['roles'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(),
      phone: json['phone'] as String?,
      shopName: json['shop_name'] as String?,
      emailVerifiedAt: json['email_verified_at'] as String?,
    );
  }

  bool get isBuyer => roles.contains('buyer');
  bool get isFarmerSeller => roles.contains('farmer_seller');
  bool get isSuperAdmin => roles.contains('super_admin');
  bool get isVerified => emailVerifiedAt != null && emailVerifiedAt!.isNotEmpty;
  String get roleLabel =>
      roles.isEmpty ? 'unknown' : roles.first.replaceAll('_', ' ');
}

class CategoryItem {
  const CategoryItem({required this.id, required this.name, this.slug});

  final int id;
  final String name;
  final String? slug;

  factory CategoryItem.fromJson(Map<String, dynamic> json) {
    return CategoryItem(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
    );
  }
}

class ListingItem {
  const ListingItem({
    required this.id,
    required this.name,
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
  });

  final int id;
  final String name;
  final String? unit;
  final String? unitLabel;
  final String pricePerUnit;
  final String quantityAvailable;
  final String? description;
  final String? imageUrl;
  final bool isActive;
  final CategoryItem? category;
  final String? sellerName;
  final String? sellerLocation;
  final int? sellerId;
  final String? averageRating;
  final int reviewsCount;

  bool get hasRating => reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  bool get isLowStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity > 0 && quantity < 5;
  }

  bool get isInStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity >= 5;
  }

  factory ListingItem.fromJson(Map<String, dynamic> json) {
    final categoryJson = json['category'];
    final sellerJson = json['seller'];
    final sellerMap = sellerJson is Map ? Map<String, dynamic>.from(sellerJson) : null;
    return ListingItem(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      unit: json['unit'] as String?,
      unitLabel: json['unit_label'] as String?,
      pricePerUnit: '${json['price_per_unit'] ?? '0'}',
      quantityAvailable: '${json['quantity_available'] ?? '0'}',
      description: json['description'] as String?,
      imageUrl: json['image_url'] as String?,
      isActive: json['is_active'] == true || json['is_active'] == 1,
      category: categoryJson is Map<String, dynamic>
          ? CategoryItem.fromJson(categoryJson)
          : categoryJson is Map
              ? CategoryItem.fromJson(Map<String, dynamic>.from(categoryJson))
              : null,
      sellerName: sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      sellerLocation: sellerMap?['location'] as String?,
      sellerId: _asCount(sellerMap?['id']),
      averageRating: json['average_rating']?.toString() ?? sellerMap?['average_rating']?.toString(),
      reviewsCount: _asCount(json['reviews_count']) ?? _asCount(sellerMap?['reviews_count']) ?? 0,
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

class ReservationItemRow {
  const ReservationItemRow({
    required this.listingName,
    required this.quantity,
    required this.unitPrice,
    required this.lineSubtotal,
    this.unit,
  });

  final String listingName;
  final String quantity;
  final String unitPrice;
  final String lineSubtotal;
  final String? unit;

  String get quantityLabel {
    final unit = this.unit;
    if (unit == null || unit.isEmpty) {
      return quantity;
    }
    return '$quantity $unit';
  }

  factory ReservationItemRow.fromJson(Map<String, dynamic> json) {
    return ReservationItemRow(
      listingName: json['listing_name'] as String? ?? 'Item',
      quantity: '${json['quantity'] ?? ''}',
      unitPrice: '${json['unit_price'] ?? ''}',
      lineSubtotal: '${json['line_subtotal'] ?? ''}',
      unit: json['unit'] as String?,
    );
  }
}

class ReservationRecord {
  const ReservationRecord({
    required this.id,
    required this.status,
    required this.total,
    required this.items,
    this.statusLabel,
    this.notes,
    this.counterpartyName,
    this.shopName,
    this.location,
    this.contact,
    this.sellerId,
    this.createdAt,
    this.canReview = false,
    this.reviewRating,
    this.cancellationReason,
  });

  final int id;
  final String status;
  final String? statusLabel;
  final String total;
  final String? notes;
  final String? counterpartyName;
  final String? shopName;
  final String? location;
  final String? contact;
  final int? sellerId;
  final String? createdAt;
  final bool canReview;
  final int? reviewRating;
  final String? cancellationReason;
  final List<ReservationItemRow> items;

  factory ReservationRecord.fromJson(Map<String, dynamic> json) {
    final buyer = json['buyer'];
    final seller = json['seller'];
    final review = json['review'];
    final sellerMap = seller is Map ? Map<String, dynamic>.from(seller) : null;
    final buyerMap = buyer is Map ? Map<String, dynamic>.from(buyer) : null;
    final reviewMap = review is Map ? Map<String, dynamic>.from(review) : null;
    return ReservationRecord(
      id: json['id'] as int,
      status: json['status'] as String? ?? '',
      statusLabel: json['status_label'] as String?,
      total: '${json['total'] ?? '0'}',
      notes: json['notes'] as String?,
      counterpartyName: buyerMap?['name'] as String? ??
          sellerMap?['shop_name'] as String? ??
          sellerMap?['name'] as String?,
      shopName: sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      location: sellerMap?['location'] as String?,
      contact: sellerMap?['contact'] as String? ?? sellerMap?['phone'] as String?,
      sellerId: ListingItem._asCount(sellerMap?['id']),
      createdAt: json['created_at'] as String?,
      canReview: json['can_review'] == true,
      reviewRating: reviewMap == null ? null : ListingItem._asCount(reviewMap['rating']),
      cancellationReason: json['cancellation_reason'] as String?,
      items: ((json['items'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => ReservationItemRow.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }

  String get stallName => shopName ?? counterpartyName ?? 'Stall';

  String get itemSummary {
    if (items.isEmpty) {
      return 'Reservation #$id';
    }
    return items.map((item) => item.listingName).join(', ');
  }

  bool get isPending => status == 'pending';
  bool get isReady => status == 'ready_for_pickup' || status == 'ready';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';
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
    this.excerpt,
    this.category,
    this.isOfficial = true,
    this.canEdit = false,
    this.authorId,
    this.authorName,
    this.authorShopName,
  });

  final int id;
  final String title;
  final String body;
  final String? excerpt;
  final CategoryItem? category;
  final bool isOfficial;
  final bool canEdit;
  final int? authorId;
  final String? authorName;
  final String? authorShopName;

  String get authorLabel {
    if (isOfficial) {
      return 'Official';
    }
    final shop = authorShopName?.trim();
    if (shop != null && shop.isNotEmpty) {
      return shop;
    }
    final name = authorName?.trim();
    if (name != null && name.isNotEmpty) {
      return name;
    }
    return 'Farmer-seller';
  }

  String get summary {
    final excerpt = this.excerpt?.trim();
    if (excerpt != null && excerpt.isNotEmpty) {
      return excerpt;
    }
    final firstLine = body
        .split('\n')
        .map((line) => line.trim())
        .firstWhere((line) => line.isNotEmpty, orElse: () => '');
    return firstLine;
  }

  factory CropCareArticle.fromJson(Map<String, dynamic> json) {
    final categoryJson = json['category'];
    final authorJson = json['author'];
    final authorMap = authorJson is Map ? Map<String, dynamic>.from(authorJson) : null;
    return CropCareArticle(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      excerpt: json['excerpt'] as String?,
      category: categoryJson is Map
          ? CategoryItem.fromJson(Map<String, dynamic>.from(categoryJson))
          : null,
      isOfficial: json['is_official'] != false,
      canEdit: json['can_edit'] == true,
      authorId: ListingItem._asCount(authorMap?['id']),
      authorName: authorMap?['name'] as String?,
      authorShopName: authorMap?['shop_name'] as String?,
    );
  }
}

class CropCareCategory {
  const CropCareCategory({
    required this.id,
    required this.name,
    required this.tipsCount,
    this.slug,
  });

  final int id;
  final String name;
  final String? slug;
  final int tipsCount;

  CategoryItem get asCategory => CategoryItem(id: id, name: name, slug: slug);

  factory CropCareCategory.fromJson(Map<String, dynamic> json) {
    return CropCareCategory(
      id: json['id'] as int? ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      tipsCount: ListingItem._asCount(json['tips_count']) ?? 0,
    );
  }
}

class SaleItemRow {
  const SaleItemRow({
    required this.listingName,
    required this.quantity,
    required this.unitPrice,
    required this.lineSubtotal,
    this.unit,
  });

  final String listingName;
  final String quantity;
  final String unitPrice;
  final String lineSubtotal;
  final String? unit;

  factory SaleItemRow.fromJson(Map<String, dynamic> json) {
    return SaleItemRow(
      listingName: json['listing_name'] as String? ?? 'Item',
      quantity: '${json['quantity'] ?? ''}',
      unitPrice: '${json['unit_price'] ?? ''}',
      lineSubtotal: '${json['line_subtotal'] ?? ''}',
      unit: json['unit'] as String?,
    );
  }
}

class SaleRecord {
  const SaleRecord({
    required this.id,
    required this.total,
    this.notes,
    this.createdAt,
    this.items = const [],
  });

  final int id;
  final String total;
  final String? notes;
  final String? createdAt;
  final List<SaleItemRow> items;

  bool get isToday {
    final parsed = DateTime.tryParse(createdAt ?? '');
    if (parsed == null) {
      return false;
    }
    final local = parsed.toLocal();
    final now = DateTime.now();
    return local.year == now.year && local.month == now.month && local.day == now.day;
  }

  factory SaleRecord.fromJson(Map<String, dynamic> json) {
    return SaleRecord(
      id: json['id'] as int,
      total: '${json['total'] ?? '0'}',
      notes: json['notes'] as String?,
      createdAt: json['created_at'] as String?,
      items: ((json['items'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => SaleItemRow.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
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

  bool get hasRating => reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  factory ShopProfile.fromJson(Map<String, dynamic> json) {
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
    final buyer = json['buyer'];
    final buyerMap = buyer is Map ? Map<String, dynamic>.from(buyer) : null;
    return ShopReview(
      id: json['id'] as int,
      rating: ListingItem._asCount(json['rating']) ?? 0,
      reviewerName: buyerMap?['name'] as String? ?? 'Buyer',
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

  bool get pointsToListing {
    final related = relatedType ?? '';
    return type == 'listing_low_stock' ||
        related == 'listing' ||
        related.endsWith('Listing');
  }

  bool get pointsToReservation {
    final related = relatedType ?? '';
    return type == 'reservation_created' ||
        type == 'reservation_status_changed' ||
        related == 'reservation' ||
        related.endsWith('Reservation');
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

