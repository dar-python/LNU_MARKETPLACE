import 'package:flutter/material.dart';

import 'app_palette.dart';
import 'config/app_config.dart';
import 'core/network/api_client.dart';

class UserPublicProfilePage extends StatefulWidget {
  const UserPublicProfilePage({
    super.key,
    required this.userId,
    required this.initialName,
    required this.initialAvatar,
  });

  final int userId;
  final String initialName;
  final String initialAvatar;

  @override
  State<UserPublicProfilePage> createState() => _UserPublicProfilePageState();
}

class _UserPublicProfilePageState extends State<UserPublicProfilePage> {
  final ApiClient _apiClient = ApiClient();

  Map<String, dynamic>? _profile;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _loadProfile();
  }

  Future<void> _loadProfile({bool showLoading = true}) async {
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
      final response = await _apiClient.dio.get(
        '/api/v1/users/${widget.userId}/profile',
      );
      final rawUser = _apiClient.extractDataItemMap(response.data, 'user');
      if (rawUser == null) {
        throw const FormatException('Invalid public profile payload.');
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _profile = rawUser;
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

  Widget _buildAvatar(Map<String, dynamic>? profile) {
    final imageUrl = _profileImageUrl(profile);
    final fallbackLabel = widget.initialAvatar.trim().isNotEmpty
        ? widget.initialAvatar.trim().substring(0, 1).toUpperCase()
        : '?';

    return Container(
      width: 92,
      height: 92,
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
                errorBuilder: (_, _, _) => _buildAvatarFallback(fallbackLabel),
              )
            : _buildAvatarFallback(fallbackLabel),
      ),
    );
  }

  Widget _buildAvatarFallback(String label) {
    return Center(
      child: Text(
        label,
        style: const TextStyle(
          color: kNavy,
          fontSize: 34,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final profile = _profile;
    final displayName = profile?['name']?.toString().trim().isNotEmpty == true
        ? profile!['name'].toString().trim()
        : widget.initialName;
    final details = <_PublicDetail>[
      _PublicDetail(
        label: 'Contact Number',
        value: profile?['contact_number']?.toString() ?? '',
        icon: Icons.phone_outlined,
      ),
      _PublicDetail(
        label: 'Program',
        value: profile?['program']?.toString() ?? '',
        icon: Icons.school_outlined,
      ),
      _PublicDetail(
        label: 'Year Level',
        value: profile?['year_level']?.toString() ?? '',
        icon: Icons.calendar_today_outlined,
      ),
      _PublicDetail(
        label: 'Organization',
        value: profile?['organization']?.toString() ?? '',
        icon: Icons.groups_outlined,
      ),
      _PublicDetail(
        label: 'Section',
        value: profile?['section']?.toString() ?? '',
        icon: Icons.badge_outlined,
      ),
      _PublicDetail(
        label: 'Bio',
        value: profile?['bio']?.toString() ?? '',
        icon: Icons.edit_note_outlined,
        isMultiline: true,
      ),
    ].where((detail) => detail.value.trim().isNotEmpty).toList();

    return Scaffold(
      backgroundColor: kPageBackground,
      appBar: AppBar(
        backgroundColor: kNavy,
        foregroundColor: kWhite,
        title: const Text('Seller Profile'),
      ),
      body: RefreshIndicator(
        color: kNavy,
        onRefresh: () => _loadProfile(showLoading: false),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
          children: <Widget>[
            Container(
              padding: const EdgeInsets.all(24),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: <Color>[kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(28),
              ),
              child: Column(
                children: <Widget>[
                  _buildAvatar(profile),
                  const SizedBox(height: 16),
                  Text(
                    displayName,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: kWhite,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'LNU Student Seller',
                    style: TextStyle(
                      color: kGold.withValues(alpha: 0.95),
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (_isLoading) ...<Widget>[
                    const SizedBox(height: 18),
                    const SizedBox(
                      width: 24,
                      height: 24,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        color: kGold,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            if (_errorMessage != null) ...<Widget>[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF8E1),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: kGold),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    const Text(
                      'Unable to load the seller profile right now.',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      _errorMessage!,
                      style: TextStyle(color: Colors.grey[700], fontSize: 12),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _loadProfile,
                      style: OutlinedButton.styleFrom(foregroundColor: kNavy),
                      child: const Text('Retry'),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 18),
            const Text(
              'Public Details',
              style: TextStyle(
                color: kNavy,
                fontWeight: FontWeight.w800,
                fontSize: 16,
              ),
            ),
            const SizedBox(height: 10),
            if (details.isEmpty && !_isLoading)
              Container(
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: kWhite,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: <BoxShadow>[
                    BoxShadow(
                      color: Colors.black.withValues(alpha: 0.04),
                      blurRadius: 8,
                      offset: const Offset(0, 3),
                    ),
                  ],
                ),
                child: Text(
                  'This seller has not made any profile details public yet.',
                  style: TextStyle(color: Colors.grey[700], fontSize: 13),
                ),
              )
            else
              ...List<Widget>.generate(details.length, (index) {
                final detail = details[index];
                return Padding(
                  padding: EdgeInsets.only(
                    bottom: index == details.length - 1 ? 0 : 10,
                  ),
                  child: _PublicDetailTile(detail: detail),
                );
              }),
          ],
        ),
      ),
    );
  }
}

class _PublicDetail {
  const _PublicDetail({
    required this.label,
    required this.value,
    required this.icon,
    this.isMultiline = false,
  });

  final String label;
  final String value;
  final IconData icon;
  final bool isMultiline;
}

class _PublicDetailTile extends StatelessWidget {
  const _PublicDetailTile({required this.detail});

  final _PublicDetail detail;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(18),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: detail.isMultiline
            ? CrossAxisAlignment.start
            : CrossAxisAlignment.center,
        children: <Widget>[
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: kGold.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(detail.icon, color: kNavy, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  detail.label,
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  detail.value.trim(),
                  style: const TextStyle(
                    color: kNavy,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    height: 1.4,
                  ),
                  maxLines: detail.isMultiline ? null : 1,
                  overflow: detail.isMultiline ? null : TextOverflow.ellipsis,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
