import 'package:flutter/material.dart';

import 'inquiry_model.dart';
import 'inquiry_service.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'inquiry_detail_page.dart';
import 'login_page.dart';

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
  bool _isLoadingReceived = true;
  bool _isLoadingSent = true;
  bool _isRedirectingToLogin = false;
  String? _receivedErrorMessage;
  String? _sentErrorMessage;

  int get _pendingCount => _receivedInquiries
      .where((inquiry) => inquiry.status == InquiryStatus.pending)
      .length;

  bool get _isLoadingInitialState =>
      _isLoadingReceived &&
      _isLoadingSent &&
      _receivedInquiries.isEmpty &&
      _sentInquiries.isEmpty;

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
    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    final loadedReceived = await _loadReceivedInquiries(
      showLoading: showLoading,
    );
    if (!loadedReceived || !mounted) {
      return;
    }

    await _loadSentInquiries(showLoading: showLoading);
  }

  Future<bool> _loadReceivedInquiries({bool showLoading = true}) async {
    if (showLoading) {
      setState(() {
        _isLoadingReceived = true;
        _receivedErrorMessage = null;
      });
    } else {
      setState(() {
        _receivedErrorMessage = null;
      });
    }

    try {
      final inquiries = await InquiryService().fetchReceivedInquiries();

      if (!mounted) {
        return false;
      }

      setState(() {
        _receivedInquiries = inquiries;
        _isLoadingReceived = false;
      });
      return true;
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );
      if (!mounted) {
        return false;
      }

      if (sessionExpired) {
        await _redirectToLogin();
        return false;
      }

      setState(() {
        _isLoadingReceived = false;
        _receivedErrorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
      return true;
    }
  }

  Future<bool> _loadSentInquiries({bool showLoading = true}) async {
    if (showLoading) {
      setState(() {
        _isLoadingSent = true;
        _sentErrorMessage = null;
      });
    } else {
      setState(() {
        _sentErrorMessage = null;
      });
    }

    try {
      final inquiries = await InquiryService().fetchSentInquiries();

      if (!mounted) {
        return false;
      }

      setState(() {
        _sentInquiries = inquiries;
        _isLoadingSent = false;
      });
      return true;
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );
      if (!mounted) {
        return false;
      }

      if (sessionExpired) {
        await _redirectToLogin();
        return false;
      }

      setState(() {
        _isLoadingSent = false;
        _sentErrorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
      return true;
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
      final updatedInquiry = await InquiryService().decideInquiry(
        inquiryId: inquiry.id,
        decision: decision,
        fallback: inquiry,
      );

      if (!mounted) {
        return;
      }

      _replaceInquiryInLocalState(updatedInquiry);

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

      await _loadReceivedInquiries(showLoading: false);
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

  void _replaceInquiryInLocalState(Inquiry updatedInquiry) {
    setState(() {
      _receivedInquiries = _receivedInquiries
          .map(
            (existingInquiry) => existingInquiry.id == updatedInquiry.id
                ? updatedInquiry
                : existingInquiry,
          )
          .toList();
      _sentInquiries = _sentInquiries
          .map(
            (existingInquiry) => existingInquiry.id == updatedInquiry.id
                ? updatedInquiry
                : existingInquiry,
          )
          .toList();
    });
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
              child: _isLoadingInitialState
                  ? const Center(
                      child: CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                      ),
                    )
                  : TabBarView(
                      controller: _tabController,
                      children: <Widget>[
                        _InquiryList(
                          inquiries: _receivedInquiries,
                          isReceived: true,
                          isLoading: _isLoadingReceived,
                          errorMessage: _receivedErrorMessage,
                          busyInquiryIds: _busyInquiryIds,
                          onRefresh: () =>
                              _loadReceivedInquiries(showLoading: false),
                          onRetry: _loadReceivedInquiries,
                          onTap: (inquiry) {
                            _openInquiryDetail(inquiry, true);
                          },
                          onDecision: _handleDecision,
                        ),
                        _InquiryList(
                          inquiries: _sentInquiries,
                          isReceived: false,
                          isLoading: _isLoadingSent,
                          errorMessage: _sentErrorMessage,
                          busyInquiryIds: _busyInquiryIds,
                          onRefresh: () =>
                              _loadSentInquiries(showLoading: false),
                          onRetry: _loadSentInquiries,
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
  final Future<bool> Function({bool showLoading}) onRetry;

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
    required this.isLoading,
    required this.errorMessage,
    required this.busyInquiryIds,
    required this.onRefresh,
    required this.onRetry,
    required this.onTap,
    required this.onDecision,
  });

  final List<Inquiry> inquiries;
  final bool isReceived;
  final bool isLoading;
  final String? errorMessage;
  final Set<int> busyInquiryIds;
  final Future<bool> Function() onRefresh;
  final Future<bool> Function({bool showLoading}) onRetry;
  final ValueChanged<Inquiry> onTap;
  final Future<void> Function(Inquiry inquiry, InquiryStatus decision)
  onDecision;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      color: kNavy,
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        physics: const AlwaysScrollableScrollPhysics(),
        children: <Widget>[
          if (isLoading && inquiries.isEmpty)
            const Padding(
              padding: EdgeInsets.only(top: 120),
              child: Center(
                child: CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                ),
              ),
            )
          else if (errorMessage != null && inquiries.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 80),
              child: _InquiryLoadError(
                message: errorMessage!,
                onRetry: onRetry,
              ),
            )
          else if (inquiries.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 120),
              child: Center(
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
                      isReceived
                          ? 'No inquiries received'
                          : 'No inquiries sent',
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
              ),
            )
          else ...<Widget>[
            if (errorMessage != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: _InlineLoadWarning(
                  message: errorMessage!,
                  onRetry: onRetry,
                ),
              ),
            ...inquiries.map((inquiry) {
              return _InquiryCard(
                inquiry: inquiry,
                isReceived: isReceived,
                isBusy: busyInquiryIds.contains(inquiry.id),
                onTap: () => onTap(inquiry),
                onDecision: onDecision,
              );
            }),
          ],
        ],
      ),
    );
  }
}

class _InlineLoadWarning extends StatelessWidget {
  const _InlineLoadWarning({required this.message, required this.onRetry});

  final String message;
  final Future<bool> Function({bool showLoading}) onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF8E1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFF5C518)),
      ),
      child: Row(
        children: <Widget>[
          const Icon(Icons.info_outline, color: kNavy, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: kNavy,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
          TextButton(
            onPressed: () => onRetry(showLoading: false),
            child: const Text('Retry'),
          ),
        ],
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
