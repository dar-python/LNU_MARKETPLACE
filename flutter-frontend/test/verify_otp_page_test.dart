import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_lnu_marketplace/verify_otp_page.dart';

void main() {
  testWidgets('VerifyOtpPage shows identifier and initial cooldown', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: VerifyOtpPage(identifier: '2203838@lnu.edu.ph')),
    );

    expect(find.text('Email Verification'), findsOneWidget);
    expect(find.text('2203838@lnu.edu.ph'), findsOneWidget);
    expect(find.text('Resend OTP in 30s'), findsOneWidget);
  });

  testWidgets('VerifyOtpPage enables resend after cooldown', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: VerifyOtpPage(identifier: '2203838@lnu.edu.ph')),
    );

    await tester.pump(const Duration(seconds: 30));

    expect(find.text('Resend OTP'), findsOneWidget);
  });
}
