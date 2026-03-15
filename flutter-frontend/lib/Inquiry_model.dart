import 'listing_model_page.dart';

enum InquiryStatus { pending, accepted, completed, declined }

class Inquiry {
  const Inquiry({
    required this.id,
    required this.listingId,
    required this.senderUserId,
    required this.recipientUserId,
    required this.listingTitle,
    required this.listingPrice,
    required this.listingCategory,
    required this.listingStatus,
    required this.senderName,
    required this.senderAvatar,
    required this.recipientName,
    required this.recipientAvatar,
    required this.preferredContactMethod,
    required this.message,
    required this.createdAt,
    required this.status,
    this.inquiryStatus = '',
    this.proofImagePath,
    this.completedAt,
    this.decidedAt,
    this.decidedBy,
    this.counterpartyContact,
    this.counterpartyProgram,
    this.counterpartyYearLevel,
    this.counterpartyOrganization,
    this.counterpartySection,
    this.counterpartyBio,
  });

  final int id;
  final int listingId;
  final int senderUserId;
  final int recipientUserId;
  final String listingTitle;
  final String listingPrice;
  final String listingCategory;
  final String listingStatus;
  final String senderName;
  final String senderAvatar;
  final String recipientName;
  final String recipientAvatar;
  final String preferredContactMethod;
  final String message;
  final DateTime createdAt;
  final InquiryStatus status;
  final String inquiryStatus;
  final String? proofImagePath;
  final DateTime? completedAt;
  final DateTime? decidedAt;
  final int? decidedBy;
  final String? counterpartyContact;
  final String? counterpartyProgram;
  final String? counterpartyYearLevel;
  final String? counterpartyOrganization;
  final String? counterpartySection;
  final String? counterpartyBio;

  factory Inquiry.fromApi(
    Map<String, dynamic> json, {
    Listing? fallbackListing,
    BackendListingAdapter? listingAdapter,
  }) {
    final resolvedListingAdapter =
        listingAdapter ?? BackendListingAdapter.instance;
    final listingMap = _mapValue(json['listing']);
    final listingId = _parseInt(
      json['listing_id'] ?? listingMap?['id'],
      fallback: fallbackListing?.id ?? 0,
    );
    final cachedListing =
        fallbackListing ?? resolvedListingAdapter.cached(listingId);

    final senderMap = _mapValue(json['sender']);
    final recipientMap = _mapValue(json['recipient']);
    final counterpartyMap = _mapValue(json['counterparty']);
    final senderName = _stringValue(senderMap?['full_name'], fallback: 'Buyer');
    final recipientName = _stringValue(
      recipientMap?['full_name'],
      fallback: 'Seller',
    );

    return Inquiry(
      id: _parseInt(json['id']),
      listingId: listingId,
      senderUserId: _parseInt(json['sender_user_id']),
      recipientUserId: _parseInt(json['recipient_user_id']),
      listingTitle: _stringValue(
        listingMap?['title'],
        fallback: cachedListing?.title ?? 'Listing #$listingId',
      ),
      listingPrice: _stringValue(cachedListing?.price, fallback: 'N/A'),
      listingCategory: _stringValue(
        cachedListing?.category,
        fallback: 'Category',
      ),
      listingStatus: _stringValue(
        listingMap?['listing_status'],
        fallback: cachedListing?.listingStatus ?? '',
      ),
      senderName: capitalizeName(senderName),
      senderAvatar: _avatarFromName(senderName),
      recipientName: capitalizeName(recipientName),
      recipientAvatar: _avatarFromName(recipientName),
      preferredContactMethod: _stringValue(
        json['preferred_contact_method'],
        fallback: 'in_app',
      ).toLowerCase(),
      message: _stringValue(json['message']),
      createdAt: _parseDateTime(json['created_at']) ?? DateTime.now(),
      status: _statusFromApi(json['status']),
      inquiryStatus: _stringValue(json['inquiry_status']),
      proofImagePath: _nullableString(json['proof_image_path']),
      completedAt: _parseDateTime(json['completed_at']),
      decidedAt: _parseDateTime(json['decided_at']),
      decidedBy: _parseNullableInt(json['decided_by']),
      counterpartyContact: _nullableString(
        json['counterparty_contact'] ?? counterpartyMap?['contact_number'],
      ),
      counterpartyProgram: _nullableString(
        json['counterparty_program'] ?? counterpartyMap?['program'],
      ),
      counterpartyYearLevel: _nullableString(
        json['counterparty_year_level'] ?? counterpartyMap?['year_level'],
      ),
      counterpartyOrganization: _nullableString(
        json['counterparty_organization'] ?? counterpartyMap?['organization'],
      ),
      counterpartySection: _nullableString(
        json['counterparty_section'] ?? counterpartyMap?['section'],
      ),
      counterpartyBio: _nullableString(
        json['counterparty_bio'] ?? counterpartyMap?['bio'],
      ),
    );
  }

  Inquiry copyWith({
    int? id,
    int? listingId,
    int? senderUserId,
    int? recipientUserId,
    String? listingTitle,
    String? listingPrice,
    String? listingCategory,
    String? listingStatus,
    String? senderName,
    String? senderAvatar,
    String? recipientName,
    String? recipientAvatar,
    String? preferredContactMethod,
    String? message,
    DateTime? createdAt,
    InquiryStatus? status,
    String? inquiryStatus,
    String? proofImagePath,
    DateTime? completedAt,
    DateTime? decidedAt,
    int? decidedBy,
    String? counterpartyContact,
    String? counterpartyProgram,
    String? counterpartyYearLevel,
    String? counterpartyOrganization,
    String? counterpartySection,
    String? counterpartyBio,
  }) {
    return Inquiry(
      id: id ?? this.id,
      listingId: listingId ?? this.listingId,
      senderUserId: senderUserId ?? this.senderUserId,
      recipientUserId: recipientUserId ?? this.recipientUserId,
      listingTitle: listingTitle ?? this.listingTitle,
      listingPrice: listingPrice ?? this.listingPrice,
      listingCategory: listingCategory ?? this.listingCategory,
      listingStatus: listingStatus ?? this.listingStatus,
      senderName: senderName ?? this.senderName,
      senderAvatar: senderAvatar ?? this.senderAvatar,
      recipientName: recipientName ?? this.recipientName,
      recipientAvatar: recipientAvatar ?? this.recipientAvatar,
      preferredContactMethod:
          preferredContactMethod ?? this.preferredContactMethod,
      message: message ?? this.message,
      createdAt: createdAt ?? this.createdAt,
      status: status ?? this.status,
      inquiryStatus: inquiryStatus ?? this.inquiryStatus,
      proofImagePath: proofImagePath ?? this.proofImagePath,
      completedAt: completedAt ?? this.completedAt,
      decidedAt: decidedAt ?? this.decidedAt,
      decidedBy: decidedBy ?? this.decidedBy,
      counterpartyContact: counterpartyContact ?? this.counterpartyContact,
      counterpartyProgram: counterpartyProgram ?? this.counterpartyProgram,
      counterpartyYearLevel:
          counterpartyYearLevel ?? this.counterpartyYearLevel,
      counterpartyOrganization:
          counterpartyOrganization ?? this.counterpartyOrganization,
      counterpartySection: counterpartySection ?? this.counterpartySection,
      counterpartyBio: counterpartyBio ?? this.counterpartyBio,
    );
  }

  String counterpartyName({required bool isReceived}) {
    return isReceived ? senderName : recipientName;
  }

  String counterpartyAvatar({required bool isReceived}) {
    return isReceived ? senderAvatar : recipientAvatar;
  }

  String get preferredContactLabel {
    switch (preferredContactMethod) {
      case 'email':
        return 'Email';
      case 'phone':
        return 'Phone';
      default:
        return 'In-app';
    }
  }

  String get listingMetaLabel {
    if (listingPrice != 'N/A') {
      return listingPrice;
    }

    if (listingStatus.trim().isNotEmpty) {
      return _titleCase(listingStatus.replaceAll('_', ' '));
    }

    return 'Listing';
  }
}

InquiryStatus _statusFromApi(dynamic rawValue) {
  switch (_stringValue(rawValue).toLowerCase()) {
    case 'accepted':
      return InquiryStatus.accepted;
    case 'completed':
      return InquiryStatus.completed;
    case 'declined':
      return InquiryStatus.declined;
    default:
      return InquiryStatus.pending;
  }
}

int _parseInt(dynamic rawValue, {int fallback = 0}) {
  if (rawValue is int) {
    return rawValue;
  }

  return int.tryParse(rawValue?.toString() ?? '') ?? fallback;
}

int? _parseNullableInt(dynamic rawValue) {
  if (rawValue == null) {
    return null;
  }

  return _parseInt(rawValue);
}

DateTime? _parseDateTime(dynamic rawValue) {
  final value = _stringValue(rawValue);
  if (value.isEmpty) {
    return null;
  }

  return DateTime.tryParse(value)?.toLocal();
}

String _stringValue(dynamic rawValue, {String fallback = ''}) {
  final value = rawValue?.toString().trim() ?? '';
  return value.isEmpty ? fallback : value;
}

String? _nullableString(dynamic rawValue) {
  final value = rawValue?.toString().trim() ?? '';
  return value.isEmpty ? null : value;
}

Map<String, dynamic>? _mapValue(dynamic rawValue) {
  if (rawValue is Map) {
    return Map<String, dynamic>.from(rawValue);
  }

  return null;
}

String _avatarFromName(String value) {
  final normalizedValue = value.trim();
  return normalizedValue.isNotEmpty ? normalizedValue[0].toUpperCase() : '?';
}

String _titleCase(String value) {
  return value
      .split(' ')
      .where((part) => part.trim().isNotEmpty)
      .map((part) => part[0].toUpperCase() + part.substring(1).toLowerCase())
      .join(' ');
}
