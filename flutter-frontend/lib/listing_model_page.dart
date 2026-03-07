import 'dart:io';

import 'package:flutter/material.dart';

class Listing {
  final int id;
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
    this.imageFile,
  });

  Listing copyWith({
    int? id,
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
    final sellerName = _sellerNameFromApi(json);
    final categoryName = _categoryNameFromApi(json);

    return Listing(
      id: _parseListingId(json['id']),
      title: _stringValue(json['title'], fallback: 'Untitled Listing'),
      price: _priceLabelFromApi(json['price']),
      category: categoryName,
      condition: _conditionLabelFromApi(json['item_condition']),
      description: _stringValue(
        json['description'],
        fallback: 'No description provided.',
      ),
      seller: sellerName,
      sellerAvatar: _sellerAvatarFromName(sellerName),
      icon: categoryIcon(categoryName),
      color: categoryColor(categoryName),
    );
  }
}

final List<Listing> dummyListings = [];

IconData categoryIcon(String category) {
  switch (category) {
    case 'Books':
      return Icons.menu_book_rounded;
    case 'Uniforms':
      return Icons.checkroom_rounded;
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

int _parseListingId(dynamic rawValue) {
  if (rawValue is int) {
    return rawValue;
  }

  return int.tryParse(rawValue?.toString() ?? '') ?? 0;
}

String _priceLabelFromApi(dynamic rawValue) {
  final value = _stringValue(rawValue);

  if (value.isEmpty) {
    return 'P0.00';
  }

  if (value.startsWith('P') || value.toUpperCase().startsWith('PHP')) {
    return value;
  }

  final parsed = double.tryParse(value);
  if (parsed != null) {
    return 'P${parsed.toStringAsFixed(2)}';
  }

  return 'P$value';
}

String _conditionLabelFromApi(dynamic rawValue) {
  switch (_stringValue(rawValue).toLowerCase()) {
    case 'brandnew':
    case 'new':
      return 'New';
    case 'like_new':
    case 'good':
    case 'preowned':
      return 'Good';
    case 'fair':
      return 'Fair';
    case 'poor':
      return 'Poor';
    default:
      return 'Used';
  }
}

String _categoryNameFromApi(Map<String, dynamic> json) {
  final directCategory = _stringValue(json['category_name']);
  if (directCategory.isNotEmpty) {
    return directCategory;
  }

  final category = json['category'];
  if (category is Map<String, dynamic>) {
    final nestedName = _stringValue(category['name']);
    if (nestedName.isNotEmpty) {
      return nestedName;
    }
  }

  return 'Marketplace';
}

String _sellerNameFromApi(Map<String, dynamic> json) {
  final directSeller = _stringValue(
    json['seller_name'] ?? json['seller'] ?? json['user_name'],
  );
  if (directSeller.isNotEmpty) {
    return capitalizeName(directSeller);
  }

  final user = json['user'];
  if (user is Map<String, dynamic>) {
    final nestedName = _stringValue(user['name']);
    if (nestedName.isNotEmpty) {
      return capitalizeName(nestedName);
    }
  }

  return 'LNU Seller';
}

String _sellerAvatarFromName(String sellerName) {
  return sellerName.isNotEmpty ? sellerName[0].toUpperCase() : '?';
}

String _stringValue(dynamic rawValue, {String fallback = ''}) {
  final value = rawValue?.toString().trim() ?? '';

  return value.isEmpty ? fallback : value;
}
