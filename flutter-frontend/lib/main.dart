import 'package:flutter/material.dart';
import 'home_page.dart'; 
import 'login_page.dart';
import 'profile_page.dart';
import 'register_page.dart';

void main() {
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
      }, 
    );
  }
}