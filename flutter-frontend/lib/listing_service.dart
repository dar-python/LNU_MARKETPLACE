import 'dart:io';

import 'auth_service.dart';
import 'listing_model_page.dart';

class ListingService {
  static final ListingService _instance = ListingService._internal();

  factory ListingService() => _instance;

  ListingService._internal();

  final List<Listing> _listings = List.from(dummyListings);

  List<Listing> get listings => List.unmodifiable(_listings);

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

    _listings.insert(0, newListing);
  }

  void deleteListing(int id) {
    _listings.removeWhere((l) => l.id == id);
  }
}
