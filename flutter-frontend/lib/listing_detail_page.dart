import 'package:flutter/material.dart';

import 'inquiry_service.dart';
import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'favorite_service.dart';
import 'listing_model_page.dart';
import 'listing_service.dart';
import 'login_page.dart';
import 'profile_page.dart';
import 'user_public_profile_page.dart';

const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

class ListingDetailPage extends StatefulWidget {
  const ListingDetailPage({super.key, required this.listing});

  final Listing listing;

  @override
  State<ListingDetailPage> createState() => _ListingDetailPageState();
}

class _ListingDetailPageState extends State<ListingDetailPage> {
  final ApiClient _apiClient = ApiClient();

  late Listing _listing;
  List<ListingImageAsset> _detailImages = <ListingImageAsset>[];
  bool _isFavorite = false;
  bool _isLoadingFavoriteState = true;
  bool _isUpdatingFavorite = false;
  bool _isLoadingDetail = true;
  bool _isSendingInquiry = false;
  bool _isListingUnavailable = false;
  String? _detailErrorMessage;
  int _activeImageIndex = 0;

  @override
  void initState() {
    super.initState();
    _listing = widget.listing;
    _loadFavoriteState();
    _loadListingDetail();
  }

  Future<void> _loadFavoriteState() async {
    if (!AuthService().hasSession) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isFavorite = false;
        _isLoadingFavoriteState = false;
      });
      return;
    }

    final error = await FavoritesService().ensureLoaded();

    if (!mounted) {
      return;
    }

    setState(() {
      _isFavorite =
          error == null && FavoritesService().isFavorite(widget.listing.id);
      _isLoadingFavoriteState = false;
    });
  }

  Future<void> _loadListingDetail() async {
    setState(() {
      _isLoadingDetail = true;
      _detailErrorMessage = null;
    });

    try {
      final detail = await ListingService().fetchListingDetail(
        widget.listing.id,
        fallback: widget.listing,
      );

      if (!mounted) {
        return;
      }

      setState(() {
        _listing = detail.listing;
        _detailImages = detail.images;
        _activeImageIndex = 0;
        _isLoadingDetail = false;
        _isListingUnavailable = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoadingDetail = false;
        _detailErrorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
        _isListingUnavailable = _apiClient.isNotFoundError(error);
      });
    }
  }

  Future<void> _toggleFavorite() async {
    if (!AuthService().hasSession) {
      await _openLoginPage();
      return;
    }

    if (_isUpdatingFavorite) {
      return;
    }

    final wasAlreadyFavorite = _isFavorite;

    setState(() {
      _isUpdatingFavorite = true;
    });

    final error = wasAlreadyFavorite
        ? await FavoritesService().removeFavorite(_listing.id)
        : await FavoritesService().addFavorite(_listing);

    if (!mounted) {
      return;
    }

    setState(() {
      _isUpdatingFavorite = false;
      if (error == null) {
        _isFavorite = !wasAlreadyFavorite;
      }
    });

    if (error != null) {
      if (!AuthService().hasSession) {
        await _openLoginPage();
        return;
      }

      _showSnackBar(error);
      return;
    }

    _showSnackBar(
      wasAlreadyFavorite ? 'Removed from favorites' : 'Added to favorites',
    );
  }

  Future<String?> _submitInquiry(
    String message,
    String preferredContactMethod,
  ) async {
    setState(() {
      _isSendingInquiry = true;
    });

    try {
      await InquiryService().sendInquiry(
        listingId: _listing.id,
        message: message,
        preferredContactMethod: preferredContactMethod,
        listing: _listing,
      );
      return null;
    } catch (error) {
      final sessionExpired = await AuthService().clearSessionIfUnauthorized(
        error,
      );
      if (sessionExpired) {
        if (mounted && Navigator.of(context).canPop()) {
          Navigator.of(context).pop(false);
        }
        if (mounted) {
          await _openLoginPage();
        }
        return null;
      }

      return error is FormatException
          ? error.message
          : _apiClient.mapError(error, maxMessages: 2, includeFieldNames: true);
    } finally {
      if (mounted) {
        setState(() {
          _isSendingInquiry = false;
        });
      }
    }
  }

  Future<void> _openInquiryComposer() async {
    if (!AuthService().hasSession) {
      await _openLoginPage();
      return;
    }

    final submitted = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) {
        return _InquiryComposerSheet(
          listingTitle: _listing.title,
          isSubmitting: _isSendingInquiry,
          onSubmit: _submitInquiry,
        );
      },
    );

    if (!mounted || submitted != true) {
      return;
    }

    _showSnackBar('Inquiry sent!');
  }

  Future<void> _openLoginPage() async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
    );
    if (mounted) {
      _loadFavoriteState();
    }
  }

  Future<void> _openSellerProfile() async {
    final sellerUserId = _listing.userId;
    if (sellerUserId <= 0) {
      _showSnackBar('Seller profile is unavailable right now.');
      return;
    }

    final currentUserId = _currentUserId();
    if (currentUserId != null && currentUserId == sellerUserId) {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const ProfilePage()),
      );
      return;
    }

    await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UserPublicProfilePage(
          userId: sellerUserId,
          initialName: _listing.seller,
          initialAvatar: _listing.sellerAvatar,
        ),
      ),
    );
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: kNavy,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        duration: const Duration(seconds: 2),
      ),
    );
  }

  int? _currentUserId() {
    final rawId = AuthService().currentUser?['id'];
    if (rawId is int) {
      return rawId;
    }

    return int.tryParse(rawId?.toString() ?? '');
  }

  @override
  Widget build(BuildContext context) {
    final listing = _listing;
    final detailRows = <_DetailEntry>[
      _DetailEntry(label: 'Category', value: listing.category),
      _DetailEntry(label: 'Condition', value: listing.condition),
      if (listing.campusLocation.trim().isNotEmpty)
        _DetailEntry(label: 'Campus', value: listing.campusLocation),
      _DetailEntry(label: 'Seller', value: listing.seller),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: RefreshIndicator(
        color: kNavy,
        onRefresh: _loadListingDetail,
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(
            parent: BouncingScrollPhysics(),
          ),
          slivers: <Widget>[
            SliverAppBar(
              expandedHeight: 260,
              pinned: true,
              backgroundColor: kNavy,
              leading: GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  margin: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: kWhite.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.arrow_back, color: kWhite, size: 20),
                ),
              ),
              actions: <Widget>[
                Container(
                  margin: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: kWhite.withValues(alpha: 0.2),
                    shape: BoxShape.circle,
                  ),
                  child: IconButton(
                    icon: _isLoadingFavoriteState || _isUpdatingFavorite
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(kWhite),
                            ),
                          )
                        : Icon(
                            _isFavorite
                                ? Icons.favorite
                                : Icons.favorite_border,
                            color: _isFavorite ? Colors.red.shade300 : kWhite,
                            size: 20,
                          ),
                    onPressed: _isLoadingFavoriteState || _isUpdatingFavorite
                        ? null
                        : _toggleFavorite,
                  ),
                ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                background: _ListingHeroGallery(
                  listing: listing,
                  images: _detailImages,
                  activeImageIndex: _activeImageIndex,
                  onPageChanged: (index) {
                    setState(() => _activeImageIndex = index);
                  },
                ),
              ),
            ),
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    if (_isLoadingDetail) ...<Widget>[
                      const LinearProgressIndicator(
                        color: kGold,
                        backgroundColor: Color(0xFFDCE4FF),
                      ),
                      const SizedBox(height: 16),
                    ],
                    if (_detailErrorMessage != null) ...<Widget>[
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFFFF8E1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFF5C518)),
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            const Text(
                              'Showing the available listing summary while full details are unavailable.',
                              style: TextStyle(
                                color: kNavy,
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _detailErrorMessage!,
                              style: TextStyle(
                                color: Colors.grey[700],
                                fontSize: 11,
                              ),
                            ),
                            if (!_isListingUnavailable) ...<Widget>[
                              const SizedBox(height: 10),
                              OutlinedButton(
                                onPressed: _loadListingDetail,
                                style: OutlinedButton.styleFrom(
                                  foregroundColor: kNavy,
                                ),
                                child: const Text('Retry'),
                              ),
                            ],
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Expanded(
                          child: Text(
                            listing.title,
                            style: const TextStyle(
                              color: kNavy,
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              height: 1.2,
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 6,
                          ),
                          decoration: BoxDecoration(
                            color: kNavy,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: Text(
                            listing.price,
                            style: const TextStyle(
                              color: kGold,
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: <Widget>[
                        _Tag(
                          label: listing.category,
                          bgColor: kGold,
                          textColor: kNavy,
                        ),
                        const SizedBox(width: 8),
                        _Tag(
                          label: listing.condition,
                          bgColor: _conditionBackgroundColor(listing.condition),
                          textColor: _conditionTextColor(listing.condition),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    Material(
                      color: Colors.transparent,
                      child: InkWell(
                        onTap: _openSellerProfile,
                        borderRadius: BorderRadius.circular(14),
                        child: Container(
                          padding: const EdgeInsets.all(14),
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
                          child: Row(
                            children: <Widget>[
                              CircleAvatar(
                                radius: 22,
                                backgroundColor: kGold,
                                child: Text(
                                  listing.sellerAvatar,
                                  style: const TextStyle(
                                    color: kNavy,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 18,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: <Widget>[
                                    Text(
                                      listing.seller,
                                      style: const TextStyle(
                                        color: kNavy,
                                        fontWeight: FontWeight.w700,
                                        fontSize: 14,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      'Tap to view seller profile',
                                      style: TextStyle(
                                        color: Colors.grey[500],
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              Row(
                                mainAxisSize: MainAxisSize.min,
                                children: <Widget>[
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 10,
                                      vertical: 6,
                                    ),
                                    decoration: BoxDecoration(
                                      color: const Color(0xFFF4F6FF),
                                      borderRadius: BorderRadius.circular(10),
                                    ),
                                    child: const Row(
                                      children: <Widget>[
                                        Icon(
                                          Icons.star_rounded,
                                          color: kGold,
                                          size: 14,
                                        ),
                                        SizedBox(width: 3),
                                        Text(
                                          '4.8',
                                          style: TextStyle(
                                            color: kNavy,
                                            fontWeight: FontWeight.w700,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const SizedBox(width: 6),
                                  Icon(
                                    Icons.chevron_right_rounded,
                                    color: Colors.grey[400],
                                    size: 20,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Description',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(14),
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
                      child: Text(
                        listing.description,
                        style: TextStyle(
                          color: Colors.grey[700],
                          fontSize: 13,
                          height: 1.6,
                        ),
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Item Details',
                      style: TextStyle(
                        color: kNavy,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
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
                        children: List<Widget>.generate(detailRows.length, (
                          index,
                        ) {
                          final detail = detailRows[index];
                          return _DetailRow(
                            label: detail.label,
                            value: detail.value,
                            isLast: index == detailRows.length - 1,
                          );
                        }),
                      ),
                    ),
                    const SizedBox(height: 24),
                    SizedBox(
                      width: double.infinity,
                      height: 50,
                      child: ElevatedButton.icon(
                        onPressed: _isSendingInquiry || _isListingUnavailable
                            ? null
                            : _openInquiryComposer,
                        icon: _isSendingInquiry
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                    kGold,
                                  ),
                                ),
                              )
                            : const Icon(Icons.send_rounded, size: 18),
                        label: const Text(
                          'Send Inquiry',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kNavy,
                          foregroundColor: kGold,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(14),
                          ),
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

  Color _conditionBackgroundColor(String condition) {
    switch (condition) {
      case 'New':
      case 'Brand New':
        return Colors.green.shade100;
      case 'Used':
      case 'Pre-owned':
        return Colors.blue.shade100;
      default:
        return Colors.orange.shade100;
    }
  }

  Color _conditionTextColor(String condition) {
    switch (condition) {
      case 'New':
      case 'Brand New':
        return Colors.green.shade700;
      case 'Used':
      case 'Pre-owned':
        return Colors.blue.shade700;
      default:
        return Colors.orange.shade700;
    }
  }
}

class _ListingHeroGallery extends StatelessWidget {
  const _ListingHeroGallery({
    required this.listing,
    required this.images,
    required this.activeImageIndex,
    required this.onPageChanged,
  });

  final Listing listing;
  final List<ListingImageAsset> images;
  final int activeImageIndex;
  final ValueChanged<int> onPageChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[kDarkNavy, listing.color],
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          if (images.isNotEmpty)
            PageView.builder(
              itemCount: images.length,
              onPageChanged: onPageChanged,
              itemBuilder: (context, index) {
                final image = images[index];
                return Image.network(
                  image.imageUrl,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) =>
                      _ListingFallbackArt(listing: listing),
                );
              },
            )
          else if (listing.imageFile != null)
            Image.file(
              listing.imageFile!,
              fit: BoxFit.cover,
              width: double.infinity,
              height: double.infinity,
            )
          else
            _ListingFallbackArt(listing: listing),
          if (images.length > 1)
            Positioned(
              left: 0,
              right: 0,
              bottom: 18,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: List<Widget>.generate(images.length, (index) {
                  final isActive = index == activeImageIndex;
                  return AnimatedContainer(
                    duration: const Duration(milliseconds: 180),
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: isActive ? 18 : 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: isActive ? kWhite : kWhite.withValues(alpha: 0.45),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  );
                }),
              ),
            ),
        ],
      ),
    );
  }
}

class _ListingFallbackArt extends StatelessWidget {
  const _ListingFallbackArt({required this.listing});

  final Listing listing;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Icon(
        listing.icon,
        size: 100,
        color: kNavy.withValues(alpha: 0.25),
      ),
    );
  }
}

typedef _InquirySubmitCallback =
    Future<String?> Function(String message, String preferredContactMethod);

class _InquiryComposerSheet extends StatefulWidget {
  const _InquiryComposerSheet({
    required this.listingTitle,
    required this.isSubmitting,
    required this.onSubmit,
  });

  final String listingTitle;
  final bool isSubmitting;
  final _InquirySubmitCallback onSubmit;

  @override
  State<_InquiryComposerSheet> createState() => _InquiryComposerSheetState();
}

class _InquiryComposerSheetState extends State<_InquiryComposerSheet> {
  final TextEditingController _messageController = TextEditingController(
    text: 'Is this still available?',
  );

  String _selectedContactMethod = 'in_app';
  String? _errorMessage;
  bool _isSubmitting = false;

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<void> _handleSubmit() async {
    final message = _messageController.text.trim();
    if (message.isEmpty) {
      setState(() => _errorMessage = 'Please enter a message.');
      return;
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    final error = await widget.onSubmit(message, _selectedContactMethod);
    if (!mounted) {
      return;
    }

    if (error != null) {
      setState(() {
        _isSubmitting = false;
        _errorMessage = error;
      });
      return;
    }

    Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Padding(
      padding: EdgeInsets.fromLTRB(12, 12, 12, bottomInset + 12),
      child: Material(
        color: Colors.transparent,
        child: Container(
          padding: const EdgeInsets.all(20),
          decoration: BoxDecoration(
            color: kWhite,
            borderRadius: BorderRadius.circular(22),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  const Expanded(
                    child: Text(
                      'Send Inquiry',
                      style: TextStyle(
                        color: kNavy,
                        fontSize: 18,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _isSubmitting
                        ? null
                        : () => Navigator.pop(context),
                    icon: const Icon(Icons.close, color: kNavy),
                  ),
                ],
              ),
              Text(
                widget.listingTitle,
                style: TextStyle(
                  color: Colors.grey[600],
                  fontSize: 12,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Preferred Contact',
                style: TextStyle(
                  color: kNavy,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: <Widget>[
                  _ContactChoice(
                    label: 'In-app',
                    value: 'in_app',
                    selectedValue: _selectedContactMethod,
                    onSelected: (value) {
                      setState(() => _selectedContactMethod = value);
                    },
                  ),
                  _ContactChoice(
                    label: 'Email',
                    value: 'email',
                    selectedValue: _selectedContactMethod,
                    onSelected: (value) {
                      setState(() => _selectedContactMethod = value);
                    },
                  ),
                  _ContactChoice(
                    label: 'Phone',
                    value: 'phone',
                    selectedValue: _selectedContactMethod,
                    onSelected: (value) {
                      setState(() => _selectedContactMethod = value);
                    },
                  ),
                ],
              ),
              const SizedBox(height: 16),
              const Text(
                'Message',
                style: TextStyle(
                  color: kNavy,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 8),
              Container(
                decoration: BoxDecoration(
                  color: const Color(0xFFF4F6FF),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: TextField(
                  controller: _messageController,
                  maxLines: 5,
                  decoration: const InputDecoration(
                    hintText:
                        'Ask about availability, meetup, or condition details.',
                    border: InputBorder.none,
                    contentPadding: EdgeInsets.all(14),
                  ),
                ),
              ),
              if (_errorMessage != null) ...<Widget>[
                const SizedBox(height: 12),
                Text(
                  _errorMessage!,
                  style: const TextStyle(
                    color: Colors.red,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
              const SizedBox(height: 18),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  onPressed: _isSubmitting || widget.isSubmitting
                      ? null
                      : _handleSubmit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: kNavy,
                    foregroundColor: kGold,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(14),
                    ),
                    elevation: 0,
                  ),
                  child: _isSubmitting || widget.isSubmitting
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2.2,
                            valueColor: AlwaysStoppedAnimation<Color>(kGold),
                          ),
                        )
                      : const Text(
                          'Send Inquiry',
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w700,
                          ),
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

class _ContactChoice extends StatelessWidget {
  const _ContactChoice({
    required this.label,
    required this.value,
    required this.selectedValue,
    required this.onSelected,
  });

  final String label;
  final String value;
  final String selectedValue;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final isSelected = value == selectedValue;

    return GestureDetector(
      onTap: () => onSelected(value),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
        decoration: BoxDecoration(
          color: isSelected ? kNavy : const Color(0xFFF4F6FF),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? kGold : kNavy,
            fontWeight: FontWeight.w700,
            fontSize: 12,
          ),
        ),
      ),
    );
  }
}

class _Tag extends StatelessWidget {
  const _Tag({
    required this.label,
    required this.bgColor,
    required this.textColor,
  });

  final String label;
  final Color bgColor;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(10),
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

class _DetailEntry {
  const _DetailEntry({required this.label, required this.value});

  final String label;
  final String value;
}

class _DetailRow extends StatelessWidget {
  const _DetailRow({
    required this.label,
    required this.value,
    this.isLast = false,
  });

  final String label;
  final String value;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 13),
      decoration: BoxDecoration(
        border: isLast
            ? null
            : Border(bottom: BorderSide(color: Colors.grey.shade100)),
      ),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Text(label, style: TextStyle(color: Colors.grey[500], fontSize: 13)),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(
                color: kNavy,
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
