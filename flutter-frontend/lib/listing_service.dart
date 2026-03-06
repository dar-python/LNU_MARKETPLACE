import 'dart:io';
import 'package:flutter/material.dart';
import 'listing_model_page.dart';
import 'auth_service.dart';

// ─── Icon map for categories ──────────────────────────────────────────────────
IconData categoryIcon(String category) {
  switch (category) {
    case 'Gadgets': return Icons.laptop_rounded;
    case 'Lab Tools': return Icons.science_rounded;
    case 'Sports Equipment': return Icons.sports_basketball_rounded;
    case 'School Supplies': return Icons.backpack_rounded;
    case 'Services': return Icons.miscellaneous_services_rounded;
    case 'Clothing': return Icons.checkroom_rounded;
    case 'Electronics': return Icons.electrical_services_rounded;
    default: return Icons.inventory_2_rounded;
  }
}

// ─── Color map for categories ─────────────────────────────────────────────────
Color categoryColor(String category) {
  switch (category) {
    case 'Gadgets': return const Color(0xFFE8F4FF);
    case 'Lab Tools': return const Color(0xFFFFE8E8);
    case 'Sports Equipment': return const Color(0xFFE8FFE8);
    case 'School Supplies': return const Color(0xFFE8ECFF);
    case 'Services': return const Color(0xFFFFF8E8);
    case 'Clothing': return const Color(0xFFFFEECC);
    case 'Electronics': return const Color(0xFFE8F8FF);
    default: return const Color(0xFFE3E8FF);
  }
}

// ─── Capitalize Name Function ────────────────────────────────────────────────
String capitalizeName(String name) {
  return name
      .split(' ')
      .map((word) =>
          word.isNotEmpty ? word[0].toUpperCase() + word.substring(1) : '')
      .join(' ');
}

// ─── ListingService (Singleton) ───────────────────────────────────────────────
class ListingService {
  static final ListingService _instance = ListingService._internal();
  factory ListingService() => _instance;
  ListingService._internal();

  // In-memory listings list — starts with dummy data
  final List<Listing> _listings = List.from(dummyListings);

  // Get all listings
  List<Listing> get listings => List.unmodifiable(_listings);

  // Add a new listing
  void addListing({
    required String title,
    required String price,
    required String category,
    required String condition,
    required String description,
    File? imageFile,
  }) {
    final user = AuthService().currentUser;

    final sellerName = capitalizeName(user?['name'] ?? 'Unknown Seller');

    final newListing = Listing(
      id: DateTime.now().millisecondsSinceEpoch,
      title: title.toUpperCase(),
      price: price,
      category: category,
      condition: condition,
      description: description,
      seller: sellerName,
      sellerAvatar: sellerName[0].toUpperCase(),
      icon: categoryIcon(category),
      color: categoryColor(category),
      imageFile: imageFile,
    );

    _listings.insert(0, newListing); // Add to top of list
  }

  // Delete a listing by id
  void deleteListing(int id) {
    _listings.removeWhere((l) => l.id == id);
  }
}