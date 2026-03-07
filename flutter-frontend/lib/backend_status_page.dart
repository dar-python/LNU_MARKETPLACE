import 'dart:convert';

import 'package:flutter/material.dart';

import 'auth_service.dart';

class BackendStatusPage extends StatefulWidget {
  const BackendStatusPage({super.key});

  @override
  State<BackendStatusPage> createState() => _BackendStatusPageState();
}

class _BackendStatusPageState extends State<BackendStatusPage> {
  bool _loading = false;
  String? _error;
  int? _statusCode;
  Map<String, dynamic>? _body;

  @override
  void initState() {
    super.initState();
    _checkBackend();
  }

  Future<void> _checkBackend() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    final error = await AuthService().pingBackend();
    if (!mounted) {
      return;
    }

    setState(() {
      _loading = false;
      _error = error;
      _statusCode = AuthService().lastPingStatusCode;
      _body = AuthService().lastPingBody;
    });
  }

  @override
  Widget build(BuildContext context) {
    final baseUrl = AuthService().baseUrl;
    final formattedBody = _body == null
        ? 'No response yet.'
        : const JsonEncoder.withIndent('  ').convert(_body);

    return Scaffold(
      appBar: AppBar(title: const Text('Backend Status')),
      body: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              'Base URL: $baseUrl',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 12),
            Text(
              'Endpoint: $baseUrl/api/ping',
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
            const SizedBox(height: 16),
            if (_statusCode != null)
              Text(
                'HTTP Status: $_statusCode',
                style: TextStyle(
                  color: (_statusCode! >= 200 && _statusCode! < 300)
                      ? Colors.green[700]
                      : Colors.red[700],
                  fontWeight: FontWeight.w700,
                ),
              ),
            if (_error != null) ...<Widget>[
              const SizedBox(height: 8),
              Text(
                _error!,
                style: TextStyle(
                  color: Colors.red[700],
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const SizedBox(height: 16),
            Expanded(
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  border: Border.all(color: Colors.grey.shade300),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: SingleChildScrollView(
                  child: Text(
                    formattedBody,
                    style: const TextStyle(fontFamily: 'monospace'),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            SizedBox(
              width: double.infinity,
              child: ElevatedButton(
                onPressed: _loading ? null : _checkBackend,
                child: _loading
                    ? const SizedBox(
                        width: 18,
                        height: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Check /api/ping'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
