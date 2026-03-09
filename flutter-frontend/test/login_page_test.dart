import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_lnu_marketplace/login_page.dart';
import 'package:flutter_lnu_marketplace/verify_otp_page.dart';

void main() {
  testWidgets('Login form validation renders error message', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: LoginPage(
          loginHandler:
              ({required String identifier, required String password}) async {
                if (identifier.trim().isEmpty) {
                  return 'Email is required.';
                }

                if (password.trim().isEmpty) {
                  return 'Password is required.';
                }

                return null;
              },
        ),
      ),
    );

    await tester.tap(find.text('Sign In'));
    await tester.pumpAndSettle();

    expect(find.text('Email is required.'), findsOneWidget);
  });

  testWidgets('Unverified login response navigates to verify OTP screen', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: LoginPage(
          loginHandler:
              ({required String identifier, required String password}) async =>
                  'Email not verified yet.',
          authErrorCodeResolver: () => 'EMAIL_NOT_VERIFIED',
          authErrorIdentifierResolver: () => '2308888@lnu.edu.ph',
        ),
      ),
    );

    await tester.enterText(find.byType(TextField).at(0), '2308888');
    await tester.enterText(find.byType(TextField).at(1), 'Safe!Pass123');

    await tester.tap(find.text('Sign In'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));

    expect(find.byType(VerifyOtpPage), findsOneWidget);
    expect(find.text('Email Verification'), findsOneWidget);
    expect(find.text('2308888@lnu.edu.ph'), findsOneWidget);
  });
}
