class AppConfig {
  static const String _baseUrlFromEnv = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: String.fromEnvironment(
      'BASE_URL',
      defaultValue: 'http://127.0.0.1:8080',
    ),
  );

  static String get baseUrl {
    final trimmed = _baseUrlFromEnv.trim();
    if (trimmed.endsWith('/')) {
      return trimmed.substring(0, trimmed.length - 1);
    }
    return trimmed;
  }
}
