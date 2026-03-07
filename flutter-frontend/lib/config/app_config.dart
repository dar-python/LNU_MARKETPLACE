import 'dart:io';

import 'package:flutter/foundation.dart';

class AppConfig {
  static const String _baseUrlFromEnv = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: String.fromEnvironment('BASE_URL', defaultValue: ''),
  );

  static String get baseUrl {
    final configuredBaseUrl = _baseUrlFromEnv.trim();
    final resolvedBaseUrl = configuredBaseUrl.isNotEmpty
        ? configuredBaseUrl
        : _defaultBaseUrl;

    if (resolvedBaseUrl.endsWith('/')) {
      return resolvedBaseUrl.substring(0, resolvedBaseUrl.length - 1);
    }
    return resolvedBaseUrl;
  }

  static String get _defaultBaseUrl {
    // Match the local Docker mapping without requiring a dart-define.
    if (Platform.isAndroid) {
      return 'http://10.0.2.2:8082';
    }

    return 'http://127.0.0.1:8082';
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
