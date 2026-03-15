import 'dart:io';

import 'package:dio/dio.dart';

import 'inquiry_model.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_model_page.dart';

class InquiryService {
  static final InquiryService _instance = InquiryService._internal();

  factory InquiryService() => _instance;

  InquiryService._internal();

  final ApiClient _apiClient = ApiClient();
  final BackendListingAdapter _listingAdapter = BackendListingAdapter.instance;

  Future<List<Inquiry>> fetchSentInquiries({int perPage = 50}) async {
    final response = await _apiClient.dio.get(
      '/api/v1/inquiries/sent',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );

    return _extractInquiryList(response.data);
  }

  Future<List<Inquiry>> fetchReceivedInquiries({int perPage = 50}) async {
    final response = await _apiClient.dio.get(
      '/api/v1/inquiries/received',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );

    return _extractInquiryList(response.data);
  }

  Future<Inquiry> fetchInquiryDetail(int inquiryId, {Inquiry? fallback}) async {
    final response = await _apiClient.dio.get('/api/v1/inquiries/$inquiryId');
    final rawInquiry = _apiClient.extractDataItemMap(response.data, 'inquiry');
    if (rawInquiry == null) {
      throw const FormatException('Invalid inquiry payload.');
    }

    return Inquiry.fromApi(
      rawInquiry,
      fallbackListing: _fallbackListingFromInquiry(fallback),
      listingAdapter: _listingAdapter,
    );
  }

  Future<Inquiry> sendInquiry({
    required int listingId,
    required String message,
    required String preferredContactMethod,
    Listing? listing,
  }) async {
    if (!AuthService().hasSession) {
      throw const FormatException('Please log in to send an inquiry.');
    }

    final response = await _apiClient.dio.post(
      '/api/v1/listings/$listingId/inquiries',
      data: <String, dynamic>{
        'message': message.trim(),
        'preferred_contact_method': preferredContactMethod,
      },
    );

    final rawInquiry = _apiClient.extractDataItemMap(response.data, 'inquiry');
    if (rawInquiry == null) {
      throw const FormatException('Invalid inquiry payload.');
    }

    return Inquiry.fromApi(
      rawInquiry,
      fallbackListing: listing,
      listingAdapter: _listingAdapter,
    );
  }

  Future<Inquiry> decideInquiry({
    required int inquiryId,
    required InquiryStatus decision,
    Inquiry? fallback,
  }) async {
    final response = await _apiClient.dio.patch(
      '/api/v1/inquiries/$inquiryId/decision',
      data: <String, dynamic>{'status': _decisionStatus(decision)},
    );

    final rawInquiry = _apiClient.extractDataItemMap(response.data, 'inquiry');
    if (rawInquiry == null) {
      throw const FormatException('Invalid inquiry payload.');
    }

    return Inquiry.fromApi(
      rawInquiry,
      fallbackListing: _fallbackListingFromInquiry(fallback),
      listingAdapter: _listingAdapter,
    );
  }

  Future<Inquiry> completeTransaction({
    required int inquiryId,
    required File proofImage,
    Inquiry? fallback,
  }) async {
    final response = await _apiClient.dio.post(
      '/api/v1/inquiries/$inquiryId/complete',
      data: FormData.fromMap(<String, dynamic>{
        'proof_image': await MultipartFile.fromFile(
          proofImage.path,
          filename: _safeFileName(proofImage),
        ),
      }),
      options: Options(contentType: 'multipart/form-data'),
    );

    final rawInquiry = _apiClient.extractDataItemMap(response.data, 'inquiry');
    if (rawInquiry == null) {
      throw const FormatException('Invalid inquiry payload.');
    }

    return Inquiry.fromApi(
      rawInquiry,
      fallbackListing: _fallbackListingFromInquiry(fallback),
      listingAdapter: _listingAdapter,
    );
  }

  List<Inquiry> _extractInquiryList(dynamic body) {
    final rawInquiries = _apiClient.extractDataItemList(body, 'inquiries');
    if (rawInquiries == null) {
      throw const FormatException('Invalid inquiries payload.');
    }

    return rawInquiries
        .map(
          (rawInquiry) =>
              Inquiry.fromApi(rawInquiry, listingAdapter: _listingAdapter),
        )
        .toList();
  }

  Listing? _fallbackListingFromInquiry(Inquiry? inquiry) {
    if (inquiry == null) {
      return null;
    }

    return _listingAdapter.cached(inquiry.listingId) ??
        Listing(
          id: inquiry.listingId,
          title: inquiry.listingTitle,
          price: inquiry.listingPrice,
          category: inquiry.listingCategory,
          condition: 'Pre-owned',
          description: '',
          seller: inquiry.recipientName,
          sellerAvatar: inquiry.recipientAvatar,
          icon: categoryIcon(inquiry.listingCategory),
          color: categoryColor(inquiry.listingCategory),
          listingStatus: inquiry.listingStatus,
        );
  }

  String _decisionStatus(InquiryStatus decision) {
    switch (decision) {
      case InquiryStatus.accepted:
        return 'accepted';
      case InquiryStatus.completed:
        throw ArgumentError('Completed is not a valid inquiry decision.');
      case InquiryStatus.declined:
        return 'declined';
      case InquiryStatus.pending:
        return 'pending';
    }
  }

  String _safeFileName(File imageFile) {
    final segments = imageFile.uri.pathSegments;
    if (segments.isNotEmpty && segments.last.trim().isNotEmpty) {
      return segments.last.trim();
    }

    return 'proof-image.jpg';
  }
}
