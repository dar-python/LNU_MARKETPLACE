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

typedef ListingCreateProgressCallback = void Function(String message);

class ListingService {
  static final ListingService _instance = ListingService._internal();

  factory ListingService() => _instance;

  ListingService._internal();

  final ApiClient _apiClient = ApiClient();
  final BackendListingAdapter _listingAdapter = BackendListingAdapter.instance;

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

    _listingAdapter.prime(remoteCollection.listings);
    return remoteCollection;
  }

  Future<ListingCollection> fetchMyListings({
    int page = 1,
    int perPage = 50,
  }) async {
    final response = await _apiClient.dio.get(
      '/api/v1/listings/mine',
      queryParameters: <String, dynamic>{'page': page, 'per_page': perPage},
    );

    final remoteCollection = ListingCollection.fromEnvelope(
      response.data,
      adapter: _listingAdapter,
    );

    _listingAdapter.prime(remoteCollection.listings);
    return remoteCollection;
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
    String? condition,
    required String description,
    List<File> imageFiles = const <File>[],
    String? campusLocation,
    String? meetupArrangement,
    String? serviceType,
    String? serviceMode,
    ListingCreateProgressCallback? onProgress,
  }) async {
    final normalizedTitle = title.trim();
    final normalizedDescription = description.trim();
    final normalizedCondition = condition?.trim();
    final normalizedCampusLocation = (campusLocation ?? '').trim();
    final normalizedMeetupArrangement = (meetupArrangement ?? '').trim();
    final normalizedServiceType = (serviceType ?? '').trim();
    final normalizedServiceMode = (serviceMode ?? '').trim().toLowerCase();
    final normalizedDisplayLocation = normalizedMeetupArrangement.isNotEmpty
        ? normalizedMeetupArrangement
        : normalizedCampusLocation;
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

    final backendCategory = resolveBackendCategoryForFrontendLabel(category);

    final fallbackListing = _buildDraftListing(
      id: 0,
      title: normalizedTitle,
      price: 'P${parsedPrice.toStringAsFixed(2)}',
      categoryName: backendCategory.name,
      condition: normalizedCondition != null && normalizedCondition.isNotEmpty
          ? normalizeListingConditionLabel(normalizedCondition)
          : '',
      description: normalizedDescription,
      campusLocation: normalizedDisplayLocation,
      imageFile: imageFiles.isNotEmpty ? imageFiles.first : null,
    );

    onProgress?.call('Creating listing...');
    final response = await _apiClient.dio.post(
      '/api/v1/listings',
      data: <String, dynamic>{
        'category_slug': backendCategory.slug,
        'title': normalizedTitle,
        'description': normalizedDescription,
        'price': parsedPrice.toStringAsFixed(2),
        'quantity': 1,
        'is_negotiable': false,
        if (normalizedCondition != null && normalizedCondition.isNotEmpty)
          'item_condition': backendItemConditionForLabel(normalizedCondition),
        if (normalizedMeetupArrangement.isNotEmpty)
          'meetup_arrangement': normalizedMeetupArrangement,
        if (normalizedServiceType.isNotEmpty)
          'service_type': normalizedServiceType,
        if (normalizedServiceMode.isNotEmpty)
          'service_mode': normalizedServiceMode,
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
      condition: normalizedCondition != null && normalizedCondition.isNotEmpty
          ? normalizeListingConditionLabel(normalizedCondition)
          : '',
      category: backendCategory.name,
    );

    _listingAdapter.prime(<Listing>[resolvedListing]);

    final uploadedImages = <ListingImageAsset>[];
    final imageUploadErrors = <String>[];

    for (var index = 0; index < imageFiles.length; index++) {
      final imageFile = imageFiles[index];
      onProgress?.call(
        'Uploading image ${index + 1} of ${imageFiles.length}...',
      );
      try {
        uploadedImages.add(
          await _uploadListingImage(
            listingId: resolvedListing.id,
            imageFile: imageFile,
          ),
        );
      } catch (error) {
        imageUploadErrors.add(
          '${_safeFileName(imageFile)}: ${_apiClient.mapError(error, maxMessages: 2)}',
        );
      }
    }

    onProgress?.call(
      imageUploadErrors.isEmpty
          ? 'Listing posted successfully.'
          : 'Listing created, but some image uploads failed.',
    );

    return ListingCreateResult(
      listing: resolvedListing,
      uploadedImages: uploadedImages,
      imageUploadErrors: imageUploadErrors,
    );
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
      itemStatus: '',
      moderationStatus: 'pending',
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
}
