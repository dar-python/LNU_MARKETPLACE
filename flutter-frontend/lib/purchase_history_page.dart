import 'package:flutter/material.dart';

import 'app_palette.dart';

class PurchaseHistoryPage extends StatelessWidget {
  const PurchaseHistoryPage({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kPageBackground,
      appBar: AppBar(
        backgroundColor: kNavy,
        centerTitle: true,
        title: const Text('Purchase History'),
      ),
      body: const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: Text(
            'Purchase history screen coming soon.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: kNavy,
              fontSize: 18,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ),
    );
  }
}
