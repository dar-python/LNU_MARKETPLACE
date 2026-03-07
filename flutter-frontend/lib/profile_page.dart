import 'package:flutter/material.dart';

import 'auth_service.dart';
import 'backend_status_page.dart';
import 'login_page.dart';
import 'home_page.dart';
import 'favorite_page.dart';
import 'Inquiry_page.dart';

const kNavy = Color(0xFF000080);
const kDarkNavy = Color(0xFF00263E);
const kGold = Color(0xFFFFD700);
const kWhite = Color(0xFFFFFFFF);

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  Map<String, dynamic>? _user;
  bool _isCheckingBackend = false;
  String? _profileError;

  @override
  void initState() {
    super.initState();
    _user = AuthService().currentUser;
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final profileError = await AuthService().refreshCurrentUser();
    await AuthService().pingBackend();

    if (!mounted) {
      return;
    }

    setState(() {
      _user = AuthService().currentUser;
      _profileError = profileError;
    });
  }

  Future<void> _refreshBackendStatus() async {
    setState(() {
      _isCheckingBackend = true;
    });

    await AuthService().pingBackend();

    if (!mounted) {
      return;
    }

    setState(() {
      _isCheckingBackend = false;
    });
  }

  Future<void> _logout() async {
    await AuthService().logout();

    if (!mounted) {
      return;
    }

    Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
      (Route<dynamic> route) => route.isFirst,
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    final statusCode = AuthService().lastPingStatusCode;
    final pingBody = AuthService().lastPingBody;
    final pingError = AuthService().lastPingError;

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: SingleChildScrollView(
          physics: const BouncingScrollPhysics(),
          child: Column(
            children: <Widget>[
              Container(
                width: double.infinity,
                decoration: const BoxDecoration(
                  gradient: LinearGradient(
                    colors: <Color>[kDarkNavy, kNavy],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                  borderRadius: BorderRadius.only(
                    bottomLeft: Radius.circular(36),
                    bottomRight: Radius.circular(36),
                  ),
                ),
                padding: const EdgeInsets.fromLTRB(24, 16, 24, 32),
                child: Column(
                  children: [
                    // ── Home Button Row ──────────────────────────────────
                    Row(
                      children: [
                        GestureDetector(
                          onTap: () => Navigator.pushAndRemoveUntil(
                            context,
                            MaterialPageRoute(builder: (_) => const HomePage()),
                            (route) => false,
                          ),
                          child: Container(
                            padding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 6,
                            ),
                            decoration: BoxDecoration(
                              color: kWhite.withValues(alpha: 0.15),
                              borderRadius: BorderRadius.circular(20),
                            ),
                            child: Row(
                              children: const [
                                Icon(
                                  Icons.home_rounded,
                                  color: kWhite,
                                  size: 16,
                                ),
                                SizedBox(width: 6),
                                Text(
                                  'Home',
                                  style: TextStyle(
                                    color: kWhite,
                                    fontSize: 12,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    // Avatar
                    Container(
                      width: 80,
                      height: 80,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        color: kGold,
                        border: Border.all(color: kWhite, width: 3),
                      ),
                      child: Center(
                        child: Text(
                          user?['avatar'] ?? '?',
                          style: const TextStyle(
                            color: kNavy,
                            fontSize: 32,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Text(
                      user?['name'] ?? 'Guest User',
                      style: const TextStyle(
                        color: kWhite,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user?['email'] ?? '',
                      style: const TextStyle(color: kGold, fontSize: 12),
                    ),
                    const SizedBox(height: 4),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: kWhite.withValues(alpha: 0.15),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        'ID: ${user?['studentId'] ?? 'N/A'}',
                        style: const TextStyle(
                          color: kWhite,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    if (_profileError != null) ...<Widget>[
                      const SizedBox(height: 8),
                      Text(
                        _profileError!,
                        style: const TextStyle(
                          color: Colors.redAccent,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 24),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Row(
                  children: const <Widget>[
                    _StatCard(label: 'Listings', value: '0'),
                    SizedBox(width: 12),
                    _StatCard(label: 'Sold', value: '0'),
                    SizedBox(width: 12),
                    _StatCard(label: 'Saved', value: '0'),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: kWhite,
                    borderRadius: BorderRadius.circular(12),
                    boxShadow: <BoxShadow>[
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.04),
                        blurRadius: 6,
                        offset: const Offset(0, 2),
                      ),
                    ],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      const Text(
                        'Backend Ping',
                        style: TextStyle(
                          color: kNavy,
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'API_BASE_URL: ${AuthService().baseUrl}',
                        style: TextStyle(color: Colors.grey[700], fontSize: 11),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        pingError ??
                            'Status: ${statusCode ?? 'Not checked'} | Body: ${pingBody ?? {}}',
                        style: TextStyle(
                          color: pingError != null
                              ? Colors.red[700]
                              : Colors.grey[800],
                          fontSize: 11,
                        ),
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: <Widget>[
                          ElevatedButton(
                            onPressed: _isCheckingBackend
                                ? null
                                : _refreshBackendStatus,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: kNavy,
                              foregroundColor: kWhite,
                              textStyle: const TextStyle(fontSize: 12),
                            ),
                            child: _isCheckingBackend
                                ? const SizedBox(
                                    width: 16,
                                    height: 16,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: kWhite,
                                    ),
                                  )
                                : const Text('Refresh Ping'),
                          ),
                          const SizedBox(width: 10),
                          OutlinedButton(
                            onPressed: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const BackendStatusPage(),
                                ),
                              );
                            },
                            child: const Text('Open Debug Screen'),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 24),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Text(
                      'Account',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _MenuItem(
                      icon: Icons.person_outline,
                      label: 'Edit Profile',
                      onTap: () {},
                    ),
                    _MenuItem(
                      icon: Icons.store_outlined,
                      label: 'My Listings',
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const InquiryPage(),
                          ),
                        );
                      },
                    ),
                    _MenuItem(
                      icon: Icons.favorite_outline,
                      label: 'Saved Items',
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const FavoritesPage(),
                          ),
                        );
                      },
                    ),
                    _MenuItem(
                      icon: Icons.history,
                      label: 'Purchase History',
                      onTap: () {},
                    ),
                    _MenuItem(
                      icon: Icons.bug_report_outlined,
                      label: 'Backend Status',
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const BackendStatusPage(),
                          ),
                        );
                      },
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Settings',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _MenuItem(
                      icon: Icons.notifications_outlined,
                      label: 'Notifications',
                      onTap: () {},
                    ),
                    _MenuItem(
                      icon: Icons.help_outline,
                      label: 'Help & Support',
                      onTap: () {},
                    ),
                    _MenuItem(
                      icon: Icons.info_outline,
                      label: 'About',
                      onTap: () {},
                    ),
                    const SizedBox(height: 20),
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: _logout,
                        icon: const Icon(Icons.logout, size: 18),
                        label: const Text(
                          'Logout',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.red[50],
                          foregroundColor: Colors.red[600],
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                            side: BorderSide(color: Colors.red[200]!),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String label;
  final String value;

  const _StatCard({required this.label, required this.value});

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(14),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.05),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          children: <Widget>[
            Text(
              value,
              style: const TextStyle(
                color: kNavy,
                fontSize: 22,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(color: Colors.grey[500], fontSize: 11),
            ),
          ],
        ),
      ),
    );
  }
}

class _MenuItem extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(12),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.04),
              blurRadius: 6,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: <Widget>[
            Icon(icon, color: kNavy, size: 20),
            const SizedBox(width: 14),
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: kNavy,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
            Icon(Icons.chevron_right, color: Colors.grey[400], size: 18),
          ],
        ),
      ),
    );
  }
}
