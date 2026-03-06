import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'listing_service.dart';

// ─── Color Palette ───────────────────────────────────────────────────────────
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

  String? _selectedCategory;
  String? _selectedCondition;
  File? _selectedImage;
  bool _isLoading = false;
  String? _errorMessage;

  final List<String> _categories = [
    'Gadgets', 'Lab Tools', 'Sports Equipment',
    'School Supplies', 'Services', 'Clothing', 'Electronics',
  ];

  final List<String> _conditions = ['New', 'Good', 'Fair'];

  final ImagePicker _picker = ImagePicker();

  Future<void> _pickImage() async {
    final XFile? image = await _picker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 80,
      maxWidth: 800,
    );
    if (image != null) {
      setState(() => _selectedImage = File(image.path));
    }
  }

  Future<void> _submitListing() async {
    // Validate
    if (_titleController.text.trim().isEmpty) {
      setState(() => _errorMessage = 'Please enter a title');
      return;
    }
    if (_priceController.text.trim().isEmpty) {
      setState(() => _errorMessage = 'Please enter a price');
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
    });

    // Save to app memory via ListingService
    ListingService().addListing(
      title: _titleController.text.trim(),
      price: '₱${double.parse(_priceController.text).toStringAsFixed(2)}',
      category: _selectedCategory!,
      condition: _selectedCondition!,
      description: _descriptionController.text.trim(),
      imageFile: _selectedImage,
    );

    setState(() => _isLoading = false);

    if (!mounted) return;

    // Show success and go back
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('✅ Listing posted successfully!'),
        backgroundColor: Colors.green,
        duration: Duration(seconds: 2),
      ),
    );
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ──────────────────────────────────────────────────
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
              child: Row(
                children: [
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      padding: const EdgeInsets.all(8),
                      decoration: BoxDecoration(
                        color: kWhite.withValues(alpha: 0.15),
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(Icons.arrow_back, color: kWhite, size: 20),
                    ),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
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

            // ── Form ────────────────────────────────────────────────────
            Expanded(
              child: SingleChildScrollView(
                physics: const BouncingScrollPhysics(),
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [

                    // ── Image Picker ─────────────────────────────────────
                    _buildLabel('Product Photo'),
                    const SizedBox(height: 8),
                    GestureDetector(
                      onTap: _pickImage,
                      child: Container(
                        width: double.infinity,
                        height: 180,
                        decoration: BoxDecoration(
                          color: kWhite,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: _selectedImage != null ? kNavy : Colors.grey.shade300,
                            width: _selectedImage != null ? 2 : 1,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: Colors.black.withValues(alpha: 0.05),
                              blurRadius: 8,
                              offset: const Offset(0, 2),
                            ),
                          ],
                        ),
                        child: _selectedImage != null
                            ? Stack(
                                children: [
                                  ClipRRect(
                                    borderRadius: BorderRadius.circular(16),
                                    child: Image.file(
                                      _selectedImage!,
                                      width: double.infinity,
                                      height: double.infinity,
                                      fit: BoxFit.cover,
                                    ),
                                  ),
                                  Positioned(
                                    top: 8,
                                    right: 8,
                                    child: GestureDetector(
                                      onTap: () => setState(() => _selectedImage = null),
                                      child: Container(
                                        padding: const EdgeInsets.all(6),
                                        decoration: const BoxDecoration(
                                          color: Colors.red,
                                          shape: BoxShape.circle,
                                        ),
                                        child: const Icon(Icons.close, color: kWhite, size: 16),
                                      ),
                                    ),
                                  ),
                                  Positioned(
                                    bottom: 8,
                                    right: 8,
                                    child: GestureDetector(
                                      onTap: _pickImage,
                                      child: Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                        decoration: BoxDecoration(
                                          color: kNavy.withValues(alpha: 0.8),
                                          borderRadius: BorderRadius.circular(10),
                                        ),
                                        child: const Row(
                                          children: [
                                            Icon(Icons.edit, color: kWhite, size: 12),
                                            SizedBox(width: 4),
                                            Text('Change', style: TextStyle(color: kWhite, fontSize: 11)),
                                          ],
                                        ),
                                      ),
                                    ),
                                  ),
                                ],
                              )
                            : Column(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  Container(
                                    padding: const EdgeInsets.all(16),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF4F6FF),
                                      shape: BoxShape.circle,
                                    ),
                                    child: const Icon(Icons.add_photo_alternate_outlined, color: kNavy, size: 32),
                                  ),
                                  const SizedBox(height: 10),
                                  const Text(
                                    'Tap to add a photo',
                                    style: TextStyle(
                                      color: kNavy,
                                      fontWeight: FontWeight.w600,
                                      fontSize: 14,
                                    ),
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    'Pick from gallery',
                                    style: TextStyle(color: Colors.grey[400], fontSize: 12),
                                  ),
                                ],
                              ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // ── Title ────────────────────────────────────────────
                    _buildLabel('Title'),
                    const SizedBox(height: 8),
                    _buildTextField(
                      controller: _titleController,
                      hint: 'e.g. Engineering Mathematics Book',
                      icon: Icons.title_rounded,
                    ),
                    const SizedBox(height: 16),

                    // ── Price ────────────────────────────────────────────
                    _buildLabel('Price (₱)'),
                    const SizedBox(height: 8),
                    _buildTextField(
                      controller: _priceController,
                      hint: 'e.g. 150',
                      icon: Icons.payments_outlined,
                      keyboardType: TextInputType.number,
                    ),
                    const SizedBox(height: 16),

                    // ── Category ─────────────────────────────────────────
                    _buildLabel('Category'),
                    const SizedBox(height: 8),
                    _buildDropdown(
                      value: _selectedCategory,
                      hint: 'Select a category',
                      icon: Icons.category_outlined,
                      items: _categories,
                      onChanged: (val) => setState(() => _selectedCategory = val),
                    ),
                    const SizedBox(height: 16),

                    // ── Condition ─────────────────────────────────────────
                    _buildLabel('Condition'),
                    const SizedBox(height: 8),
                    Row(
                      children: _conditions.map((cond) {
                        final isSelected = _selectedCondition == cond;
                        return Expanded(
                          child: GestureDetector(
                            onTap: () => setState(() => _selectedCondition = cond),
                            child: Container(
                              margin: EdgeInsets.only(
                                right: cond != _conditions.last ? 8 : 0,
                              ),
                              padding: const EdgeInsets.symmetric(vertical: 12),
                              decoration: BoxDecoration(
                                color: isSelected ? kNavy : kWhite,
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(
                                  color: isSelected ? kNavy : Colors.grey.shade300,
                                ),
                                boxShadow: [
                                  BoxShadow(
                                    color: Colors.black.withValues(alpha: 0.04),
                                    blurRadius: 6,
                                    offset: const Offset(0, 2),
                                  ),
                                ],
                              ),
                              child: Column(
                                children: [
                                  Icon(
                                    cond == 'New' ? Icons.fiber_new_rounded
                                        : cond == 'Good' ? Icons.thumb_up_outlined
                                        : Icons.info_outline,
                                    color: isSelected ? kGold : Colors.grey[400],
                                    size: 20,
                                  ),
                                  const SizedBox(height: 4),
                                  Text(
                                    cond,
                                    style: TextStyle(
                                      color: isSelected ? kWhite : Colors.grey[600],
                                      fontSize: 12,
                                      fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
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

                    // ── Description ───────────────────────────────────────
                    _buildLabel('Description'),
                    const SizedBox(height: 8),
                    Container(
                      decoration: BoxDecoration(
                        color: kWhite,
                        borderRadius: BorderRadius.circular(12),
                        boxShadow: [
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
                          hintText: 'Describe your item — condition details, reason for selling, etc.',
                          hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
                          prefixIcon: const Padding(
                            padding: EdgeInsets.only(bottom: 60),
                            child: Icon(Icons.description_outlined, color: kNavy, size: 20),
                          ),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.all(14),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),

                    // ── Error Message ─────────────────────────────────────
                    if (_errorMessage != null) ...[
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        decoration: BoxDecoration(
                          color: Colors.red[50],
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: Colors.red[200]!),
                        ),
                        child: Row(
                          children: [
                            Icon(Icons.error_outline, color: Colors.red[400], size: 16),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                _errorMessage!,
                                style: TextStyle(color: Colors.red[600], fontSize: 12),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],

                    // ── Submit Button ─────────────────────────────────────
                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: ElevatedButton.icon(
                        onPressed: _isLoading ? null : _submitListing,
                        icon: _isLoading
                            ? const SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(color: kNavy, strokeWidth: 2.5),
                              )
                            : const Icon(Icons.upload_rounded, size: 20),
                        label: Text(
                          _isLoading ? 'Posting...' : 'Post Listing',
                          style: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
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

  Widget _buildLabel(String text) => Text(
    text,
    style: const TextStyle(color: kNavy, fontSize: 13, fontWeight: FontWeight.w600),
  );

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
        boxShadow: [
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
        boxShadow: [
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
            children: [
              Icon(icon, color: kNavy, size: 20),
              const SizedBox(width: 12),
              Text(hint, style: TextStyle(color: Colors.grey[400], fontSize: 13)),
            ],
          ),
          icon: Icon(Icons.keyboard_arrow_down, color: Colors.grey[400]),
          items: items.map((item) => DropdownMenuItem(
            value: item,
            child: Row(
              children: [
                Icon(icon, color: kNavy, size: 20),
                const SizedBox(width: 12),
                Text(item, style: const TextStyle(color: kNavy, fontSize: 13)),
              ],
            ),
          )).toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }
}