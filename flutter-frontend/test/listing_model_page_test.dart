import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_lnu_marketplace/listing_model_page.dart';

void main() {
  test('Listing adapter safely maps raw backend browse payload', () {
    final listing = Listing.fromApi(<String, dynamic>{
      'id': 11,
      'user_id': 25,
      'category_id': 3,
      'title': 'Physics Book',
      'description': 'Used for one semester',
      'price': '120',
      'item_condition': 'brandnew',
      'listing_status': 'available',
      'campus_location': 'Main Gate',
    });

    expect(listing.id, 11);
    expect(listing.userId, 25);
    expect(listing.categoryId, 3);
    expect(listing.title, 'Physics Book');
    expect(listing.price, 'P120.00');
    expect(listing.condition, 'New');
    expect(listing.category, 'Category');
    expect(listing.seller, 'LNU Seller');
    expect(listing.listingStatus, 'available');
    expect(listing.campusLocation, 'Main Gate');
  });

  test(
    'Listing adapter keeps richer fallback details when payload is sparse',
    () {
      const fallbackListing = Listing(
        id: 11,
        userId: 25,
        categoryId: 3,
        listingStatus: 'available',
        campusLocation: 'Main Gate',
        title: 'Physics Book',
        price: 'P120.00',
        category: 'Books',
        condition: 'Good',
        description: 'Used for one semester',
        seller: 'Jane Doe',
        sellerAvatar: 'J',
        icon: Icons.menu_book_rounded,
        color: Color(0xFFFFF2CC),
      );

      final listing = Listing.fromApiWithFallback(<String, dynamic>{
        'id': 11,
        'user_id': 25,
        'category_id': 3,
        'title': 'Physics Book',
        'price': '120.00',
        'listing_status': 'available',
      }, fallback: fallbackListing);

      expect(listing.category, 'Books');
      expect(listing.seller, 'Jane Doe');
      expect(listing.condition, 'Good');
      expect(listing.icon, Icons.menu_book_rounded);
    },
  );

  test('Listing collection parses backend envelope pagination', () {
    final collection = ListingCollection.fromEnvelope(<String, dynamic>{
      'success': true,
      'message': 'Listings retrieved successfully.',
      'data': <String, dynamic>{
        'listings': <Map<String, dynamic>>[
          <String, dynamic>{
            'id': '7',
            'user_id': '19',
            'category_id': '2',
            'title': 'Notebook',
            'description': 'Slightly used',
            'price': '75.5',
            'item_condition': 'preowned',
            'listing_status': 'reserved',
          },
        ],
        'meta': <String, dynamic>{
          'current_page': 2,
          'per_page': 10,
          'total': 12,
          'last_page': 2,
        },
      },
      'trace_id': 'trace-123',
    });

    expect(collection.listings, hasLength(1));
    expect(collection.listings.single.id, 7);
    expect(collection.listings.single.price, 'P75.50');
    expect(collection.listings.single.condition, 'Good');
    expect(collection.pagination.currentPage, 2);
    expect(collection.pagination.perPage, 10);
    expect(collection.pagination.total, 12);
    expect(collection.pagination.lastPage, 2);
  });
}
