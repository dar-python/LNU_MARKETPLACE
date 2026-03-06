import 'listing_model_page.dart';

// ─── FavoritesService (Singleton) ─────────────────────────────────────────────
class FavoritesService {
  static final FavoritesService _instance = FavoritesService._internal();
  factory FavoritesService() => _instance;
  FavoritesService._internal();

  final List<Listing> _favorites = [];

  // Get all favorites
  List<Listing> get favorites => List.unmodifiable(_favorites);

  // Check if a listing is favorited
  bool isFavorite(int id) => _favorites.any((l) => l.id == id);

  // Add a listing to favorites
  void addFavorite(Listing listing) {
    if (!isFavorite(listing.id)) {
      _favorites.add(listing);
    }
  }

  // Remove a listing from favorites
  void removeFavorite(int id) {
    _favorites.removeWhere((l) => l.id == id);
  }

  // Toggle favorite
  void toggleFavorite(Listing listing) {
    if (isFavorite(listing.id)) {
      removeFavorite(listing.id);
    } else {
      addFavorite(listing);
    }
  }

  // Clear all favorites
  void clearAll() {
    _favorites.clear();
  }
}