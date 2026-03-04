import 'package:flutter/material.dart';
import 'listing_detail_page.dart';

// ─── Color Palette ───────────────────────────────────────────────────────────
const kNavy = Color(0xFF0D1B6E);
const kDarkNavy = Color(0xFF080F45);
const kGold = Color(0xFFF5C518);
const kWhite = Color(0xFFFFFFFF);

// ─── Dummy Data Model ─────────────────────────────────────────────────────────
class Listing {
  final int id;
  final String title;
  final String price;
  final String category;
  final String condition;
  final String description;
  final String seller;
  final String sellerAvatar;
  final IconData icon;
  final Color color;

  const Listing({
    required this.id,
    required this.title,
    required this.price,
    required this.category,
    required this.condition,
    required this.description,
    required this.seller,
    required this.sellerAvatar,
    required this.icon,
    required this.color,
  });
}

// ─── Dummy Listings ───────────────────────────────────────────────────────────
final List<Listing> dummyListings = [
  Listing(id: 1, title: 'Engineering Mathematics Book', price: '₱150', category: 'Books', condition: 'Good', description: 'Used Engineering Math textbook. Complete pages, no missing content. Minor highlights only.', seller: 'Juan dela Cruz', sellerAvatar: 'J', icon: Icons.menu_book_rounded, color: const Color(0xFFE3E8FF)),
  Listing(id: 2, title: 'LNU PE Uniform (Large)', price: '₱300', category: 'Uniforms', condition: 'New', description: 'Brand new PE uniform, never worn. Size Large. Bought wrong size.', seller: 'Maria Santos', sellerAvatar: 'M', icon: Icons.checkroom_rounded, color: const Color(0xFFFFEECC)),
  Listing(id: 3, title: 'Scientific Calculator fx-991', price: '₱800', category: 'Gadgets', condition: 'Good', description: 'Casio fx-991EX scientific calculator. Works perfectly. Selling because graduated.', seller: 'Pedro Reyes', sellerAvatar: 'P', icon: Icons.calculate_rounded, color: const Color(0xFFE8FFE8)),
  Listing(id: 4, title: 'Lab Coat (Medium)', price: '₱250', category: 'Lab Tools', condition: 'Good', description: 'White lab coat, size medium. Clean and well-maintained. Used for 1 semester only.', seller: 'Ana Lopez', sellerAvatar: 'A', icon: Icons.science_rounded, color: const Color(0xFFFFE8E8)),
  Listing(id: 5, title: 'Calculus by Thomas (13th Ed)', price: '₱200', category: 'Books', condition: 'Fair', description: 'Calculus textbook 13th edition. Some annotations but all pages complete.', seller: 'Carlos Bautista', sellerAvatar: 'C', icon: Icons.menu_book_rounded, color: const Color(0xFFE3E8FF)),
  Listing(id: 6, title: 'Laptop Stand + Mouse', price: '₱450', category: 'Gadgets', condition: 'New', description: 'Adjustable laptop stand and wireless mouse bundle. Only used for 2 weeks.', seller: 'Rosa Mendoza', sellerAvatar: 'R', icon: Icons.laptop_rounded, color: const Color(0xFFE8F4FF)),
  Listing(id: 7, title: 'School Bag (Navy Blue)', price: '₱350', category: 'Uniforms', condition: 'Good', description: 'LNU-colored school bag with multiple compartments. Still very sturdy.', seller: 'Jose Garcia', sellerAvatar: 'J', icon: Icons.backpack_rounded, color: const Color(0xFFE8ECFF)),
  Listing(id: 8, title: 'Chemistry Lab Kit', price: '₱600', category: 'Lab Tools', condition: 'Good', description: 'Complete chemistry lab kit with test tubes, beakers, and stirrers.', seller: 'Luz Villanueva', sellerAvatar: 'L', icon: Icons.biotech_rounded, color: const Color(0xFFFFE8F8)),
];

// ─── Browse Page ──────────────────────────────────────────────────────────────
class BrowsePage extends StatefulWidget {
  const BrowsePage({super.key});

  @override
  State<BrowsePage> createState() => _BrowsePageState();
}

class _BrowsePageState extends State<BrowsePage> {
  final TextEditingController _searchController = TextEditingController();
  String _selectedCategory = 'All';
  String _searchQuery = '';

  final List<String> _categories = [
    'All', 'Books', 'Uniforms', 'Gadgets', 'Lab Tools',
  ];

  List<Listing> get _filteredListings {
    return dummyListings.where((listing) {
      final matchesCategory = _selectedCategory == 'All' || listing.category == _selectedCategory;
      final matchesSearch = listing.title.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          listing.seller.toLowerCase().contains(_searchQuery.toLowerCase()) ||
          listing.category.toLowerCase().contains(_searchQuery.toLowerCase());
      return matchesCategory && matchesSearch;
    }).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF4F6FF),
      body: SafeArea(
        child: Column(
          children: [
            // ── Header ───────────────────────────────────────────────────────
            Container(
              decoration: const BoxDecoration(
                gradient: LinearGradient(
                  colors: [kDarkNavy, kNavy],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
              padding: const EdgeInsets.fromLTRB(16, 16, 16, 20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Browse Listings',
                    style: TextStyle(
                      color: kWhite,
                      fontSize: 22,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.3,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Find what you need from LNU students',
                    style: TextStyle(color: kGold, fontSize: 12),
                  ),
                  const SizedBox(height: 14),
                  // Search Bar
                  Container(
                    height: 44,
                    decoration: BoxDecoration(
                      color: kWhite,
                      borderRadius: BorderRadius.circular(22),
                    ),
                    child: TextField(
                      controller: _searchController,
                      onChanged: (val) => setState(() => _searchQuery = val),
                      decoration: InputDecoration(
                        hintText: 'Search listings...',
                        hintStyle: TextStyle(color: Colors.grey[400], fontSize: 13),
                        prefixIcon: const Icon(Icons.search, color: kNavy, size: 20),
                        suffixIcon: _searchQuery.isNotEmpty
                            ? IconButton(
                                icon: Icon(Icons.clear, color: Colors.grey[400], size: 18),
                                onPressed: () {
                                  _searchController.clear();
                                  setState(() => _searchQuery = '');
                                },
                              )
                            : null,
                        border: InputBorder.none,
                        contentPadding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            // ── Category Filter ───────────────────────────────────────────────
            Container(
              color: kWhite,
              padding: const EdgeInsets.symmetric(vertical: 12),
              child: SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Row(
                  children: _categories.map((cat) {
                    final isSelected = _selectedCategory == cat;
                    return GestureDetector(
                      onTap: () => setState(() => _selectedCategory = cat),
                      child: Container(
                        margin: const EdgeInsets.only(right: 10),
                        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                        decoration: BoxDecoration(
                          color: isSelected ? kNavy : const Color(0xFFF4F6FF),
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: isSelected ? kNavy : Colors.grey.shade300,
                          ),
                        ),
                        child: Text(
                          cat,
                          style: TextStyle(
                            color: isSelected ? kWhite : Colors.grey[600],
                            fontSize: 12,
                            fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
              ),
            ),

            // ── Results Count ─────────────────────────────────────────────────
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 14, 16, 8),
              child: Row(
                children: [
                  Text(
                    '${_filteredListings.length} listing${_filteredListings.length != 1 ? 's' : ''} found',
                    style: const TextStyle(
                      color: kNavy,
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                    ),
                  ),
                  if (_selectedCategory != 'All') ...[
                    const SizedBox(width: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                      decoration: BoxDecoration(
                        color: kGold,
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Text(
                        _selectedCategory,
                        style: const TextStyle(
                          color: kNavy,
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),

            // ── Listings Grid ─────────────────────────────────────────────────
            Expanded(
              child: _filteredListings.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.search_off_rounded, size: 64, color: Colors.grey[300]),
                          const SizedBox(height: 12),
                          Text(
                            'No listings found',
                            style: TextStyle(color: Colors.grey[400], fontSize: 15, fontWeight: FontWeight.w600),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Try a different search or category',
                            style: TextStyle(color: Colors.grey[400], fontSize: 12),
                          ),
                        ],
                      ),
                    )
                  : GridView.builder(
                      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                      physics: const BouncingScrollPhysics(),
                      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: 2,
                        crossAxisSpacing: 12,
                        mainAxisSpacing: 12,
                        childAspectRatio: 0.72,
                      ),
                      itemCount: _filteredListings.length,
                      itemBuilder: (context, index) {
                        return _ListingCard(listing: _filteredListings[index]);
                      },
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Listing Card ─────────────────────────────────────────────────────────────
class _ListingCard extends StatelessWidget {
  final Listing listing;
  const _ListingCard({required this.listing});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(builder: (_) => ListingDetailPage(listing: listing)),
      ),
      child: Container(
        decoration: BoxDecoration(
          color: kWhite,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.06),
              blurRadius: 8,
              offset: const Offset(0, 3),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image area
            Container(
              height: 110,
              decoration: BoxDecoration(
                color: listing.color,
                borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
              ),
              child: Stack(
                children: [
                  Center(
                    child: Icon(listing.icon, size: 48, color: kNavy.withValues(alpha: 0.3)),
                  ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: Container(
                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
                      decoration: BoxDecoration(
                        color: kNavy,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Text(
                        listing.condition,
                        style: const TextStyle(color: kGold, fontSize: 9, fontWeight: FontWeight.w700),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            // Info area
            Padding(
              padding: const EdgeInsets.all(10),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    listing.title,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                      color: kNavy,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    listing.price,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      color: kNavy,
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
                          style: const TextStyle(fontSize: 8, color: kNavy, fontWeight: FontWeight.w800),
                        ),
                      ),
                      const SizedBox(width: 4),
                      Expanded(
                        child: Text(
                          listing.seller,
                          style: TextStyle(fontSize: 10, color: Colors.grey[500]),
                          overflow: TextOverflow.ellipsis,
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