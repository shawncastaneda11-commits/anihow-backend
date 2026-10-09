import '../config/api_config.dart';
import '../support/crop_language.dart';
import '../theme/anihow_space.dart';

class FarmFeatures {
  const FarmFeatures({
    this.valueAdded = true,
    this.reservations = true,
    this.tawad = true,
    this.walkIn = true,
  });

  final bool valueAdded;
  final bool reservations;
  final bool tawad;
  final bool walkIn;

  factory FarmFeatures.fromJson(Object? json) {
    if (json is! Map) {
      return const FarmFeatures();
    }
    final map = Map<String, dynamic>.from(json);
    bool on(String key) => map.containsKey(key) ? map[key] == true : true;
    return FarmFeatures(
      valueAdded: on('value_added'),
      reservations: on('reservations'),
      tawad: on('tawad'),
      walkIn: on('walk_in'),
    );
  }
}

class UserAccount {
  const UserAccount({
    required this.id,
    required this.name,
    required this.email,
    required this.roles,
    this.permissions = const [],
    this.phone,
    this.location,
    this.shopName,
    this.avatarUrl,
    this.emailVerifiedAt,
    this.farmId,
    this.farmName,
    this.farmIsOrganicCertified = false,
    this.farmFeatures = const FarmFeatures(),
    this.mustChangePassword = false,
  });

  final int id;
  final String name;
  final String email;
  final List<String> roles;
  final List<String> permissions;
  final String? phone;
  final String? location;
  final String? shopName;
  final String? avatarUrl;
  final String? emailVerifiedAt;
  final int? farmId;
  final String? farmName;
  final bool farmIsOrganicCertified;
  final FarmFeatures farmFeatures;
  final bool mustChangePassword;

  UserAccount withMustChangePassword(bool value) {
    return UserAccount(
      id: id,
      name: name,
      email: email,
      roles: roles,
      permissions: permissions,
      phone: phone,
      location: location,
      shopName: shopName,
      avatarUrl: avatarUrl,
      emailVerifiedAt: emailVerifiedAt,
      farmId: farmId,
      farmName: farmName,
      farmIsOrganicCertified: farmIsOrganicCertified,
      farmFeatures: farmFeatures,
      mustChangePassword: value,
    );
  }

  factory UserAccount.fromJson(Map<String, dynamic> json) {
    final farmJson = json['farm'];
    final farmMap = farmJson is Map
        ? Map<String, dynamic>.from(farmJson)
        : null;
    return UserAccount(
      id: ListingItem._asCount(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      roles: ((json['roles'] as List?) ?? const [])
          .map((role) => role.toString())
          .toList(),
      permissions: ((json['permissions'] as List?) ?? const [])
          .map((permission) => permission.toString())
          .toList(),
      phone: json['phone'] as String?,
      location: json['location'] as String?,
      shopName: json['shop_name'] as String?,
      avatarUrl: ApiConfig.mediaUrl(json['avatar_url'] as String?),
      emailVerifiedAt: json['email_verified_at']?.toString(),
      farmId: ListingItem._asCount(farmMap?['id']),
      farmName: farmMap?['name'] as String?,
      farmIsOrganicCertified: farmMap?['is_organic_certified'] == true,
      farmFeatures: FarmFeatures.fromJson(farmMap?['features']),
      mustChangePassword: json['must_change_password'] == true,
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

class AccountDeletionRequest {
  const AccountDeletionRequest({
    required this.id,
    required this.status,
    this.reason,
    this.rejectionNote,
  });

  final int id;
  final String status;
  final String? reason;
  final String? rejectionNote;

  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';

  factory AccountDeletionRequest.fromJson(Map<String, dynamic> json) {
    return AccountDeletionRequest(
      id: ListingItem._asCount(json['id']) ?? 0,
      status: json['status'] as String? ?? '',
      reason: json['reason'] as String?,
      rejectionNote: json['rejection_note'] as String?,
    );
  }
}

class AllowedListingUnit {
  const AllowedListingUnit({
    required this.value,
    required this.family,
    this.label,
  });

  final String value;
  final String family;
  final String? label;
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
    this.allowedUnits = const [],
    this.productCategory,
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
  final List<AllowedListingUnit> allowedUnits;
  final String? productCategory;

  bool get isValueAdded => productCategory == 'value_added';

  factory CategoryItem.fromJson(Map<String, dynamic> json) {
    return CategoryItem(
      id: ListingItem._asCount(json['id']) ?? 0,
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
      allowedUnits: ((json['allowed_units'] as List?) ?? const [])
          .whereType<Map>()
          .map((row) {
            final unit = Map<String, dynamic>.from(row);
            return AllowedListingUnit(
              value: unit['value']?.toString() ?? '',
              family: unit['family']?.toString() ?? '',
              label: unit['label']?.toString(),
            );
          })
          .where((unit) => unit.value.isNotEmpty)
          .toList(),
      productCategory: json['category'] is Map
          ? (json['category'] as Map)['value']?.toString()
          : json['category']?.toString(),
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

  String displaySummary({
    required String Function(String peso) offThisOrder,
    required String Function(String peso, String quantity) offAtMin,
  }) {
    final peso = AniHowMoney.peso(discountAmount);
    if (isMinQuantity && (minQuantity ?? '').isNotEmpty) {
      return offAtMin(peso, minQuantity!);
    }
    return offThisOrder(peso);
  }

  factory TawadRule.fromJson(Map<String, dynamic> json) {
    return TawadRule(
      id: ListingItem._asCount(json['id']) ?? 0,
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
    this.thumbnailUrl,
    this.isActive = true,
    this.category,
    this.sellerName,
    this.sellerLocation,
    this.sellerId,
    this.averageRating,
    this.reviewsCount = 0,
    this.tawad,
    this.status,
    this.takedownReason,
    this.availableFrom,
    this.availableUntil,
    this.harvestedOn,
    this.isUpcoming = false,
    this.availabilityState,
    this.growingMethod,
    this.organicBadge,
    this.organicCertifier,
    this.distanceKm,
    this.reservedQuantity,
    this.activeReservationsCount,
    this.minOrderQuantity = 1,
    this.orderStep = 1,
    this.sellableQuantity,
    this.acceptsOnlinePayment = true,
    this.canReserve,
    this.tawadPaused = false,
    this.needsActualHarvest = false,
    this.expiredWithStock = false,
    this.hasHarvestRecords = false,
  });

  final int id;
  final String title;
  final String? unit;
  final String? unitLabel;
  final String pricePerUnit;
  final String quantityAvailable;
  final String? description;
  final String? imageUrl;
  final String? thumbnailUrl;
  final bool isActive;
  final String? status;
  final CategoryItem? category;
  final String? sellerName;
  final String? sellerLocation;
  final int? sellerId;
  final String? averageRating;
  final int reviewsCount;
  final TawadRule? tawad;
  final String? takedownReason;
  final DateTime? availableFrom;
  final DateTime? availableUntil;
  final DateTime? harvestedOn;
  final bool isUpcoming;
  final String? availabilityState;
  final String? growingMethod;
  final String? organicBadge;
  final String? organicCertifier;
  final double? distanceKm;
  final double? reservedQuantity;
  final int? activeReservationsCount;
  final double minOrderQuantity;
  final double orderStep;
  final String? sellableQuantity;
  final bool acceptsOnlinePayment;
  final bool? canReserve;
  final bool tawadPaused;
  final bool needsActualHarvest;
  final bool expiredWithStock;
  final bool hasHarvestRecords;

  bool get harvestDueSoon {
    if (!needsActualHarvest || availableFrom == null) {
      return false;
    }
    final opening = availableFrom!.toLocal();
    final now = DateTime.now();
    return !opening.isBefore(now) &&
        opening.difference(now) <= const Duration(hours: 24);
  }

  double? get heldQuantity {
    final available = double.tryParse(quantityAvailable);
    final sellable = double.tryParse(sellableQuantity ?? '');
    if (available == null || sellable == null) {
      return null;
    }
    final held = available - sellable;
    return held < 0 ? 0 : held;
  }

  bool get showReserveButton => isUpcoming && canReserve != false;

  bool get showComingSoon => isUpcoming && canReserve == false;

  String get name => title;

  bool get hasRating =>
      reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  bool get isLowStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity > 0 && quantity < 5;
  }

  bool get isInStock {
    final quantity = double.tryParse(quantityAvailable) ?? 0;
    return quantity >= 5;
  }

  bool get isTakenDown => status == 'taken_down';

  /// Short unit code, for example ₱60.00 / kg.
  String get priceLabel {
    final peso = AniHowMoney.pesoUnit(pricePerUnit);
    final code = unit;
    if (code == null || code.isEmpty) {
      return peso;
    }
    return '$peso / $code';
  }

  bool get isSellerActive => isActive && !isTakenDown;

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
      thumbnailUrl: thumbnailUrl,
      isActive: isActive ?? this.isActive,
      status: status,
      category: category,
      sellerName: sellerName,
      sellerLocation: sellerLocation,
      sellerId: sellerId,
      averageRating: averageRating,
      reviewsCount: reviewsCount,
      tawad: tawad,
      takedownReason: takedownReason,
      availableFrom: availableFrom,
      availableUntil: availableUntil,
      harvestedOn: harvestedOn,
      isUpcoming: isUpcoming,
      availabilityState: availabilityState,
      growingMethod: growingMethod,
      organicBadge: organicBadge,
      organicCertifier: organicCertifier,
      distanceKm: distanceKm,
      reservedQuantity: reservedQuantity,
      activeReservationsCount: activeReservationsCount,
      minOrderQuantity: minOrderQuantity,
      orderStep: orderStep,
      sellableQuantity: sellableQuantity,
      acceptsOnlinePayment: acceptsOnlinePayment,
      canReserve: canReserve,
      tawadPaused: tawadPaused,
      needsActualHarvest: needsActualHarvest,
      expiredWithStock: expiredWithStock,
      hasHarvestRecords: hasHarvestRecords,
    );
  }

  factory ListingItem.fromJson(Map<String, dynamic> json) {
    final cropTypeJson = json['crop_type'];
    final cropTypeMap = cropTypeJson is Map
        ? Map<String, dynamic>.from(cropTypeJson)
        : null;
    final sellerJson = json['seller'];
    final sellerMap = sellerJson is Map
        ? Map<String, dynamic>.from(sellerJson)
        : null;
    final tawadJson = json['tawad'];
    return ListingItem(
      id: ListingItem._asCount(json['id']) ?? 0,
      title: json['title'] as String? ?? '',
      unit:
          json['unit'] as String? ?? cropTypeMap?['unit_of_measure'] as String?,
      unitLabel:
          json['unit_label'] as String? ??
          cropTypeMap?['unit_label'] as String?,
      pricePerUnit: '${json['price_per_unit'] ?? '0'}',
      quantityAvailable: '${json['quantity_available'] ?? '0'}',
      description: json['description'] as String?,
      imageUrl: ApiConfig.mediaUrl(json['image_url']?.toString()),
      thumbnailUrl:
          ApiConfig.mediaUrl(json['thumbnail_url']?.toString()) ??
          ApiConfig.mediaUrl(json['image_url']?.toString()),
      isActive: json['is_active'] == true || json['is_active'] == 1,
      status: json['status'] as String?,
      category: cropTypeMap == null ? null : CategoryItem.fromJson(cropTypeMap),
      sellerName:
          sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      sellerLocation: sellerMap?['location'] as String?,
      sellerId: _asCount(sellerMap?['id']),
      averageRating:
          json['average_rating']?.toString() ??
          sellerMap?['average_rating']?.toString(),
      reviewsCount:
          _asCount(json['reviews_count']) ??
          _asCount(sellerMap?['reviews_count']) ??
          0,
      tawad: tawadJson is Map && tawadJson['id'] != null
          ? TawadRule.fromJson(Map<String, dynamic>.from(tawadJson))
          : null,
      takedownReason: json['takedown_reason'] as String?,
      availableFrom: _asDate(json['available_from']),
      availableUntil: _asDate(json['available_until']),
      harvestedOn: _asDate(json['harvested_on']),
      isUpcoming: json['is_upcoming'] == true,
      canReserve: json.containsKey('can_reserve')
          ? json['can_reserve'] == true
          : null,
      tawadPaused: json['tawad_paused'] == true,
      availabilityState: json['availability_state'] as String?,
      growingMethod: json['growing_method'] as String?,
      organicBadge: json['organic_badge'] as String?,
      organicCertifier: json['organic_certifier'] as String?,
      distanceKm: _asDouble(json['distance_km']),
      reservedQuantity: json.containsKey('reserved_quantity')
          ? _asDouble(json['reserved_quantity']) ?? 0
          : null,
      activeReservationsCount: json.containsKey('active_reservations_count')
          ? _asCount(json['active_reservations_count']) ?? 0
          : null,
      minOrderQuantity: _asDouble(json['min_order_quantity']) ?? 1,
      orderStep: _asDouble(json['order_step']) ?? 1,
      sellableQuantity: json['sellable_quantity']?.toString(),
      acceptsOnlinePayment: _acceptsOnline(
        sellerMap?['accepts_online_payment'],
      ),
      needsActualHarvest: json['needs_actual_harvest'] == true,
      expiredWithStock: json['expired_with_stock'] == true,
      hasHarvestRecords: json['has_harvest_records'] == true,
    );
  }

  static bool _acceptsOnline(Object? value) {
    if (value == null) {
      return true;
    }
    if (value is bool) {
      return value;
    }
    if (value is num) {
      return value != 0;
    }
    final text = value.toString().toLowerCase();
    return text != 'false' && text != '0';
  }

  static double? _asDouble(Object? value) {
    if (value == null) {
      return null;
    }
    if (value is num) {
      return value.toDouble();
    }
    return double.tryParse(value.toString());
  }

  static DateTime? _asDate(Object? value) {
    if (value == null) {
      return null;
    }
    return DateTime.tryParse(value.toString());
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

class PaymentQrCode {
  const PaymentQrCode({
    required this.id,
    required this.wallet,
    required this.accountName,
    required this.accountLast4,
    this.walletLabel,
    this.imageUrl,
  });

  final int id;
  final String wallet;
  final String? walletLabel;
  final String accountName;
  final String accountLast4;
  final String? imageUrl;

  factory PaymentQrCode.fromJson(Map<String, dynamic> json) {
    return PaymentQrCode(
      id: ListingItem._asCount(json['id']) ?? 0,
      wallet: json['wallet'] as String? ?? '',
      walletLabel: json['wallet_label'] as String?,
      accountName: json['account_name'] as String? ?? '',
      accountLast4: json['account_last4']?.toString() ?? '',
      imageUrl: json['image_url'] as String?,
    );
  }
}

class PaymentProofRecord {
  const PaymentProofRecord({
    this.id,
    this.reference,
    this.amount,
    this.wallet,
    this.accountLast4,
    this.status,
    this.rejectionReason,
    this.rejectionNote,
    this.hasScreenshot = false,
    this.screenshotUrl,
    this.sentAt,
    this.reviewedAt,
  });

  final int? id;
  final String? reference;
  final String? amount;
  final String? wallet;
  final String? accountLast4;
  final String? status;
  final String? rejectionReason;
  final String? rejectionNote;
  final bool hasScreenshot;
  final String? screenshotUrl;
  final DateTime? sentAt;
  final DateTime? reviewedAt;

  bool get isPending => status == 'pending';
  bool get isRejected => status == 'rejected';

  factory PaymentProofRecord.fromJson(Map<String, dynamic> json) {
    return PaymentProofRecord(
      id: ListingItem._asCount(json['id']),
      reference: json['reference_number'] as String?,
      amount: json['amount']?.toString(),
      wallet: json['wallet'] as String?,
      status: json['status'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      rejectionNote: json['rejection_note'] as String?,
      accountLast4: json['account_last4']?.toString(),
      hasScreenshot: json['has_screenshot'] == true || json['has_screenshot'] == 1,
      screenshotUrl: json['screenshot_url'] as String?,
      sentAt: OrderRecord._asDate(json['sent_at']),
      reviewedAt: OrderRecord._asDate(json['reviewed_at']),
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
    this.sellerAvatarUrl,
    this.buyerAvatarUrl,
    this.sellerId,
    this.buyerId,
    this.placedAt,
    this.confirmedAt,
    this.readyAt,
    this.completedAt,
    this.cancelledAt,
    this.canBeReviewed = false,
    this.reviewRating,
    this.cancellationReason,
    this.cancellationLabel,
    this.cancellationNote,
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
    this.paymentStatus,
    this.paymentDueAt,
    this.paidAt,
    this.refundReference,
    this.refundedAt,
    this.latestProof,
    this.paymentQrs = const [],
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
  final String? sellerAvatarUrl;
  final String? buyerAvatarUrl;
  final int? sellerId;
  final int? buyerId;
  final String? placedAt;
  final String? confirmedAt;
  final String? readyAt;
  final String? completedAt;
  final DateTime? cancelledAt;
  final bool canBeReviewed;
  final int? reviewRating;
  final String? cancellationReason;
  final String? cancellationLabel;
  final String? cancellationNote;
  final String? amountReceived;
  final String? source;
  final bool isWalkIn;
  final String? walkInBuyerName;
  final String? paymentStatus;
  final DateTime? paymentDueAt;
  final DateTime? paidAt;
  final String? refundReference;
  final DateTime? refundedAt;
  final PaymentProofRecord? latestProof;
  final List<PaymentQrCode> paymentQrs;
  final List<String> allowedNext;
  final List<OrderItemRow> items;

  static DateTime? _asDate(Object? value) {
    if (value is! String || value.isEmpty) {
      return null;
    }
    return DateTime.tryParse(value)?.toLocal();
  }

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
      id: ListingItem._asCount(json['id']) ?? 0,
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
      counterpartyName:
          buyerMap?['name'] as String? ??
          sellerMap?['shop_name'] as String? ??
          sellerMap?['name'] as String?,
      shopName:
          sellerMap?['shop_name'] as String? ?? sellerMap?['name'] as String?,
      location: locationParts.isEmpty ? null : locationParts.join(', '),
      contact:
          sellerMap?['contact'] as String? ??
          sellerMap?['phone'] as String? ??
          buyerMap?['contact'] as String? ??
          buyerMap?['phone'] as String?,
      sellerAvatarUrl: ApiConfig.mediaUrl(sellerMap?['avatar_url'] as String?),
      buyerAvatarUrl: ApiConfig.mediaUrl(buyerMap?['avatar_url'] as String?),
      sellerId: ListingItem._asCount(sellerMap?['id']),
      buyerId: ListingItem._asCount(buyerMap?['id']),
      placedAt: json['placed_at'] as String?,
      confirmedAt: json['confirmed_at'] as String?,
      readyAt: json['ready_at'] as String?,
      completedAt: json['completed_at'] as String?,
      cancelledAt: _asDate(json['cancelled_at']),
      canBeReviewed: json['can_be_reviewed'] == true,
      reviewRating: reviewMap == null
          ? null
          : ListingItem._asCount(reviewMap['rating']),
      cancellationReason: json['cancellation_reason'] as String?,
      cancellationLabel: json['cancellation_label'] as String?,
      cancellationNote: json['cancellation_note'] as String?,
      amountReceived: json['amount_received']?.toString(),
      source: json['source'] as String?,
      isWalkIn: json['is_walk_in'] == true || json['is_walk_in'] == 1,
      walkInBuyerName: json['walk_in_buyer_name'] as String?,
      paymentStatus: json['payment_status'] as String?,
      paymentDueAt: _asDate(json['payment_due_at']),
      paidAt: _asDate(json['paid_at']),
      refundReference: json['refund_reference'] as String?,
      refundedAt: _asDate(json['refunded_at']),
      latestProof: json['latest_proof'] is Map
          ? PaymentProofRecord.fromJson(
              Map<String, dynamic>.from(json['latest_proof'] as Map),
            )
          : null,
      paymentQrs: ((json['payment_qrs'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => PaymentQrCode.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      allowedNext: ((json['allowed_next'] as List?) ?? const [])
          .map((item) => item.toString())
          .toList(),
      items: _jsonMaps(json['items']).map(OrderItemRow.fromJson).toList(),
    );
  }

  static List<Map<String, dynamic>> _jsonMaps(Object? value) {
    final raw = value is List
        ? value
        : value is Map && value['data'] is List
        ? value['data'] as List
        : const [];
    return raw
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  String get stallName => shopName ?? counterpartyName ?? 'Stall';

  String chatPeerTitle({required bool viewingAsSeller}) {
    return viewingAsSeller ? buyerName : stallName;
  }

  String? chatPeerAvatar({required bool viewingAsSeller}) {
    return viewingAsSeller ? buyerAvatarUrl : sellerAvatarUrl;
  }

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

  bool get isPaymentTracked {
    final status = paymentStatus;
    return status != null && status.isNotEmpty && status != 'not_tracked';
  }

  bool get isAwaitingPayment => paymentStatus == 'awaiting_payment';

  bool get isPaymentSent => paymentStatus == 'payment_sent';

  bool get paymentIsPaid => paymentStatus == 'paid';

  bool get buyerCancelLocked => isPaymentSent || paymentIsPaid;

  bool get canReportPaymentProblem {
    return paymentStatus == 'payment_sent' ||
        paymentStatus == 'paid' ||
        paymentStatus == 'refund_due' ||
        paymentStatus == 'refunded';
  }

  bool get hasCancellationReason =>
      isCancelled &&
      ((cancellationReason != null && cancellationReason!.isNotEmpty) ||
          (cancellationLabel != null && cancellationLabel!.isNotEmpty));

  bool get hasCancellationNote =>
      isCancelled &&
      cancellationNote != null &&
      cancellationNote!.trim().isNotEmpty;

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
      : paymentMethod == 'online_transfer'
      ? "Online payment (seller's QR)"
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
    int? reviewRating,
    String? cancellationReason,
    String? cancellationLabel,
    String? cancellationNote,
    String? amountReceived,
    String? paymentStatus,
    DateTime? paymentDueAt,
    PaymentProofRecord? latestProof,
    List<PaymentQrCode>? paymentQrs,
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
      sellerAvatarUrl: sellerAvatarUrl,
      buyerAvatarUrl: buyerAvatarUrl,
      sellerId: sellerId,
      buyerId: buyerId,
      placedAt: placedAt,
      confirmedAt: confirmedAt,
      readyAt: readyAt,
      completedAt: completedAt,
      cancelledAt: cancelledAt,
      canBeReviewed: canBeReviewed ?? this.canBeReviewed,
      reviewRating: reviewRating ?? this.reviewRating,
      cancellationReason: cancellationReason ?? this.cancellationReason,
      cancellationLabel: cancellationLabel ?? this.cancellationLabel,
      cancellationNote: cancellationNote ?? this.cancellationNote,
      amountReceived: amountReceived ?? this.amountReceived,
      source: source,
      isWalkIn: isWalkIn,
      walkInBuyerName: walkInBuyerName,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      paymentDueAt: paymentDueAt ?? this.paymentDueAt,
      paidAt: paidAt,
      refundReference: refundReference,
      refundedAt: refundedAt,
      latestProof: latestProof ?? this.latestProof,
      paymentQrs: paymentQrs ?? this.paymentQrs,
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

  String get unitLabel => listing?.unit ?? listing?.unitLabel ?? '';

  bool get isPurchasable => listing != null && listing!.isSellerActive;

  factory CartLine.fromJson(Map<String, dynamic> json) {
    final listingJson = json['listing'];
    return CartLine(
      id: ListingItem._asCount(json['id']) ?? 0,
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

  bool get acceptsOnlinePayment =>
      items.isEmpty ||
      items.every((item) => item.listing?.acceptsOnlinePayment ?? true);

  double _sum(String Function(CartLine) read) {
    return items.fold<double>(
      0,
      (sum, item) => sum + (double.tryParse(read(item)) ?? 0),
    );
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
      final name = groupsBySeller.isEmpty
          ? 'this seller'
          : groupsBySeller.first.sellerName;
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
      id: ListingItem._asCount(json['id']) ?? 0,
      listingId: ListingItem._asCount(json['listing_id']) ?? 0,
      listing: listingJson is Map<String, dynamic>
          ? ListingItem.fromJson(listingJson)
          : listingJson is Map
          ? ListingItem.fromJson(Map<String, dynamic>.from(listingJson))
          : null,
    );
  }
}

class ShopFavoriteRecord {
  const ShopFavoriteRecord({
    required this.id,
    required this.sellerId,
    this.shop,
  });

  final int id;
  final int sellerId;
  final ShopProfile? shop;

  factory ShopFavoriteRecord.fromJson(Map<String, dynamic> json) {
    final shopJson = json['shop'];
    return ShopFavoriteRecord(
      id: ListingItem._asCount(json['id']) ?? 0,
      sellerId: ListingItem._asCount(json['farmer_seller_id']) ?? 0,
      shop: shopJson is Map
          ? ShopProfile.fromJson(Map<String, dynamic>.from(shopJson))
          : null,
    );
  }
}

class FarmFavoriteRecord {
  const FarmFavoriteRecord({
    required this.id,
    required this.farmId,
    required this.name,
    this.place,
    this.coverUrl,
    this.sellersCount = 0,
  });

  final int id;
  final int farmId;
  final String name;
  final String? place;
  final String? coverUrl;
  final int sellersCount;

  factory FarmFavoriteRecord.fromJson(Map<String, dynamic> json) {
    return FarmFavoriteRecord(
      id: ListingItem._asCount(json['id']) ?? 0,
      farmId: ListingItem._asCount(json['farm_id']) ?? 0,
      name: json['name'] as String? ?? 'Farm',
      place: json['place'] as String?,
      coverUrl: ApiConfig.mediaUrl(
        json['thumbnail_url'] as String? ?? json['cover_photo_url'] as String?,
      ),
      sellersCount: ListingItem._asCount(json['sellers_count']) ?? 0,
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
    this.thumbnailUrl,
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
  final String? thumbnailUrl;
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
    final farmMap = farmJson is Map
        ? Map<String, dynamic>.from(farmJson)
        : null;
    return CropCareArticle(
      id: ListingItem._asCount(json['id']) ?? 0,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      summary: json['summary'] as String? ?? '',
      slug: json['slug'] as String?,
      category: json['category'] as String? ?? '',
      categoryLabel: json['category_label'] as String? ?? '',
      authorName: json['author_name'] as String?,
      imageUrl: ApiConfig.mediaUrl(json['image_url']?.toString()),
      thumbnailUrl:
          ApiConfig.mediaUrl(json['thumbnail_url']?.toString()) ??
          ApiConfig.mediaUrl(json['image_url']?.toString()),
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

  static const cropCare = CropCareCategory(
    value: 'crop_care',
    label: 'Crop care',
  );
  static const pestManagement = CropCareCategory(
    value: 'pest_management',
    label: 'Pest management',
  );
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
    this.avatarUrl,
    this.coverUrl,
    this.averageRating,
    this.reviewsCount = 0,
    this.listings = const [],
    this.farmId,
    this.farmName,
    this.farmBarangay,
    this.farmMunicipality,
    this.farmIsActive = false,
    this.farmIsFavorited = false,
    this.farmFavoritesCount = 0,
    this.farmCoverUrl,
    this.farmLogoUrl,
    this.acceptsOnlinePayment = true,
    this.paymentTimeLimitHours = 24,
    this.paymentQrs = const [],
    this.isFavorited = false,
    this.distanceKm,
  });

  final int id;
  final String shopName;
  final String name;
  final String? bio;
  final String? location;
  final String? contact;
  final String? avatarUrl;
  final String? coverUrl;
  final String? averageRating;
  final int reviewsCount;
  final List<ListingItem> listings;
  final int? farmId;
  final String? farmName;
  final String? farmBarangay;
  final String? farmMunicipality;
  final bool farmIsActive;
  final bool farmIsFavorited;
  final int farmFavoritesCount;
  final String? farmCoverUrl;
  final String? farmLogoUrl;
  final bool acceptsOnlinePayment;
  final int paymentTimeLimitHours;
  final List<PaymentQrCode> paymentQrs;
  final bool isFavorited;
  final double? distanceKm;

  bool get hasRating =>
      reviewsCount > 0 && averageRating != null && averageRating!.isNotEmpty;

  factory ShopProfile.fromJson(Map<String, dynamic> json) {
    final farmJson = json['farm'];
    final farmMap = farmJson is Map
        ? Map<String, dynamic>.from(farmJson)
        : null;
    return ShopProfile(
      id: ListingItem._asCount(json['id']) ?? 0,
      shopName: json['shop_name'] as String? ?? json['name'] as String? ?? '',
      name: json['name'] as String? ?? '',
      bio: json['bio'] as String?,
      location: json['location'] as String?,
      contact: json['contact'] as String?,
      avatarUrl: ApiConfig.mediaUrl(json['avatar_url'] as String?),
      coverUrl: ApiConfig.mediaUrl(json['cover_url'] as String?),
      averageRating: json['average_rating']?.toString(),
      reviewsCount: ListingItem._asCount(json['reviews_count']) ?? 0,
      listings: ((json['listings'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => ListingItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      farmId: ListingItem._asCount(farmMap?['id']),
      farmName: farmMap?['name'] as String?,
      farmBarangay: farmMap?['barangay'] as String?,
      farmMunicipality: farmMap?['municipality'] as String?,
      farmIsActive:
          farmMap != null &&
          (farmMap['is_active'] == true ||
              farmMap['is_active'] == 1 ||
              farmMap['is_active'] == '1'),
      farmIsFavorited:
          farmMap?['is_favorited'] == true || farmMap?['is_favorited'] == 1,
      farmFavoritesCount:
          ListingItem._asCount(farmMap?['favorites_count']) ?? 0,
      farmCoverUrl: ApiConfig.mediaUrl(farmMap?['cover_photo_url'] as String?),
      farmLogoUrl: ApiConfig.mediaUrl(() {
        final thumbnail = farmMap?['logo_thumbnail_url'];
        final logo = farmMap?['logo_url'];
        final chosen = thumbnail ?? logo;
        return chosen is String ? chosen : null;
      }()),
      acceptsOnlinePayment: ListingItem._acceptsOnline(
        json['accepts_online_payment'],
      ),
      paymentTimeLimitHours:
          ListingItem._asCount(json['payment_time_limit_hours']) ?? 24,
      paymentQrs: ((json['payment_qrs'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => PaymentQrCode.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      isFavorited: json['is_favorited'] == true || json['is_favorited'] == 1,
      distanceKm: ListingItem._asDouble(json['distance_km']),
    );
  }

  ShopProfile copyWith({bool? isFavorited}) {
    return ShopProfile(
      id: id,
      shopName: shopName,
      name: name,
      bio: bio,
      location: location,
      contact: contact,
      avatarUrl: avatarUrl,
      coverUrl: coverUrl,
      averageRating: averageRating,
      reviewsCount: reviewsCount,
      listings: listings,
      farmId: farmId,
      farmName: farmName,
      farmBarangay: farmBarangay,
      farmMunicipality: farmMunicipality,
      farmIsActive: farmIsActive,
      farmIsFavorited: farmIsFavorited,
      farmFavoritesCount: farmFavoritesCount,
      farmCoverUrl: farmCoverUrl,
      farmLogoUrl: farmLogoUrl,
      acceptsOnlinePayment: acceptsOnlinePayment,
      paymentTimeLimitHours: paymentTimeLimitHours,
      paymentQrs: paymentQrs,
      isFavorited: isFavorited ?? this.isFavorited,
      distanceKm: distanceKm,
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
    this.imageUrl,
  });

  final int id;
  final String title;
  final String body;
  final String? audience;
  final String? startsAt;
  final String? endsAt;
  final bool isPinned;
  final String? createdAt;
  final String? imageUrl;

  factory FarmAnnouncement.fromJson(Map<String, dynamic> json) {
    return FarmAnnouncement(
      id: ListingItem._asCount(json['id']) ?? 0,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      audience: json['audience'] as String?,
      startsAt: json['starts_at'] as String?,
      endsAt: json['ends_at'] as String?,
      isPinned:
          json['is_pinned'] == true ||
          json['is_pinned'] == 1 ||
          json['is_pinned'] == '1',
      createdAt: json['created_at'] as String?,
      imageUrl: ApiConfig.mediaUrl(json['image_url'] as String?),
    );
  }
}

class BuyerFarmAnnouncement {
  const BuyerFarmAnnouncement({
    required this.id,
    required this.title,
    required this.body,
    required this.farmId,
    required this.farmName,
    this.isPinned = false,
    this.imageUrl,
    this.publishedAt,
    this.farmCoverUrl,
  });

  final int id;
  final String title;
  final String body;
  final int farmId;
  final String farmName;
  final bool isPinned;
  final String? imageUrl;
  final String? publishedAt;
  final String? farmCoverUrl;

  factory BuyerFarmAnnouncement.fromJson(Map<String, dynamic> json) {
    final farm = json['farm'];
    final farmMap = farm is Map
        ? Map<String, dynamic>.from(farm)
        : const <String, dynamic>{};
    return BuyerFarmAnnouncement(
      id: ListingItem._asCount(json['id']) ?? 0,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      isPinned:
          json['is_pinned'] == true ||
          json['is_pinned'] == 1 ||
          json['is_pinned'] == '1',
      imageUrl: ApiConfig.mediaUrl(json['image_url']?.toString()),
      publishedAt: json['published_at'] as String?,
      farmId: ListingItem._asCount(farmMap['id']) ?? 0,
      farmName: farmMap['name'] as String? ?? '',
      farmCoverUrl: ApiConfig.mediaUrl(farmMap['cover_url']?.toString()),
    );
  }
}

class PagedBuyerAnnouncements {
  const PagedBuyerAnnouncements({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  final List<BuyerFarmAnnouncement> items;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;
}

class FarmPhotoItem {
  const FarmPhotoItem({
    required this.id,
    required this.url,
    this.thumbnailUrl,
    this.caption,
  });

  final int id;
  final String url;
  final String? thumbnailUrl;
  final String? caption;

  String get gridUrl {
    final thumb = thumbnailUrl?.trim();
    if (thumb != null && thumb.isNotEmpty) {
      return thumb;
    }
    return url;
  }

  factory FarmPhotoItem.fromJson(Map<String, dynamic> json) {
    return FarmPhotoItem(
      id: ListingItem._asCount(json['id']) ?? 0,
      url: ApiConfig.mediaUrl(json['url'] as String?) ?? '',
      thumbnailUrl:
          ApiConfig.mediaUrl(json['thumbnail_url']?.toString()) ??
          ApiConfig.mediaUrl(json['url'] as String?),
      caption: json['caption'] as String?,
    );
  }
}

class FarmStorefront {
  const FarmStorefront({required this.id, required this.shopName, this.avatar});

  final int id;
  final String shopName;
  final String? avatar;

  factory FarmStorefront.fromJson(Map<String, dynamic> json) {
    return FarmStorefront(
      id: ListingItem._asCount(json['id']) ?? 0,
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
    this.logoUrl,
    this.logoThumbnailUrl,
    this.isActive = true,
    this.photos = const [],
    this.farmerSellersCount = 0,
    this.storefronts = const [],
    this.announcements = const [],
    this.latitude,
    this.longitude,
    this.isFavorited = false,
    this.favoritesCount = 0,
    this.isOrganicCertified = false,
    this.organicCertifier,
    this.organicCertifiedUntil,
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
  final String? logoUrl;
  final String? logoThumbnailUrl;
  final bool isActive;
  final List<FarmPhotoItem> photos;
  final int farmerSellersCount;
  final List<FarmStorefront> storefronts;
  final List<FarmAnnouncement> announcements;
  final double? latitude;
  final double? longitude;
  final bool isFavorited;
  final int favoritesCount;
  final bool isOrganicCertified;
  final String? organicCertifier;
  final String? organicCertifiedUntil;

  bool get isCertified => isOrganicCertified;

  /// A pin opens turn-by-turn maps. A barangay or municipality opens search.
  bool get canGetDirections => hasPin || placeLabel.isNotEmpty;

  Uri? get directionsUri {
    if (hasPin) {
      return Uri.parse(
        'https://www.google.com/maps/dir/?api=1&destination=$latitude,$longitude',
      );
    }
    if (placeLabel.isEmpty) {
      return null;
    }
    final parts = <String>[
      if (name.trim().isNotEmpty) name.trim(),
      if ((barangay?.trim() ?? '').isNotEmpty) barangay!.trim(),
      if ((municipality?.trim() ?? '').isNotEmpty) municipality!.trim(),
      'Cavite',
    ];
    return Uri.parse(
      'https://www.google.com/maps/search/?api=1&query=${Uri.encodeComponent(parts.join(', '))}',
    );
  }

  bool get hasCoverPhoto => coverPhotoUrl != null && coverPhotoUrl!.isNotEmpty;

  String? get logoImageUrl {
    for (final candidate in [logoUrl, logoThumbnailUrl]) {
      final value = candidate?.trim();
      if (value != null && value.isNotEmpty) {
        return value;
      }
    }
    return null;
  }

  bool get hasPin => latitude != null && longitude != null;

  String get placeLabel {
    final parts = [barangay, municipality]
        .map((part) => part?.trim() ?? '')
        .where((part) => part.isNotEmpty)
        .toList();
    return parts.join(', ');
  }

  factory FarmProfile.fromJson(Map<String, dynamic> json) {
    return FarmProfile(
      id: ListingItem._asCount(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String?,
      description: json['description'] as String?,
      contactPerson: json['contact_person'] as String?,
      contactNumber: json['contact_number'] as String?,
      barangay: json['barangay'] as String?,
      municipality: json['municipality'] as String?,
      pickupPoint: json['pickup_point'] as String?,
      coverPhotoUrl: ApiConfig.mediaUrl(json['cover_photo_url'] as String?),
      logoUrl: ApiConfig.mediaUrl(json['logo_url'] as String?),
      logoThumbnailUrl: ApiConfig.mediaUrl(
        json['logo_thumbnail_url'] as String?,
      ),
      isActive:
          json['is_active'] == true ||
          json['is_active'] == 1 ||
          json['is_active'] == '1',
      photos: ((json['photos'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmPhotoItem.fromJson(Map<String, dynamic>.from(item)),
          )
          .where((photo) => photo.url.isNotEmpty)
          .toList(),
      farmerSellersCount:
          ListingItem._asCount(json['farmer_sellers_count']) ?? 0,
      storefronts: ((json['storefronts'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmStorefront.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      announcements: ((json['announcements'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                FarmAnnouncement.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      latitude: ListingItem._asDouble(json['latitude']),
      longitude: ListingItem._asDouble(json['longitude']),
      isFavorited: json['is_favorited'] == true || json['is_favorited'] == 1,
      favoritesCount: ListingItem._asCount(json['favorites_count']) ?? 0,
      isOrganicCertified:
          json['is_organic_certified'] == true ||
          json['is_organic_certified'] == 1,
      organicCertifier: json['organic_certifier'] as String?,
      organicCertifiedUntil: json['organic_certified_until']?.toString(),
    );
  }
}

class ShopReview {
  const ShopReview({
    required this.id,
    required this.rating,
    required this.reviewerName,
    this.isOwn = false,
    this.comment,
    this.createdAt,
  });

  final int id;
  final int rating;
  final String reviewerName;
  final bool isOwn;
  final String? comment;
  final String? createdAt;

  factory ShopReview.fromJson(Map<String, dynamic> json) {
    return ShopReview(
      id: ListingItem._asCount(json['id']) ?? 0,
      rating: ListingItem._asCount(json['rating']) ?? 0,
      reviewerName: json['buyer_name'] as String? ?? 'Buyer',
      isOwn: json['is_own'] == true || json['is_own'] == 1,
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

class ReservationRecord {
  const ReservationRecord({
    required this.id,
    required this.listingName,
    required this.quantity,
    required this.lineTotal,
    required this.status,
    this.listingId,
    this.unit,
    this.unitPrice,
    this.orderId,
    this.fulfillmentPreference,
    this.cancellationReason,
    this.buyerName,
    this.createdAt,
    this.paymentStatus,
    this.paymentDueAt,
    this.paidAt,
    this.refundReference,
    this.refundedAt,
    this.latestProof,
    this.paymentQrs = const [],
  });

  final int id;
  final int? listingId;
  final String listingName;
  final double quantity;
  final String? unit;
  final double? unitPrice;
  final double lineTotal;
  final String status;
  final int? orderId;
  final String? fulfillmentPreference;
  final String? cancellationReason;
  final String? buyerName;
  final DateTime? createdAt;
  final String? paymentStatus;
  final DateTime? paymentDueAt;
  final DateTime? paidAt;
  final String? refundReference;
  final DateTime? refundedAt;
  final PaymentProofRecord? latestProof;
  final List<PaymentQrCode> paymentQrs;

  bool get isActive => status == 'active';

  bool get isConverted => status == 'converted' && orderId != null;

  bool get isPaymentTracked {
    final payment = paymentStatus;
    return payment != null && payment.isNotEmpty && payment != 'not_tracked';
  }

  bool get isAwaitingPayment => paymentStatus == 'awaiting_payment';

  bool get isPaymentSent => paymentStatus == 'payment_sent';

  bool get paymentIsPaid => paymentStatus == 'paid';

  bool get canBuyerCancel {
    if (!isActive) {
      return false;
    }
    final payment = paymentStatus;
    return payment == null ||
        payment.isEmpty ||
        payment == 'awaiting_payment' ||
        payment == 'not_tracked';
  }

  String get amountLabel => lineTotal.toStringAsFixed(2);

  factory ReservationRecord.fromJson(Map<String, dynamic> json) {
    final buyer = json['buyer'];
    final buyerMap = buyer is Map ? Map<String, dynamic>.from(buyer) : null;
    return ReservationRecord(
      id: ListingItem._asCount(json['id']) ?? 0,
      listingId: ListingItem._asCount(json['listing_id']),
      listingName: json['listing_name'] as String? ?? '',
      quantity: ListingItem._asDouble(json['quantity']) ?? 0,
      unit: json['unit'] as String?,
      unitPrice: ListingItem._asDouble(json['unit_price']),
      lineTotal: ListingItem._asDouble(json['line_total']) ?? 0,
      status: json['status'] as String? ?? '',
      orderId: ListingItem._asCount(json['order_id']),
      fulfillmentPreference: json['fulfillment_preference'] as String?,
      cancellationReason: json['cancellation_reason'] as String?,
      buyerName: buyerMap?['name'] as String?,
      createdAt: ListingItem._asDate(json['created_at']),
      paymentStatus: json['payment_status'] as String?,
      paymentDueAt: OrderRecord._asDate(json['payment_due_at']),
      paidAt: OrderRecord._asDate(json['paid_at']),
      refundReference: json['refund_reference'] as String?,
      refundedAt: OrderRecord._asDate(json['refunded_at']),
      latestProof: json['latest_proof'] is Map
          ? PaymentProofRecord.fromJson(
              Map<String, dynamic>.from(json['latest_proof'] as Map),
            )
          : null,
      paymentQrs: ((json['payment_qrs'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => PaymentQrCode.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }

  ReservationRecord copyWith({
    String? paymentStatus,
    DateTime? paymentDueAt,
    DateTime? paidAt,
    String? refundReference,
    DateTime? refundedAt,
    PaymentProofRecord? latestProof,
    List<PaymentQrCode>? paymentQrs,
    String? status,
  }) {
    return ReservationRecord(
      id: id,
      listingName: listingName,
      quantity: quantity,
      lineTotal: lineTotal,
      status: status ?? this.status,
      listingId: listingId,
      unit: unit,
      unitPrice: unitPrice,
      orderId: orderId,
      fulfillmentPreference: fulfillmentPreference,
      cancellationReason: cancellationReason,
      buyerName: buyerName,
      createdAt: createdAt,
      paymentStatus: paymentStatus ?? this.paymentStatus,
      paymentDueAt: paymentDueAt ?? this.paymentDueAt,
      paidAt: paidAt ?? this.paidAt,
      refundReference: refundReference ?? this.refundReference,
      refundedAt: refundedAt ?? this.refundedAt,
      latestProof: latestProof ?? this.latestProof,
      paymentQrs: paymentQrs ?? this.paymentQrs,
    );
  }
}

class PaymentListItem {
  const PaymentListItem({
    required this.kind,
    required this.id,
    required this.buyerName,
    required this.title,
    required this.amount,
    this.items = const [],
    this.wallet,
    this.reference,
    this.sentAt,
    this.paidAt,
    this.statusAt,
    this.orderId,
    this.reservationId,
  });

  final String kind;
  final int id;
  final String buyerName;
  final String title;
  final List<String> items;
  final String amount;
  final String? wallet;
  final String? reference;
  final DateTime? sentAt;
  final DateTime? paidAt;
  final DateTime? statusAt;
  final int? orderId;
  final int? reservationId;

  bool get isReservation => kind == 'reservation';

  factory PaymentListItem.fromJson(Map<String, dynamic> json) {
    final names = json['items'];
    return PaymentListItem(
      kind: json['kind'] as String? ?? 'order',
      id: ListingItem._asCount(json['id']) ?? 0,
      buyerName: json['buyer_name'] as String? ?? '',
      title: json['title'] as String? ?? '',
      items: names is List
          ? names.map((item) => item.toString()).toList()
          : const [],
      amount: '${json['amount'] ?? '0'}',
      wallet: json['wallet'] as String?,
      reference: json['reference'] as String?,
      sentAt: OrderRecord._asDate(json['sent_at']),
      paidAt: OrderRecord._asDate(json['paid_at']),
      statusAt: OrderRecord._asDate(json['status_at']),
      orderId: ListingItem._asCount(json['order_id']),
      reservationId: ListingItem._asCount(json['reservation_id']),
    );
  }
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

  bool get pointsToStockHistory =>
      type == 'harvest_reminder' || type == 'expired_stock_left';

  bool get pointsToListing {
    final related = relatedType ?? '';
    return type == 'listing_low_stock' ||
        type == 'harvest_reminder' ||
        type == 'expired_stock_left' ||
        related == 'listing' ||
        related.endsWith('Listing');
  }

  bool get pointsToAnnouncement {
    final related = relatedType ?? '';
    return type == 'farm_announcement' ||
        related == 'farm_announcement' ||
        related.endsWith('FarmAnnouncement');
  }

  bool get isReportNotice =>
      type == 'report_submitted' ||
      type == 'report_resolved' ||
      type == 'report_dismissed';

  bool get isPaymentNotice {
    return type == 'payment_proof_submitted' ||
        type == 'payment_confirmed' ||
        type == 'payment_rejected' ||
        type == 'payment_due_soon' ||
        type == 'payment_expired' ||
        type == 'payment_check_reminder' ||
        type == 'refund_due' ||
        type == 'refund_completed';
  }

  bool get pointsToReservation {
    final related = relatedType ?? '';
    return related == 'reservation' || related.endsWith('Reservation');
  }

  bool get pointsToOrder {
    if (pointsToReservation) {
      return false;
    }
    final related = relatedType ?? '';
    return type == 'order_placed' ||
        type == 'order_awaiting_confirmation' ||
        type == 'order_confirmed' ||
        type == 'order_ready' ||
        type == 'order_completed' ||
        type == 'order_cancelled' ||
        type == 'order_message' ||
        isPaymentNotice ||
        related == 'order' ||
        type == 'reservation_converted' ||
        related.endsWith('Order');
  }

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: ListingItem._asCount(json['id']) ?? 0,
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

class ChatAttachment {
  const ChatAttachment({
    required this.name,
    required this.mime,
    required this.size,
    required this.url,
    this.thumbnailUrl,
  });

  final String name;
  final String mime;
  final int size;
  final String url;
  final String? thumbnailUrl;

  bool get isPhoto => mime.startsWith('image/');

  factory ChatAttachment.fromJson(Map<String, dynamic> json) {
    final size = json['size'];
    return ChatAttachment(
      name: json['name'] as String? ?? 'Attachment',
      mime: json['mime'] as String? ?? '',
      size: size is int
          ? size
          : size is num
          ? size.toInt()
          : int.tryParse('$size') ?? 0,
      url: json['url'] as String? ?? '',
      thumbnailUrl: json['thumbnail_url'] as String?,
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
    this.authorAvatarUrl,
    this.createdAt,
    this.orderId,
    this.orderNumber,
    this.listingId,
    this.listingTitle,
    this.listingPrice,
    this.listingUnit,
    this.listingThumbnailUrl,
    this.attachment,
  });

  final int id;
  final String body;
  final int authorId;
  final String authorName;
  final String? authorRole;
  final String? authorAvatarUrl;
  final String? createdAt;
  final int? orderId;
  final String? orderNumber;
  final int? listingId;
  final String? listingTitle;
  final String? listingPrice;
  final String? listingUnit;
  final String? listingThumbnailUrl;
  final ChatAttachment? attachment;

  bool get hasProductCard =>
      listingTitle != null && listingTitle!.trim().isNotEmpty;

  factory OrderMessage.fromJson(Map<String, dynamic> json) {
    final author = json['author'];
    final authorMap = author is Map ? Map<String, dynamic>.from(author) : null;
    return OrderMessage(
      id: ListingItem._asCount(json['id']) ?? 0,
      body: json['body'] as String? ?? '',
      authorId: ListingItem._asCount(authorMap?['id']) ?? 0,
      authorName: authorMap?['name'] as String? ?? 'Someone',
      authorRole: authorMap?['role'] as String?,
      authorAvatarUrl: ApiConfig.mediaUrl(authorMap?['avatar_url'] as String?),
      createdAt: json['created_at'] as String?,
      orderId: ListingItem._asCount(json['order_id']),
      orderNumber: json['order_number'] as String?,
      listingId: ListingItem._asCount(json['listing_id']),
      listingTitle: json['listing_title'] as String?,
      listingPrice: json['listing_price_per_unit']?.toString(),
      listingUnit: json['listing_unit'] as String?,
      listingThumbnailUrl: ApiConfig.mediaUrl(
        json['listing_thumbnail_url'] as String?,
      ),
      attachment: json['attachment'] is Map
          ? ChatAttachment.fromJson(
              Map<String, dynamic>.from(json['attachment'] as Map),
            )
          : null,
    );
  }
}

class StallChat {
  const StallChat({
    required this.id,
    required this.sellerId,
    required this.shopName,
    required this.buyerName,
    this.buyerId,
    this.sellerAvatarUrl,
    this.buyerAvatarUrl,
    this.updatedAt,
    this.latestBody,
    this.latestAt,
  });

  final int id;
  final int sellerId;
  final String shopName;
  final String buyerName;
  final int? buyerId;
  final String? sellerAvatarUrl;
  final String? buyerAvatarUrl;
  final String? updatedAt;
  final String? latestBody;
  final String? latestAt;

  String title({required bool viewingAsSeller}) {
    return viewingAsSeller ? buyerName : shopName;
  }

  String? avatarUrl({required bool viewingAsSeller}) {
    return viewingAsSeller ? buyerAvatarUrl : sellerAvatarUrl;
  }

  factory StallChat.fromJson(Map<String, dynamic> json) {
    final latest = json['latest_message'];
    final latestMap = latest is Map ? Map<String, dynamic>.from(latest) : null;
    return StallChat(
      id: ListingItem._asCount(json['id']) ?? 0,
      sellerId: ListingItem._asCount(json['farmer_seller_id']) ?? 0,
      shopName: json['shop_name'] as String? ?? 'Stall',
      buyerName: json['buyer_name'] as String? ?? 'Buyer',
      buyerId: ListingItem._asCount(json['buyer_id']),
      sellerAvatarUrl: ApiConfig.mediaUrl(json['seller_avatar_url'] as String?),
      buyerAvatarUrl: ApiConfig.mediaUrl(json['buyer_avatar_url'] as String?),
      updatedAt: json['updated_at'] as String?,
      latestBody: latestMap?['body'] as String?,
      latestAt: latestMap?['created_at'] as String?,
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

class FarmerAnalyticsRange {
  const FarmerAnalyticsRange({
    required this.key,
    required this.from,
    required this.to,
    required this.grouping,
    required this.category,
    required this.availableYears,
  });

  final String key;
  final String from;
  final String to;
  final String grouping;
  final String category;
  final List<int> availableYears;

  factory FarmerAnalyticsRange.fromJson(Map<String, dynamic> json) {
    return FarmerAnalyticsRange(
      key: json['key'] as String? ?? '',
      from: json['from'] as String? ?? '',
      to: json['to'] as String? ?? '',
      grouping: json['grouping'] as String? ?? 'day',
      category: json['category'] as String? ?? 'all',
      availableYears: ((json['available_years'] as List?) ?? const [])
          .map((year) => ListingItem._asCount(year) ?? 0)
          .where((year) => year > 0)
          .toList(),
    );
  }
}

class FarmerSalesTotals {
  const FarmerSalesTotals({
    required this.sales,
    required this.orders,
    required this.averageOrder,
    required this.tawadTotal,
    required this.averageTawad,
  });

  final double sales;
  final int orders;
  final double averageOrder;
  final double tawadTotal;
  final double averageTawad;

  factory FarmerSalesTotals.fromJson(Map<String, dynamic> json) {
    return FarmerSalesTotals(
      sales: _asDouble(json['sales']),
      orders: ListingItem._asCount(json['orders']) ?? 0,
      averageOrder: _asDouble(json['average_order']),
      tawadTotal: _asDouble(json['tawad_total']),
      averageTawad: _asDouble(json['average_tawad']),
    );
  }
}

class FarmerPieSlice {
  const FarmerPieSlice({
    required this.crop,
    required this.sales,
    required this.percent,
    this.cropTypeId,
  });

  final int? cropTypeId;
  final String crop;
  final double sales;
  final double percent;

  factory FarmerPieSlice.fromJson(Map<String, dynamic> json) {
    return FarmerPieSlice(
      cropTypeId: ListingItem._asCount(json['crop_type_id']),
      crop: json['crop'] as String? ?? '',
      sales: _asDouble(json['sales']),
      percent: _asDouble(json['percent']),
    );
  }
}

class FarmerPieOthers {
  const FarmerPieOthers({
    required this.crops,
    required this.sales,
    required this.percent,
  });

  final int crops;
  final double sales;
  final double percent;

  factory FarmerPieOthers.fromJson(Map<String, dynamic> json) {
    return FarmerPieOthers(
      crops: ListingItem._asCount(json['crops']) ?? 0,
      sales: _asDouble(json['sales']),
      percent: _asDouble(json['percent']),
    );
  }
}

class FarmerSalesPie {
  const FarmerSalesPie({required this.slices, this.others});

  final List<FarmerPieSlice> slices;
  final FarmerPieOthers? others;

  factory FarmerSalesPie.fromJson(Map<String, dynamic> json) {
    return FarmerSalesPie(
      slices: ((json['slices'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmerPieSlice.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      others: json['others'] is Map
          ? FarmerPieOthers.fromJson(Map<String, dynamic>.from(json['others'] as Map))
          : null,
    );
  }
}

class FarmerTopCrop {
  const FarmerTopCrop({
    required this.crop,
    required this.unit,
    required this.quantity,
    required this.sales,
    this.cropTypeId,
  });

  final int? cropTypeId;
  final String crop;
  final String unit;
  final double quantity;
  final double sales;

  factory FarmerTopCrop.fromJson(Map<String, dynamic> json) {
    return FarmerTopCrop(
      cropTypeId: ListingItem._asCount(json['crop_type_id']),
      crop: json['crop'] as String? ?? '',
      unit: json['unit'] as String? ?? '',
      quantity: _asDouble(json['quantity']),
      sales: _asDouble(json['sales']),
    );
  }
}

class FarmerSalesPeriod {
  const FarmerSalesPeriod({
    required this.key,
    required this.start,
    required this.end,
    required this.future,
    required this.orders,
    required this.sales,
  });

  final String key;
  final String start;
  final String end;
  final bool future;
  final int orders;
  final double sales;

  factory FarmerSalesPeriod.fromJson(Map<String, dynamic> json) {
    return FarmerSalesPeriod(
      key: json['key'] as String? ?? '',
      start: json['start'] as String? ?? '',
      end: json['end'] as String? ?? '',
      future: json['future'] == true,
      orders: ListingItem._asCount(json['orders']) ?? 0,
      sales: _asDouble(json['sales']),
    );
  }
}

class FarmerSalesBucket {
  const FarmerSalesBucket({required this.orders, required this.sales});

  final int orders;
  final double sales;

  factory FarmerSalesBucket.fromJson(Map<String, dynamic> json) {
    return FarmerSalesBucket(
      orders: ListingItem._asCount(json['orders']) ?? 0,
      sales: _asDouble(json['sales']),
    );
  }
}

class FarmerPaymentSplit {
  const FarmerPaymentSplit({required this.online, required this.cash});

  final FarmerSalesBucket online;
  final FarmerSalesBucket cash;

  factory FarmerPaymentSplit.fromJson(Map<String, dynamic> json) {
    return FarmerPaymentSplit(
      online: FarmerSalesBucket.fromJson(_mapOf(json['online'])),
      cash: FarmerSalesBucket.fromJson(_mapOf(json['cash'])),
    );
  }
}

class FarmerSourceSplit {
  const FarmerSourceSplit({required this.app, required this.walkIn});

  final FarmerSalesBucket app;
  final FarmerSalesBucket walkIn;

  factory FarmerSourceSplit.fromJson(Map<String, dynamic> json) {
    return FarmerSourceSplit(
      app: FarmerSalesBucket.fromJson(_mapOf(json['app'])),
      walkIn: FarmerSalesBucket.fromJson(_mapOf(json['walk_in'])),
    );
  }
}

class FarmerBestMonth {
  const FarmerBestMonth({required this.key, required this.sales});

  final String key;
  final double sales;

  factory FarmerBestMonth.fromJson(Map<String, dynamic> json) {
    return FarmerBestMonth(
      key: json['key'] as String? ?? '',
      sales: _asDouble(json['sales']),
    );
  }
}

class FarmerYearTotal {
  const FarmerYearTotal({
    required this.sales,
    required this.orders,
    this.bestMonth,
  });

  final double sales;
  final int orders;
  final FarmerBestMonth? bestMonth;

  factory FarmerYearTotal.fromJson(Map<String, dynamic> json) {
    return FarmerYearTotal(
      sales: _asDouble(json['sales']),
      orders: ListingItem._asCount(json['orders']) ?? 0,
      bestMonth: json['best_month'] is Map
          ? FarmerBestMonth.fromJson(
              Map<String, dynamic>.from(json['best_month'] as Map),
            )
          : null,
    );
  }
}

class FarmerSalesReport {
  const FarmerSalesReport({
    required this.totals,
    required this.pie,
    required this.topCrops,
    required this.perPeriod,
    required this.paymentSplit,
    required this.sourceSplit,
    this.yearTotal,
  });

  final FarmerSalesTotals totals;
  final FarmerSalesPie pie;
  final List<FarmerTopCrop> topCrops;
  final List<FarmerSalesPeriod> perPeriod;
  final FarmerPaymentSplit paymentSplit;
  final FarmerSourceSplit sourceSplit;
  final FarmerYearTotal? yearTotal;

  factory FarmerSalesReport.fromJson(Map<String, dynamic> json) {
    return FarmerSalesReport(
      totals: FarmerSalesTotals.fromJson(_mapOf(json['totals'])),
      pie: FarmerSalesPie.fromJson(_mapOf(json['pie'])),
      topCrops: ((json['top_crops'] as List?) ?? const [])
          .whereType<Map>()
          .map((item) => FarmerTopCrop.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      perPeriod: ((json['per_period'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmerSalesPeriod.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      paymentSplit: FarmerPaymentSplit.fromJson(_mapOf(json['payment_split'])),
      sourceSplit: FarmerSourceSplit.fromJson(_mapOf(json['source_split'])),
      yearTotal: json['year_total'] is Map
          ? FarmerYearTotal.fromJson(
              Map<String, dynamic>.from(json['year_total'] as Map),
            )
          : null,
    );
  }
}

class FarmerHarvestCrop {
  const FarmerHarvestCrop({
    required this.crop,
    required this.unit,
    required this.harvested,
    required this.rejected,
    required this.good,
    required this.rejectedByReason,
    required this.sold,
    required this.waiting,
    required this.removed,
    required this.removedByReason,
    required this.remaining,
    required this.potentialIncome,
    required this.actualIncome,
    this.cropTypeId,
  });

  final int? cropTypeId;
  final String crop;
  final String unit;
  final double harvested;
  final double rejected;
  final double good;
  final Map<String, double> rejectedByReason;
  final double sold;
  final double waiting;
  final double removed;
  final Map<String, double> removedByReason;
  final double remaining;
  final double potentialIncome;
  final double actualIncome;

  factory FarmerHarvestCrop.fromJson(Map<String, dynamic> json) {
    final removed = _doubleMap(json['removed_by_reason']);
    return FarmerHarvestCrop(
      cropTypeId: ListingItem._asCount(json['crop_type_id']),
      crop: json['crop'] as String? ?? '',
      unit: json['unit'] as String? ?? '',
      harvested: _asDouble(json['harvested']),
      rejected: _asDouble(json['rejected']),
      good: _asDouble(json['good']),
      rejectedByReason: _doubleMap(json['rejected_by_reason']),
      sold: _asDouble(json['sold']),
      waiting: _asDouble(json['waiting']),
      removed: _asDouble(json['removed']),
      removedByReason: {
        'spoiled': removed['spoiled'] ?? 0,
        'damaged': removed['damaged'] ?? 0,
        'sold_outside': removed['sold_outside'] ?? 0,
        'correction': removed['correction'] ?? 0,
      },
      remaining: _asDouble(json['remaining']),
      potentialIncome: _asDouble(json['potential_income']),
      actualIncome: _asDouble(json['actual_income']),
    );
  }
}

class FarmerHarvestIncome {
  const FarmerHarvestIncome({required this.potential, required this.actual});

  final double potential;
  final double actual;

  factory FarmerHarvestIncome.fromJson(Map<String, dynamic> json) {
    return FarmerHarvestIncome(
      potential: _asDouble(json['potential']),
      actual: _asDouble(json['actual']),
    );
  }
}

class FarmerHarvestCost {
  const FarmerHarvestCost({
    required this.recordsWithCost,
    required this.recordsWithoutCost,
    this.costTotal,
    this.potentialIncomeWithCost,
    this.actualIncomeWithCost,
    this.potentialProfit,
    this.actualProfit,
  });

  final int recordsWithCost;
  final int recordsWithoutCost;
  final double? costTotal;
  final double? potentialIncomeWithCost;
  final double? actualIncomeWithCost;
  final double? potentialProfit;
  final double? actualProfit;

  factory FarmerHarvestCost.fromJson(Map<String, dynamic> json) {
    return FarmerHarvestCost(
      recordsWithCost: ListingItem._asCount(json['records_with_cost']) ?? 0,
      recordsWithoutCost: ListingItem._asCount(json['records_without_cost']) ?? 0,
      costTotal: _nullableDouble(json['cost_total']),
      potentialIncomeWithCost: _nullableDouble(json['potential_income_with_cost']),
      actualIncomeWithCost: _nullableDouble(json['actual_income_with_cost']),
      potentialProfit: _nullableDouble(json['potential_profit']),
      actualProfit: _nullableDouble(json['actual_profit']),
    );
  }
}

class FarmerHarvestReport {
  const FarmerHarvestReport({
    required this.records,
    required this.estimatedRecords,
    required this.unlinkedRecords,
    required this.crops,
    required this.income,
    required this.cost,
  });

  final int records;
  final int estimatedRecords;
  final int unlinkedRecords;
  final List<FarmerHarvestCrop> crops;
  final FarmerHarvestIncome income;
  final FarmerHarvestCost cost;

  factory FarmerHarvestReport.fromJson(Map<String, dynamic> json) {
    return FarmerHarvestReport(
      records: ListingItem._asCount(json['records']) ?? 0,
      estimatedRecords: ListingItem._asCount(json['estimated_records']) ?? 0,
      unlinkedRecords: ListingItem._asCount(json['unlinked_records']) ?? 0,
      crops: ((json['crops'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmerHarvestCrop.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      income: FarmerHarvestIncome.fromJson(_mapOf(json['income'])),
      cost: FarmerHarvestCost.fromJson(_mapOf(json['cost'])),
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
    this.range,
    this.sales,
    this.harvest,
  });

  final String period;
  final String? windowStart;
  final String? windowEnd;
  final FarmerAnalyticsSummary summary;
  final List<FarmerSalesPoint> salesPerPeriod;
  final List<FarmerCropSales> unitsPerCropType;
  final List<FarmerCropSales> bestSelling;
  final FarmerWalkInShare walkInShare;
  final FarmerAnalyticsRange? range;
  final FarmerSalesReport? sales;
  final FarmerHarvestReport? harvest;

  bool get isEmpty => summary.isEmpty && sales == null && harvest == null;

  bool get hasCompletedSales {
    final report = sales;
    if (report != null) {
      return report.totals.orders > 0 || report.totals.sales > 0;
    }
    return !summary.isEmpty;
  }

  FarmerSalesTotals get displayTotals {
    final report = sales;
    if (report != null) {
      return report.totals;
    }
    final orders = summary.completedOrders;
    return FarmerSalesTotals(
      sales: summary.grossSales,
      orders: orders,
      averageOrder: orders == 0 ? 0 : summary.grossSales / orders,
      tawadTotal: 0,
      averageTawad: summary.averageDiscount,
    );
  }

  List<FarmerSalesPeriod> get displayPeriods {
    final report = sales;
    if (report != null) {
      return report.perPeriod;
    }
    return [
      for (final point in salesPerPeriod)
        FarmerSalesPeriod(
          key: point.period,
          start: point.period,
          end: point.period,
          future: false,
          orders: point.orders,
          sales: point.revenue,
        ),
    ];
  }

  List<FarmerTopCrop> get displayTopCrops {
    final report = sales;
    if (report != null) {
      return report.topCrops;
    }
    return [
      for (final row in unitsPerCropType)
        FarmerTopCrop(
          crop: row.crop,
          unit: row.unit ?? '',
          quantity: row.units,
          sales: row.revenue,
        ),
    ];
  }

  FarmerSourceSplit get displaySource {
    final report = sales;
    if (report != null) {
      return report.sourceSplit;
    }
    return FarmerSourceSplit(
      app: FarmerSalesBucket(
        orders: walkInShare.appOrders,
        sales: walkInShare.appSales,
      ),
      walkIn: FarmerSalesBucket(
        orders: walkInShare.walkInOrders,
        sales: walkInShare.walkInSales,
      ),
    );
  }

  factory FarmerAnalytics.fromJson(Map<String, dynamic> json) {
    return FarmerAnalytics(
      period: json['period'] as String? ?? 'week',
      windowStart: json['window_start'] as String?,
      windowEnd: json['window_end'] as String?,
      summary: FarmerAnalyticsSummary.fromJson(
        json['summary'] is Map
            ? Map<String, dynamic>.from(json['summary'] as Map)
            : <String, dynamic>{},
      ),
      salesPerPeriod: ((json['sales_per_period'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) =>
                FarmerSalesPoint.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      unitsPerCropType: ((json['units_per_crop_type'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmerCropSales.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      bestSelling: ((json['best_selling'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => FarmerCropSales.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
      walkInShare: FarmerWalkInShare.fromJson(
        json['walk_in_share'] is Map
            ? Map<String, dynamic>.from(json['walk_in_share'] as Map)
            : <String, dynamic>{},
      ),
      range: json['range'] is Map
          ? FarmerAnalyticsRange.fromJson(
              Map<String, dynamic>.from(json['range'] as Map),
            )
          : null,
      sales: json['sales'] is Map
          ? FarmerSalesReport.fromJson(
              Map<String, dynamic>.from(json['sales'] as Map),
            )
          : null,
      harvest: json['harvest'] is Map
          ? FarmerHarvestReport.fromJson(
              Map<String, dynamic>.from(json['harvest'] as Map),
            )
          : null,
    );
  }
}

Map<String, dynamic> _mapOf(Object? value) {
  if (value is Map) {
    return Map<String, dynamic>.from(value);
  }
  return const {};
}

Map<String, double> _doubleMap(Object? value) {
  if (value is! Map) {
    return const {};
  }
  return {
    for (final entry in value.entries) '${entry.key}': _asDouble(entry.value),
  };
}

double? _nullableDouble(Object? value) {
  if (value == null) {
    return null;
  }
  return ListingItem._asDouble(value);
}

class StockHistoryPage {
  const StockHistoryPage({
    required this.events,
    required this.summary,
    required this.currentPage,
    required this.lastPage,
  });

  final List<StockEvent> events;
  final StockSummary summary;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  factory StockHistoryPage.fromJson(Map<String, dynamic> json) {
    final meta = json['meta'] is Map
        ? Map<String, dynamic>.from(json['meta'] as Map)
        : const <String, dynamic>{};
    final summary = json['summary'] is Map
        ? Map<String, dynamic>.from(json['summary'] as Map)
        : const <String, dynamic>{};
    return StockHistoryPage(
      events: ((json['data'] as List?) ?? const [])
          .whereType<Map>()
          .map((row) => StockEvent.fromJson(Map<String, dynamic>.from(row)))
          .toList(),
      summary: StockSummary.fromJson(summary),
      currentPage: ListingItem._asCount(meta['current_page']) ?? 1,
      lastPage: ListingItem._asCount(meta['last_page']) ?? 1,
    );
  }
}

class StockSummary {
  const StockSummary({
    required this.trackedSince,
    required this.harvested,
    required this.good,
    required this.sold,
    required this.removed,
    required this.available,
    required this.held,
    required this.hasEstimated,
    required this.recordsWithoutCost,
    this.starting = '0',
    this.harvestsWithoutCost,
    this.costTotal,
    this.unit,
  });

  final String? trackedSince;
  final String? unit;
  final String starting;
  final String harvested;
  final String good;
  final String sold;
  final String removed;
  final String available;
  final String held;
  final bool hasEstimated;
  final String? costTotal;
  final int recordsWithoutCost;
  final int? harvestsWithoutCost;

  int get harvestsMissingCost => harvestsWithoutCost ?? recordsWithoutCost;

  factory StockSummary.fromJson(Map<String, dynamic> json) {
    final harvests = ListingItem._asCount(json['harvests_without_cost']);
    final records = ListingItem._asCount(json['records_without_cost']) ?? 0;
    return StockSummary(
      trackedSince: json['tracked_since']?.toString(),
      starting: '${json['starting'] ?? '0'}',
      harvested: '${json['harvested'] ?? '0'}',
      good: '${json['good'] ?? '0'}',
      sold: '${json['sold'] ?? '0'}',
      removed: '${json['removed'] ?? '0'}',
      available: '${json['available'] ?? '0'}',
      held: '${json['held'] ?? '0'}',
      hasEstimated: json['has_estimated'] == true,
      costTotal: json['cost_total']?.toString(),
      recordsWithoutCost: records,
      harvestsWithoutCost: harvests ?? records,
      unit: json['unit'] as String?,
    );
  }
}

class StockEvent {
  const StockEvent({
    required this.type,
    required this.id,
    this.kind,
    this.harvestedOn,
    this.quantityHarvested,
    this.quantityRejected,
    this.quantityGood,
    this.quantity,
    this.reason,
    this.note,
    this.productionCost,
    this.createdAt,
  });

  final String type;
  final int id;
  final String? kind;
  final String? harvestedOn;
  final String? quantityHarvested;
  final String? quantityRejected;
  final String? quantityGood;
  final String? quantity;
  final String? reason;
  final String? note;
  final String? productionCost;
  final String? createdAt;

  bool get isOpening => kind == 'opening';
  bool get isEstimated => kind == 'estimated';
  bool get hasCost =>
      productionCost != null &&
      productionCost!.isNotEmpty &&
      productionCost != 'null';

  factory StockEvent.fromJson(Map<String, dynamic> json) {
    return StockEvent(
      type: json['type'] as String? ?? 'harvest',
      id: ListingItem._asCount(json['id']) ?? 0,
      kind: json['kind'] as String?,
      harvestedOn: json['harvested_on']?.toString(),
      quantityHarvested: json['quantity_harvested']?.toString(),
      quantityRejected: json['quantity_rejected']?.toString(),
      quantityGood: json['quantity_good']?.toString(),
      quantity: json['quantity']?.toString(),
      reason: json['reason'] as String?,
      note: json['note'] as String?,
      productionCost: json['production_cost']?.toString(),
      createdAt: json['created_at']?.toString(),
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
          .map(
            (item) => FaqSuggestion.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
    );
  }
}

class SellerHelpFarm {
  const SellerHelpFarm({
    required this.id,
    required this.name,
    this.municipality,
    this.contactPerson,
    this.contactNumber,
  });

  final int id;
  final String name;
  final String? municipality;
  final String? contactPerson;
  final String? contactNumber;

  factory SellerHelpFarm.fromJson(Map<String, dynamic> json) {
    return SellerHelpFarm(
      id: ListingItem._asCount(json['id']) ?? 0,
      name: json['name'] as String? ?? '',
      municipality: _blankToNull(json['municipality']),
      contactPerson: _blankToNull(json['contact_person']),
      contactNumber: _blankToNull(json['contact_number']),
    );
  }
}

class SellerHelp {
  const SellerHelp({
    required this.office,
    required this.email,
    required this.temporaryPasswordDays,
    required this.farms,
    this.phone,
  });

  final String office;
  final String email;
  final String? phone;
  final int temporaryPasswordDays;
  final List<SellerHelpFarm> farms;

  factory SellerHelp.fromJson(Map<String, dynamic> json) {
    final contact = json['contact'] is Map
        ? Map<String, dynamic>.from(json['contact'] as Map)
        : const <String, dynamic>{};
    return SellerHelp(
      office: _blankToNull(contact['office']) ?? '',
      email: _blankToNull(contact['email']) ?? '',
      phone: _blankToNull(contact['phone']),
      temporaryPasswordDays:
          ListingItem._asCount(json['temporary_password_days']) ?? 0,
      farms: ((json['farms'] as List?) ?? const [])
          .whereType<Map>()
          .map(
            (item) => SellerHelpFarm.fromJson(Map<String, dynamic>.from(item)),
          )
          .toList(),
    );
  }
}

String? _blankToNull(Object? value) {
  if (value == null) {
    return null;
  }
  final text = value.toString().trim();
  return text.isEmpty ? null : text;
}
