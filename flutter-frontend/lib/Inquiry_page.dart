import 'package:flutter/material.dart';

import 'Inquiry_model.dart';
import 'Inquiry_service.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'inquiry_detail_page.dart';

const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

class InquiryPage extends StatefulWidget {
  const InquiryPage({super.key});

  @override
  State<InquiryPage> createState() => _InquiryPageState();
}

class _InquiryPageState extends State<InquiryPage>
    with SingleTickerProviderStateMixin {
  final ApiClient _apiClient = ApiClient();

  late TabController _tabController;
  List<Inquiry> _receivedInquiries = <Inquiry>[];
  List<Inquiry> _sentInquiries = <Inquiry>[];
  Set<int> _busyInquiryIds = <int>{};
  bool _isLoading = true;
  String? _errorMessage;

  int get _pendingCount => _receivedInquiries
      .where((inquiry) => inquiry.status == InquiryStatus.pending)
      .length;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadInquiries();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadInquiries({bool showLoading = true}) async {
    if (!AuthService().isLoggedIn) {
      setState(() {
        _isLoading = false;
        _receivedInquiries = <Inquiry>[];
        _sentInquiries = <Inquiry>[];
        _errorMessage = 'Please log in to view your inquiries.';
      });
      return;
    }

    if (showLoading) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    } else {
      setState(() {
        _errorMessage = null;
      });
    }

    try {
      final results = await Future.wait<List<Inquiry>>(<Future<List<Inquiry>>>[
        InquiryService().fetchReceivedInquiries(),
        InquiryService().fetchSentInquiries(),
      ]);

      if (!mounted) {
        return;
      }

      setState(() {
        _receivedInquiries = results[0];
        _sentInquiries = results[1];
        _isLoading = false;
      });
    } catch (error) {
      if (!mounted) {
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

  Future<void> _handleDecision(Inquiry inquiry, InquiryStatus decision) async {
    if (_busyInquiryIds.contains(inquiry.id)) {
      return;
    }

    setState(() {
      _busyInquiryIds = <int>{..._busyInquiryIds, inquiry.id};
    });

    try {
      await InquiryService().decideInquiry(
        inquiryId: inquiry.id,
        decision: decision,
        fallback: inquiry,
      );

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            decision == InquiryStatus.accepted
                ? 'Inquiry accepted.'
                : 'Inquiry declined.',
          ),
          backgroundColor: kNavy,
        ),
      );

      await _loadInquiries(showLoading: false);
    } catch (error) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            error is FormatException
                ? error.message
                : _apiClient.mapError(error),
          ),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _busyInquiryIds = <int>{
            ..._busyInquiryIds.where((id) => id != inquiry.id),
          };
        });
      }
    }
  }

  Future<void> _openInquiryDetail(Inquiry inquiry, bool isReceived) async {
    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) =>
            InquiryDetailPage(inquiry: inquiry, isReceived: isReceived),
      ),
    );

    if (!mounted) {
      return;
    }

    await _loadInquiries(showLoading: false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: <Color>[kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Row(
                    children: <Widget>[
                      const Text(
                        'Inquiries',
                        style: TextStyle(
                          color: kWhite,
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.3,
                        ),
                      ),
                      if (_pendingCount > 0) ...<Widget>[
                        const SizedBox(width: 10),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: kGold,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            '$_pendingCount pending',
                            style: const TextStyle(
                              color: kNavy,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Manage your buy and sell inquiries',
                    style: TextStyle(color: kGold, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  TabBar(
                    controller: _tabController,
                    indicatorColor: kGold,
                    indicatorWeight: 3,
                    labelColor: kGold,
                    unselectedLabelColor: kWhite.withValues(alpha: 0.5),
                    labelStyle: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                    tabs: <Tab>[
                      Tab(text: 'Received (${_receivedInquiries.length})'),
                      Tab(text: 'Sent (${_sentInquiries.length})'),
                    ],
                  ),
                ],
              ),
            ),
            Expanded(
              child: _isLoading
                  ? const Center(
                      child: CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                      ),
                    )
                  : _errorMessage != null &&
                        _receivedInquiries.isEmpty &&
                        _sentInquiries.isEmpty
                  ? _InquiryLoadError(
                      message: _errorMessage!,
                      onRetry: _loadInquiries,
                    )
                  : TabBarView(
                      controller: _tabController,
                      children: <Widget>[
                        _InquiryList(
                          inquiries: _receivedInquiries,
                          isReceived: true,
                          busyInquiryIds: _busyInquiryIds,
                          onRefresh: () => _loadInquiries(showLoading: false),
                          onTap: (inquiry) {
                            _openInquiryDetail(inquiry, true);
                          },
                          onDecision: _handleDecision,
                        ),
                        _InquiryList(
                          inquiries: _sentInquiries,
                          isReceived: false,
                          busyInquiryIds: _busyInquiryIds,
                          onRefresh: () => _loadInquiries(showLoading: false),
                          onTap: (inquiry) {
                            _openInquiryDetail(inquiry, false);
                          },
                          onDecision: _handleDecision,
                        ),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InquiryLoadError extends StatelessWidget {
  const _InquiryLoadError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function({bool showLoading}) onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            const Icon(Icons.cloud_off_rounded, size: 64, color: kNavy),
            const SizedBox(height: 12),
            const Text(
              'Unable to load inquiries',
              style: TextStyle(
                color: kNavy,
                fontSize: 15,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[500], fontSize: 12),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => onRetry(),
              style: ElevatedButton.styleFrom(
                backgroundColor: kNavy,
                foregroundColor: kWhite,
              ),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class _InquiryList extends StatelessWidget {
  const _InquiryList({
    required this.inquiries,
    required this.isReceived,
    required this.busyInquiryIds,
    required this.onRefresh,
    required this.onTap,
    required this.onDecision,
  });

  final List<Inquiry> inquiries;
  final bool isReceived;
  final Set<int> busyInquiryIds;
  final Future<void> Function() onRefresh;
  final ValueChanged<Inquiry> onTap;
  final Future<void> Function(Inquiry inquiry, InquiryStatus decision)
  onDecision;

  @override
  Widget build(BuildContext context) {
    if (inquiries.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            Icon(
              isReceived ? Icons.inbox_outlined : Icons.send_outlined,
              size: 64,
              color: Colors.grey[300],
            ),
            const SizedBox(height: 12),
            Text(
              isReceived ? 'No inquiries received' : 'No inquiries sent',
              style: TextStyle(
                color: Colors.grey[400],
                fontSize: 15,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              isReceived
                  ? 'Buyers will contact you here'
                  : 'Send an inquiry from any listing',
              style: TextStyle(color: Colors.grey[400], fontSize: 12),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      color: kNavy,
      onRefresh: onRefresh,
      child: ListView.builder(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        physics: const AlwaysScrollableScrollPhysics(),
        itemCount: inquiries.length,
        itemBuilder: (context, index) {
          final inquiry = inquiries[index];
          return _InquiryCard(
            inquiry: inquiry,
            isReceived: isReceived,
            isBusy: busyInquiryIds.contains(inquiry.id),
            onTap: () => onTap(inquiry),
            onDecision: onDecision,
          );
        },
      ),
    );
  }
}

class _InquiryCard extends StatelessWidget {
  const _InquiryCard({
    required this.inquiry,
    required this.isReceived,
    required this.isBusy,
    required this.onTap,
    required this.onDecision,
  });

  final Inquiry inquiry;
  final bool isReceived;
  final bool isBusy;
  final VoidCallback onTap;
  final Future<void> Function(Inquiry inquiry, InquiryStatus decision)
  onDecision;

  _StatusStyle _statusStyle(InquiryStatus status) {
    switch (status) {
      case InquiryStatus.pending:
        return const _StatusStyle(
          bg: Color(0xFFFFF8E1),
          text: Color(0xFFE65100),
          icon: Icons.hourglass_empty_rounded,
        );
      case InquiryStatus.accepted:
        return const _StatusStyle(
          bg: Color(0xFFE8F5E9),
          text: Color(0xFF2E7D32),
          icon: Icons.check_circle_outline_rounded,
        );
      case InquiryStatus.declined:
        return const _StatusStyle(
          bg: Color(0xFFFFEBEE),
          text: Color(0xFFC62828),
          icon: Icons.cancel_outlined,
        );
    }
  }

  String _statusLabel(InquiryStatus status) {
    switch (status) {
      case InquiryStatus.pending:
        return 'Pending';
      case InquiryStatus.accepted:
        return 'Accepted';
      case InquiryStatus.declined:
        return 'Declined';
    }
  }

  String _timeAgo(DateTime value) {
    final diff = DateTime.now().difference(value);
    if (diff.inMinutes < 60) {
      return '${diff.inMinutes}m ago';
    }
    if (diff.inHours < 24) {
      return '${diff.inHours}h ago';
    }
    return '${diff.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final style = _statusStyle(inquiry.status);
    final counterpartName = inquiry.counterpartyName(isReceived: isReceived);
    final counterpartAvatar = inquiry.counterpartyAvatar(
      isReceived: isReceived,
    );

    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  CircleAvatar(
                    radius: 20,
                    backgroundColor: kGold,
                    child: Text(
                      counterpartAvatar,
                      style: const TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          counterpartName,
                          style: const TextStyle(
                            color: kNavy,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                        Text(
                          '${isReceived ? 'Buyer' : 'Seller'} - ${inquiry.preferredContactLabel}',
                          style: TextStyle(
                            color: Colors.grey[500],
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: style.bg,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        Icon(style.icon, color: style.text, size: 12),
                        const SizedBox(width: 4),
                        Text(
                          _statusLabel(inquiry.status),
                          style: TextStyle(
                            color: style.text,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              const Divider(height: 1),
              const SizedBox(height: 10),
              Row(
                children: <Widget>[
                  const Icon(
                    Icons.inventory_2_outlined,
                    color: kNavy,
                    size: 14,
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      inquiry.listingTitle,
                      style: const TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w600,
                        fontSize: 13,
                      ),
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  Text(
                    inquiry.listingMetaLabel,
                    style: const TextStyle(
                      color: kNavy,
                      fontWeight: FontWeight.w800,
                      fontSize: 13,
                    ),
                  ),
                ],
              ),
              if (inquiry.message.isNotEmpty) ...<Widget>[
                const SizedBox(height: 6),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF4F6FF),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '"${inquiry.message}"',
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 12,
                      fontStyle: FontStyle.italic,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 10),
              Row(
                children: <Widget>[
                  Icon(
                    Icons.access_time_rounded,
                    size: 12,
                    color: Colors.grey[400],
                  ),
                  const SizedBox(width: 4),
                  Text(
                    _timeAgo(inquiry.createdAt),
                    style: TextStyle(color: Colors.grey[400], fontSize: 11),
                  ),
                  const Spacer(),
                  if (isBusy)
                    const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                      ),
                    )
                  else if (isReceived &&
                      inquiry.status == InquiryStatus.pending) ...<Widget>[
                    _ActionButton(
                      label: 'Decline',
                      color: Colors.red.shade100,
                      textColor: Colors.red.shade700,
                      onTap: () => onDecision(inquiry, InquiryStatus.declined),
                    ),
                    const SizedBox(width: 8),
                    _ActionButton(
                      label: 'Accept',
                      color: Colors.green.shade100,
                      textColor: Colors.green.shade700,
                      onTap: () => onDecision(inquiry, InquiryStatus.accepted),
                    ),
                  ] else
                    _ActionButton(
                      label: 'View',
                      color: Colors.grey.shade100,
                      textColor: Colors.grey.shade700,
                      onTap: onTap,
                    ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.label,
    required this.color,
    required this.textColor,
    required this.onTap,
  });

  final String label;
  final Color color;
  final Color textColor;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: color,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: textColor,
            fontSize: 12,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    );
  }
}

class _StatusStyle {
  const _StatusStyle({
    required this.bg,
    required this.text,
    required this.icon,
  });

  final Color bg;
  final Color text;
  final IconData icon;
}
