import 'package:flutter/material.dart';

import 'app_palette.dart';

enum PolicySection { privacy, terms }

class PrivacyPolicyPage extends StatelessWidget {
  const PrivacyPolicyPage({super.key});

  @override
  Widget build(BuildContext context) {
    return const PolicyDetailsPage(initialSection: PolicySection.privacy);
  }
}

class PolicyDetailsPage extends StatefulWidget {
  final PolicySection initialSection;

  const PolicyDetailsPage({
    super.key,
    this.initialSection = PolicySection.privacy,
  });

  @override
  State<PolicyDetailsPage> createState() => _PolicyDetailsPageState();
}

class _PolicyDetailsPageState extends State<PolicyDetailsPage> {
  final GlobalKey _privacySectionKey = GlobalKey();
  final GlobalKey _termsSectionKey = GlobalKey();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || widget.initialSection == PolicySection.privacy) {
        return;
      }

      final BuildContext? targetContext = _termsSectionKey.currentContext;
      if (targetContext != null) {
        Scrollable.ensureVisible(
          targetContext,
          duration: const Duration(milliseconds: 350),
          curve: Curves.easeOutCubic,
          alignment: 0.08,
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: kPageBackground,
      appBar: AppBar(
        backgroundColor: kNavy,
        centerTitle: true,
        title: const Text('Privacy Policy & Terms'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: <Widget>[
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(22),
              decoration: BoxDecoration(
                color: kWhite,
                borderRadius: BorderRadius.circular(24),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.06),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  const Text(
                    'Please read these policies before using LNU Student Square.',
                    style: TextStyle(
                      color: kNavy,
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                      height: 1.5,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: <Widget>[
                      _PolicyChip(
                        label: 'Privacy Policy',
                        onTap: () => _scrollTo(_privacySectionKey),
                      ),
                      _PolicyChip(
                        label: 'Terms of Service',
                        onTap: () => _scrollTo(_termsSectionKey),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _PolicySectionCard(
              key: _privacySectionKey,
              icon: Icons.privacy_tip_outlined,
              title: 'Privacy Policy',
              children: <Widget>[
                _PolicyParagraph(
                  text:
                      'LNU Student Square collects Student IDs, LNU email '
                      'addresses, and profile details only to verify campus '
                      'membership, support marketplace moderation, and help '
                      'keep transactions safer for students.',
                ),
                _PolicyParagraph(
                  text:
                      'Any profile information you provide, including your '
                      'program, year level, organization, section, contact '
                      'number, and bio, is handled strictly for account '
                      'management and moderation workflows.',
                ),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: kGold.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(18),
                  ),
                  child: const Text(
                    'This policy is intended to align with campus moderation '
                    'needs and the Philippine Data Privacy Act.',
                    style: TextStyle(
                      color: kNavy,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      height: 1.6,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _PolicySectionCard(
              key: _termsSectionKey,
              icon: Icons.description_outlined,
              title: 'Terms of Service',
              children: const <Widget>[
                _PolicyRule(
                  title: 'Restricted Items',
                  body:
                      'Users must not list prohibited items, including general '
                      'retail goods and food, when those products fall outside '
                      'the allowed student marketplace scope.',
                ),
                SizedBox(height: 14),
                _PolicyRule(
                  title: 'Meetup Commitments',
                  body:
                      'Buyers and sellers are expected to honor agreed meetup '
                      'arrangements and communicate responsibly when plans '
                      'change.',
                ),
                SizedBox(height: 14),
                _PolicyRule(
                  title: 'Admin Enforcement',
                  body:
                      'Admins reserve the right to suspend users who violate '
                      'marketplace rules or repeatedly create unsafe '
                      'experiences for the LNU community.',
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  void _scrollTo(GlobalKey sectionKey) {
    final BuildContext? targetContext = sectionKey.currentContext;
    if (targetContext == null) {
      return;
    }

    Scrollable.ensureVisible(
      targetContext,
      duration: const Duration(milliseconds: 350),
      curve: Curves.easeOutCubic,
      alignment: 0.08,
    );
  }
}

class _PolicyChip extends StatelessWidget {
  final String label;
  final VoidCallback onTap;

  const _PolicyChip({required this.label, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: kGold.withValues(alpha: 0.16),
      borderRadius: BorderRadius.circular(999),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Text(
            label,
            style: const TextStyle(
              color: kNavy,
              fontSize: 13,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }
}

class _PolicySectionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final List<Widget> children;

  const _PolicySectionCard({
    super.key,
    required this.icon,
    required this.title,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(24),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.06),
            blurRadius: 14,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: kGold.withValues(alpha: 0.16),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: kNavy),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: kNavy,
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          ...children,
        ],
      ),
    );
  }
}

class _PolicyParagraph extends StatelessWidget {
  final String text;

  const _PolicyParagraph({required this.text});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 16),
      child: Text(
        text,
        style: TextStyle(color: Colors.grey[800], fontSize: 14, height: 1.7),
      ),
    );
  }
}

class _PolicyRule extends StatelessWidget {
  final String title;
  final String body;

  const _PolicyRule({required this.title, required this.body});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: const Color(0xFFF7F8FD),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: const TextStyle(
              color: kNavy,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            body,
            style: TextStyle(
              color: Colors.grey[800],
              fontSize: 14,
              height: 1.65,
            ),
          ),
        ],
      ),
    );
  }
}
