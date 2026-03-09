import 'dart:convert';
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

              if (_shouldLog) {
                debugPrint(
                  'HTTP --> ${options.method} ${options.uri} auth=${token != null && token.isNotEmpty ? 'attached' : 'none'}',
                );
              }
              handler.next(options);
            },
        onResponse:
            (Response<dynamic> response, ResponseInterceptorHandler handler) {
              if (_shouldLog) {
                debugPrint(
                  'HTTP <-- ${response.statusCode} ${response.requestOptions.method} ${response.requestOptions.uri} body=${_bodySnippet(response.data)}',
                );
              }
              handler.next(response);
            },
        onError: (DioException error, ErrorInterceptorHandler handler) {
          if (_shouldLog) {
            debugPrint(
              'HTTP xx ${error.response?.statusCode ?? 'NO_STATUS'} ${error.requestOptions.method} ${error.requestOptions.uri} type=${error.type.name} message=${error.message} body=${_bodySnippet(error.response?.data)}',
            );
          }
          handler.next(error);
        },
      ),
    );
  }

  final Dio _dio;
  bool get _shouldLog => AppConfig.enableNetworkDebugLogs;

  Dio get dio => _dio;

  Map<String, dynamic>? asMap(dynamic value) {
    if (value is Map) {
      return Map<String, dynamic>.from(value);
    }

    return null;
  }

  Map<String, dynamic>? extractDataMap(dynamic body) {
    final envelope = asMap(body);
    if (envelope == null) {
      return null;
    }

    return asMap(envelope['data']);
  }

  Map<String, dynamic>? extractDataItemMap(dynamic body, String key) {
    final data = extractDataMap(body);
    if (data == null) {
      return null;
    }

    return asMap(data[key]);
  }

  List<Map<String, dynamic>>? extractDataItemList(dynamic body, String key) {
    final data = extractDataMap(body);
    if (data == null) {
      return null;
    }

    final rawList = data[key];
    if (rawList is! List) {
      return null;
    }

    return rawList.map(asMap).whereType<Map<String, dynamic>>().toList();
  }

  String? extractTraceId(dynamic body) {
    final envelope = asMap(body);
    if (envelope == null) {
      return null;
    }

    final traceId = envelope['trace_id'];
    if (traceId is String && traceId.isNotEmpty) {
      return traceId;
    }

    return null;
  }

  String? extractMessage(dynamic body) {
    final envelope = asMap(body);
    if (envelope == null) {
      return null;
    }

    final message = envelope['message'];
    if (message is String && message.trim().isNotEmpty) {
      return message.trim();
    }

    return null;
  }

  String mapError(
    Object error, {
    int maxMessages = 1,
    bool includeFieldNames = false,
  }) {
    if (error is DioException) {
      final statusCode = error.response?.statusCode;
      late final String mappedMessage;

      switch (error.type) {
        case DioExceptionType.connectionTimeout:
        case DioExceptionType.sendTimeout:
        case DioExceptionType.receiveTimeout:
          mappedMessage = 'Request timed out. Please try again.';
        case DioExceptionType.connectionError:
          if (error.error is SocketException) {
            mappedMessage =
                'Cannot reach the server at ${AppConfig.baseUrl}. Check API_BASE_URL and network access.';
          } else {
            mappedMessage =
                'Connection failed for ${AppConfig.baseUrl}. Check API_BASE_URL and network access.';
          }
        case DioExceptionType.badCertificate:
          mappedMessage = 'TLS certificate validation failed.';
        case DioExceptionType.cancel:
          mappedMessage = 'Request canceled.';
        case DioExceptionType.badResponse:
          mappedMessage = _extractBackendMessage(
            error.response,
            maxMessages: maxMessages,
            includeFieldNames: includeFieldNames,
          );
        case DioExceptionType.unknown:
          if (error.error is SocketException) {
            mappedMessage = 'Network error. Please check your connection.';
          } else {
            final rawMessage = error.message?.trim();
            if (rawMessage == null ||
                rawMessage.isEmpty ||
                rawMessage.toLowerCase() == 'xmlhttprequest error') {
              mappedMessage =
                  'Unexpected network error while contacting ${AppConfig.baseUrl}. Check API_BASE_URL and backend availability.';
            } else {
              mappedMessage = rawMessage;
            }
          }
      }

      _logMappedError(
        statusCode: statusCode,
        mappedMessage: mappedMessage,
        error: error,
      );

      return mappedMessage;
    }

    return 'Unexpected error.';
  }

  bool isUnauthorizedError(Object error) {
    return error is DioException && error.response?.statusCode == 401;
  }

  bool isNotFoundError(Object error) {
    return statusCodeOf(error) == 404;
  }

  int? statusCodeOf(Object error) {
    if (error is DioException) {
      return error.response?.statusCode;
    }

    return null;
  }

  String? extractErrorCode(Object error) {
    return _extractErrorMeta(error, 'code');
  }

  String? extractErrorIdentifier(Object error) {
    return _extractErrorMeta(error, 'identifier');
  }

  String _extractBackendMessage(
    Response<dynamic>? response, {
    int maxMessages = 1,
    bool includeFieldNames = false,
  }) {
    final statusCode = response?.statusCode ?? 0;
    final data = response?.data;

    if (data is Map) {
      final dataMap = Map<String, dynamic>.from(data);
      final dynamic message = dataMap['message'];
      final dynamic errors = dataMap['errors'];
      final validationSummary = _summarizeBackendErrors(
        errors,
        maxMessages: maxMessages,
        includeFieldNames: includeFieldNames,
      );

      if (validationSummary != null) {
        return validationSummary;
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

  String? _summarizeBackendErrors(
    dynamic rawErrors, {
    required int maxMessages,
    required bool includeFieldNames,
  }) {
    if (rawErrors is! Map || rawErrors.isEmpty) {
      return null;
    }

    final entries = Map<String, dynamic>.from(rawErrors).entries
        .where((entry) => entry.key != 'code' && entry.key != 'identifier')
        .toList();
    if (entries.isEmpty) {
      return null;
    }

    final messages = <String>[];
    for (final entry in entries) {
      final rawMessage = _firstErrorMessage(entry.value);
      if (rawMessage == null || rawMessage.isEmpty) {
        continue;
      }

      if (includeFieldNames) {
        messages.add('${_humanizeFieldName(entry.key)}: $rawMessage');
      } else {
        messages.add(rawMessage);
      }

      if (messages.length >= maxMessages) {
        break;
      }
    }

    if (messages.isEmpty) {
      return null;
    }

    final remainingCount = entries.length - messages.length;
    if (remainingCount > 0) {
      messages.add(
        '+$remainingCount more error${remainingCount == 1 ? '' : 's'}',
      );
    }

    return messages.join('\n');
  }

  String? _firstErrorMessage(dynamic rawValue) {
    if (rawValue is List && rawValue.isNotEmpty) {
      return rawValue.first?.toString().trim();
    }

    final value = rawValue?.toString().trim() ?? '';
    if (value.isEmpty) {
      return null;
    }

    return value;
  }

  String _humanizeFieldName(String value) {
    final normalized = value
        .replaceAll(RegExp(r'\[\d+\]'), '')
        .replaceAll('_', ' ')
        .replaceAll('.', ' ')
        .trim();
    if (normalized.isEmpty) {
      return 'Field';
    }

    return normalized
        .split(RegExp(r'\s+'))
        .map((part) {
          if (part.isEmpty) {
            return part;
          }

          return part[0].toUpperCase() + part.substring(1);
        })
        .join(' ');
  }

  void _logMappedError({
    required int? statusCode,
    required String mappedMessage,
    required DioException error,
  }) {
    if (!_shouldLog) {
      return;
    }

    final shouldLogStatus =
        statusCode == 401 ||
        statusCode == 403 ||
        statusCode == 422 ||
        (statusCode != null && statusCode >= 500);

    if (!shouldLogStatus) {
      return;
    }

    debugPrint(
      'HTTP map status=${statusCode ?? 'NO_STATUS'} message="$mappedMessage" method=${error.requestOptions.method} path=${error.requestOptions.path}',
    );
  }

  String _bodySnippet(dynamic body) {
    final serializedBody = _serializeBody(
      body,
    ).replaceAll(RegExp(r'\s+'), ' ').trim();
    if (serializedBody.isEmpty) {
      return '<empty>';
    }

    if (serializedBody.length <= AppConfig.networkDebugBodySnippetLimit) {
      return serializedBody;
    }

    return '${serializedBody.substring(0, AppConfig.networkDebugBodySnippetLimit)}...';
  }

  String _serializeBody(dynamic body) {
    if (body == null) {
      return '';
    }

    if (body is String) {
      return body;
    }

    try {
      return jsonEncode(body);
    } catch (_) {
      return body.toString();
    }
  }

  String? _extractErrorMeta(Object error, String key) {
    if (error is! DioException) {
      return null;
    }

    final data = asMap(error.response?.data);
    if (data == null) {
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
