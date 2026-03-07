import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_model_page.dart';

class FavoritesService {
  static final FavoritesService _instance = FavoritesService._internal();

  factory FavoritesService() => _instance;

  FavoritesService._internal();

  final ApiClient _apiClient = ApiClient();
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

      final existingById = <int, Listing>{
        for (final listing in _favorites) listing.id: listing,
      };
      final listings = _extractListings(
        response.data,
        existingById: existingById,
      );
      if (listings == null) {
        return 'Invalid favorites payload.';
      }

      _favorites
        ..clear()
        ..addAll(listings);
      _hasLoadedFavorites = true;
      _loadedForUserKey = _currentUserKey;
      return null;
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

      final apiListing = _extractSingleListing(response.data);
      final favoriteListing = _mergeListingDetails(
        apiListing ?? listing,
        listing,
      );

      if (!isFavorite(favoriteListing.id)) {
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

  List<Listing>? _extractListings(
    dynamic body, {
    Map<int, Listing> existingById = const <int, Listing>{},
  }) {
    if (body is! Map<String, dynamic>) {
      return null;
    }

    final payload = body['data'];
    if (payload is! Map<String, dynamic>) {
      return null;
    }

    final rawListings = payload['listings'];
    if (rawListings is! List) {
      return null;
    }

    return rawListings.whereType<Map>().map((rawListing) {
      final parsed = Listing.fromApi(Map<String, dynamic>.from(rawListing));
      return _mergeListingDetails(parsed, existingById[parsed.id]);
    }).toList();
  }

  Listing? _extractSingleListing(dynamic body) {
    if (body is! Map<String, dynamic>) {
      return null;
    }

    final payload = body['data'];
    if (payload is! Map<String, dynamic>) {
      return null;
    }

    final rawListing = payload['listing'];
    if (rawListing is! Map) {
      return null;
    }

    return Listing.fromApi(Map<String, dynamic>.from(rawListing));
  }

  Listing _mergeListingDetails(Listing parsed, Listing? existing) {
    if (existing == null) {
      return parsed;
    }

    final usesFallbackCategory = parsed.category == 'Marketplace';
    final usesFallbackSeller = parsed.seller == 'LNU Seller';

    return parsed.copyWith(
      category: usesFallbackCategory ? existing.category : null,
      seller: usesFallbackSeller ? existing.seller : null,
      sellerAvatar: usesFallbackSeller ? existing.sellerAvatar : null,
      icon: usesFallbackCategory ? existing.icon : null,
      color: usesFallbackCategory ? existing.color : null,
      imageFile: existing.imageFile,
    );
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
