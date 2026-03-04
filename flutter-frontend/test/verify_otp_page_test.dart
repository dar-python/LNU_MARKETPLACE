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

  testWidgets(
    'VerifyOtpPage shows cooldown message when resend is tapped too early',
    (WidgetTester tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: VerifyOtpPage(identifier: '2203838@lnu.edu.ph'),
        ),
      );

      await tester.tap(find.text('Resend OTP in 30s'));
      await tester.pump();

      expect(find.textContaining('You can resend OTP in'), findsOneWidget);
    },
  );

  testWidgets('VerifyOtpPage strips non-digit OTP input', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: VerifyOtpPage(identifier: '2203838@lnu.edu.ph')),
    );

    await tester.enterText(find.byType(TextField), '12 34a6');

    expect(find.text('12346'), findsOneWidget);
  });

  testWidgets('VerifyOtpPage validates OTP length before request', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(home: VerifyOtpPage(identifier: '2203838@lnu.edu.ph')),
    );

    await tester.enterText(find.byType(TextField), '12345');
    await tester.tap(find.widgetWithText(ElevatedButton, 'Verify OTP'));
    await tester.pump();

    expect(find.text('OTP must be exactly 6 digits.'), findsOneWidget);
  });
}
