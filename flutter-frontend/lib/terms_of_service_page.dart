import 'package:flutter/material.dart';

import 'privacy_policy_page.dart';

class TermsOfServicePage extends StatelessWidget {
  const TermsOfServicePage({super.key});

  @override
  Widget build(BuildContext context) {
    return const PolicyDetailsPage(initialSection: PolicySection.terms);
  }
}
