import 'package:flutter/material.dart';
import 'Inquiry_model.dart';
import 'Inquiry_service.dart';

// ─── Color Palette ────────────────────────────────────────────────────────────
const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

// ─── Inquiry Page ─────────────────────────────────────────────────────────────
class InquiryPage extends StatefulWidget {
  const InquiryPage({super.key});

  @override
  State<InquiryPage> createState() => _InquiryPageState();
}

class _InquiryPageState extends State<InquiryPage>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final received = InquiryService().receivedInquiries;
    final sent = InquiryService().sentInquiries;
    final pendingCount = InquiryService().pendingCount;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ────────────────────────────────────────────────────
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Text(
                        'Inquiries',
                        style: TextStyle(
                          color: kWhite,
                          fontSize: 22,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.3,
                        ),
                      ),
                      if (pendingCount > 0) ...[
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
                            '$pendingCount pending',
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
                    'Manage your buy & sell inquiries',
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
                    tabs: [
                      Tab(text: 'Received (${received.length})'),
                      Tab(text: 'Sent (${sent.length})'),
                    ],
                  ),
                ],
              ),
            ),

            // ── Tab Views ─────────────────────────────────────────────────
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: [
                  _InquiryList(
                    inquiries: received,
                    isReceived: true,
                    onAction: () => setState(() {}),
                  ),
                  _InquiryList(
                    inquiries: sent,
                    isReceived: false,
                    onAction: () => setState(() {}),
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

// ─── Inquiry List ─────────────────────────────────────────────────────────────
class _InquiryList extends StatelessWidget {
  final List<Inquiry> inquiries;
  final bool isReceived;
  final VoidCallback onAction;

  const _InquiryList({
    required this.inquiries,
    required this.isReceived,
    required this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    if (inquiries.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isReceived
                  ? Icons.inbox_outlined
                  : Icons.send_outlined,
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

    return ListView.builder(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      physics: const BouncingScrollPhysics(),
      itemCount: inquiries.length,
      itemBuilder: (context, index) {
        return _InquiryCard(
          inquiry: inquiries[index],
          isReceived: isReceived,
          onAction: onAction,
        );
      },
    );
  }
}

// ─── Inquiry Card ─────────────────────────────────────────────────────────────
class _InquiryCard extends StatelessWidget {
  final Inquiry inquiry;
  final bool isReceived;
  final VoidCallback onAction;

  const _InquiryCard({
    required this.inquiry,
    required this.isReceived,
    required this.onAction,
  });

  // Status styling
  _StatusStyle _getStatusStyle(InquiryStatus status) {
    Color bg, text;
    IconData icon;
    switch (status) {
      case InquiryStatus.pending:
        bg = const Color(0xFFFFF8E1);
        text = const Color(0xFFE65100);
        icon = Icons.hourglass_empty_rounded;
        break;
      case InquiryStatus.accepted:
        bg = const Color(0xFFE8F5E9);
        text = const Color(0xFF2E7D32);
        icon = Icons.check_circle_outline_rounded;
        break;
      case InquiryStatus.declined:
        bg = const Color(0xFFFFEBEE);
        text = const Color(0xFFC62828);
        icon = Icons.cancel_outlined;
        break;
    }
    return _StatusStyle(bg: bg, text: text, icon: icon);
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

  String _timeAgo(DateTime dt) {
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 60) return '${diff.inMinutes}m ago';
    if (diff.inHours < 24) return '${diff.inHours}h ago';
    return '${diff.inDays}d ago';
  }

  @override
  Widget build(BuildContext context) {
    final style = _getStatusStyle(inquiry.status);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
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
          children: [
            // ── Top Row: avatar + info + status ──────────────────────────
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                CircleAvatar(
                  radius: 20,
                  backgroundColor: kGold,
                  child: Text(
                    inquiry.buyerAvatar,
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
                    children: [
                      Text(
                        inquiry.buyerName,
                        style: const TextStyle(
                          color: kNavy,
                          fontWeight: FontWeight.w700,
                          fontSize: 13,
                        ),
                      ),
                      Text(
                        inquiry.buyerStudentId,
                        style: TextStyle(
                          color: Colors.grey[500],
                          fontSize: 11,
                        ),
                      ),
                    ],
                  ),
                ),
                // Status badge
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
                    children: [
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

            // ── Listing Info ──────────────────────────────────────────────
            Row(
              children: [
                const Icon(Icons.inventory_2_outlined, color: kNavy, size: 14),
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
                  inquiry.listingPrice,
                  style: const TextStyle(
                    color: kNavy,
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ],
            ),

            if (inquiry.message.isNotEmpty) ...[
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

            // ── Bottom Row: time + actions ────────────────────────────────
            Row(
              children: [
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
                // Seller actions: accept / decline
                if (isReceived &&
                    inquiry.status == InquiryStatus.pending) ...[
                  _ActionButton(
                    label: 'Decline',
                    color: Colors.red.shade100,
                    textColor: Colors.red.shade700,
                    onTap: () {
                      InquiryService().declineInquiry(inquiry.id);
                      onAction();
                    },
                  ),
                  const SizedBox(width: 8),
                  _ActionButton(
                    label: 'Accept',
                    color: Colors.green.shade100,
                    textColor: Colors.green.shade700,
                    onTap: () {
                      InquiryService().acceptInquiry(inquiry.id);
                      onAction();
                    },
                  ),
                ],
                // Buyer: delete sent inquiry
                if (!isReceived) ...[
                  _ActionButton(
                    label: 'Delete',
                    color: Colors.grey.shade100,
                    textColor: Colors.grey.shade600,
                    onTap: () {
                      InquiryService().deleteInquiry(inquiry.id);
                      onAction();
                    },
                  ),
                ],
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Small action button ──────────────────────────────────────────────────────
class _ActionButton extends StatelessWidget {
  final String label;
  final Color color;
  final Color textColor;
  final VoidCallback onTap;

  const _ActionButton({
    required this.label,
    required this.color,
    required this.textColor,
    required this.onTap,
  });

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

// ─── Internal helper ──────────────────────────────────────────────────────────
class _StatusStyle {
  final Color bg;
  final Color text;
  final IconData icon;
  const _StatusStyle({
    required this.bg,
    required this.text,
    required this.icon,
  });
}