import 'auth_service.dart';
import 'core/network/api_client.dart';

class ReportService {
  static final ReportService _instance = ReportService._internal();

  factory ReportService() => _instance;

  ReportService._internal();

  final ApiClient _apiClient = ApiClient();

  Future<void> submitListingReport({
    required int listingId,
    required String reasonCategory,
    required String description,
  }) async {
    if (!AuthService().hasSession) {
      throw const FormatException('Please log in to submit a report.');
    }

    await _apiClient.dio.post(
      '/api/v1/reports/listings/$listingId',
      data: <String, dynamic>{
        'reason_category': reasonCategory.trim(),
        'description': description.trim(),
      },
    );
  }
}
