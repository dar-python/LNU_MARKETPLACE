// Simple in-memory auth service — replace with your real backend later

class AuthService {
  static final AuthService _instance = AuthService._internal();
  factory AuthService() => _instance;
  AuthService._internal();

  Map<String, dynamic>? _currentUser;

  // Stores all registered users: key = username (lowercase)
  final Map<String, Map<String, dynamic>> _registeredUsers = {};

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
    if (email.isNotEmpty && !email.contains('@')) return 'Enter a valid Institutional Email';
    if (username.trim().isEmpty) return 'Username is required';
    if (password.length < 6) return 'Password must be at least 6 characters';
    if (studentId.trim().isEmpty) return 'Student ID is required';

    // Check if username already taken
    if (_registeredUsers.containsKey(username.toLowerCase())) {
      return 'Username already taken. Please choose another.';
    }

    // Save user to in-memory store
    _registeredUsers[username.toLowerCase()] = {
      'name': name,
      'email': email,
      'username': username,
      'password': password,
      'studentId': studentId,
      'avatar': name[0].toUpperCase(),
    };

    // Auto login after register
    _currentUser = {
      'name': name,
      'email': email,
      'username': username,
      'studentId': studentId,
      'avatar': name[0].toUpperCase(),
    };

    return null; // null means success
  }

  // Login using username + password only
  Future<String?> login({
    required String username,
    required String password,
  }) async {
    await Future.delayed(const Duration(seconds: 1)); // simulate network

    if (username.trim().isEmpty) return 'Username is required';
    if (password.length < 6) return 'Password must be at least 6 characters';

    // Check if username exists
    final user = _registeredUsers[username.toLowerCase()];
    if (user == null) return 'Username not found. Please register first.';

    // Check if password matches
    if (user['password'] != password) return 'Incorrect password.';

    // Successful login
    _currentUser = {
      'name': user['name'],
      'email': user['email'],
      'username': user['username'],
      'studentId': user['studentId'],
      'avatar': user['avatar'],
    };

    return null; // null means success
  }

  void logout() {
    _currentUser = null;
  }
}