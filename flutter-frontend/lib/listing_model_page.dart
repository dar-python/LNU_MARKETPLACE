import 'dart:io';

import 'package:flutter/material.dart';

import 'config/app_config.dart';

class Listing {
  final int id;
  final int userId;
  final int categoryId;
  final String listingStatus;
  final String campusLocation;
  final String title;
  final String price;
  final String category;
  final String condition;
  final String description;
  final String seller;
  final String sellerAvatar;
  final IconData icon;
  final Color color;
  final File? imageFile;

  const Listing({
    required this.id,
    required this.title,
    required this.price,
    required this.category,
    required this.condition,
    required this.description,
    required this.seller,
    required this.sellerAvatar,
    required this.icon,
    required this.color,
    this.userId = 0,
    this.categoryId = 0,
    this.listingStatus = '',
    this.campusLocation = '',
    this.imageFile,
  });

  Listing copyWith({
    int? id,
    int? userId,
    int? categoryId,
    String? listingStatus,
    String? campusLocation,
    String? title,
    String? price,
    String? category,
    String? condition,
    String? description,
    String? seller,
    String? sellerAvatar,
    IconData? icon,
    Color? color,
    File? imageFile,
  }) {
    return Listing(
      id: id ?? this.id,
      userId: userId ?? this.userId,
      categoryId: categoryId ?? this.categoryId,
      listingStatus: listingStatus ?? this.listingStatus,
      campusLocation: campusLocation ?? this.campusLocation,
      title: title ?? this.title,
      price: price ?? this.price,
      category: category ?? this.category,
      condition: condition ?? this.condition,
      description: description ?? this.description,
      seller: seller ?? this.seller,
      sellerAvatar: sellerAvatar ?? this.sellerAvatar,
      icon: icon ?? this.icon,
      color: color ?? this.color,
      imageFile: imageFile ?? this.imageFile,
    );
  }

  factory Listing.fromApi(Map<String, dynamic> json) {
    return BackendListingAdapter.instance.fromApi(json);
  }

  static Listing fromApiWithFallback(
    Map<String, dynamic> json, {
    Listing? fallback,
  }) {
    return BackendListingAdapter.instance.fromApi(json, fallback: fallback);
  }
}

class ListingCollection {
  const ListingCollection({required this.listings, required this.pagination});

  final List<Listing> listings;
  final ListingPagination pagination;

  factory ListingCollection.fromEnvelope(
    dynamic body, {
    BackendListingAdapter? adapter,
  }) {
    final resolvedAdapter = adapter ?? BackendListingAdapter.instance;
    final envelope = _mapValue(body);
    if (envelope == null) {
      throw const FormatException('Invalid response from server.');
    }

    final data = _mapValue(envelope['data']);
    if (data == null) {
      throw const FormatException('Invalid listings payload.');
    }

    final rawListings = data['listings'];
    if (rawListings is! List) {
      throw const FormatException('Invalid listings payload.');
    }

    final listings = rawListings
        .map(_mapValue)
        .whereType<Map<String, dynamic>>()
        .map((rawListing) => resolvedAdapter.fromApi(rawListing))
        .toList();

    return ListingCollection(
      listings: listings,
      pagination: ListingPagination.fromApi(
        _mapValue(data['meta']) ?? <String, dynamic>{},
      ),
    );
  }
}

class ListingPagination {
  const ListingPagination({
    required this.currentPage,
    required this.perPage,
    required this.total,
    required this.lastPage,
  });

  final int currentPage;
  final int perPage;
  final int total;
  final int lastPage;

  factory ListingPagination.fromApi(Map<String, dynamic> json) {
    return ListingPagination(
      currentPage: _parseListingId(json['current_page'], fallback: 1),
      perPage: _parseListingId(json['per_page'], fallback: 0),
      total: _parseListingId(json['total'], fallback: 0),
      lastPage: _parseListingId(json['last_page'], fallback: 0),
    );
  }
}

class ListingDetail {
  const ListingDetail({required this.listing, required this.images});

  final Listing listing;
  final List<ListingImageAsset> images;

  factory ListingDetail.fromEnvelope(
    dynamic body, {
    BackendListingAdapter? adapter,
    Listing? fallback,
  }) {
    final resolvedAdapter = adapter ?? BackendListingAdapter.instance;
    final envelope = _mapValue(body);
    if (envelope == null) {
      throw const FormatException('Invalid response from server.');
    }

    final data = _mapValue(envelope['data']);
    final rawListing = data == null ? null : _mapValue(data['listing']);
    if (rawListing == null) {
      throw const FormatException('Invalid listing detail payload.');
    }

    return ListingDetail.fromApi(
      rawListing,
      adapter: resolvedAdapter,
      fallback: fallback,
    );
  }

  factory ListingDetail.fromApi(
    Map<String, dynamic> json, {
    BackendListingAdapter? adapter,
    Listing? fallback,
  }) {
    final resolvedAdapter = adapter ?? BackendListingAdapter.instance;
    final images = _imageListValue(
      json['images'],
    ).map(ListingImageAsset.fromApi).toList();

    return ListingDetail(
      listing: resolvedAdapter.fromApi(json, fallback: fallback),
      images: images,
    );
  }
}

class ListingImageAsset {
  const ListingImageAsset({
    required this.id,
    required this.imagePath,
    required this.imageUrl,
    required this.sortOrder,
    required this.isPrimary,
  });

  final int id;
  final String imagePath;
  final String imageUrl;
  final int sortOrder;
  final bool isPrimary;

  factory ListingImageAsset.fromApi(Map<String, dynamic> json) {
    final imagePath = _stringValue(json['image_path']);

    return ListingImageAsset(
      id: _parseListingId(json['id']),
      imagePath: imagePath,
      imageUrl: _publicImageUrl(imagePath),
      sortOrder: _parseListingId(json['sort_order']),
      isPrimary: json['is_primary'] == true,
    );
  }
}

class BackendListingAdapter {
  BackendListingAdapter._internal();

  static final BackendListingAdapter instance =
      BackendListingAdapter._internal();

  static const String _fallbackCategoryLabel = 'Category';
  static const String _fallbackSellerLabel = 'LNU Seller';

  final Map<int, Listing> _cache = <int, Listing>{};

  Listing fromApi(Map<String, dynamic> json, {Listing? fallback}) {
    final id = _parseListingId(json['id']);
    final cached = id > 0 ? _cache[id] : null;
    final seed = fallback ?? cached;
    final resolvedCategory = _resolveCategory(json, seed);
    final resolvedCondition = _conditionLabelFromApi(
      json['item_condition'],
      fallback: seed?.condition,
    );
    final resolvedSeller = _resolveSeller(json, seed);
    final resolvedListing = Listing(
      id: id,
      userId: _parseListingId(json['user_id'], fallback: seed?.userId ?? 0),
      categoryId: _parseListingId(
        json['category_id'],
        fallback: seed?.categoryId ?? 0,
      ),
      listingStatus: _stringValue(
        json['listing_status'],
        fallback: seed?.listingStatus ?? '',
      ),
      campusLocation: _stringValue(
        json['campus_location'] ?? json['meetup_location'],
        fallback: seed?.campusLocation ?? '',
      ),
      title: _stringValue(
        json['title'],
        fallback: seed?.title ?? 'Untitled Listing',
      ),
      price: _priceLabelFromApi(json['price'], fallback: seed?.price),
      category: resolvedCategory,
      condition: resolvedCondition,
      description: _stringValue(
        json['description'],
        fallback: seed?.description ?? 'No description provided.',
      ),
      seller: resolvedSeller,
      sellerAvatar: _sellerAvatarFromName(resolvedSeller),
      icon: categoryIcon(resolvedCategory),
      color: categoryColor(resolvedCategory),
      imageFile: seed?.imageFile,
    );

    if (id > 0) {
      _cache[id] = resolvedListing;
    }

    return resolvedListing;
  }

  void prime(List<Listing> listings) {
    for (final listing in listings) {
      if (listing.id > 0) {
        _cache[listing.id] = listing;
      }
    }
  }

  Listing? cached(int id) {
    return _cache[id];
  }

  String _resolveCategory(Map<String, dynamic> json, Listing? seed) {
    final directCategory = _stringValue(json['category_name']);
    if (directCategory.isNotEmpty) {
      return directCategory;
    }

    final category = json['category'];
    if (category is String && category.trim().isNotEmpty) {
      return category.trim();
    }

    final categoryMap = _mapValue(category);
    if (categoryMap != null) {
      final nestedName = _stringValue(categoryMap['name']);
      if (nestedName.isNotEmpty) {
        return nestedName;
      }
    }

    final knownCategory = backendCategoryById(
      _parseListingId(json['category_id'], fallback: seed?.categoryId ?? 0),
    );
    if (knownCategory != null) {
      return knownCategory.name;
    }

    if (seed != null && !_isFallbackCategory(seed.category)) {
      return seed.category;
    }

    return _fallbackCategoryLabel;
  }

  String _resolveSeller(Map<String, dynamic> json, Listing? seed) {
    final directSeller = _stringValue(
      json['seller_name'] ?? json['seller'] ?? json['user_name'],
    );
    if (directSeller.isNotEmpty) {
      return capitalizeName(directSeller);
    }

    final userMap = _mapValue(json['user']);
    if (userMap != null) {
      final nestedName = _stringValue(userMap['name']);
      if (nestedName.isNotEmpty) {
        return capitalizeName(nestedName);
      }
    }

    if (seed != null && !_isFallbackSeller(seed.seller)) {
      return seed.seller;
    }

    return _fallbackSellerLabel;
  }

  bool _isFallbackCategory(String value) {
    return value.trim().isEmpty || value == _fallbackCategoryLabel;
  }

  bool _isFallbackSeller(String value) {
    return value.trim().isEmpty || value == _fallbackSellerLabel;
  }
}

class BackendListingCategory {
  const BackendListingCategory({
    required this.id,
    required this.name,
    required this.aliases,
  });

  final int id;
  final String name;
  final List<String> aliases;
}

const List<BackendListingCategory> _backendListingCategories =
    <BackendListingCategory>[
      BackendListingCategory(
        id: 1,
        name: 'Electronics',
        aliases: <String>['electronics', 'gadgets'],
      ),
      BackendListingCategory(id: 2, name: 'Books', aliases: <String>['books']),
      BackendListingCategory(
        id: 3,
        name: 'School Supplies',
        aliases: <String>['school supplies', 'lab tools'],
      ),
      BackendListingCategory(
        id: 4,
        name: 'Uniforms',
        aliases: <String>['uniforms'],
      ),
      BackendListingCategory(
        id: 5,
        name: 'Dorm Essentials',
        aliases: <String>['dorm essentials'],
      ),
      BackendListingCategory(
        id: 6,
        name: 'Others',
        aliases: <String>[
          'others',
          'sports',
          'sports equipment',
          'clothing',
          'food',
          'drinks',
          'accessories',
        ],
      ),
    ];

// The frontend exposes broader shopper-friendly labels than the current
// backend taxonomy, so we map them to the closest supported backend category
// and safely fall back to "Others" when there is no neat one-to-one match.

BackendListingCategory? backendCategoryById(int id) {
  for (final category in _backendListingCategories) {
    if (category.id == id) {
      return category;
    }
  }

  return null;
}

BackendListingCategory resolveBackendCategoryForFrontendLabel(String label) {
  return backendCategoryForFrontendLabel(label) ??
      backendCategoryForFrontendLabel('Others') ??
      _backendListingCategories.last;
}

BackendListingCategory? backendCategoryForFrontendLabel(String label) {
  final normalizedLabel = _normalizeLookupValue(label);
  if (normalizedLabel.isEmpty) {
    return null;
  }

  for (final category in _backendListingCategories) {
    if (_normalizeLookupValue(category.name) == normalizedLabel) {
      return category;
    }

    for (final alias in category.aliases) {
      if (_normalizeLookupValue(alias) == normalizedLabel) {
        return category;
      }
    }
  }

  return null;
}

String normalizeListingConditionLabel(String label) {
  switch (_normalizeLookupValue(label)) {
    case 'brand new':
    case 'brandnew':
    case 'new':
      return 'Brand New';
    case 'pre-owned':
    case 'preowned':
    case 'used':
    case 'good':
    case 'like new':
    case 'like_new':
      return 'Pre-owned';
    case 'fair':
      return 'Fair';
    case 'poor':
      return 'Poor';
    default:
      return label.trim().isNotEmpty ? label.trim() : 'Pre-owned';
  }
}

String backendItemConditionForLabel(String label) {
  switch (_normalizeLookupValue(label)) {
    case 'brand new':
    case 'brandnew':
    case 'new':
      return 'brandnew';
    default:
      return 'preowned';
  }
}

IconData categoryIcon(String category) {
  switch (category) {
    case 'Books':
      return Icons.menu_book_rounded;
    case 'Uniforms':
      return Icons.checkroom_rounded;
    case 'Dorm Essentials':
      return Icons.chair_alt_rounded;
    case 'Gadgets':
      return Icons.laptop_rounded;
    case 'Lab Tools':
      return Icons.science_rounded;
    case 'School Supplies':
      return Icons.backpack_rounded;
    case 'Services':
      return Icons.miscellaneous_services_rounded;
    case 'Clothing':
      return Icons.checkroom_rounded;
    case 'Electronics':
      return Icons.electrical_services_rounded;
    case 'Sports':
    case 'Sports Equipment':
      return Icons.sports_basketball_rounded;
    case 'Food':
      return Icons.fastfood_rounded;
    case 'Drinks':
      return Icons.local_drink_rounded;
    case 'Accessories':
      return Icons.watch_rounded;
    case 'Others':
      return Icons.inventory_2_rounded;
    default:
      return Icons.inventory_2_rounded;
  }
}

Color categoryColor(String category) {
  switch (category) {
    case 'Books':
      return const Color(0xFFFFF2CC);
    case 'Uniforms':
      return const Color(0xFFFFEECC);
    case 'Dorm Essentials':
      return const Color(0xFFEFF3FF);
    case 'Gadgets':
      return const Color(0xFFE8F4FF);
    case 'Lab Tools':
      return const Color(0xFFFFE8E8);
    case 'Sports':
    case 'Sports Equipment':
      return const Color(0xFFE8FFE8);
    case 'School Supplies':
      return const Color(0xFFE8ECFF);
    case 'Services':
      return const Color(0xFFFFF8E8);
    case 'Clothing':
      return const Color(0xFFFFEECC);
    case 'Electronics':
      return const Color(0xFFE8F8FF);
    case 'Food':
      return const Color(0xFFFFEFD9);
    case 'Drinks':
      return const Color(0xFFE8FFF7);
    case 'Accessories':
      return const Color(0xFFF2E8FF);
    case 'Others':
      return const Color(0xFFE3E8FF);
    default:
      return const Color(0xFFE3E8FF);
  }
}

String capitalizeName(String name) {
  return name
      .split(' ')
      .map(
        (word) =>
            word.isNotEmpty ? word[0].toUpperCase() + word.substring(1) : '',
      )
      .join(' ');
}

int _parseListingId(dynamic rawValue, {int fallback = 0}) {
  if (rawValue is int) {
    return rawValue;
  }

  return int.tryParse(rawValue?.toString() ?? '') ?? fallback;
}

String _priceLabelFromApi(dynamic rawValue, {String? fallback}) {
  final value = _stringValue(rawValue);
  final seed = (fallback ?? '').trim();

  if (value.isEmpty) {
    return seed.isNotEmpty ? seed : 'P0.00';
  }

  final normalizedValue = value
      .replaceAll('PHP', '')
      .replaceAll('Php', '')
      .replaceAll('php', '')
      .replaceAll('P', '')
      .replaceAll('p', '')
      .replaceAll('\u20B1', '')
      .replaceAll(',', '')
      .trim();

  final parsed = double.tryParse(normalizedValue);
  if (parsed != null) {
    return 'P${parsed.toStringAsFixed(2)}';
  }

  return value.startsWith('P') ? value : 'P$value';
}

String _conditionLabelFromApi(dynamic rawValue, {String? fallback}) {
  switch (_normalizeLookupValue(rawValue?.toString() ?? '')) {
    case 'brandnew':
    case 'brand new':
    case 'new':
      return 'Brand New';
    case 'preowned':
    case 'pre-owned':
    case 'used':
    case 'good':
    case 'like_new':
    case 'like new':
      return 'Pre-owned';
    case 'fair':
      return 'Fair';
    case 'poor':
      return 'Poor';
    default:
      return normalizeListingConditionLabel(fallback ?? '');
  }
}

List<Map<String, dynamic>> _imageListValue(dynamic rawValue) {
  if (rawValue is! List) {
    return const <Map<String, dynamic>>[];
  }

  return rawValue.map(_mapValue).whereType<Map<String, dynamic>>().toList();
}

Map<String, dynamic>? _mapValue(dynamic rawValue) {
  if (rawValue is Map) {
    return Map<String, dynamic>.from(rawValue);
  }

  return null;
}

String _sellerAvatarFromName(String sellerName) {
  return sellerName.isNotEmpty ? sellerName[0].toUpperCase() : '?';
}

String _stringValue(dynamic rawValue, {String fallback = ''}) {
  final value = rawValue?.toString().trim() ?? '';
  return value.isEmpty ? fallback : value;
}

String _normalizeLookupValue(String value) {
  return value.trim().toLowerCase().replaceAll('_', ' ');
}

String _publicImageUrl(String imagePath) {
  final normalizedImagePath = imagePath.trim();
  if (normalizedImagePath.isEmpty) {
    return '';
  }

  final parsedUri = Uri.tryParse(normalizedImagePath);
  if (parsedUri != null && parsedUri.hasScheme) {
    return normalizedImagePath;
  }

  final relativePath = normalizedImagePath.startsWith('/')
      ? normalizedImagePath.substring(1)
      : normalizedImagePath;

  return Uri.parse(
    '${AppConfig.baseUrl}/',
  ).resolve('storage/$relativePath').toString();
}
