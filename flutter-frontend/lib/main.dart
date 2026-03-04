import 'package:flutter/material.dart';
import 'auth_service.dart';
import 'backend_status_page.dart';
import 'home_page.dart';
import 'login_page.dart';
import 'profile_page.dart';
import 'register_page.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AuthService().init();
  runApp(const MyApp());
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'LNU Marketplace',
      debugShowCheckedModeBanner: false,
      home: const HomePage(),
      routes: {
        '/login': (context) => const LoginPage(),
        '/register': (context) => const RegisterPage(),
        '/profile': (context) => const ProfilePage(),
        '/backend-status': (context) => const BackendStatusPage(),
      },
    );
  }
}
