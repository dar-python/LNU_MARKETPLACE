import 'dart:io';

import 'package:dio/dio.dart';

import 'auth_service.dart';
import 'core/network/api_client.dart';
import 'listing_model_page.dart';

class ListingCreateResult {
  const ListingCreateResult({
    required this.listing,
    required this.uploadedImages,
    required this.imageUploadErrors,
  });

  final Listing listing;
  final List<ListingImageAsset> uploadedImages;
  final List<String> imageUploadErrors;

  bool get hasImageUploadErrors => imageUploadErrors.isNotEmpty;
}

class ListingService {
  static final ListingService _instance = ListingService._internal();

  factory ListingService() => _instance;

  ListingService._internal();

  final ApiClient _apiClient = ApiClient();
  final BackendListingAdapter _listingAdapter = BackendListingAdapter.instance;
  final List<Listing> _draftListings = List<Listing>.from(dummyListings);

  List<Listing> get listings => List<Listing>.unmodifiable(_draftListings);

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

  Future<ListingDetail> fetchListingDetail(
    int listingId, {
    Listing? fallback,
  }) async {
    final response = await _apiClient.dio.get('/api/v1/listings/$listingId');
    return ListingDetail.fromEnvelope(
      response.data,
      adapter: _listingAdapter,
      fallback: fallback,
    );
  }

  Future<ListingCreateResult> createListing({
    required String title,
    required String price,
    required String category,
    required String condition,
    required String description,
    List<File> imageFiles = const <File>[],
    String? campusLocation,
  }) async {
    final normalizedTitle = title.trim();
    final normalizedDescription = description.trim();
    final normalizedCampusLocation = (campusLocation ?? '').trim();
    final parsedPrice = _normalizePriceForApi(price);
    if (normalizedTitle.isEmpty) {
      throw const FormatException('Please enter a title.');
    }
    if (normalizedDescription.isEmpty) {
      throw const FormatException('Please enter a description.');
    }
    if (parsedPrice == null) {
      throw const FormatException('Please enter a valid price.');
    }

    final backendCategory = backendCategoryForFrontendLabel(category);
    if (backendCategory == null) {
      throw FormatException('Unable to map "$category" to a backend category.');
    }

    final fallbackListing = _buildDraftListing(
      id: 0,
      title: normalizedTitle,
      price: 'P${parsedPrice.toStringAsFixed(2)}',
      categoryName: backendCategory.name,
      condition: normalizeListingConditionLabel(condition),
      description: normalizedDescription,
      campusLocation: normalizedCampusLocation,
      imageFile: imageFiles.isNotEmpty ? imageFiles.first : null,
    );

    final response = await _apiClient.dio.post(
      '/api/v1/listings',
      data: <String, dynamic>{
        'category_id': backendCategory.id,
        'title': normalizedTitle,
        'description': normalizedDescription,
        'price': parsedPrice.toStringAsFixed(2),
        'item_condition': backendItemConditionForLabel(condition),
        'quantity': 1,
        'is_negotiable': false,
        if (normalizedCampusLocation.isNotEmpty)
          'campus_location': normalizedCampusLocation,
      },
    );

    final rawListing = _apiClient.extractDataItemMap(response.data, 'listing');
    if (rawListing == null) {
      throw const FormatException('Invalid listing payload.');
    }

    final createdListing = _listingAdapter.fromApi(
      rawListing,
      fallback: fallbackListing,
    );

    final resolvedListing = createdListing.copyWith(
      imageFile: fallbackListing.imageFile,
      seller: fallbackListing.seller,
      sellerAvatar: fallbackListing.sellerAvatar,
      condition: normalizeListingConditionLabel(createdListing.condition),
      category: backendCategory.name,
    );

    _upsertDraftListing(resolvedListing);
    _listingAdapter.prime(<Listing>[resolvedListing]);

    final uploadedImages = <ListingImageAsset>[];
    final imageUploadErrors = <String>[];

    for (final imageFile in imageFiles) {
      try {
        uploadedImages.add(
          await _uploadListingImage(
            listingId: resolvedListing.id,
            imageFile: imageFile,
          ),
        );
      } catch (error) {
        imageUploadErrors.add(
          '${_safeFileName(imageFile)}: ${_apiClient.mapError(error)}',
        );
      }
    }

    return ListingCreateResult(
      listing: resolvedListing,
      uploadedImages: uploadedImages,
      imageUploadErrors: imageUploadErrors,
    );
  }

  void deleteListing(int id) {
    _draftListings.removeWhere((listing) => listing.id == id);
  }

  Future<ListingImageAsset> _uploadListingImage({
    required int listingId,
    required File imageFile,
  }) async {
    final response = await _apiClient.dio.post(
      '/api/v1/listings/$listingId/images',
      data: FormData.fromMap(<String, dynamic>{
        'image': await MultipartFile.fromFile(
          imageFile.path,
          filename: _safeFileName(imageFile),
        ),
      }),
      options: Options(contentType: 'multipart/form-data'),
    );

    final rawImage = _apiClient.extractDataItemMap(response.data, 'image');
    if (rawImage == null) {
      throw const FormatException('Invalid listing image payload.');
    }

    return ListingImageAsset.fromApi(rawImage);
  }

  Listing _buildDraftListing({
    required int id,
    required String title,
    required String price,
    required String categoryName,
    required String condition,
    required String description,
    required String campusLocation,
    File? imageFile,
  }) {
    final user = AuthService().currentUser;
    final sellerName = capitalizeName(
      user?['name']?.toString().trim() ?? 'Unknown Seller',
    );

    return Listing(
      id: id,
      title: title,
      price: price,
      category: categoryName,
      condition: condition,
      description: description,
      seller: sellerName,
      sellerAvatar: sellerName.isNotEmpty ? sellerName[0].toUpperCase() : '?',
      icon: categoryIcon(categoryName),
      color: categoryColor(categoryName),
      listingStatus: 'pending_review',
      campusLocation: campusLocation,
      imageFile: imageFile,
    );
  }

  String _safeFileName(File imageFile) {
    final segments = imageFile.uri.pathSegments;
    if (segments.isNotEmpty && segments.last.trim().isNotEmpty) {
      return segments.last.trim();
    }

    return 'listing-image.jpg';
  }

  double? _normalizePriceForApi(String rawValue) {
    final normalizedValue = rawValue
        .trim()
        .replaceAll('PHP', '')
        .replaceAll('Php', '')
        .replaceAll('php', '')
        .replaceAll('P', '')
        .replaceAll('p', '')
        .replaceAll('\u20B1', '')
        .replaceAll(',', '')
        .trim();

    return double.tryParse(normalizedValue);
  }

  void _upsertDraftListing(Listing listing) {
    final existingIndex = _draftListings.indexWhere(
      (existingListing) => existingListing.id == listing.id,
    );

    if (existingIndex >= 0) {
      _draftListings[existingIndex] = listing;
      return;
    }

    _draftListings.insert(0, listing);
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
