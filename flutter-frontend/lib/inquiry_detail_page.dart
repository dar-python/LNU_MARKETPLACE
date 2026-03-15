import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'auth_service.dart';
import 'config/app_config.dart';
import 'core/network/api_client.dart';
import 'inquiry_model.dart';
import 'inquiry_service.dart';
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
  bool _isCompletingTransaction = false;
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

  Future<void> _handleCompleteTransaction() async {
    if (_isCompletingTransaction) {
      return;
    }

    XFile? pickedImage;

    try {
      pickedImage = await ImagePicker().pickImage(source: ImageSource.gallery);
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Unable to open the gallery right now.'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (!mounted || pickedImage == null) {
      return;
    }

    late final Inquiry updatedInquiry;

    setState(() {
      _isCompletingTransaction = true;
    });

    try {
      updatedInquiry = await InquiryService().completeTransaction(
        inquiryId: _inquiry.id,
        proofImage: File(pickedImage.path),
        fallback: _inquiry,
      );
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
      return;
    } finally {
      if (mounted) {
        setState(() {
          _isCompletingTransaction = false;
        });
      }
    }

    if (!mounted) {
      return;
    }

    setState(() {
      _inquiry = updatedInquiry;
    });

    await _showCompletionSuccessDialog();

    if (!mounted) {
      return;
    }

    await _loadInquiry();
  }

  Future<void> _showCompletionSuccessDialog() async {
    await showDialog<void>(
      context: context,
      builder: (BuildContext dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
          content: const Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Icon(
                Icons.check_circle_rounded,
                color: Color(0xFF2E7D32),
                size: 64,
              ),
              SizedBox(height: 16),
              Text(
                'Congratulations of successful transaction!',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: kInquiryNavy,
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          actions: <Widget>[
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: const Text(
                'OK',
                style: TextStyle(
                  color: kInquiryNavy,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        );
      },
    );
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
    final currentUserId = AuthService().currentUser?['id'];
    final isSeller =
        currentUserId is int && currentUserId == inquiry.recipientUserId;
    final canCompleteTransaction =
        isSeller && inquiry.status == InquiryStatus.accepted;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Stack(
          children: <Widget>[
            Column(
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
                              style: TextStyle(
                                color: kInquiryGold,
                                fontSize: 11,
                              ),
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
                          title: 'About the Counterparty',
                          children: _buildCounterpartyDetails(inquiry),
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
                        if (inquiry.proofImagePath != null) ...<Widget>[
                          const SizedBox(height: 16),
                          _InfoCard(
                            title: 'Proof of Transaction',
                            children: <Widget>[
                              ClipRRect(
                                borderRadius: BorderRadius.circular(14),
                                child: AspectRatio(
                                  aspectRatio: 4 / 3,
                                  child: Image.network(
                                    _resolvePublicImageUrl(
                                      inquiry.proofImagePath!,
                                    ),
                                    fit: BoxFit.cover,
                                    loadingBuilder:
                                        (
                                          BuildContext context,
                                          Widget child,
                                          ImageChunkEvent? loadingProgress,
                                        ) {
                                          if (loadingProgress == null) {
                                            return child;
                                          }

                                          return Container(
                                            color: const Color(0xFFF4F6FF),
                                            alignment: Alignment.center,
                                            child:
                                                const CircularProgressIndicator(
                                                  color: kInquiryNavy,
                                                ),
                                          );
                                        },
                                    errorBuilder: (_, _, _) => Container(
                                      color: const Color(0xFFF4F6FF),
                                      alignment: Alignment.center,
                                      child: const Column(
                                        mainAxisAlignment:
                                            MainAxisAlignment.center,
                                        children: <Widget>[
                                          Icon(
                                            Icons.broken_image_outlined,
                                            color: kInquiryNavy,
                                            size: 36,
                                          ),
                                          SizedBox(height: 8),
                                          Text(
                                            'Unable to load proof image.',
                                            style: TextStyle(
                                              color: kInquiryNavy,
                                              fontWeight: FontWeight.w600,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                        if (canCompleteTransaction) ...<Widget>[
                          const SizedBox(height: 16),
                          SizedBox(
                            width: double.infinity,
                            height: 52,
                            child: ElevatedButton.icon(
                              onPressed: _isCompletingTransaction
                                  ? null
                                  : _handleCompleteTransaction,
                              style: ElevatedButton.styleFrom(
                                backgroundColor: kInquiryGold,
                                foregroundColor: kInquiryNavy,
                                elevation: 0,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(14),
                                ),
                              ),
                              icon: const Icon(Icons.task_alt_rounded),
                              label: const Text(
                                'Complete Transaction & Attach Proof',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                          ),
                        ],
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
                            if (inquiry.completedAt != null)
                              _InfoRow(
                                label: 'Completed',
                                value: _formatDateTime(inquiry.completedAt!),
                              ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
            if (_isCompletingTransaction)
              Positioned.fill(
                child: ColoredBox(
                  color: Colors.black.withValues(alpha: 0.35),
                  child: const Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: <Widget>[
                        CircularProgressIndicator(color: kInquiryWhite),
                        SizedBox(height: 14),
                        Text(
                          'Completing transaction...',
                          style: TextStyle(
                            color: kInquiryWhite,
                            fontSize: 14,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  List<Widget> _buildCounterpartyDetails(Inquiry inquiry) {
    final detailRows = <Widget>[
      if (inquiry.counterpartyContact != null)
        _InfoRow(label: 'Contact', value: inquiry.counterpartyContact!),
      if (inquiry.counterpartyProgram != null)
        _InfoRow(label: 'Program', value: inquiry.counterpartyProgram!),
      if (inquiry.counterpartyYearLevel != null)
        _InfoRow(label: 'Year Level', value: inquiry.counterpartyYearLevel!),
      if (inquiry.counterpartyOrganization != null)
        _InfoRow(
          label: 'Organization',
          value: inquiry.counterpartyOrganization!,
        ),
      if (inquiry.counterpartySection != null)
        _InfoRow(label: 'Section', value: inquiry.counterpartySection!),
    ];

    if (inquiry.counterpartyBio != null) {
      detailRows.add(
        Padding(
          padding: const EdgeInsets.only(top: 2),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Text(
                'Bio',
                style: TextStyle(color: Colors.grey[500], fontSize: 12),
              ),
              const SizedBox(height: 6),
              Text(
                inquiry.counterpartyBio!,
                style: const TextStyle(
                  color: kInquiryNavy,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  height: 1.5,
                ),
              ),
            ],
          ),
        ),
      );
    }

    if (detailRows.isNotEmpty) {
      return detailRows;
    }

    return <Widget>[
      Text(
        'No public details shared yet.',
        style: TextStyle(
          color: Colors.grey[600],
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
      ),
    ];
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

  String _resolvePublicImageUrl(String imagePath) {
    final normalizedImagePath = imagePath.trim();
    if (normalizedImagePath.isEmpty) {
      return '';
    }

    final parsedUri = Uri.tryParse(normalizedImagePath);
    if (parsedUri != null && parsedUri.hasScheme) {
      return normalizedImagePath;
    }

    final relativePath = normalizedImagePath.startsWith('/')
        ? normalizedImagePath.substring(1)
        : normalizedImagePath;

    return Uri.parse(
      '${AppConfig.baseUrl}/',
    ).resolve('storage/$relativePath').toString();
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
      InquiryStatus.completed => const Color(0xFFE3F2FD),
      InquiryStatus.declined => const Color(0xFFFFEBEE),
    };
    final textColor = switch (status) {
      InquiryStatus.pending => const Color(0xFFE65100),
      InquiryStatus.accepted => const Color(0xFF2E7D32),
      InquiryStatus.completed => const Color(0xFF1565C0),
      InquiryStatus.declined => const Color(0xFFC62828),
    };
    final label = switch (status) {
      InquiryStatus.pending => 'Pending',
      InquiryStatus.accepted => 'Accepted',
      InquiryStatus.completed => 'Completed',
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
