import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

import 'config/app_config.dart';
import 'core/network/api_client.dart';
import 'services/token_storage.dart';

class AuthService {
  static final AuthService _instance = AuthService._internal();

  factory AuthService() => _instance;

  AuthService._internal();

  final ApiClient _apiClient = ApiClient();
  final TokenStorage _tokenStorage = TokenStorage();

  bool _initialized = false;
  Map<String, dynamic>? _currentUser;
  Map<String, dynamic>? _lastPingBody;
  int? _lastPingStatusCode;
  String? _lastPingError;
  String? _lastAuthErrorCode;
  String? _lastAuthErrorIdentifier;

  bool get isLoggedIn => _currentUser != null;
  Map<String, dynamic>? get currentUser => _currentUser;
  Map<String, dynamic>? get lastPingBody => _lastPingBody;
  int? get lastPingStatusCode => _lastPingStatusCode;
  String? get lastPingError => _lastPingError;
  String get baseUrl => AppConfig.baseUrl;
  String? get lastAuthErrorCode => _lastAuthErrorCode;
  String? get lastAuthErrorIdentifier => _lastAuthErrorIdentifier;

  Future<void> init() async {
    if (_initialized) {
      return;
    }
    _initialized = true;

    final token = await _tokenStorage.readToken();
    if (token == null || token.isEmpty) {
      return;
    }

    final error = await refreshCurrentUser();
    if (error != null) {
      await _tokenStorage.clearToken();
      _currentUser = null;
    }
  }

  Future<String?> refreshCurrentUser() async {
    try {
      final response = await _apiClient.dio.get('/api/v1/auth/me');
      final data = response.data;
      if (data is! Map<String, dynamic>) {
        return 'Invalid response from server.';
      }

      final payload = data['data'];
      if (payload is! Map<String, dynamic>) {
        return 'Invalid user payload.';
      }

      final rawUser = payload['user'];
      if (rawUser is! Map<String, dynamic>) {
        return 'Invalid user payload.';
      }

      _currentUser = _normalizeUser(rawUser);
      return null;
    } catch (error) {
      final message = _apiClient.mapError(error);
      if (message.contains('Unauthenticated')) {
        await _tokenStorage.clearToken();
        _currentUser = null;
      }
      return message;
    }
  }

  Future<String?> register({
    required String name,
    required String email,
    required String username,
    required String password,
    required String studentId,
  }) async {
    if (name.trim().isEmpty) {
      return 'Name is required.';
    }
    if (studentId.trim().isEmpty) {
      return 'Student ID is required.';
    }
    if (password.trim().isEmpty) {
      return 'Password is required.';
    }

    _lastAuthErrorCode = null;
    _lastAuthErrorIdentifier = null;

    final normalizedEmail = email.trim();

    try {
      await _apiClient.dio.post(
        '/api/v1/auth/register',
        data: <String, dynamic>{
          'name': name.trim(),
          'student_id': studentId.trim(),
          'password': password,
          'password_confirmation': password,
          if (normalizedEmail.isNotEmpty) 'email': normalizedEmail,
        },
      );
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> login({
    required String studentId,
    required String password,
  }) async {
    if (studentId.trim().isEmpty) {
      return 'Student ID is required.';
    }
    if (password.trim().isEmpty) {
      return 'Password is required.';
    }

    _lastAuthErrorCode = null;
    _lastAuthErrorIdentifier = null;

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/login',
        data: <String, dynamic>{
          'identifier': studentId.trim(),
          'password': password,
        },
      );

      final body = response.data;
      if (body is! Map<String, dynamic>) {
        return 'Invalid response from server.';
      }

      final payload = body['data'];
      if (payload is! Map<String, dynamic>) {
        return 'Invalid login payload.';
      }

      final token = payload['token'];
      final rawUser = payload['user'];
      if (token is! String ||
          token.isEmpty ||
          rawUser is! Map<String, dynamic>) {
        return 'Invalid login payload.';
      }

      await _tokenStorage.saveToken(token);
      _currentUser = _normalizeUser(rawUser);

      await pingBackend();
      return null;
    } catch (error) {
      if (error is DioException) {
        _lastAuthErrorCode = _apiClient.extractErrorCode(error);
        _lastAuthErrorIdentifier = _apiClient.extractErrorIdentifier(error);
      }
      return _apiClient.mapError(error);
    }
  }

  Future<String?> resendEmailOtp({required String identifier}) async {
    final normalizedIdentifier = identifier.trim();
    if (normalizedIdentifier.isEmpty) {
      return 'Identifier is required.';
    }

    try {
      await _apiClient.dio.post(
        '/api/v1/auth/email/otp/resend',
        data: <String, dynamic>{'identifier': normalizedIdentifier},
      );
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> verifyEmailOtp({
    required String identifier,
    required String otp,
  }) async {
    final normalizedIdentifier = identifier.trim();
    final normalizedOtp = otp.trim();

    if (normalizedIdentifier.isEmpty) {
      return 'Identifier is required.';
    }

    if (normalizedOtp.length != 6) {
      return 'OTP must be 6 digits.';
    }

    try {
      await _apiClient.dio.post(
        '/api/v1/auth/email/otp/verify',
        data: <String, dynamic>{
          'identifier': normalizedIdentifier,
          'otp': normalizedOtp,
        },
      );
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> forgotPassword({required String identifier}) async {
    final normalizedIdentifier = identifier.trim();
    if (normalizedIdentifier.isEmpty) {
      return 'Student ID or email is required.';
    }

    try {
      await _apiClient.dio.post(
        '/api/v1/auth/password/forgot',
        data: <String, dynamic>{'identifier': normalizedIdentifier},
      );
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> resetPassword({
    required String identifier,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    final normalizedIdentifier = identifier.trim();
    final normalizedOtp = otp.trim();

    if (normalizedIdentifier.isEmpty) {
      return 'Identifier is required.';
    }
    if (normalizedOtp.length != 6) {
      return 'OTP must be 6 digits.';
    }
    if (password.isEmpty) {
      return 'Password is required.';
    }
    if (password != passwordConfirmation) {
      return 'Passwords do not match.';
    }

    try {
      await _apiClient.dio.post(
        '/api/v1/auth/password/reset',
        data: <String, dynamic>{
          'identifier': normalizedIdentifier,
          'otp': normalizedOtp,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<void> logout() async {
    try {
      await _apiClient.dio.post('/api/v1/auth/logout');
    } catch (_) {
      if (kDebugMode) {
        debugPrint('Logout endpoint failed; clearing local session anyway.');
      }
    } finally {
      await _tokenStorage.clearToken();
      _currentUser = null;
      _lastPingBody = null;
      _lastPingStatusCode = null;
      _lastPingError = null;
    }
  }

  Future<String?> pingBackend() async {
    try {
      final response = await _apiClient.dio.get('/api/ping');
      _lastPingStatusCode = response.statusCode;
      final data = response.data;
      if (data is Map<String, dynamic>) {
        _lastPingBody = data;
      } else {
        _lastPingBody = <String, dynamic>{'data': data};
      }
      _lastPingError = null;
      return null;
    } catch (error) {
      _lastPingStatusCode = null;
      _lastPingBody = null;
      _lastPingError = _apiClient.mapError(error);
      return _lastPingError;
    }
  }

  Map<String, dynamic> _normalizeUser(Map<String, dynamic> rawUser) {
    final name = (rawUser['name'] ?? '').toString();
    final studentId = (rawUser['student_id'] ?? rawUser['studentId'] ?? '')
        .toString();
    final email = (rawUser['email'] ?? '').toString();
    final status = (rawUser['status'] ?? '').toString();

    return <String, dynamic>{
      'name': name,
      'studentId': studentId,
      'email': email,
      'status': status,
      'avatar': name.isNotEmpty ? name.substring(0, 1).toUpperCase() : '?',
    };
  }
}
