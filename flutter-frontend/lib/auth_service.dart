// Simple in-memory auth service — replace with your real backend later

class AuthService {
  static final AuthService _instance = AuthService._internal();
  factory AuthService() => _instance;
  AuthService._internal();

  Map<String, dynamic>? _currentUser;

  bool get isLoggedIn => _currentUser != null;
  Map<String, dynamic>? get currentUser => _currentUser;

  // Simulated register — replace with real API call
  Future<String?> register({
    required String name,
    required String email,
    required String username,
    required String password,
    required String studentId,
  }) async {
    await Future.delayed(const Duration(seconds: 1)); // simulate network

    // Basic validation
    if (name.trim().isEmpty) return 'Name is required';
    if (!email.contains('@')) return 'Enter Institutional Email';
    if (username.trim().isEmpty) return 'Username is required'; 
    if (password.length < 6) return 'Password must be at least 6 characters';
    if (studentId.trim().isEmpty) return 'Student ID is required';

    _currentUser = {
      'name': name,
      'email': email,
      'studentId': studentId,
      'avatar': name[0].toUpperCase(),
    };
    return null; // null means success
  }

  // Simulated login — replace with real API call
  Future<String?> login({
    required String studentId,
    required String password,
  }) async {
    await Future.delayed(const Duration(seconds: 1)); // simulate network

    if (studentId.trim().isEmpty) return 'Student ID is required';
    if (password.length < 6) return 'Password must be at least 6 characters';

    // Simulate successful login
    _currentUser = {
      'name': studentId,
      'email': '',
      'studentId': studentId,
      'avatar': studentId[0].toUpperCase(),
    };
    return null; // null means success
  }

  void logout() {
    _currentUser = null;
  }
}
