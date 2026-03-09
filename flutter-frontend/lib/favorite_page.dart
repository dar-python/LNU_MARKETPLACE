import 'package:flutter/material.dart';

import 'auth_service.dart';
import 'favorite_service.dart';
import 'listing_detail_page.dart';
import 'listing_model_page.dart';
import 'login_page.dart';

const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

class FavoritesPage extends StatefulWidget {
  const FavoritesPage({super.key});

  @override
  State<FavoritesPage> createState() => _FavoritesPageState();
}

class _FavoritesPageState extends State<FavoritesPage> {
  bool _isLoading = true;
  bool _isClearing = false;
  bool _isRedirectingToLogin = false;
  String? _errorMessage;

  List<Listing> get _favorites => FavoritesService().favorites;

  @override
  void initState() {
    super.initState();
    _loadFavorites();
  }

  Future<void> _loadFavorites({bool forceRefresh = false}) async {
    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final error = await FavoritesService().loadFavorites(
      forceRefresh: forceRefresh,
    );

    if (!mounted) {
      return;
    }

    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    setState(() {
      _isLoading = false;
      _errorMessage = error;
    });
  }

  Future<void> _removeFavorite(Listing listing) async {
    final error = await FavoritesService().removeFavorite(listing.id);

    if (!mounted) {
      return;
    }

    if (error != null) {
      if (!AuthService().hasSession) {
        await _redirectToLogin();
        return;
      }

      _showSnackBar(error);
      return;
    }

    setState(() {});
    _showSnackBar(
      '${listing.title} removed from favorites',
      actionLabel: 'Undo',
      onAction: () async {
        final undoError = await FavoritesService().addFavorite(listing);
        if (!mounted) {
          return;
        }

        if (undoError != null) {
          if (!AuthService().hasSession) {
            await _redirectToLogin();
            return;
          }

          _showSnackBar(undoError);
          return;
        }

        setState(() {});
      },
    );
  }

  Future<void> _clearAllFavorites() async {
    setState(() {
      _isClearing = true;
    });

    final error = await FavoritesService().clearAll();

    if (!mounted) {
      return;
    }

    if (!AuthService().hasSession) {
      await _redirectToLogin();
      return;
    }

    setState(() {
      _isClearing = false;
      if (error == null) {
        _errorMessage = null;
      }
    });

    if (error != null) {
      _showSnackBar(error);
      return;
    }

    _showSnackBar('Favorites cleared.');
  }

  Future<void> _redirectToLogin() async {
    if (_isRedirectingToLogin || !mounted) {
      return;
    }

    _isRedirectingToLogin = true;
    await Navigator.pushAndRemoveUntil(
      context,
      MaterialPageRoute(builder: (_) => const LoginPage()),
      (route) => route.isFirst,
    );
    _isRedirectingToLogin = false;
  }

  void _showSnackBar(
    String message, {
    String? actionLabel,
    Future<void> Function()? onAction,
  }) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: kNavy,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        action: actionLabel == null || onAction == null
            ? null
            : SnackBarAction(
                label: actionLabel,
                textColor: kGold,
                onPressed: () async {
                  await onAction();
                },
              ),
      ),
    );
  }

  void _showClearAllDialog() {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Clear All Favorites?',
          style: TextStyle(
            color: kNavy,
            fontWeight: FontWeight.w800,
            fontSize: 16,
          ),
        ),
        content: Text(
          'This will remove all ${_favorites.length} saved items.',
          style: TextStyle(color: Colors.grey[600], fontSize: 13),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel', style: TextStyle(color: kNavy)),
          ),
          ElevatedButton(
            onPressed: () async {
              Navigator.pop(ctx);
              await _clearAllFavorites();
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red[400],
              foregroundColor: kWhite,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              elevation: 0,
            ),
            child: const Text('Clear All'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: [
            Container(
              width: double.infinity,
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.only(
                  bottomLeft: Radius.circular(28),
                  bottomRight: Radius.circular(28),
                ),
              ),
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
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
                      child: const Icon(
                        Icons.arrow_back,
                        color: kWhite,
                        size: 20,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Saved Items',
                          style: TextStyle(
                            color: kWhite,
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          '${_favorites.length} item${_favorites.length != 1 ? 's' : ''} saved',
                          style: const TextStyle(color: kGold, fontSize: 11),
                        ),
                      ],
                    ),
                  ),
                  if (_favorites.isNotEmpty)
                    GestureDetector(
                      onTap: _isClearing ? null : _showClearAllDialog,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 12,
                          vertical: 6,
                        ),
                        decoration: BoxDecoration(
                          color: kWhite.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: Text(
                          _isClearing ? 'Clearing...' : 'Clear All',
                          style: const TextStyle(
                            color: kWhite,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
            Expanded(child: _buildBody()),
          ],
        ),
      ),
    );
  }

  Widget _buildBody() {
    if (_isLoading) {
      return const Center(
        child: CircularProgressIndicator(
          valueColor: AlwaysStoppedAnimation<Color>(kNavy),
        ),
      );
    }

    if (!AuthService().hasSession) {
      return _buildEmptyState(
        icon: Icons.lock_outline_rounded,
        title: 'Login Required',
        subtitle: 'Please log in to view and manage your saved items.',
      );
    }

    if (_errorMessage != null) {
      return _buildErrorState(_errorMessage!);
    }

    if (_favorites.isEmpty) {
      return _buildEmptyState(
        icon: Icons.favorite_outline_rounded,
        title: 'No Saved Items Yet',
        subtitle: 'Tap the heart icon on any listing\nto save it here.',
      );
    }

    return RefreshIndicator(
      color: kNavy,
      onRefresh: () => _loadFavorites(forceRefresh: true),
      child: ListView.builder(
        physics: const AlwaysScrollableScrollPhysics(
          parent: BouncingScrollPhysics(),
        ),
        padding: const EdgeInsets.all(16),
        itemCount: _favorites.length,
        itemBuilder: (context, index) {
          final listing = _favorites[index];
          return _FavoriteCard(
            listing: listing,
            onRemove: () => _removeFavorite(listing),
            onTap: () => Navigator.push(
              context,
              MaterialPageRoute(
                builder: (_) => ListingDetailPage(listing: listing),
              ),
            ).then((_) => _loadFavorites(forceRefresh: true)),
          );
        },
      ),
    );
  }

  Widget _buildEmptyState({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 90,
            height: 90,
            decoration: BoxDecoration(
              color: kNavy.withValues(alpha: 0.08),
              shape: BoxShape.circle,
            ),
            child: Icon(icon, color: kNavy, size: 44),
          ),
          const SizedBox(height: 20),
          Text(
            title,
            style: const TextStyle(
              color: kNavy,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: Colors.grey[500],
              fontSize: 13,
              height: 1.5,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildErrorState(String message) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 24),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 90,
              height: 90,
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.08),
                shape: BoxShape.circle,
              ),
              child: Icon(
                Icons.error_outline_rounded,
                color: Colors.red.shade400,
                size: 44,
              ),
            ),
            const SizedBox(height: 20),
            const Text(
              'Unable to Load Favorites',
              style: TextStyle(
                color: kNavy,
                fontSize: 18,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 13,
                height: 1.5,
              ),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => _loadFavorites(forceRefresh: true),
              style: ElevatedButton.styleFrom(
                backgroundColor: kNavy,
                foregroundColor: kWhite,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

class _FavoriteCard extends StatelessWidget {
  final Listing listing;
  final VoidCallback onRemove;
  final VoidCallback onTap;

  const _FavoriteCard({
    required this.listing,
    required this.onRemove,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        margin: const EdgeInsets.only(bottom: 12),
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 10,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Row(
          children: [
            ClipRRect(
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(16),
                bottomLeft: Radius.circular(16),
              ),
              child: Container(
                width: 100,
                height: 100,
                color: listing.color,
                child: listing.imageFile != null
                    ? Image.file(
                        listing.imageFile!,
                        fit: BoxFit.cover,
                        width: 100,
                        height: 100,
                      )
                    : Center(
                        child: Icon(
                          listing.icon,
                          size: 40,
                          color: kNavy.withValues(alpha: 0.3),
                        ),
                      ),
              ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 12,
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: kGold,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            listing.category,
                            style: const TextStyle(
                              color: kNavy,
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: listing.condition == 'New'
                                ? Colors.green.shade100
                                : listing.condition == 'Good'
                                ? Colors.blue.shade100
                                : Colors.orange.shade100,
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            listing.condition,
                            style: TextStyle(
                              color: listing.condition == 'New'
                                  ? Colors.green.shade700
                                  : listing.condition == 'Good'
                                  ? Colors.blue.shade700
                                  : Colors.orange.shade700,
                              fontSize: 9,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      listing.title,
                      style: const TextStyle(
                        color: kNavy,
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      listing.price,
                      style: const TextStyle(
                        color: kNavy,
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Row(
                      children: [
                        CircleAvatar(
                          radius: 8,
                          backgroundColor: kGold,
                          child: Text(
                            listing.sellerAvatar,
                            style: const TextStyle(
                              fontSize: 8,
                              color: kNavy,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        const SizedBox(width: 4),
                        Expanded(
                          child: Text(
                            listing.seller,
                            style: TextStyle(
                              fontSize: 10,
                              color: Colors.grey[500],
                            ),
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            Padding(
              padding: const EdgeInsets.only(right: 10),
              child: GestureDetector(
                onTap: onRemove,
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.favorite_rounded,
                    color: Colors.red.shade400,
                    size: 18,
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
