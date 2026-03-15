import 'package:flutter/material.dart';

import 'app_palette.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'inquiry_model.dart';
import 'inquiry_service.dart';
import 'login_page.dart' show LoginPage;

class PurchaseHistoryPage extends StatefulWidget {
  const PurchaseHistoryPage({super.key});

  @override
  State<PurchaseHistoryPage> createState() => _PurchaseHistoryPageState();
}

class _PurchaseHistoryPageState extends State<PurchaseHistoryPage> {
  final ApiClient _apiClient = ApiClient();

  List<Inquiry> _successfulTransactions = <Inquiry>[];
  bool _isLoading = true;
  bool _isRedirectingToLogin = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadSuccessfulTransactions();
  }

  Future<void> _loadSuccessfulTransactions() async {
    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      final sentInquiries = await InquiryService().fetchSentInquiries();
      final successfulInquiries =
          sentInquiries
              .where(
                (inquiry) =>
                    inquiry.status == InquiryStatus.accepted ||
                    inquiry.status == InquiryStatus.completed,
              )
              .toList()
            ..sort((a, b) {
              final aDate = a.completedAt ?? a.decidedAt ?? a.createdAt;
              final bDate = b.completedAt ?? b.decidedAt ?? b.createdAt;
              return bDate.compareTo(aDate);
            });

      if (!mounted) {
        return;
      }

      setState(() {
        _successfulTransactions = successfulInquiries;
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
      (Route<dynamic> route) => route.isFirst,
    );
    _isRedirectingToLogin = false;
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        backgroundColor: kPageBackground,
        appBar: AppBar(
          backgroundColor: kNavy,
          centerTitle: true,
          title: const Text('Purchase History'),
        ),
        body: const Center(child: CircularProgressIndicator(color: kNavy)),
      );
    }

    return Scaffold(
      backgroundColor: kPageBackground,
      appBar: AppBar(
        backgroundColor: kNavy,
        centerTitle: true,
        title: const Text('Purchase History'),
      ),
      body: RefreshIndicator(
        color: kNavy,
        onRefresh: _loadSuccessfulTransactions,
        child: _buildBody(),
      ),
    );
  }

  Widget _buildBody() {
    if (_errorMessage != null) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: <Widget>[
          const SizedBox(height: 80),
          const Icon(Icons.cloud_off_rounded, size: 62, color: kNavy),
          const SizedBox(height: 14),
          const Text(
            'Unable to load purchase history.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: kNavy,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            _errorMessage!,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.grey[700],
              fontSize: 13,
              height: 1.5,
            ),
          ),
          const SizedBox(height: 18),
          Center(
            child: ElevatedButton(
              onPressed: _loadSuccessfulTransactions,
              style: ElevatedButton.styleFrom(
                backgroundColor: kNavy,
                foregroundColor: kWhite,
              ),
              child: const Text('Retry'),
            ),
          ),
        ],
      );
    }

    if (_successfulTransactions.isEmpty) {
      return ListView(
        padding: const EdgeInsets.all(24),
        children: const <Widget>[
          SizedBox(height: 90),
          Icon(Icons.receipt_long_outlined, size: 68, color: kNavy),
          SizedBox(height: 16),
          Text(
            'No successful transactions yet.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: kNavy,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
      itemCount: _successfulTransactions.length,
      itemBuilder: (BuildContext context, int index) {
        final inquiry = _successfulTransactions[index];
        final transactionDate =
            inquiry.completedAt ?? inquiry.decidedAt ?? inquiry.createdAt;
        final sellerName = inquiry.counterpartyName(isReceived: false);
        final dateLabel = inquiry.completedAt != null
            ? 'Completed'
            : (inquiry.decidedAt != null ? 'Accepted' : 'Created');
        final statusLabel = inquiry.status == InquiryStatus.completed
            ? 'Completed'
            : 'Accepted / Meetup Pending';

        return Container(
          margin: const EdgeInsets.only(bottom: 14),
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: kWhite,
            borderRadius: BorderRadius.circular(22),
            boxShadow: <BoxShadow>[
              BoxShadow(
                color: Colors.black.withValues(alpha: 0.05),
                blurRadius: 14,
                offset: const Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Expanded(
                    child: Text(
                      inquiry.listingTitle,
                      style: const TextStyle(
                        color: kNavy,
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Text(
                    inquiry.listingMetaLabel,
                    style: const TextStyle(
                      color: kNavy,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Row(
                children: <Widget>[
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: kNavy.withValues(alpha: 0.08),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: Text(
                        sellerName.isNotEmpty
                            ? sellerName[0].toUpperCase()
                            : '?',
                        style: const TextStyle(
                          color: kNavy,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          'Seller',
                          style: TextStyle(
                            color: Colors.grey[500],
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          sellerName,
                          style: const TextStyle(
                            color: kNavy,
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: inquiry.status == InquiryStatus.completed
                      ? const Color(0xFFE3F2FD)
                      : const Color(0xFFE8F5E9),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  statusLabel,
                  style: TextStyle(
                    color: inquiry.status == InquiryStatus.completed
                        ? const Color(0xFF1565C0)
                        : const Color(0xFF2E7D32),
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: <Widget>[
                  Icon(
                    Icons.event_available_rounded,
                    size: 14,
                    color: Colors.grey[500],
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      '$dateLabel ${_formatDateTime(transactionDate)}',
                      style: TextStyle(
                        color: Colors.grey[700],
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
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
    const List<String> labels = <String>[
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

    return labels[month - 1];
  }
}
