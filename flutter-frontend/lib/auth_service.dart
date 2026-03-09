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
  bool _hasSessionToken = false;
  Map<String, dynamic>? _currentUser;
  Map<String, dynamic>? _lastPingBody;
  int? _lastPingStatusCode;
  String? _lastPingError;
  String? _lastResponseMessage;
  String? _lastAuthErrorCode;
  String? _lastAuthErrorIdentifier;
  final Set<VoidCallback> _sessionResetCallbacks = <VoidCallback>{};

  bool get isLoggedIn => _currentUser != null;
  bool get hasSession => _hasSessionToken;
  Map<String, dynamic>? get currentUser => _currentUser;
  Map<String, dynamic>? get lastPingBody => _lastPingBody;
  int? get lastPingStatusCode => _lastPingStatusCode;
  String? get lastPingError => _lastPingError;
  String get baseUrl => AppConfig.baseUrl;
  String? get lastResponseMessage => _lastResponseMessage;
  String? get lastAuthErrorCode => _lastAuthErrorCode;
  String? get lastAuthErrorIdentifier => _lastAuthErrorIdentifier;

  void registerSessionResetCallback(VoidCallback callback) {
    _sessionResetCallbacks.add(callback);
  }

  Future<void> init() async {
    if (_initialized) {
      return;
    }
    _initialized = true;

    final token = await _tokenStorage.readToken();
    if (token == null || token.isEmpty) {
      _hasSessionToken = false;
      return;
    }

    _hasSessionToken = true;
    await refreshCurrentUser();
  }

  Future<String?> refreshCurrentUser() async {
    try {
      final response = await _apiClient.dio.get('/api/v1/auth/me');
      final rawUser = _apiClient.extractDataItemMap(response.data, 'user');
      if (rawUser == null) {
        return 'Invalid user payload.';
      }

      _lastResponseMessage = _apiClient.extractMessage(response.data);
      _currentUser = _normalizeUser(rawUser);
      _hasSessionToken = true;
      return null;
    } catch (error) {
      final message = _apiClient.mapError(error);
      if (await clearSessionIfUnauthorized(error)) {
        return message;
      }

      return message;
    }
  }

  Future<String?> register({
    required String name,
    required String email,
    String? username,
    required String password,
    required String studentId,
  }) async {
    if (name.trim().isEmpty) {
      return 'Name is required.';
    }
    if (email.trim().isEmpty) {
      return 'Email is required.';
    }
    if (studentId.trim().isEmpty) {
      return 'Student ID is required.';
    }
    if (password.trim().isEmpty) {
      return 'Password is required.';
    }

    _lastAuthErrorCode = null;
    _lastAuthErrorIdentifier = null;
    _lastResponseMessage = null;

    final normalizedEmail = email.trim().toLowerCase();

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/register',
        data: <String, dynamic>{
          'name': name.trim(),
          'student_id': studentId.trim(),
          'email': normalizedEmail,
          'password': password,
          'password_confirmation': password,
        },
      );
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> login({
    required String identifier,
    required String password,
  }) async {
    final normalizedIdentifier = _normalizeIdentifier(identifier);

    if (normalizedIdentifier.isEmpty) {
      return 'Email is required.';
    }
    if (password.trim().isEmpty) {
      return 'Password is required.';
    }

    _lastAuthErrorCode = null;
    _lastAuthErrorIdentifier = null;
    _lastResponseMessage = null;

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/login',
        data: _buildLoginPayload(
          identifier: normalizedIdentifier,
          password: password,
        ),
      );

      final payload = _apiClient.extractDataMap(response.data);
      if (payload == null) {
        return 'Invalid login payload.';
      }

      final token = payload['token'];
      final rawUser = _apiClient.asMap(payload['user']);
      if (token is! String || token.isEmpty || rawUser == null) {
        return 'Invalid login payload.';
      }

      await _tokenStorage.saveToken(token);
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      _currentUser = _normalizeUser(rawUser);
      _hasSessionToken = true;

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
    final normalizedIdentifier = _normalizeIdentifier(identifier);
    if (normalizedIdentifier.isEmpty) {
      return 'Email is required.';
    }

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/email/otp/resend',
        data: <String, dynamic>{'identifier': normalizedIdentifier},
      );
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> verifyEmailOtp({
    required String identifier,
    required String otp,
  }) async {
    final normalizedIdentifier = _normalizeIdentifier(identifier);
    final normalizedOtp = otp.trim();

    if (normalizedIdentifier.isEmpty) {
      return 'Email is required.';
    }

    if (normalizedOtp.length != 6) {
      return 'OTP must be 6 digits.';
    }

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/email/otp/verify',
        data: <String, dynamic>{
          'identifier': normalizedIdentifier,
          'otp': normalizedOtp,
        },
      );
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> forgotPassword({required String email}) async {
    final normalizedEmail = email.trim().toLowerCase();
    if (normalizedEmail.isEmpty) {
      return 'Email is required.';
    }

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/auth/password/forgot',
        data: <String, dynamic>{'email': normalizedEmail},
      );
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> resetPassword({
    required String email,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    final normalizedEmail = email.trim().toLowerCase();
    final normalizedOtp = otp.trim();

    if (normalizedEmail.isEmpty) {
      return 'Email is required.';
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
      final response = await _apiClient.dio.post(
        '/api/v1/auth/password/reset',
        data: <String, dynamic>{
          'email': normalizedEmail,
          'otp': normalizedOtp,
          'password': password,
          'password_confirmation': passwordConfirmation,
        },
      );
      _lastResponseMessage = _apiClient.extractMessage(response.data);
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<void> logout() async {
    try {
      final response = await _apiClient.dio.post('/api/v1/auth/logout');
      _lastResponseMessage = _apiClient.extractMessage(response.data);
    } catch (_) {
      if (kDebugMode) {
        debugPrint('Logout endpoint failed; clearing local session anyway.');
      }
    } finally {
      await _clearLocalSession();
    }
  }

  Future<bool> clearSessionIfUnauthorized(Object error) async {
    if (!_apiClient.isUnauthorizedError(error)) {
      return false;
    }

    await _clearLocalSession();
    return true;
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
    final id = rawUser['id'];
    final name = (rawUser['name'] ?? '').toString();
    final studentId = (rawUser['student_id'] ?? rawUser['studentId'] ?? '')
        .toString();
    final email = (rawUser['email'] ?? '').toString();
    final status = (rawUser['status'] ?? '').toString();
    final roles = rawUser['roles'] is List
        ? List<String>.from(
            (rawUser['roles'] as List).map((role) => role.toString()),
          )
        : <String>[];

    return <String, dynamic>{
      'id': id is int ? id : int.tryParse(id?.toString() ?? ''),
      'name': name,
      'studentId': studentId,
      'email': email,
      'status': status,
      'roles': roles,
      'avatar': name.isNotEmpty ? name.substring(0, 1).toUpperCase() : '?',
    };
  }

  String _normalizeIdentifier(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty) {
      return '';
    }

    if (_looksLikeEmail(trimmed)) {
      return trimmed.toLowerCase();
    }

    return trimmed;
  }

  bool _looksLikeEmail(String value) {
    return value.contains('@');
  }

  Map<String, dynamic> _buildLoginPayload({
    required String identifier,
    required String password,
  }) {
    if (_looksLikeEmail(identifier)) {
      return <String, dynamic>{'email': identifier, 'password': password};
    }

    return <String, dynamic>{'student_id': identifier, 'password': password};
  }

  Future<void> _clearLocalSession() async {
    await _tokenStorage.clearToken();
    _hasSessionToken = false;
    _currentUser = null;
    _lastPingBody = null;
    _lastPingStatusCode = null;
    _lastPingError = null;
    _lastResponseMessage = null;
    _lastAuthErrorCode = null;
    _lastAuthErrorIdentifier = null;

    for (final callback in _sessionResetCallbacks) {
      callback();
    }
  }
}
