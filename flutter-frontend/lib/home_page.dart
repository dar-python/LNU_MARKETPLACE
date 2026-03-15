import 'package:flutter/material.dart';

import 'add_listing_page.dart';
import 'auth_service.dart';
import 'browse_page.dart';
import 'config/app_config.dart';
import 'core/network/api_client.dart';
import 'favorite_page.dart';
import 'inquiry_page.dart';
import 'listing_detail_page.dart';
import 'listing_model_page.dart';
import 'listing_service.dart';
import 'login_page.dart';
import 'my_listings_page.dart';
import 'profile_page.dart';

const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

class _HomePageState extends State<HomePage> {
  final TextEditingController _searchController = TextEditingController();
  final ListingService _listingService = ListingService();
  final ApiClient _apiClient = ApiClient();

  int _selectedIndex = 0;
  bool _isLoadingListings = true;
  bool _isLoadingMyListings = false;
  String? _listingsErrorMessage;
  String? _myListingsErrorMessage;
  List<Listing> _homeListings = <Listing>[];
  List<Listing> _myListings = <Listing>[];

  final List<_HomeCategory> _categories = const <_HomeCategory>[
    _HomeCategory(icon: Icons.menu_book, label: 'Books'),
    _HomeCategory(icon: Icons.checkroom, label: 'Uniforms'),
    _HomeCategory(icon: Icons.devices, label: 'Gadgets'),
    _HomeCategory(icon: Icons.science, label: 'Lab Tools'),
    _HomeCategory(icon: Icons.school, label: 'Tutoring'),
    _HomeCategory(icon: Icons.edit_document, label: 'Editing'),
    _HomeCategory(icon: Icons.design_services, label: 'Design'),
    _HomeCategory(icon: Icons.build, label: 'Repair'),
  ];

  List<Listing> get _featuredListings => _homeListings.take(4).toList();

  List<Listing> get _recentListings {
    final recentListings = _homeListings.skip(4).take(4).toList();
    if (recentListings.isNotEmpty) {
      return recentListings;
    }

    return _homeListings.take(4).toList();
  }

  @override
  void initState() {
    super.initState();
    _loadHomeListings();
    _loadMyListings();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  Future<void> _loadHomeListings({bool showLoading = true}) async {
    if (showLoading) {
      setState(() {
        _isLoadingListings = true;
        _listingsErrorMessage = null;
      });
    } else {
      setState(() {
        _listingsErrorMessage = null;
      });
    }

    try {
      final collection = await _listingService.fetchBrowseListings(perPage: 12);

      if (!mounted) {
        return;
      }

      setState(() {
        _homeListings = collection.listings;
        _isLoadingListings = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoadingListings = false;
        _listingsErrorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
    }
  }

  Future<void> _loadMyListings({bool showLoading = true}) async {
    if (!AuthService().hasSession) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoadingMyListings = false;
        _myListingsErrorMessage = null;
        _myListings = <Listing>[];
      });
      return;
    }

    if (showLoading) {
      setState(() {
        _isLoadingMyListings = true;
        _myListingsErrorMessage = null;
      });
    } else {
      setState(() {
        _myListingsErrorMessage = null;
      });
    }

    try {
      final collection = await _listingService.fetchMyListings(perPage: 4);
      if (!mounted) {
        return;
      }

      setState(() {
        _myListings = collection.listings;
        _isLoadingMyListings = false;
      });
    } catch (error) {
      if (!mounted) {
        return;
      }

      setState(() {
        _isLoadingMyListings = false;
        _myListingsErrorMessage = error is FormatException
            ? error.message
            : _apiClient.mapError(error);
      });
    }
  }

  Future<void> _openBrowsePage() async {
    setState(() {
      _selectedIndex = 1;
    });

    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const BrowsePage()),
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedIndex = 0;
    });
  }

  Future<void> _openListing(Listing listing) async {
    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => ListingDetailPage(listing: listing)),
    );
  }

  Future<void> _openMyListingsPage() async {
    await _openAuthenticatedPage(const MyListingsPage());
  }

  Future<void> _openProfilePage() async {
    if (!AuthService().hasSession) {
      await _openAuthenticatedPage(const ProfilePage());
      return;
    }

    setState(() {
      _selectedIndex = 3;
    });

    await Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const ProfilePage()),
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedIndex = 0;
    });
  }

  Future<void> _openOwnerListing(Listing listing) async {
    if (listing.isModerationApproved) {
      await _openListing(listing);
      return;
    }

    await _openMyListingsPage();
  }

  Future<void> _openAuthenticatedPage(Widget page) async {
    if (!AuthService().hasSession) {
      await Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => const LoginPage()),
      );
      return;
    }

    await Navigator.push(context, MaterialPageRoute(builder: (_) => page));
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

  Widget _buildHeaderAvatar(Map<String, dynamic>? currentUser) {
    final avatarLabel = currentUser?['avatar']?.toString().trim();
    final imageUrl = _profileImageUrl(currentUser);

    return CircleAvatar(
      radius: 16,
      backgroundColor: kGold,
      child: ClipOval(
        child: SizedBox.expand(
          child: imageUrl.isNotEmpty
              ? Image.network(
                  imageUrl,
                  fit: BoxFit.cover,
                  errorBuilder: (_, _, _) =>
                      _buildHeaderAvatarFallback(avatarLabel),
                )
              : _buildHeaderAvatarFallback(avatarLabel),
        ),
      ),
    );
  }

  Widget _buildHeaderAvatarFallback(String? avatarLabel) {
    if (avatarLabel == null || avatarLabel.isEmpty) {
      return const Icon(Icons.person, color: kNavy, size: 18);
    }

    return Center(
      child: Text(
        avatarLabel,
        style: const TextStyle(color: kNavy, fontWeight: FontWeight.w800),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: <Widget>[
            _buildHeader(),
            Expanded(
              child: RefreshIndicator(
                color: kNavy,
                onRefresh: () async {
                  await _loadHomeListings(showLoading: false);
                  await _loadMyListings(showLoading: false);
                },
                child: SingleChildScrollView(
                  physics: const AlwaysScrollableScrollPhysics(
                    parent: BouncingScrollPhysics(),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      _buildSearchBar(),
                      _buildHeroBanner(),
                      _buildCategories(),
                      if (AuthService().hasSession) _buildMyListingsSection(),
                      if (_listingsErrorMessage != null &&
                          _homeListings.isNotEmpty) ...<Widget>[
                        Padding(
                          padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                          child: _HomeStatusCard(
                            icon: Icons.cloud_off_rounded,
                            title: 'Could not refresh listings',
                            message: _listingsErrorMessage!,
                            actionLabel: 'Retry',
                            onAction: _loadHomeListings,
                          ),
                        ),
                      ],
                      _buildFeaturedSection(),
                      _buildRecentListings(),
                      const SizedBox(height: 20),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: _buildBottomNav(),
      floatingActionButton: _buildFAB(),
      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,
    );
  }

  Widget _buildHeader() {
    final currentUser = AuthService().currentUser;

    return Container(
      color: kNavy,
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: <Widget>[
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: kGold,
              border: Border.all(color: kWhite, width: 2),
            ),
            child: const Icon(Icons.school, color: kNavy, size: 20),
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'LNU Student Square',
                    style: TextStyle(
                      color: kWhite,
                      fontWeight: FontWeight.w800,
                      fontSize: 16,
                      letterSpacing: 0.5,
                    ),
                  ),
                ),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Leyte Normal University',
                    style: TextStyle(
                      color: kGold,
                      fontSize: 10,
                      letterSpacing: 1.2,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Stack(
            children: <Widget>[
              IconButton(
                icon: const Icon(Icons.notifications_outlined, color: kWhite),
                onPressed: () => _openAuthenticatedPage(const InquiryPage()),
              ),
              Positioned(
                top: 8,
                right: 8,
                child: Container(
                  width: 8,
                  height: 8,
                  decoration: const BoxDecoration(
                    color: kGold,
                    shape: BoxShape.circle,
                  ),
                ),
              ),
            ],
          ),
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => _openProfilePage(),
              customBorder: const CircleBorder(),
              child: Padding(
                padding: const EdgeInsets.all(4),
                child: _buildHeaderAvatar(currentUser),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Container(
      color: kNavy,
      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
      child: Container(
        height: 44,
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(22),
        ),
        child: TextField(
          controller: _searchController,
          readOnly: true,
          onTap: _openBrowsePage,
          decoration: InputDecoration(
            hintText: 'Search books, uniforms, gadgets...',
            hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
            prefixIcon: const Icon(Icons.search, color: kNavy, size: 20),
            suffixIcon: Container(
              margin: const EdgeInsets.all(6),
              decoration: BoxDecoration(
                color: kGold,
                borderRadius: BorderRadius.circular(16),
              ),
              child: const Icon(Icons.tune, color: kNavy, size: 16),
            ),
            border: InputBorder.none,
            contentPadding: const EdgeInsets.symmetric(vertical: 12),
          ),
        ),
      ),
    );
  }

  Widget _buildHeroBanner() {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.all(16),
      height: 180,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(20),
        gradient: const LinearGradient(
          colors: <Color>[kDarkNavy, kNavy, Color(0xFF1A2E9E)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: kNavy.withValues(alpha: 0.4),
            blurRadius: 16,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Stack(
        clipBehavior: Clip.hardEdge,
        children: <Widget>[
          Positioned(
            right: -20,
            top: -20,
            child: Container(
              width: 140,
              height: 140,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: kGold.withValues(alpha: 0.08),
              ),
            ),
          ),
          Positioned(
            right: 20,
            bottom: -30,
            child: Container(
              width: 100,
              height: 100,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: kGold.withValues(alpha: 0.06),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
            child: Row(
              children: <Widget>[
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: <Widget>[
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 10,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: kGold,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Text(
                          '#RISEwithLNU',
                          style: TextStyle(
                            color: kNavy,
                            fontWeight: FontWeight.w800,
                            fontSize: 10,
                            letterSpacing: 1,
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Buy & Sell Within\nthe LNU Community',
                        style: TextStyle(
                          color: kWhite,
                          fontWeight: FontWeight.w800,
                          fontSize: 20,
                          height: 1.2,
                        ),
                      ),
                      const SizedBox(height: 12),
                      ElevatedButton(
                        onPressed: _openBrowsePage,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: kGold,
                          foregroundColor: kNavy,
                          padding: const EdgeInsets.symmetric(
                            horizontal: 20,
                            vertical: 8,
                          ),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(20),
                          ),
                          elevation: 0,
                        ),
                        child: const Text(
                          'Browse Now',
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                SizedBox(width: 104, child: _buildHeroIllustration()),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeroIllustration() {
    return Stack(
      alignment: Alignment.center,
      children: <Widget>[
        Positioned(
          top: 8,
          left: 8,
          right: 8,
          child: Container(
            height: 72,
            decoration: BoxDecoration(
              color: kWhite.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: kWhite.withValues(alpha: 0.18)),
            ),
          ),
        ),
        Positioned(
          top: 20,
          child: Icon(
            Icons.desktop_windows_rounded,
            color: kGold.withValues(alpha: 0.9),
            size: 56,
          ),
        ),
        Positioned(
          bottom: 28,
          left: 24,
          right: 24,
          child: Container(
            height: 8,
            decoration: BoxDecoration(
              color: kWhite.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
        ),
        Positioned(
          bottom: 16,
          child: Container(
            width: 44,
            height: 8,
            decoration: BoxDecoration(
              color: kGold.withValues(alpha: 0.7),
              borderRadius: BorderRadius.circular(999),
            ),
          ),
        ),
        Positioned(
          bottom: 2,
          right: 6,
          child: Icon(
            Icons.mouse_rounded,
            color: kGold.withValues(alpha: 0.82),
            size: 18,
          ),
        ),
      ],
    );
  }

  Widget _buildCategories() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Text(
            'Categories',
            style: TextStyle(
              color: kNavy,
              fontWeight: FontWeight.w800,
              fontSize: 16,
            ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 100,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              physics: const BouncingScrollPhysics(),
              itemCount: _categories.length,
              separatorBuilder: (context, index) => const SizedBox(width: 12),
              itemBuilder: (context, index) {
                final category = _categories[index];
                return _CategoryChip(
                  icon: category.icon,
                  label: category.label,
                );
              },
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMyListingsSection() {
    return Padding(
      padding: const EdgeInsets.only(top: 24, left: 16, right: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: <Widget>[
              const Text(
                'My Recent Posts',
                style: TextStyle(
                  color: kNavy,
                  fontWeight: FontWeight.w800,
                  fontSize: 16,
                ),
              ),
              GestureDetector(
                onTap: _openMyListingsPage,
                child: const Text(
                  'View All',
                  style: TextStyle(
                    color: kNavy,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    decoration: TextDecoration.underline,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (_isLoadingMyListings && _myListings.isEmpty)
            const SizedBox(
              height: 170,
              child: Center(
                child: CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                ),
              ),
            )
          else if (_myListingsErrorMessage != null && _myListings.isEmpty)
            _HomeStatusCard(
              icon: Icons.cloud_off_rounded,
              title: 'Unable to load your listings',
              message: _myListingsErrorMessage!,
              actionLabel: 'Retry',
              onAction: _loadMyListings,
            )
          else if (_myListings.isEmpty)
            const _SectionPlaceholder(
              title: 'No posts yet.',
              subtitle: 'Your own listings will appear here after you post.',
            )
          else
            SizedBox(
              height: 170,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                itemCount: _myListings.length,
                separatorBuilder: (context, index) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final listing = _myListings[index];
                  return _MyListingCard(
                    listing: listing,
                    onTap: () => _openOwnerListing(listing),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildFeaturedSection() {
    return Padding(
      padding: const EdgeInsets.only(top: 24, left: 16, right: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          _sectionTitle('Featured Listings'),
          const SizedBox(height: 12),
          if (_isLoadingListings && _homeListings.isEmpty)
            const SizedBox(
              height: 180,
              child: Center(
                child: CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                ),
              ),
            )
          else if (_listingsErrorMessage != null && _homeListings.isEmpty)
            _HomeStatusCard(
              icon: Icons.cloud_off_rounded,
              title: 'Unable to load featured listings',
              message: _listingsErrorMessage!,
              actionLabel: 'Retry',
              onAction: _loadHomeListings,
            )
          else if (_featuredListings.isEmpty)
            const _SectionPlaceholder(
              title: 'No featured listings yet.',
              subtitle:
                  'Browse listings will appear here once the backend has data.',
            )
          else
            SizedBox(
              height: 180,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                physics: const BouncingScrollPhysics(),
                itemCount: _featuredListings.length,
                separatorBuilder: (context, index) => const SizedBox(width: 12),
                itemBuilder: (context, index) {
                  final listing = _featuredListings[index];
                  return _FeaturedCard(
                    listing: listing,
                    onTap: () => _openListing(listing),
                  );
                },
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildRecentListings() {
    return Padding(
      padding: const EdgeInsets.only(top: 24, left: 16, right: 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          _sectionTitle('Recent Listings'),
          const SizedBox(height: 12),
          if (_isLoadingListings && _homeListings.isEmpty)
            const SizedBox(
              height: 180,
              child: Center(
                child: CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(kNavy),
                ),
              ),
            )
          else if (_listingsErrorMessage != null && _homeListings.isEmpty)
            _HomeStatusCard(
              icon: Icons.cloud_off_rounded,
              title: 'Unable to load recent listings',
              message: _listingsErrorMessage!,
              actionLabel: 'Retry',
              onAction: _loadHomeListings,
            )
          else if (_recentListings.isEmpty)
            const _SectionPlaceholder(
              title: 'No recent listings yet.',
              subtitle: 'Freshly approved listings will appear here.',
            )
          else
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 1.1,
              ),
              itemCount: _recentListings.length,
              itemBuilder: (context, index) {
                final listing = _recentListings[index];
                return _ListingCard(
                  listing: listing,
                  onTap: () => _openListing(listing),
                );
              },
            ),
        ],
      ),
    );
  }

  Widget _sectionTitle(String title) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: <Widget>[
        Text(
          title,
          style: const TextStyle(
            color: kNavy,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
        ),
        GestureDetector(
          onTap: _openBrowsePage,
          child: const Text(
            'View All',
            style: TextStyle(
              color: kNavy,
              fontSize: 12,
              fontWeight: FontWeight.w600,
              decoration: TextDecoration.underline,
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildBottomNav() {
    return BottomAppBar(
      color: kNavy,
      shape: const CircularNotchedRectangle(),
      notchMargin: 8,
      child: SizedBox(
        height: 60,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: <Widget>[
            _NavItem(
              icon: Icons.home_rounded,
              label: 'Home',
              isActive: _selectedIndex == 0,
              onTap: () => setState(() => _selectedIndex = 0),
            ),
            _NavItem(
              icon: Icons.explore_rounded,
              label: 'Browse',
              isActive: _selectedIndex == 1,
              onTap: _openBrowsePage,
            ),
            const SizedBox(width: 48),
            _NavItem(
              icon: Icons.favorite_rounded,
              label: 'Saved',
              isActive: _selectedIndex == 2,
              onTap: () => _openAuthenticatedPage(const FavoritesPage()),
            ),
            _NavItem(
              icon: Icons.person_rounded,
              label: 'Profile',
              isActive: _selectedIndex == 3,
              onTap: () => _openProfilePage(),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFAB() {
    return FloatingActionButton(
      backgroundColor: kGold,
      foregroundColor: kNavy,
      elevation: 6,
      onPressed: () async {
        await _openAuthenticatedPage(const AddListingPage());
        await _loadHomeListings(showLoading: false);
        await _loadMyListings(showLoading: false);
      },
      child: const Icon(Icons.add_rounded, size: 28),
    );
  }
}

class _HomeCategory {
  const _HomeCategory({required this.icon, required this.label});

  final IconData icon;
  final String label;
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 96,
      child: Column(
        children: <Widget>[
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 14),
            decoration: BoxDecoration(
              color: kNavy,
              borderRadius: BorderRadius.circular(14),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: kNavy.withValues(alpha: 0.25),
                  blurRadius: 8,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Icon(icon, color: kGold, size: 24),
          ),
          const SizedBox(height: 8),
          Text(
            label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: kNavy,
            ),
          ),
        ],
      ),
    );
  }
}

class _HomeStatusCard extends StatelessWidget {
  const _HomeStatusCard({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final Future<void> Function({bool showLoading})? onAction;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(16),
        boxShadow: <BoxShadow>[
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Icon(icon, color: kNavy, size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    color: kNavy,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            message,
            style: TextStyle(color: Colors.grey[600], fontSize: 12),
          ),
          if (actionLabel != null && onAction != null) ...<Widget>[
            const SizedBox(height: 12),
            ElevatedButton(
              onPressed: () => onAction!.call(),
              style: ElevatedButton.styleFrom(
                backgroundColor: kNavy,
                foregroundColor: kWhite,
                elevation: 0,
              ),
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}

class _SectionPlaceholder extends StatelessWidget {
  const _SectionPlaceholder({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: kWhite,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: const TextStyle(
              color: kNavy,
              fontWeight: FontWeight.w700,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: TextStyle(color: Colors.grey[500], fontSize: 12),
          ),
        ],
      ),
    );
  }
}

class _ListingImageThumbnail extends StatelessWidget {
  const _ListingImageThumbnail({
    required this.listing,
    required this.borderRadius,
    required this.iconSize,
    required this.iconColor,
  });

  final Listing listing;
  final BorderRadius borderRadius;
  final double iconSize;
  final Color iconColor;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: borderRadius,
      child: ColoredBox(color: listing.color, child: _buildImage()),
    );
  }

  Widget _buildImage() {
    if (listing.imageUrl != null) {
      return Image.network(
        listing.imageUrl!,
        width: double.infinity,
        height: double.infinity,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => _buildFallbackIcon(),
      );
    }

    if (listing.imageFile != null) {
      return Image.file(
        listing.imageFile!,
        width: double.infinity,
        height: double.infinity,
        fit: BoxFit.cover,
        errorBuilder: (context, error, stackTrace) => _buildFallbackIcon(),
      );
    }

    return _buildFallbackIcon();
  }

  Widget _buildFallbackIcon() {
    return Center(
      child: Icon(listing.icon, size: iconSize, color: iconColor),
    );
  }
}

class _FeaturedCard extends StatelessWidget {
  const _FeaturedCard({required this.listing, required this.onTap});

  final Listing listing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 140,
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.07),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Container(
              height: 90,
              decoration: BoxDecoration(
                color: listing.color,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(16),
                ),
              ),
              child: _ListingImageThumbnail(
                listing: listing,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(16),
                ),
                iconSize: 44,
                iconColor: kNavy.withValues(alpha: 0.5),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    listing.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                      color: kNavy,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 2),
                  Text(
                    listing.seller,
                    style: TextStyle(fontSize: 10, color: Colors.grey[500]),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      Text(
                        listing.price,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                          color: kNavy,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: kGold.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          listing.category,
                          style: const TextStyle(
                            fontSize: 9,
                            color: kNavy,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MyListingCard extends StatelessWidget {
  const _MyListingCard({required this.listing, required this.onTap});

  final Listing listing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 220,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Row(
              children: <Widget>[
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: listing.color,
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: _ListingImageThumbnail(
                    listing: listing,
                    borderRadius: BorderRadius.circular(14),
                    iconSize: 24,
                    iconColor: kNavy.withValues(alpha: 0.65),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: <Widget>[
                      Text(
                        listing.title,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: kNavy,
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        listing.price,
                        style: const TextStyle(
                          color: kNavy,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _MyListingBadge(listing: listing),
            if (listing.isModerationDeclined &&
                listing.adminNotePreview != null) ...<Widget>[
              const SizedBox(height: 10),
              Text(
                listing.adminNotePreview!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: Colors.red[700],
                  fontSize: 11,
                  height: 1.35,
                ),
              ),
            ] else ...<Widget>[
              const SizedBox(height: 10),
              Text(
                listing.itemStatusLabel,
                style: TextStyle(color: Colors.grey[600], fontSize: 11),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MyListingBadge extends StatelessWidget {
  const _MyListingBadge({required this.listing});

  final Listing listing;

  @override
  Widget build(BuildContext context) {
    Color backgroundColor;
    Color textColor;

    if (listing.isModerationApproved) {
      backgroundColor = const Color(0xFFE8F7EC);
      textColor = const Color(0xFF138A36);
    } else if (listing.isModerationDeclined) {
      backgroundColor = const Color(0xFFFFE9E7);
      textColor = const Color(0xFFB3261E);
    } else {
      backgroundColor = const Color(0xFFFFF5E1);
      textColor = const Color(0xFFE08A00);
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        listing.moderationLabel,
        style: TextStyle(
          color: textColor,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _ListingCard extends StatelessWidget {
  const _ListingCard({required this.listing, required this.onTap});

  final Listing listing;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: <BoxShadow>[
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Expanded(
              child: Container(
                decoration: BoxDecoration(
                  color: listing.color,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(16),
                  ),
                ),
                child: _ListingImageThumbnail(
                  listing: listing,
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(16),
                  ),
                  iconSize: 40,
                  iconColor: kNavy.withValues(alpha: 0.45),
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    listing.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                      color: kNavy,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      Text(
                        listing.price,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 13,
                          color: kNavy,
                        ),
                      ),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 6,
                          vertical: 2,
                        ),
                        decoration: BoxDecoration(
                          color: kNavy,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          listing.condition,
                          style: const TextStyle(
                            fontSize: 9,
                            color: kGold,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NavItem extends StatelessWidget {
  const _NavItem({
    required this.icon,
    required this.label,
    required this.isActive,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool isActive;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(icon, color: isActive ? kGold : Colors.white54, size: 22),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: isActive ? kGold : Colors.white54,
              fontSize: 10,
              fontWeight: isActive ? FontWeight.w700 : FontWeight.w400,
            ),
          ),
        ],
      ),
    );
  }
}
