import 'package:flutter/material.dart';
import 'listing_model_page.dart';
import 'listing_detail_page.dart';
import 'favorite_service.dart';

// ─── Color Palette ───────────────────────────────────────────────────────────
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
  List<Listing> get _favorites => FavoritesService().favorites;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    setState(() {});
  }

  void _removeFavorite(Listing listing) {
    setState(() {
      FavoritesService().removeFavorite(listing.id);
    });
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('${listing.title} removed from favorites'),
        backgroundColor: kNavy,
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        action: SnackBarAction(
          label: 'Undo',
          textColor: kGold,
          onPressed: () {
            setState(() {
              FavoritesService().addFavorite(listing);
            });
          },
        ),
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
            // ── Header ───────────────────────────────────────────────────
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
                      child: const Icon(Icons.arrow_back, color: kWhite, size: 20),
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
                      onTap: () => _showClearAllDialog(),
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 12, vertical: 6),
                        decoration: BoxDecoration(
                          color: kWhite.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Text(
                          'Clear All',
                          style: TextStyle(
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

            // ── Body ─────────────────────────────────────────────────────
            Expanded(
              child: _favorites.isEmpty
                  ? _buildEmptyState()
                  : ListView.builder(
                      physics: const BouncingScrollPhysics(),
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
                              builder: (_) =>
                                  ListingDetailPage(listing: listing),
                            ),
                          ),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
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
            child: const Icon(
              Icons.favorite_outline_rounded,
              color: kNavy,
              size: 44,
            ),
          ),
          const SizedBox(height: 20),
          const Text(
            'No Saved Items Yet',
            style: TextStyle(
              color: kNavy,
              fontSize: 18,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Tap the heart icon on any listing\nto save it here.',
            textAlign: TextAlign.center,
            style:
                TextStyle(color: Colors.grey[500], fontSize: 13, height: 1.5),
          ),
        ],
      ),
    );
  }

  void _showClearAllDialog() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape:
            RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text(
          'Clear All Favorites?',
          style: TextStyle(
              color: kNavy, fontWeight: FontWeight.w800, fontSize: 16),
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
            onPressed: () {
              setState(() => FavoritesService().clearAll());
              Navigator.pop(ctx);
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.red[400],
              foregroundColor: kWhite,
              shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10)),
              elevation: 0,
            ),
            child: const Text('Clear All'),
          ),
        ],
      ),
    );
  }
}

// ─── Favorite Card ────────────────────────────────────────────────────────────
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
            // ── Image / Icon ──────────────────────────────────────────
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

            // ── Info ──────────────────────────────────────────────────
            Expanded(
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(
                              horizontal: 8, vertical: 3),
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
                              horizontal: 8, vertical: 3),
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
                        Text(
                          listing.seller,
                          style:
                              TextStyle(fontSize: 10, color: Colors.grey[500]),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            // ── Remove Button ─────────────────────────────────────────
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