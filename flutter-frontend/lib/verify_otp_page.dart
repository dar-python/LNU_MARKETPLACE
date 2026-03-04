import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'auth_service.dart';
import 'profile_page.dart';

class VerifyOtpPage extends StatefulWidget {
  const VerifyOtpPage({
    super.key,
    required this.identifier,
    this.loginIdentifier,
    this.loginPassword,
  });

  final String identifier;
  final String? loginIdentifier;
  final String? loginPassword;

  @override
  State<VerifyOtpPage> createState() => _VerifyOtpPageState();
}

class _VerifyOtpPageState extends State<VerifyOtpPage> {
  final TextEditingController _otpController = TextEditingController();

  Timer? _cooldownTimer;
  int _cooldownSeconds = 30;
  bool _isVerifying = false;
  bool _isResending = false;
  bool _isAutoSigningIn = false;
  String? _errorMessage;
  String? _infoMessage;

  @override
  void initState() {
    super.initState();
    _startCooldown();
  }

  @override
  void dispose() {
    _cooldownTimer?.cancel();
    _otpController.dispose();
    super.dispose();
  }

  Future<void> _verifyOtp() async {
    final otp = _otpController.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (otp != _otpController.text) {
      _otpController.value = TextEditingValue(
        text: otp,
        selection: TextSelection.collapsed(offset: otp.length),
      );
    }

    if (!RegExp(r'^\d{6}$').hasMatch(otp)) {
      setState(() {
        _errorMessage = 'OTP must be exactly 6 digits.';
        _infoMessage = null;
      });
      return;
    }

    setState(() {
      _isVerifying = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    final verifyError = await AuthService().verifyEmailOtp(
      identifier: widget.identifier,
      otp: otp,
    );

    if (verifyError != null) {
      if (!mounted) {
        return;
      }
      setState(() {
        _isVerifying = false;
        _errorMessage = verifyError;
      });
      return;
    }

    final loginIdentifier = widget.loginIdentifier?.trim();
    final loginPassword = widget.loginPassword;
    final canAutoLogin =
        loginIdentifier != null &&
        loginIdentifier.isNotEmpty &&
        loginPassword != null &&
        loginPassword.isNotEmpty;

    if (!canAutoLogin) {
      if (!mounted) {
        return;
      }
      setState(() {
        _isVerifying = false;
        _infoMessage = 'Verified. Please login.';
      });
      Navigator.pop(context, true);
      return;
    }

    setState(() {
      _isAutoSigningIn = true;
      _infoMessage = 'Email verified. Signing you in...';
    });

    final loginError = await AuthService().login(
      studentId: loginIdentifier,
      password: loginPassword,
    );

    if (!mounted) {
      return;
    }

    if (loginError != null) {
      setState(() {
        _isVerifying = false;
        _isAutoSigningIn = false;
        _errorMessage = loginError;
        _infoMessage = 'Email verified. Please sign in manually.';
      });
      return;
    }

    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const ProfilePage()),
      (route) => route.isFirst,
    );
  }

  Future<void> _resendOtp() async {
    if (_isResending) {
      return;
    }
    if (_cooldownSeconds > 0) {
      setState(() {
        _errorMessage = null;
        _infoMessage = 'You can resend OTP in $_cooldownSeconds seconds.';
      });
      return;
    }

    _startCooldown();
    setState(() {
      _isResending = true;
      _errorMessage = null;
      _infoMessage = null;
    });

    final error = await AuthService().resendEmailOtp(
      identifier: widget.identifier,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _isResending = false;
      if (error != null) {
        _errorMessage = error;
      } else {
        _infoMessage = 'OTP sent. Check your email or backend log output.';
      }
    });
  }

  void _startCooldown() {
    _cooldownTimer?.cancel();
    setState(() {
      _cooldownSeconds = 30;
    });

    _cooldownTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) {
        timer.cancel();
        return;
      }

      if (_cooldownSeconds <= 1) {
        timer.cancel();
        setState(() {
          _cooldownSeconds = 0;
        });
        return;
      }

      setState(() {
        _cooldownSeconds -= 1;
      });
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final colorScheme = theme.colorScheme;
    final textTheme = theme.textTheme;

    return Scaffold(
      appBar: AppBar(title: const Text('Verify OTP')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('Email Verification', style: textTheme.headlineSmall),
              const SizedBox(height: 8),
              Text(
                'Enter the 6-digit OTP sent to:',
                style: textTheme.bodyMedium,
              ),
              const SizedBox(height: 6),
              Text(
                widget.identifier,
                style: textTheme.bodyLarge?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: colorScheme.primary,
                ),
              ),
              const SizedBox(height: 24),
              TextField(
                controller: _otpController,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                textInputAction: TextInputAction.done,
                maxLength: 6,
                onSubmitted: (_) => _verifyOtp(),
                decoration: const InputDecoration(
                  labelText: 'OTP',
                  hintText: 'Enter 6-digit OTP',
                  counterText: '',
                  border: OutlineInputBorder(),
                ),
              ),
              if (_errorMessage != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: colorScheme.errorContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _errorMessage!,
                    style: textTheme.bodySmall?.copyWith(
                      color: colorScheme.onErrorContainer,
                    ),
                  ),
                ),
              ],
              if (_infoMessage != null) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: colorScheme.secondaryContainer,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    _infoMessage!,
                    style: textTheme.bodySmall?.copyWith(
                      color: colorScheme.onSecondaryContainer,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 20),
              SizedBox(
                height: 48,
                child: ElevatedButton(
                  onPressed: (_isVerifying || _isAutoSigningIn)
                      ? null
                      : _verifyOtp,
                  child: _isVerifying || _isAutoSigningIn
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Text('Verify OTP'),
                ),
              ),
              const SizedBox(height: 12),
              SizedBox(
                height: 48,
                child: OutlinedButton(
                  onPressed: _isResending ? null : _resendOtp,
                  child: Text(
                    _cooldownSeconds > 0
                        ? 'Resend OTP in ${_cooldownSeconds}s'
                        : (_isResending ? 'Resending...' : 'Resend OTP'),
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
