import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_lnu_marketplace/inquiry_model.dart';
import 'package:flutter_lnu_marketplace/listing_model_page.dart';

void main() {
  test(
    'Inquiry adapter maps sender, recipient, and cached listing fallback',
    () {
      BackendListingAdapter.instance.prime(<Listing>[
        const Listing(
          id: 44,
          userId: 9,
          categoryId: 2,
          listingStatus: 'available',
          campusLocation: 'LNU Main Campus',
          title: 'Calculus Book',
          price: 'P350.00',
          category: 'Books',
          condition: 'Pre-owned',
          description: 'Good condition',
          seller: 'Alicia Cruz',
          sellerAvatar: 'A',
          icon: Icons.menu_book_rounded,
          color: Color(0xFFFFF2CC),
        ),
      ]);

      final inquiry = Inquiry.fromApi(<String, dynamic>{
        'id': 19,
        'listing_id': 44,
        'sender_user_id': 12,
        'recipient_user_id': 9,
        'message': 'Can we meet tomorrow?',
        'preferred_contact_method': 'email',
        'status': 'pending',
        'inquiry_status': 'new',
        'created_at': '2026-03-09T09:30:00Z',
        'seller_confirmed_at': '2026-03-10T09:30:00Z',
        'buyer_confirmed_at': '2026-03-11T09:30:00Z',
        'listing': <String, dynamic>{
          'id': 44,
          'title': 'Calculus Book',
          'listing_status': 'available',
        },
        'sender': <String, dynamic>{'id': 12, 'full_name': 'Miguel Santos'},
        'recipient': <String, dynamic>{'id': 9, 'full_name': 'Alicia Cruz'},
      });

      expect(inquiry.id, 19);
      expect(inquiry.listingTitle, 'Calculus Book');
      expect(inquiry.listingPrice, 'P350.00');
      expect(inquiry.listingCategory, 'Books');
      expect(inquiry.senderName, 'Miguel Santos');
      expect(inquiry.recipientName, 'Alicia Cruz');
      expect(inquiry.preferredContactLabel, 'Email');
      expect(inquiry.counterpartyName(isReceived: true), 'Miguel Santos');
      expect(inquiry.counterpartyName(isReceived: false), 'Alicia Cruz');
      expect(inquiry.sellerConfirmedAt, isNotNull);
      expect(inquiry.buyerConfirmedAt, isNotNull);
    },
  );
}
