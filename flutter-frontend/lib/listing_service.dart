import 'dart:io';

import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_model_page.dart';

class ListingService {
  static final ListingService _instance = ListingService._internal();

  factory ListingService() => _instance;

  ListingService._internal();

  final ApiClient _apiClient = ApiClient();
  final BackendListingAdapter _listingAdapter = BackendListingAdapter.instance;
  final List<Listing> _draftListings = List.from(dummyListings);

  List<Listing> get listings => List.unmodifiable(_draftListings);

  Future<ListingCollection> fetchBrowseListings({
    int page = 1,
    int perPage = 50,
    String searchQuery = '',
  }) async {
    final normalizedSearchQuery = searchQuery.trim();
    final response = await _apiClient.dio.get(
      '/api/v1/listings',
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
        if (normalizedSearchQuery.isNotEmpty) 'q': normalizedSearchQuery,
      },
    );

    final remoteCollection = ListingCollection.fromEnvelope(
      response.data,
      adapter: _listingAdapter,
    );

    return ListingCollection(
      listings: _mergeDraftListings(remoteCollection.listings),
      pagination: remoteCollection.pagination,
    );
  }

  void addListing({
    required String title,
    required String price,
    required String category,
    required String condition,
    required String description,
    File? imageFile,
  }) {
    final user = AuthService().currentUser;
    final sellerName = capitalizeName(user?['name'] ?? 'Unknown Seller');

    final newListing = Listing(
      id: DateTime.now().millisecondsSinceEpoch,
      title: title.toUpperCase(),
      price: price,
      category: category,
      condition: condition,
      description: description,
      seller: sellerName,
      sellerAvatar: sellerName[0].toUpperCase(),
      icon: categoryIcon(category),
      color: categoryColor(category),
      imageFile: imageFile,
    );

    _draftListings.insert(0, newListing);
  }

  void deleteListing(int id) {
    _draftListings.removeWhere((l) => l.id == id);
  }

  List<Listing> _mergeDraftListings(List<Listing> remoteListings) {
    if (_draftListings.isEmpty) {
      return remoteListings;
    }

    final draftIds = _draftListings.map((listing) => listing.id).toSet();

    return <Listing>[
      ..._draftListings,
      ...remoteListings.where((listing) => !draftIds.contains(listing.id)),
    ];
  }
}
