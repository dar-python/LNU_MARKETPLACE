import 'package:flutter/foundation.dart';

class AppConfig {
  static const String _baseUrlFromEnv = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: String.fromEnvironment(
      'BASE_URL',
      defaultValue: 'http://localhost:8080',
    ),
  );

  static String get baseUrl {
    final trimmed = _baseUrlFromEnv.trim();
    if (trimmed.endsWith('/')) {
      return trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed;
  }

  static const bool _enableNetworkDebugLogsFromEnv = bool.fromEnvironment(
    'ENABLE_NETWORK_DEBUG_LOGS',
    defaultValue: false,
  );

  static bool get enableNetworkDebugLogs {
    return kDebugMode && _enableNetworkDebugLogsFromEnv;
  }

  static const int networkDebugBodySnippetLimit = 400;
}
