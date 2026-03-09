import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_model_page.dart';

class FavoritesService {
  static final FavoritesService _instance = FavoritesService._internal();

  factory FavoritesService() => _instance;

  FavoritesService._internal();

  final ApiClient _apiClient = ApiClient();
  final BackendListingAdapter _listingAdapter = BackendListingAdapter.instance;
  final List<Listing> _favorites = [];
  bool _hasLoadedFavorites = false;
  String? _loadedForUserKey;

  List<Listing> get favorites => List.unmodifiable(_favorites);
  bool get hasLoadedFavorites => _hasLoadedFavorites;

  bool isFavorite(int id) => _favorites.any((l) => l.id == id);

  Future<String?> ensureLoaded() async {
    _resetIfUserChanged();

    if (_hasLoadedFavorites) {
      return null;
    }

    return loadFavorites();
  }

  Future<String?> loadFavorites({bool forceRefresh = false}) async {
    _resetIfUserChanged();

    if (!forceRefresh && _hasLoadedFavorites) {
      return null;
    }

    final loginMessage = _loginRequiredMessage('view');
    if (loginMessage != null) {
      reset();
      return loginMessage;
    }

    try {
      final response = await _apiClient.dio.get(
        '/api/v1/favorites',
        queryParameters: <String, dynamic>{'per_page': 50},
      );
      final listings = ListingCollection.fromEnvelope(
        response.data,
        adapter: _listingAdapter,
      );

      _favorites
        ..clear()
        ..addAll(listings.listings);
      _hasLoadedFavorites = true;
      _loadedForUserKey = _currentUserKey;
      return null;
    } on FormatException catch (error) {
      return error.message;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> addFavorite(Listing listing) async {
    _resetIfUserChanged();

    final loginMessage = _loginRequiredMessage('save');
    if (loginMessage != null) {
      return loginMessage;
    }

    try {
      final response = await _apiClient.dio.post(
        '/api/v1/favorites',
        data: <String, dynamic>{'listing_id': listing.id},
      );

      final favoriteListing =
          _extractSingleListing(response.data, fallback: listing) ?? listing;
      final favoriteIndex = _favorites.indexWhere(
        (existingListing) => existingListing.id == favoriteListing.id,
      );

      if (favoriteIndex >= 0) {
        _favorites[favoriteIndex] = favoriteListing;
      } else {
        _favorites.insert(0, favoriteListing);
      }

      _hasLoadedFavorites = true;
      _loadedForUserKey = _currentUserKey;
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> removeFavorite(int id) async {
    _resetIfUserChanged();

    final loginMessage = _loginRequiredMessage('manage');
    if (loginMessage != null) {
      return loginMessage;
    }

    try {
      await _apiClient.dio.delete('/api/v1/favorites/$id');
      _favorites.removeWhere((l) => l.id == id);
      _loadedForUserKey = _currentUserKey;
      return null;
    } catch (error) {
      return _apiClient.mapError(error);
    }
  }

  Future<String?> toggleFavorite(Listing listing) async {
    if (isFavorite(listing.id)) {
      return removeFavorite(listing.id);
    }

    return addFavorite(listing);
  }

  Future<String?> clearAll() async {
    _resetIfUserChanged();

    final loginMessage = _loginRequiredMessage('manage');
    if (loginMessage != null) {
      return loginMessage;
    }

    final currentFavorites = List<Listing>.from(_favorites);
    for (final listing in currentFavorites) {
      final error = await removeFavorite(listing.id);
      if (error != null) {
        return error;
      }
    }

    return null;
  }

  void reset() {
    _favorites.clear();
    _hasLoadedFavorites = false;
    _loadedForUserKey = null;
  }

  Listing? _extractSingleListing(dynamic body, {Listing? fallback}) {
    final rawListing = _apiClient.extractDataItemMap(body, 'listing');
    if (rawListing == null) {
      return null;
    }

    return _listingAdapter.fromApi(rawListing, fallback: fallback);
  }

  String? _loginRequiredMessage(String action) {
    if (AuthService().isLoggedIn) {
      return null;
    }

    return 'Please log in to $action favorites.';
  }

  String? get _currentUserKey {
    final user = AuthService().currentUser;
    final studentId = user?['studentId']?.toString().trim() ?? '';
    if (studentId.isNotEmpty) {
      return studentId;
    }

    final email = user?['email']?.toString().trim() ?? '';
    if (email.isNotEmpty) {
      return email;
    }

    return null;
  }

  void _resetIfUserChanged() {
    final currentUserKey = _currentUserKey;
    if (currentUserKey == null) {
      reset();
      return;
    }

    if (_loadedForUserKey == null || _loadedForUserKey == currentUserKey) {
      return;
    }

    _favorites.clear();
    _hasLoadedFavorites = false;
    _loadedForUserKey = currentUserKey;
  }
}
