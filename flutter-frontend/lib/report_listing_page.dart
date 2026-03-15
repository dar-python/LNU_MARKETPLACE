import 'package:flutter/material.dart';

import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'login_page.dart';
import 'report_service.dart';

const kReportNavy = Color(0xFF0D1B6E);
const kReportGold = Color(0xFFF5C518);
const kReportWhite = Color(0xFFFFFFFF);

class ReportListingPage extends StatefulWidget {
  const ReportListingPage({super.key, required this.listingId});

  final int listingId;

  @override
  State<ReportListingPage> createState() => _ReportListingPageState();
}

class _ReportListingPageState extends State<ReportListingPage> {
  final TextEditingController _descriptionController = TextEditingController();
  final ApiClient _apiClient = ApiClient();

  String _selectedReason = _reportReasonOptions.first.value;
  bool _isSubmitting = false;
  String? _errorMessage;

  @override
  void dispose() {
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _openLoginPage() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
    );
  }

  Future<void> _submitReport() async {
    final description = _descriptionController.text.trim();

    if (description.isEmpty) {
      setState(
        () => _errorMessage =
            'Please describe why you are reporting this listing.',
      );
      return;
    }

    if (!AuthService().hasSession) {
      await _openLoginPage();
      if (!mounted || !AuthService().hasSession) {
        setState(() => _errorMessage = 'Please log in to submit a report.');
        return;
      }
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    try {
      await ReportService().submitListingReport(
        listingId: widget.listingId,
        reasonCategory: _selectedReason,
        description: description,
      );

      if (!mounted) {
        return;
      }

      Navigator.pop(context, true);
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );

      if (!mounted) {
        return;
      }

      if (sessionExpired) {
        await _openLoginPage();
      }

      setState(() {
        _errorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(
                error,
                maxMessages: 3,
                includeFieldNames: true,
              );
      });
    } finally {
      if (mounted) {
        setState(() => _isSubmitting = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      appBar: AppBar(
        title: const Text(
          'Report Listing',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        backgroundColor: kReportNavy,
        foregroundColor: kReportWhite,
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: kReportWhite,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.05),
                      blurRadius: 10,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Text(
                      'Help us review this listing',
                      style: TextStyle(
                        color: kReportNavy,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Tell us what looks wrong with listing #${widget.listingId}. Reports are reviewed by the moderation team.',
                      style: TextStyle(
                        color: Colors.grey[700],
                        fontSize: 13,
                        height: 1.5,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Reason',
                style: TextStyle(
                  color: kReportNavy,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 14),
                decoration: BoxDecoration(
                  color: kReportWhite,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: DropdownButtonFormField<String>(
                  initialValue: _selectedReason,
                  decoration: const InputDecoration(border: InputBorder.none),
                  items: _reportReasonOptions
                      .map(
                        (option) => DropdownMenuItem<String>(
                          value: option.value,
                          child: Text(option.label),
                        ),
                      )
                      .toList(),
                  onChanged: _isSubmitting
                      ? null
                      : (value) {
                          if (value == null) {
                            return;
                          }

                          setState(() => _selectedReason = value);
                        },
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Details',
                style: TextStyle(
                  color: kReportNavy,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  color: kReportWhite,
                  borderRadius: BorderRadius.circular(14),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: TextField(
                  controller: _descriptionController,
                  enabled: !_isSubmitting,
                  maxLines: 7,
                  maxLength: 2000,
                  decoration: const InputDecoration(
                    hintText:
                        'Share enough detail for moderators to understand the issue.',
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.all(16),
                    counterText: '',
                  ),
                ),
              ),
              if (_errorMessage != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  style: const TextStyle(
                    color: Colors.redAccent,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
              const SizedBox(height: 24),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  onPressed: _isSubmitting ? null : _submitReport,
                  icon: _isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            valueColor: AlwaysStoppedAnimation<Color>(
                              kReportGold,
                            ),
                          ),
                        )
                      : const Icon(Icons.outlined_flag),
                  label: const Text(
                    'Submit Report',
                    style: TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: Colors.redAccent,
                    foregroundColor: kReportWhite,
                    disabledBackgroundColor: Colors.redAccent.withValues(
                      alpha: 0.55,
                    ),
                    disabledForegroundColor: kReportWhite,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ReportReasonOption {
  const _ReportReasonOption({required this.value, required this.label});

  final String value;
  final String label;
}

const List<_ReportReasonOption> _reportReasonOptions = <_ReportReasonOption>[
  _ReportReasonOption(value: 'scam', label: 'Scam or fraud'),
  _ReportReasonOption(
    value: 'inappropriate_content',
    label: 'Inappropriate content',
  ),
  _ReportReasonOption(value: 'prohibited_item', label: 'Prohibited item'),
  _ReportReasonOption(value: 'harassment', label: 'Harassment'),
  _ReportReasonOption(value: 'impersonation', label: 'Impersonation'),
  _ReportReasonOption(value: 'spam', label: 'Spam'),
  _ReportReasonOption(value: 'other', label: 'Other'),
];
