import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_service.dart';
import 'login_page.dart';

const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

class AddListingPage extends StatefulWidget {
  const AddListingPage({super.key});

  @override
  State<AddListingPage> createState() => _AddListingPageState();
}

class _AddListingPageState extends State<AddListingPage> {
  final _titleController = TextEditingController();
  final _priceController = TextEditingController();
  final _descriptionController = TextEditingController();
  final ApiClient _apiClient = ApiClient();
  final ImagePicker _picker = ImagePicker();

  String? _selectedCategory;
  String? _selectedCondition;
  List<File> _selectedImages = <File>[];
  bool _isLoading = false;
  String? _errorMessage;
  String? _submissionStatusMessage;

  final List<String> _categories = <String>[
    'Gadgets',
    'Lab Tools',
    'Sports Equipment',
    'School Supplies',
    'Clothing',
    'Electronics',
    'Books',
    'Uniforms',
    'Food',
    'Drinks',
    'Accessories',
    'Others',
  ];

  final List<String> _conditions = <String>['Brand New', 'Pre-owned'];

  @override
  void dispose() {
    _titleController.dispose();
    _priceController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _pickImages() async {
    final images = await _picker.pickMultiImage(
      imageQuality: 80,
      maxWidth: 800,
    );

    if (images.isEmpty) {
      return;
    }

    final selectedFiles = images
        .take(10)
        .map((image) => File(image.path))
        .toList();

    setState(() {
      _selectedImages = selectedFiles;
      _errorMessage = images.length > 10
          ? 'Only the first 10 images will be uploaded.'
          : null;
    });
  }

  Future<void> _submitListing() async {
    if (!AuthService().hasSession) {
      setState(() => _errorMessage = 'Please log in to post a listing.');
      return;
    }
    if (_titleController.text.trim().isEmpty) {
      setState(() => _errorMessage = 'Please enter a title');
      return;
    }
    if (_priceController.text.trim().isEmpty) {
      setState(() => _errorMessage = 'Please enter a price');
      return;
    }
    if (double.tryParse(_normalizedPriceInput(_priceController.text)) == null) {
      setState(() => _errorMessage = 'Please enter a valid price');
      return;
    }
    if (_selectedCategory == null) {
      setState(() => _errorMessage = 'Please select a category');
      return;
    }
    if (_selectedCondition == null) {
      setState(() => _errorMessage = 'Please select a condition');
      return;
    }
    if (_descriptionController.text.trim().isEmpty) {
      setState(() => _errorMessage = 'Please enter a description');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _submissionStatusMessage = 'Creating listing...';
    });

    try {
      final result = await ListingService().createListing(
        title: _titleController.text.trim(),
        price: _priceController.text.trim(),
        category: _selectedCategory!,
        condition: _selectedCondition!,
        description: _descriptionController.text.trim(),
        imageFiles: _selectedImages,
        onProgress: (message) {
          if (!mounted) {
            return;
          }

          setState(() => _submissionStatusMessage = message);
        },
      );

      if (!mounted) {
        return;
      }

      await _showSubmissionResult(result);
      if (!mounted) {
        return;
      }
      Navigator.pop(context, result.listing);
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );
      if (!mounted) {
        return;
      }

      if (sessionExpired) {
        await _navigateToLogin();
        return;
      }

      setState(() {
        _errorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(
                error,
                maxMessages: 4,
                includeFieldNames: true,
              );
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
          _submissionStatusMessage = null;
        });
      }
    }
  }

  Future<void> _showSubmissionResult(ListingCreateResult result) async {
    if (!result.hasImageUploadErrors) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Listing posted successfully!'),
          backgroundColor: Colors.green,
          duration: Duration(seconds: 2),
        ),
      );
      return;
    }

    final uploadedCount = result.uploadedImages.length;
    final failedCount = result.imageUploadErrors.length;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Listing Posted With Upload Issues',
          style: TextStyle(
            color: kNavy,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
        ),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(
                'Your listing was created successfully. '
                '$uploadedCount image${uploadedCount == 1 ? '' : 's'} uploaded, '
                '$failedCount failed.',
                style: TextStyle(color: Colors.grey[700], fontSize: 13),
              ),
              const SizedBox(height: 12),
              ...result.imageUploadErrors.map(
                (error) => Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    '- $error',
                    style: TextStyle(color: Colors.grey[700], fontSize: 12),
                  ),
                ),
              ),
            ],
          ),
        ),
        actions: <Widget>[
          ElevatedButton(
            onPressed: () => Navigator.pop(dialogContext),
            style: ElevatedButton.styleFrom(
              backgroundColor: kNavy,
              foregroundColor: kWhite,
              elevation: 0,
            ),
            child: const Text('Continue'),
          ),
        ],
      ),
    );
  }

  Future<void> _navigateToLogin() async {
    await Navigator.pushReplacement(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
    );
  }

  String _normalizedPriceInput(String value) {
    return value
        .trim()
        .replaceAll('PHP', '')
        .replaceAll('Php', '')
        .replaceAll('php', '')
        .replaceAll('P', '')
        .replaceAll('p', '')
        .replaceAll('\u20B1', '')
        .replaceAll(',', '')
        .trim();
  }

  @override
  Widget build(BuildContext context) {
    final previewImage = _selectedImages.isNotEmpty
        ? _selectedImages.first
        : null;

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
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
              child: Row(
                children: <Widget>[
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: kWhite.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.arrow_back,
                        color: kWhite,
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
                          'Post a Listing',
                          style: TextStyle(
                            color: kWhite,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'Sell your items to LNU students',
                          style: TextStyle(color: kGold, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Expanded(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    _buildLabel('Product Photo'),
                    const SizedBox(height: 8),
                    GestureDetector(
                      onTap: _pickImages,
                      child: Container(
                        width: double.infinity,
                        height: 180,
                        decoration: BoxDecoration(
                          color: kWhite,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: previewImage != null
                                ? kNavy
                                : Colors.grey.shade300,
                            width: previewImage != null ? 2 : 1,
                          ),
                          boxShadow: <BoxShadow>[
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: previewImage != null
                            ? Stack(
                                children: <Widget>[
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(16),
                                    child: Image.file(
                                      previewImage,
                                      width: double.infinity,
                                      height: double.infinity,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                  if (_selectedImages.length > 1)
                                    Positioned(
                                      left: 12,
                                      bottom: 12,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 6,
                                        ),
                                        decoration: BoxDecoration(
                                          color: kDarkNavy.withValues(
                                            alpha: 0.82,
                                          ),
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                        ),
                                        child: Text(
                                          '${_selectedImages.length} images selected',
                                          style: const TextStyle(
                                            color: kWhite,
                                            fontSize: 11,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ),
                                    ),
                                  Positioned(
                                    top: 8,
                                    right: 8,
                                    child: GestureDetector(
                                      onTap: () {
                                        setState(
                                          () => _selectedImages = <File>[],
                                        );
                                      },
                                      child: Container(
                                        padding: const EdgeInsets.all(6),
                                        decoration: const BoxDecoration(
                                          color: Colors.red,
                                          shape: BoxShape.circle,
                                        ),
                                        child: const Icon(
                                          Icons.close,
                                          color: kWhite,
                                          size: 16,
                                        ),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    bottom: 8,
                                    right: 8,
                                    child: GestureDetector(
                                      onTap: _pickImages,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 6,
                                        ),
                                        decoration: BoxDecoration(
                                          color: kNavy.withValues(alpha: 0.8),
                                          borderRadius: BorderRadius.circular(
                                            10,
                                          ),
                                        ),
                                        child: const Row(
                                          children: <Widget>[
                                            Icon(
                                              Icons.edit,
                                              color: kWhite,
                                              size: 12,
                                            ),
                                            SizedBox(width: 4),
                                            Text(
                                              'Change',
                                              style: TextStyle(
                                                color: kWhite,
                                                fontSize: 11,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              )
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: <Widget>[
                                  Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: const BoxDecoration(
                                      color: Color(0xFFF4F6FF),
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(
                                      Icons.add_photo_alternate_outlined,
                                      color: kNavy,
                                      size: 32,
                                    ),
                                  ),
                                  const SizedBox(height: 10),
                                  const Text(
                                    'Tap to add photos',
                                    style: TextStyle(
                                      color: kNavy,
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Pick one or more from gallery',
                                    style: TextStyle(
                                      color: Colors.grey[400],
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    _buildLabel('Title'),
                    const SizedBox(height: 8),
                    _buildTextField(
                      controller: _titleController,
                      hint: 'e.g. Engineering Mathematics Book',
                      icon: Icons.title_rounded,
                    ),
                    const SizedBox(height: 16),
                    _buildLabel('Price (P)'),
                    const SizedBox(height: 8),
                    _buildTextField(
                      controller: _priceController,
                      hint: 'e.g. 150',
                      icon: Icons.payments_outlined,
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                    ),
                    const SizedBox(height: 16),
                    _buildLabel('Category'),
                    const SizedBox(height: 8),
                    _buildDropdown(
                      value: _selectedCategory,
                      hint: 'Select a category',
                      icon: Icons.category_outlined,
                      items: _categories,
                      onChanged: (value) {
                        setState(() => _selectedCategory = value);
                      },
                    ),
                    const SizedBox(height: 16),
                    _buildLabel('Condition'),
                    const SizedBox(height: 8),
                    Row(
                      children: _conditions.map((condition) {
                        final isSelected = _selectedCondition == condition;
                        return Expanded(
                          child: GestureDetector(
                            onTap: () {
                              setState(() => _selectedCondition = condition);
                            },
                            child: Container(
                              margin: EdgeInsets.only(
                                right: condition != _conditions.last ? 8 : 0,
                              ),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              decoration: BoxDecoration(
                                color: isSelected ? kNavy : kWhite,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: isSelected
                                      ? kNavy
                                      : Colors.grey.shade300,
                                ),
                                boxShadow: <BoxShadow>[
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.04),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Column(
                                children: <Widget>[
                                  Icon(
                                    condition == 'Brand New'
                                        ? Icons.fiber_new_rounded
                                        : Icons.recycling_rounded,
                                    color: isSelected
                                        ? kGold
                                        : Colors.grey[400],
                                    size: 20,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    condition,
                                    style: TextStyle(
                                      color: isSelected
                                          ? kWhite
                                          : Colors.grey[600],
                                      fontSize: 12,
                                      fontWeight: isSelected
                                          ? FontWeight.w700
                                          : FontWeight.w500,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ),
                        );
                      }).toList(),
                    ),
                    const SizedBox(height: 16),
                    _buildLabel('Description'),
                    const SizedBox(height: 8),
                    Container(
                      decoration: BoxDecoration(
                        color: kWhite,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: <BoxShadow>[
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.05),
                            blurRadius: 8,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: TextField(
                        controller: _descriptionController,
                        maxLines: 4,
                        style: const TextStyle(fontSize: 14, color: kNavy),
                        decoration: InputDecoration(
                          hintText:
                              'Describe your item - condition details, reason for selling, etc.',
                          hintStyle: TextStyle(
                            color: Colors.grey[400],
                            fontSize: 13,
                          ),
                          prefixIcon: const Padding(
                            padding: EdgeInsets.only(bottom: 60),
                            child: Icon(
                              Icons.description_outlined,
                              color: kNavy,
                              size: 20,
                            ),
                          ),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.all(14),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    if (_errorMessage != null) ...<Widget>[
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 8,
                        ),
                        decoration: BoxDecoration(
                          color: Colors.red[50],
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.red[200]!),
                        ),
                        child: Row(
                          children: <Widget>[
                            Icon(
                              Icons.error_outline,
                              color: Colors.red[400],
                              size: 16,
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: TextStyle(
                                  color: Colors.red[600],
                                  fontSize: 12,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (_submissionStatusMessage != null) ...<Widget>[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 10,
                        ),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E1),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: const Color(0xFFF5C518)),
                        ),
                        child: Row(
                          children: <Widget>[
                            const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(
                                  kNavy,
                                ),
                              ),
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                _submissionStatusMessage!,
                                style: const TextStyle(
                                  color: kNavy,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        onPressed: _isLoading ? null : _submitListing,
                        icon: _isLoading
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  color: kNavy,
                                  strokeWidth: 2.5,
                                ),
                              )
                            : const Icon(Icons.upload_rounded, size: 20),
                        label: Text(
                          _isLoading ? 'Posting...' : 'Post Listing',
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kGold,
                          foregroundColor: kNavy,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
                          elevation: 0,
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildLabel(String text) {
    return Text(
      text,
      style: const TextStyle(
        color: kNavy,
        fontSize: 13,
        fontWeight: FontWeight.w600,
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String hint,
    required IconData icon,
    TextInputType keyboardType = TextInputType.text,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(12),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: TextField(
        controller: controller,
        keyboardType: keyboardType,
        style: const TextStyle(fontSize: 14, color: kNavy),
        decoration: InputDecoration(
          hintText: hint,
          hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
          prefixIcon: Icon(icon, color: kNavy, size: 20),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(vertical: 14),
        ),
      ),
    );
  }

  Widget _buildDropdown({
    required String? value,
    required String hint,
    required IconData icon,
    required List<String> items,
    required void Function(String?) onChanged,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(12),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          hint: Row(
            children: <Widget>[
              Icon(icon, color: kNavy, size: 20),
              const SizedBox(width: 12),
              Text(
                hint,
                style: TextStyle(color: Colors.grey[400], fontSize: 13),
              ),
            ],
          ),
          icon: Icon(Icons.keyboard_arrow_down, color: Colors.grey[400]),
          items: items
              .map(
                (item) => DropdownMenuItem<String>(
                  value: item,
                  child: Row(
                    children: <Widget>[
                      Icon(icon, color: kNavy, size: 20),
                      const SizedBox(width: 12),
                      Text(
                        item,
                        style: const TextStyle(color: kNavy, fontSize: 13),
                      ),
                    ],
                  ),
                ),
              )
              .toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }
}
