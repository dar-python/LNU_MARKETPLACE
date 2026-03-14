import 'package:flutter/material.dart';

import 'about_page.dart';
import 'app_palette.dart';
import 'auth_service.dart';
import 'backend_status_page.dart' show BackendStatusPage;
import 'edit_profile_page.dart';
import 'favorite_page.dart' show FavoritesPage;
import 'help_support_page.dart';
import 'home_page.dart' show HomePage;
import 'login_page.dart' show LoginPage;
import 'my_listings_page.dart' show MyListingsPage;
import 'privacy_policy_page.dart';
import 'purchase_history_page.dart';
import 'settings_page.dart';
import 'config/app_config.dart';

class ProfilePage extends StatefulWidget {
  const ProfilePage({super.key});

  @override
  State<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends State<ProfilePage> {
  Map<String, dynamic>? _user;
  bool _isCheckingBackend = false;
  bool _isLoadingProfile = true;
  bool _isRedirectingToLogin = false;
  String? _profileError;

  @override
  void initState() {
    super.initState();
    _user = AuthService().currentUser;
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    final profileError = await AuthService().refreshCurrentUser();
    await AuthService().pingBackend();

    if (!mounted) {
      return;
    }

    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    setState(() {
      _user = AuthService().currentUser;
      _profileError = profileError;
      _isLoadingProfile = false;
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
      (Route<dynamic> route) => false,
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
      (Route<dynamic> route) => route.isFirst,
    );
    _isRedirectingToLogin = false;
  }

  Future<void> _openPage(Widget page) async {
    await Navigator.push(context, MaterialPageRoute(builder: (_) => page));

    if (!mounted) {
      return;
    }

    setState(() {
      _user = AuthService().currentUser;
    });
  }

  String _profileImageUrl(Map<String, dynamic>? user) {
    final rawPath =
        user?['profilePicturePath']?.toString().trim() ??
        user?['profile_picture_path']?.toString().trim() ??
        '';

    if (rawPath.isEmpty) {
      return '';
    }

    final parsedUri = Uri.tryParse(rawPath);
    if (parsedUri != null && parsedUri.hasScheme) {
      return rawPath;
    }

    final relativePath = rawPath.startsWith('/')
        ? rawPath.substring(1)
        : rawPath;

    return Uri.parse(
      '${AppConfig.baseUrl}/',
    ).resolve('storage/$relativePath').toString();
  }

  Widget _buildProfileAvatar(Map<String, dynamic>? user) {
    final imageUrl = _profileImageUrl(user);

    return Container(
      width: 80,
      height: 80,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: kGold,
        border: Border.all(color: kWhite, width: 3),
      ),
      child: ClipOval(
        child: imageUrl.isNotEmpty
            ? Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => _buildAvatarFallback(user),
                loadingBuilder: (context, child, loadingProgress) {
                  if (loadingProgress == null) {
                    return child;
                  }

                  return const Center(
                    child: SizedBox(
                      width: 22,
                      height: 22,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: kNavy,
                      ),
                    ),
                  );
                },
              )
            : _buildAvatarFallback(user),
      ),
    );
  }

  Widget _buildAvatarFallback(Map<String, dynamic>? user) {
    return Center(
      child: Text(
        user?['avatar'] ?? '?',
        style: const TextStyle(
          color: kNavy,
          fontSize: 32,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final user = _user;
    final displayName = _isLoadingProfile && user == null
        ? 'Loading profile...'
        : user?['name'] ?? 'Profile unavailable';
    final displayEmail = user?['email']?.toString() ?? '';
    final displayStudentId = user?['studentId']?.toString() ?? 'N/A';
    final program = user?['program']?.toString().trim() ?? '';
    final yearLevel = user?['yearLevel']?.toString().trim() ?? '';
    final section = user?['section']?.toString().trim() ?? '';
    final contactNumber = user?['contactNumber']?.toString().trim() ?? '';
    final bio = user?['bio']?.toString().trim() ?? '';
    final academicSummary = <String>[
      program,
      yearLevel,
      section,
    ].where((value) => value.isNotEmpty).join(' - ');
    final statusCode = AuthService().lastPingStatusCode;
    final pingBody = AuthService().lastPingBody;
    final pingError = AuthService().lastPingError;

    return Scaffold(
      backgroundColor: kPageBackground,
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
                    _buildProfileAvatar(user),
                    const SizedBox(height: 14),
                    Text(
                      displayName,
                      style: const TextStyle(
                        color: kWhite,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      displayEmail,
                      style: const TextStyle(color: kGold, fontSize: 12),
                    ),
                    if (academicSummary.isNotEmpty) ...<Widget>[
                      const SizedBox(height: 10),
                      Text(
                        academicSummary,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: kWhite.withValues(alpha: 0.92),
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if (contactNumber.isNotEmpty) ...<Widget>[
                      const SizedBox(height: 6),
                      Text(
                        contactNumber,
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          color: kWhite.withValues(alpha: 0.78),
                          fontSize: 12,
                        ),
                      ),
                    ],
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
                        'ID: $displayStudentId',
                        style: const TextStyle(
                          color: kWhite,
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                    if (bio.isNotEmpty) ...<Widget>[
                      const SizedBox(height: 12),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: kWhite.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Text(
                          bio,
                          maxLines: 3,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: kWhite.withValues(alpha: 0.9),
                            fontSize: 12,
                            fontStyle: FontStyle.italic,
                            height: 1.5,
                          ),
                        ),
                      ),
                    ],
                    if (_isLoadingProfile) ...<Widget>[
                      const SizedBox(height: 12),
                      const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: kGold,
                        ),
                      ),
                    ],
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
                    if (!_isLoadingProfile &&
                        _profileError != null &&
                        user == null) ...<Widget>[
                      const SizedBox(height: 8),
                      OutlinedButton(
                        onPressed: _bootstrap,
                        style: OutlinedButton.styleFrom(
                          foregroundColor: kWhite,
                          side: const BorderSide(color: kWhite),
                        ),
                        child: const Text('Retry profile'),
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
                      onTap: () => _openPage(const EditProfilePage()),
                    ),
                    _MenuItem(
                      icon: Icons.store_outlined,
                      label: 'My Listings',
                      onTap: () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const MyListingsPage(),
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
                      onTap: () => _openPage(const PurchaseHistoryPage()),
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
                      'Preferences',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _MenuItem(
                      icon: Icons.settings_outlined,
                      label: 'Settings',
                      onTap: () => _openPage(const SettingsPage()),
                    ),
                    _MenuItem(
                      icon: Icons.notifications_outlined,
                      label: 'Notifications',
                      onTap: () {},
                    ),
                    _MenuItem(
                      icon: Icons.help_outline,
                      label: 'Help & Support',
                      onTap: () => _openPage(const HelpSupportPage()),
                    ),
                    _MenuItem(
                      icon: Icons.info_outline,
                      label: 'About',
                      onTap: () => _openPage(const AboutPage()),
                    ),
                    _MenuItem(
                      icon: Icons.privacy_tip_outlined,
                      label: 'Privacy Policy & Terms',
                      onTap: () => _openPage(const PrivacyPolicyPage()),
                    ),
                    const SizedBox(height: 20),
                    _MenuItem(
                      icon: Icons.logout,
                      label: 'Sign Out',
                      onTap: _logout,
                      iconColor: Colors.red,
                      textColor: Colors.red,
                      trailingColor: Colors.redAccent,
                      backgroundColor: Colors.red.shade50,
                      borderColor: Colors.red.shade100,
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
  final Color? backgroundColor;
  final Color? borderColor;
  final Color? iconColor;
  final Color? textColor;
  final Color? trailingColor;

  const _MenuItem({
    required this.icon,
    required this.label,
    required this.onTap,
    this.backgroundColor,
    this.borderColor,
    this.iconColor,
    this.textColor,
    this.trailingColor,
  });

  @override
  Widget build(BuildContext context) {
    final resolvedBackgroundColor = backgroundColor ?? kWhite;
    final resolvedBorderColor = borderColor ?? Colors.transparent;
    final resolvedIconColor = iconColor ?? kNavy;
    final resolvedTextColor = textColor ?? kNavy;
    final resolvedTrailingColor = trailingColor ?? Colors.grey.shade400;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: resolvedBackgroundColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: resolvedBorderColor),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Material(
        color: Colors.transparent,
        child: ListTile(
          onTap: onTap,
          leading: Icon(icon, color: resolvedIconColor, size: 20),
          title: Text(
            label,
            style: TextStyle(
              color: resolvedTextColor,
              fontSize: 13,
              fontWeight: FontWeight.w600,
            ),
          ),
          trailing: Icon(
            Icons.chevron_right,
            color: resolvedTrailingColor,
            size: 18,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
    );
  }
}
