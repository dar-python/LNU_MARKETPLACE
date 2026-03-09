import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_lnu_marketplace/listing_model_page.dart';

void main() {
  test('Listing adapter safely maps raw backend browse payload', () {
    final listing = Listing.fromApi(<String, dynamic>{
      'id': 11,
      'user_id': 25,
      'category_id': 2,
      'title': 'Physics Book',
      'description': 'Used for one semester',
      'price': '120',
      'item_condition': 'brandnew',
      'listing_status': 'available',
      'campus_location': 'Main Gate',
    });

    expect(listing.id, 11);
    expect(listing.userId, 25);
    expect(listing.categoryId, 2);
    expect(listing.title, 'Physics Book');
    expect(listing.price, 'P120.00');
    expect(listing.condition, 'Brand New');
    expect(listing.category, 'Books');
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
        categoryId: 2,
        listingStatus: 'available',
        campusLocation: 'Main Gate',
        title: 'Physics Book',
        price: 'P120.00',
        category: 'Books',
        condition: 'Pre-owned',
        description: 'Used for one semester',
        seller: 'Jane Doe',
        sellerAvatar: 'J',
        icon: Icons.menu_book_rounded,
        color: Color(0xFFFFF2CC),
      );

      final listing = Listing.fromApiWithFallback(<String, dynamic>{
        'id': 11,
        'user_id': 25,
        'category_id': 2,
        'title': 'Physics Book',
        'price': '120.00',
        'listing_status': 'available',
      }, fallback: fallbackListing);

      expect(listing.category, 'Books');
      expect(listing.seller, 'Jane Doe');
      expect(listing.condition, 'Pre-owned');
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
    expect(collection.listings.single.condition, 'Pre-owned');
    expect(collection.pagination.currentPage, 2);
    expect(collection.pagination.perPage, 10);
    expect(collection.pagination.total, 12);
    expect(collection.pagination.lastPage, 2);
  });

  test('Listing detail parses nested category and images from envelope', () {
    final detail = ListingDetail.fromEnvelope(<String, dynamic>{
      'success': true,
      'message': 'Listing retrieved successfully.',
      'data': <String, dynamic>{
        'listing': <String, dynamic>{
          'id': 9,
          'user_id': 5,
          'category_id': 1,
          'title': 'Tablet',
          'description': 'Lightly used tablet',
          'price': '9500.00',
          'item_condition': 'preowned',
          'listing_status': 'available',
          'campus_location': 'LNU Main Campus',
          'category': <String, dynamic>{
            'id': 1,
            'name': 'Electronics',
            'slug': 'electronics',
          },
          'images': <Map<String, dynamic>>[
            <String, dynamic>{
              'id': 77,
              'image_path': 'listings/9/cover.jpg',
              'sort_order': 0,
              'is_primary': true,
            },
          ],
        },
      },
      'trace_id': 'trace-123',
    });

    expect(detail.listing.category, 'Electronics');
    expect(detail.listing.condition, 'Pre-owned');
    expect(detail.images, hasLength(1));
    expect(detail.images.single.id, 77);
    expect(detail.images.single.imagePath, 'listings/9/cover.jpg');
    expect(
      detail.images.single.imageUrl,
      contains('/storage/listings/9/cover.jpg'),
    );
    expect(detail.images.single.isPrimary, isTrue);
  });
}
