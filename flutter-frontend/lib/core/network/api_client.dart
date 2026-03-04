import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';

import '../../config/app_config.dart';
import '../../services/token_storage.dart';

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();

  factory ApiClient() => _instance;

  ApiClient._internal()
    : _dio = Dio(
        BaseOptions(
          baseUrl: AppConfig.baseUrl,
          connectTimeout: const Duration(seconds: 10),
          receiveTimeout: const Duration(seconds: 60),
          sendTimeout: const Duration(seconds: 30),
          headers: <String, String>{
            'Accept': 'application/json',
            'Content-Type': 'application/json',
          },
        ),
      ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest:
            (RequestOptions options, RequestInterceptorHandler handler) async {
              final token = await TokenStorage().readToken();
              if (token != null && token.isNotEmpty) {
                options.headers['Authorization'] = 'Bearer $token';
              }

              if (kDebugMode) {
                debugPrint('HTTP ${options.method} ${options.uri}');
              }
              handler.next(options);
            },
        onResponse:
            (Response<dynamic> response, ResponseInterceptorHandler handler) {
              if (kDebugMode) {
                debugPrint(
                  'HTTP ${response.statusCode} ${response.requestOptions.method} ${response.requestOptions.path}',
                );
              }
              handler.next(response);
            },
        onError: (DioException error, ErrorInterceptorHandler handler) {
          if (kDebugMode) {
            debugPrint(
              'HTTP ERROR ${error.response?.statusCode ?? ''} ${error.requestOptions.method} ${error.requestOptions.path} ${error.message}',
            );
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;

  Dio get dio => _dio;

  String mapError(Object error) {
    if (error is DioException) {
      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          return 'Request timed out. Please try again.';
        case DioExceptionType.connectionError:
          if (error.error is SocketException) {
            return 'Cannot reach the server. Check API_BASE_URL and network access.';
          }
          return 'Connection failed. Check API_BASE_URL and network access.';
        case DioExceptionType.badCertificate:
          return 'TLS certificate validation failed.';
        case DioExceptionType.cancel:
          return 'Request canceled.';
        case DioExceptionType.badResponse:
          return _extractBackendMessage(error.response);
        case DioExceptionType.unknown:
          if (error.error is SocketException) {
            return 'Network error. Please check your connection.';
          }
          return error.message ?? 'Unexpected network error.';
      }
    }

    return 'Unexpected error.';
  }

  String? extractErrorCode(Object error) {
    return _extractErrorMeta(error, 'code');
  }

  String? extractErrorIdentifier(Object error) {
    return _extractErrorMeta(error, 'identifier');
  }

  String _extractBackendMessage(Response<dynamic>? response) {
    final statusCode = response?.statusCode ?? 0;
    final data = response?.data;

    if (data is Map<String, dynamic>) {
      final dynamic message = data['message'];
      final dynamic errors = data['errors'];

      if (errors is Map<String, dynamic> && errors.isNotEmpty) {
        final entries = errors.entries
            .where((entry) => entry.key != 'code' && entry.key != 'identifier')
            .toList();
        if (entries.isNotEmpty) {
          final dynamic firstError = entries.first.value;
          if (firstError is List && firstError.isNotEmpty) {
            return firstError.first.toString();
          }
          if (firstError != null) {
            return firstError.toString();
          }
        }
      }

      if (message is String && message.isNotEmpty) {
        return message;
      }
    }

    if (data is String && data.isNotEmpty) {
      return data;
    }

    if (statusCode == 401) {
      return 'Unauthenticated. Please log in again.';
    }
    if (statusCode == 403) {
      return 'Forbidden.';
    }
    if (statusCode == 404) {
      return 'Endpoint not found. Check API_BASE_URL.';
    }
    if (statusCode == 422) {
      return 'Validation failed.';
    }
    if (statusCode >= 500) {
      return 'Server error. Please try again.';
    }

    return 'Request failed (${statusCode == 0 ? 'no status' : statusCode}).';
  }

  String? _extractErrorMeta(Object error, String key) {
    if (error is! DioException) {
      return null;
    }

    final data = error.response?.data;
    if (data is! Map<String, dynamic>) {
      return null;
    }

    final errors = data['errors'];
    if (errors is Map<String, dynamic>) {
      final value = errors[key];
      if (value is String && value.isNotEmpty) {
        return value;
      }
    }

    final value = data[key];
    if (value is String && value.isNotEmpty) {
      return value;
    }

    return null;
  }
}
