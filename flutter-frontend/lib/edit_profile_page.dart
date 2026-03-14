import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'app_palette.dart';
import 'auth_service.dart';
import 'config/app_config.dart';

class EditProfilePage extends StatefulWidget {
  const EditProfilePage({super.key});

  @override
  State<EditProfilePage> createState() => _EditProfilePageState();
}

class _EditProfilePageState extends State<EditProfilePage> {
  final ImagePicker _imagePicker = ImagePicker();
  late final TextEditingController _contactNumberController;
  late final TextEditingController _programController;
  late final TextEditingController _yearLevelController;
  late final TextEditingController _organizationController;
  late final TextEditingController _sectionController;
  late final TextEditingController _bioController;

  XFile? _selectedImage;
  bool _isSaving = false;
  late bool _isContactPublic;
  late bool _isProgramPublic;
  late bool _isYearLevelPublic;
  late bool _isOrganizationPublic;
  late bool _isSectionPublic;
  late bool _isBioPublic;

  @override
  void initState() {
    super.initState();
    final user = AuthService().currentUser;
    _contactNumberController = TextEditingController(
      text: user?['contactNumber']?.toString() ?? '',
    );
    _programController = TextEditingController(
      text: user?['program']?.toString() ?? '',
    );
    _yearLevelController = TextEditingController(
      text: user?['yearLevel']?.toString() ?? '',
    );
    _organizationController = TextEditingController(
      text: user?['organization']?.toString() ?? '',
    );
    _sectionController = TextEditingController(
      text: user?['section']?.toString() ?? '',
    );
    _bioController = TextEditingController(
      text: user?['bio']?.toString() ?? '',
    );
    _isContactPublic = _privacySetting(
      user,
      'isContactPublic',
      'is_contact_public',
    );
    _isProgramPublic = _privacySetting(
      user,
      'isProgramPublic',
      'is_program_public',
    );
    _isYearLevelPublic = _privacySetting(
      user,
      'isYearLevelPublic',
      'is_year_level_public',
    );
    _isOrganizationPublic = _privacySetting(
      user,
      'isOrganizationPublic',
      'is_organization_public',
    );
    _isSectionPublic = _privacySetting(
      user,
      'isSectionPublic',
      'is_section_public',
    );
    _isBioPublic = _privacySetting(user, 'isBioPublic', 'is_bio_public');
  }

  @override
  void dispose() {
    _contactNumberController.dispose();
    _programController.dispose();
    _yearLevelController.dispose();
    _organizationController.dispose();
    _sectionController.dispose();
    _bioController.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    try {
      final pickedImage = await _imagePicker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
      );

      if (!mounted || pickedImage == null) {
        return;
      }

      setState(() {
        _selectedImage = pickedImage;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Unable to open the gallery right now.')),
      );
    }
  }

  Future<void> _saveChanges() async {
    if (_isSaving) {
      return;
    }

    setState(() {
      _isSaving = true;
    });

    final message = await AuthService().updateProfile(
      contactNumber: _contactNumberController.text,
      program: _programController.text,
      yearLevel: _yearLevelController.text,
      organization: _organizationController.text,
      section: _sectionController.text,
      bio: _bioController.text,
      isContactPublic: _isContactPublic,
      isProgramPublic: _isProgramPublic,
      isYearLevelPublic: _isYearLevelPublic,
      isOrganizationPublic: _isOrganizationPublic,
      isSectionPublic: _isSectionPublic,
      isBioPublic: _isBioPublic,
      profilePicture: _selectedImage != null
          ? File(_selectedImage!.path)
          : null,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _isSaving = false;
    });

    if (message != null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(message), backgroundColor: Colors.red.shade700),
      );
      return;
    }

    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(
      SnackBar(
        content: Text(
          AuthService().lastResponseMessage ?? 'Profile updated successfully.',
        ),
        backgroundColor: kNavy,
      ),
    );
    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final user = AuthService().currentUser;
    final profilePicturePath = user?['profilePicturePath']?.toString() ?? '';

    return Scaffold(
      backgroundColor: kPageBackground,
      appBar: AppBar(
        backgroundColor: kNavy,
        centerTitle: true,
        title: const Text('Edit Profile'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
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
                  GestureDetector(
                    onTap: _pickImage,
                    child: Stack(
                      clipBehavior: Clip.none,
                      children: <Widget>[
                        Container(
                          width: 108,
                          height: 108,
                          decoration: BoxDecoration(
                            shape: BoxShape.circle,
                            color: kWhite,
                            border: Border.all(color: kGold, width: 3),
                          ),
                          child: ClipOval(
                            child: _buildAvatarContent(profilePicturePath),
                          ),
                        ),
                        Positioned(
                          right: -2,
                          bottom: -2,
                          child: Container(
                            width: 34,
                            height: 34,
                            decoration: BoxDecoration(
                              color: kGold,
                              shape: BoxShape.circle,
                              border: Border.all(color: kWhite, width: 2),
                            ),
                            child: const Icon(
                              Icons.camera_alt_rounded,
                              color: kNavy,
                              size: 18,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Tap to change profile picture',
                    style: TextStyle(
                      color: kWhite,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    user?['name']?.toString() ?? 'LNU Student',
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Color(0xFFF4E7A7),
                      fontSize: 13,
                      height: 1.5,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 18),
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: kWhite,
                borderRadius: BorderRadius.circular(24),
                boxShadow: <BoxShadow>[
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 14,
                    offset: const Offset(0, 6),
                  ),
                ],
              ),
              child: Column(
                children: <Widget>[
                  _ProfileField(
                    controller: _contactNumberController,
                    label: 'Contact Number',
                    icon: Icons.phone_outlined,
                    keyboardType: TextInputType.phone,
                    isPublic: _isContactPublic,
                    onTogglePrivacy: () =>
                        setState(() => _isContactPublic = !_isContactPublic),
                  ),
                  const SizedBox(height: 16),
                  _ProfileField(
                    controller: _programController,
                    label: 'Program',
                    icon: Icons.school_outlined,
                    isPublic: _isProgramPublic,
                    onTogglePrivacy: () =>
                        setState(() => _isProgramPublic = !_isProgramPublic),
                  ),
                  const SizedBox(height: 16),
                  _ProfileField(
                    controller: _yearLevelController,
                    label: 'Year Level',
                    icon: Icons.calendar_today_outlined,
                    isPublic: _isYearLevelPublic,
                    onTogglePrivacy: () => setState(
                      () => _isYearLevelPublic = !_isYearLevelPublic,
                    ),
                  ),
                  const SizedBox(height: 16),
                  _ProfileField(
                    controller: _organizationController,
                    label: 'Organization',
                    icon: Icons.groups_outlined,
                    isPublic: _isOrganizationPublic,
                    onTogglePrivacy: () => setState(
                      () => _isOrganizationPublic = !_isOrganizationPublic,
                    ),
                  ),
                  const SizedBox(height: 16),
                  _ProfileField(
                    controller: _sectionController,
                    label: 'Section',
                    icon: Icons.badge_outlined,
                    isPublic: _isSectionPublic,
                    onTogglePrivacy: () =>
                        setState(() => _isSectionPublic = !_isSectionPublic),
                  ),
                  const SizedBox(height: 16),
                  _ProfileField(
                    controller: _bioController,
                    label: 'Bio',
                    icon: Icons.edit_note_outlined,
                    maxLines: 3,
                    isPublic: _isBioPublic,
                    onTogglePrivacy: () =>
                        setState(() => _isBioPublic = !_isBioPublic),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 22),
            SizedBox(
              height: 54,
              child: ElevatedButton(
                onPressed: _isSaving ? null : _saveChanges,
                style: ElevatedButton.styleFrom(
                  backgroundColor: kGold,
                  foregroundColor: kNavy,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                child: _isSaving
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: kNavy,
                        ),
                      )
                    : const Text(
                        'Save Changes',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAvatarContent(String profilePicturePath) {
    if (_selectedImage != null) {
      return Image.file(File(_selectedImage!.path), fit: BoxFit.cover);
    }

    final resolvedUrl = _resolvePublicImageUrl(profilePicturePath);
    if (resolvedUrl.isNotEmpty) {
      return Image.network(
        resolvedUrl,
        fit: BoxFit.cover,
        errorBuilder: (_, _, _) => _buildAvatarFallback(),
        loadingBuilder: (context, child, loadingProgress) {
          if (loadingProgress == null) {
            return child;
          }

          return const Center(
            child: SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2.2, color: kNavy),
            ),
          );
        },
      );
    }

    return _buildAvatarFallback();
  }

  Widget _buildAvatarFallback() {
    return const ColoredBox(
      color: Color(0xFFF7F8FD),
      child: Center(child: Icon(Icons.person_rounded, color: kNavy, size: 44)),
    );
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

  bool _privacySetting(
    Map<String, dynamic>? user,
    String camelKey,
    String snakeKey, {
    bool fallback = true,
  }) {
    final value = user?[camelKey] ?? user?[snakeKey];
    if (value is bool) {
      return value;
    }

    if (value is num) {
      return value != 0;
    }

    final normalized = value?.toString().trim().toLowerCase();
    switch (normalized) {
      case '1':
      case 'true':
      case 'yes':
      case 'on':
        return true;
      case '0':
      case 'false':
      case 'no':
      case 'off':
        return false;
      default:
        return fallback;
    }
  }
}

class _ProfileField extends StatelessWidget {
  final TextEditingController controller;
  final String label;
  final IconData icon;
  final TextInputType keyboardType;
  final int maxLines;
  final bool isPublic;
  final VoidCallback onTogglePrivacy;

  const _ProfileField({
    required this.controller,
    required this.label,
    required this.icon,
    required this.isPublic,
    required this.onTogglePrivacy,
    this.keyboardType = TextInputType.text,
    this.maxLines = 1,
  });

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      style: const TextStyle(color: kNavy, fontSize: 14),
      decoration: InputDecoration(
        labelText: label,
        labelStyle: const TextStyle(color: kNavy, fontWeight: FontWeight.w600),
        alignLabelWithHint: maxLines > 1,
        prefixIcon: Icon(icon, color: kNavy),
        suffixIcon: IconButton(
          onPressed: onTogglePrivacy,
          tooltip: isPublic ? '$label is public' : '$label is private',
          icon: Icon(
            isPublic ? Icons.public : Icons.lock,
            color: isPublic ? Colors.green : Colors.grey,
          ),
        ),
        filled: true,
        fillColor: const Color(0xFFF7F8FD),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(18),
          borderSide: const BorderSide(color: kGold, width: 1.5),
        ),
      ),
    );
  }
}
