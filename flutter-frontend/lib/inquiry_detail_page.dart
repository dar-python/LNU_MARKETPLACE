import 'package:flutter/material.dart';

import 'inquiry_model.dart';
import 'inquiry_service.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'login_page.dart';

const kInquiryNavy = Color(0xFF0D1B6E);
const kInquiryDarkNavy = Color(0xFF080F45);
const kInquiryGold = Color(0xFFF5C518);
const kInquiryWhite = Color(0xFFFFFFFF);

class InquiryDetailPage extends StatefulWidget {
  const InquiryDetailPage({
    super.key,
    required this.inquiry,
    required this.isReceived,
  });

  final Inquiry inquiry;
  final bool isReceived;

  @override
  State<InquiryDetailPage> createState() => _InquiryDetailPageState();
}

class _InquiryDetailPageState extends State<InquiryDetailPage> {
  final ApiClient _apiClient = ApiClient();

  late Inquiry _inquiry;
  bool _isLoading = true;
  bool _isRedirectingToLogin = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _inquiry = widget.inquiry;
    _loadInquiry();
  }

  Future<void> _loadInquiry() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final inquiry = await InquiryService().fetchInquiryDetail(
        widget.inquiry.id,
        fallback: widget.inquiry,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _inquiry = inquiry;
        _isLoading = false;
      });
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );
      if (!mounted) {
        return;
      }

      if (sessionExpired) {
        await _redirectToLogin();
        return;
      }

      setState(() {
        _isLoading = false;
        _errorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
    }
  }

  Future<void> _redirectToLogin() async {
    if (_isRedirectingToLogin || !mounted) {
      return;
    }

    _isRedirectingToLogin = true;
    await Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
      (_) => false,
    );
    _isRedirectingToLogin = false;
  }

  @override
  Widget build(BuildContext context) {
    final inquiry = _inquiry;
    final counterpartName = inquiry.counterpartyName(
      isReceived: widget.isReceived,
    );
    final counterpartAvatar = inquiry.counterpartyAvatar(
      isReceived: widget.isReceived,
    );

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: <Color>[kInquiryDarkNavy, kInquiryNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
              child: Row(
                children: <Widget>[
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: kInquiryWhite.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.arrow_back,
                        color: kInquiryWhite,
                        size: 20,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Inquiry Detail',
                          style: TextStyle(
                            color: kInquiryWhite,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'View the latest inquiry status',
                          style: TextStyle(color: kInquiryGold, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  _StatusBadge(status: inquiry.status),
                ],
              ),
            ),
            Expanded(
              child: RefreshIndicator(
                color: kInquiryNavy,
                onRefresh: _loadInquiry,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.all(20),
                  children: <Widget>[
                    if (_isLoading) ...<Widget>[
                      const LinearProgressIndicator(
                        color: kInquiryGold,
                        backgroundColor: Color(0xFFDCE4FF),
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (_errorMessage != null) ...<Widget>[
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: kInquiryGold),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Text(
                              'Showing the latest available inquiry summary while the full detail request is unavailable.',
                              style: TextStyle(
                                color: kInquiryNavy,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _errorMessage!,
                              style: TextStyle(
                                color: Colors.grey[700],
                                fontSize: 11,
                              ),
                            ),
                            const SizedBox(height: 10),
                            OutlinedButton(
                              onPressed: _loadInquiry,
                              style: OutlinedButton.styleFrom(
                                foregroundColor: kInquiryNavy,
                              ),
                              child: const Text('Retry'),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],
                    _InfoCard(
                      title: inquiry.listingTitle,
                      trailing: inquiry.listingMetaLabel,
                      children: <Widget>[
                        _InfoRow(
                          label: 'Listing Status',
                          value: _titleCase(
                            inquiry.listingStatus.replaceAll('_', ' '),
                          ),
                        ),
                        _InfoRow(
                          label: widget.isReceived ? 'Buyer' : 'Seller',
                          value: counterpartName,
                        ),
                        _InfoRow(
                          label: 'Preferred Contact',
                          value: inquiry.preferredContactLabel,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: kInquiryWhite,
                        borderRadius: BorderRadius.circular(16),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: Row(
                        children: <Widget>[
                          CircleAvatar(
                            radius: 24,
                            backgroundColor: kInquiryGold,
                            child: Text(
                              counterpartAvatar,
                              style: const TextStyle(
                                color: kInquiryNavy,
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: <Widget>[
                                Text(
                                  counterpartName,
                                  style: const TextStyle(
                                    color: kInquiryNavy,
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  widget.isReceived
                                      ? 'Interested buyer'
                                      : 'Listing seller',
                                  style: TextStyle(
                                    color: Colors.grey[500],
                                    fontSize: 11,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _InfoCard(
                      title: 'Message',
                      children: <Widget>[
                        Text(
                          inquiry.message.isNotEmpty
                              ? inquiry.message
                              : 'No message provided.',
                          style: TextStyle(
                            color: Colors.grey[700],
                            fontSize: 13,
                            height: 1.6,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _InfoCard(
                      title: 'Timeline',
                      children: <Widget>[
                        _InfoRow(
                          label: 'Sent',
                          value: _formatDateTime(inquiry.createdAt),
                        ),
                        if (inquiry.decidedAt != null)
                          _InfoRow(
                            label: 'Decided',
                            value: _formatDateTime(inquiry.decidedAt!),
                          ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  String _formatDateTime(DateTime value) {
    final month = _monthLabel(value.month);
    final hour = value.hour == 0
        ? 12
        : (value.hour > 12 ? value.hour - 12 : value.hour);
    final minute = value.minute.toString().padLeft(2, '0');
    final period = value.hour >= 12 ? 'PM' : 'AM';
    return '$month ${value.day}, ${value.year} $hour:$minute $period';
  }

  String _monthLabel(int month) {
    const labels = <String>[
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'May',
      'Jun',
      'Jul',
      'Aug',
      'Sep',
      'Oct',
      'Nov',
      'Dec',
    ];

    if (month < 1 || month > labels.length) {
      return 'Date';
    }

    return labels[month - 1];
  }

  String _titleCase(String value) {
    final normalizedValue = value.trim();
    if (normalizedValue.isEmpty) {
      return 'Unknown';
    }

    return normalizedValue
        .split(' ')
        .map(
          (part) => part.isEmpty
              ? part
              : part[0].toUpperCase() + part.substring(1).toLowerCase(),
        )
        .join(' ');
  }
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, this.trailing, required this.children});

  final String title;
  final String? trailing;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kInquiryWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: kInquiryNavy,
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (trailing != null)
                Text(
                  trailing!,
                  style: const TextStyle(
                    color: kInquiryNavy,
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
            ],
          ),
          const SizedBox(height: 12),
          ...children,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: TextStyle(color: Colors.grey[500], fontSize: 12),
            ),
          ),
          Expanded(
            child: Text(
              value.isNotEmpty ? value : 'Unavailable',
              style: const TextStyle(
                color: kInquiryNavy,
                fontSize: 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.status});

  final InquiryStatus status;

  @override
  Widget build(BuildContext context) {
    final backgroundColor = switch (status) {
      InquiryStatus.pending => const Color(0xFFFFF8E1),
      InquiryStatus.accepted => const Color(0xFFE8F5E9),
      InquiryStatus.declined => const Color(0xFFFFEBEE),
    };
    final textColor = switch (status) {
      InquiryStatus.pending => const Color(0xFFE65100),
      InquiryStatus.accepted => const Color(0xFF2E7D32),
      InquiryStatus.declined => const Color(0xFFC62828),
    };
    final label = switch (status) {
      InquiryStatus.pending => 'Pending',
      InquiryStatus.accepted => 'Accepted',
      InquiryStatus.declined => 'Declined',
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: textColor,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}
