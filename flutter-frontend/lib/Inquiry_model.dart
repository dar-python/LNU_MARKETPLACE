// ─── Inquiry Status Enum ──────────────────────────────────────────────────────
enum InquiryStatus { pending, accepted, declined }

// ─── Inquiry Model ────────────────────────────────────────────────────────────
class Inquiry {
  final int id;
  final int listingId;
  final String listingTitle;
  final String listingPrice;
  final String listingCategory;
  final String buyerName;
  final String buyerAvatar;
  final String buyerStudentId;
  final String message;
  final DateTime createdAt;
  InquiryStatus status;

  Inquiry({
    required this.id,
    required this.listingId,
    required this.listingTitle,
    required this.listingPrice,
    required this.listingCategory,
    required this.buyerName,
    required this.buyerAvatar,
    required this.buyerStudentId,
    this.message = '',
    required this.createdAt,
    this.status = InquiryStatus.pending,
  });
}