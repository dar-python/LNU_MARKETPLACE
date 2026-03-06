import 'Inquiry_model.dart';
import 'auth_service.dart';

// ─── InquiryService (Singleton) ───────────────────────────────────────────────
class InquiryService {
  static final InquiryService _instance = InquiryService._internal();
  factory InquiryService() => _instance;
  InquiryService._internal();

  final List<Inquiry> _inquiries = [
    // ── Sample / Dummy Inquiries ──────────────────────────────────────────
    Inquiry(
      id: 1,
      listingId: 0,
      listingTitle: 'Scientific Calculator',
      listingPrice: '₱350.00',
      listingCategory: 'Gadgets',
      buyerName: 'Maria Santos',
      buyerAvatar: 'M',
      buyerStudentId: '2021-00123',
      message: 'Is this still available?',
      createdAt: DateTime.now().subtract(const Duration(hours: 2)),
      status: InquiryStatus.pending,
    ),
    Inquiry(
      id: 2,
      listingId: 1,
      listingTitle: 'Engineering Mathematics Book',
      listingPrice: '₱250.00',
      listingCategory: 'Books',
      buyerName: 'Juan Dela Cruz',
      buyerAvatar: 'J',
      buyerStudentId: '2022-00456',
      message: 'Can we meet at the library?',
      createdAt: DateTime.now().subtract(const Duration(days: 1)),
      status: InquiryStatus.accepted,
    ),
    Inquiry(
      id: 3,
      listingId: 2,
      listingTitle: 'PE Uniform (Large)',
      listingPrice: '₱180.00',
      listingCategory: 'Uniforms',
      buyerName: 'Ana Reyes',
      buyerAvatar: 'A',
      buyerStudentId: '2023-00789',
      message: 'What size is this exactly?',
      createdAt: DateTime.now().subtract(const Duration(days: 3)),
      status: InquiryStatus.declined,
    ),
  ];

  // ── Getters ───────────────────────────────────────────────────────────────

  /// Inquiries received by the current user (as a seller)
  List<Inquiry> get receivedInquiries {
    final user = AuthService().currentUser;
    if (user == null) return [];
    // In a real app, filter by seller. For now return sample received inquiries.
    return List.unmodifiable(_inquiries);
  }

  /// Inquiries sent by the current user (as a buyer)
  List<Inquiry> get sentInquiries {
    final user = AuthService().currentUser;
    if (user == null) return [];
    final studentId = user['studentId'] as String;
    return List.unmodifiable(
      _inquiries.where((i) => i.buyerStudentId == studentId).toList(),
    );
  }

  /// Count of pending received inquiries
  int get pendingCount =>
      receivedInquiries.where((i) => i.status == InquiryStatus.pending).length;

  // ── Actions ───────────────────────────────────────────────────────────────

  /// Send a new inquiry for a listing
  void sendInquiry({
    required int listingId,
    required String listingTitle,
    required String listingPrice,
    required String listingCategory,
    String message = '',
  }) {
    final user = AuthService().currentUser;
    if (user == null) return;

    final name = user['name'] as String? ?? 'Unknown';
    final studentId = user['studentId'] as String? ?? '';

    _inquiries.insert(
      0,
      Inquiry(
        id: DateTime.now().millisecondsSinceEpoch,
        listingId: listingId,
        listingTitle: listingTitle,
        listingPrice: listingPrice,
        listingCategory: listingCategory,
        buyerName: name,
        buyerAvatar: name.isNotEmpty ? name[0].toUpperCase() : '?',
        buyerStudentId: studentId,
        message: message,
        createdAt: DateTime.now(),
        status: InquiryStatus.pending,
      ),
    );
  }

  /// Accept an inquiry (seller action)
  void acceptInquiry(int id) {
    final inquiry = _inquiries.firstWhere((i) => i.id == id);
    inquiry.status = InquiryStatus.accepted;
  }

  /// Decline an inquiry (seller action)
  void declineInquiry(int id) {
    final inquiry = _inquiries.firstWhere((i) => i.id == id);
    inquiry.status = InquiryStatus.declined;
  }

  /// Delete an inquiry
  void deleteInquiry(int id) {
    _inquiries.removeWhere((i) => i.id == id);
  }
}