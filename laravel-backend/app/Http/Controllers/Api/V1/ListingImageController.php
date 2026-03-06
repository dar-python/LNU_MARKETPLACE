<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ListingImageController extends Controller
{
    public function store(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $request->validate([
            'image' => ['nullable', 'file', 'image', 'max:10240', 'required_without:images'],
            'images' => ['nullable', 'array', 'required_without:image'],
            'images.*' => ['file', 'image', 'max:10240'],
        ]);

        $image = $request->file('image');
        if (! $image && $request->hasFile('images')) {
            $images = $request->file('images');
            if (is_array($images) && $images !== []) {
                $image = $images[0];
            }
        }

        if (! $image) {
            throw ValidationException::withMessages([
                'image' => ['The image field is required.'],
            ]);
        }

        $path = $image->store('listings/'.$listing->id, 'public');
        $nextSortOrder = ((int) ($listing->listingImages()->max('sort_order') ?? -1)) + 1;

        $listingImage = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_path' => $path,
            'sort_order' => $nextSortOrder,
            'is_primary' => ! $listing->listingImages()->exists(),
            'uploaded_by_user_id' => $request->user()?->id,
        ]);

        return ApiResponse::success('Listing image uploaded.', [
            'image' => $listingImage,
        ], 201);
    }

    private function isListingOwner(Request $request, Listing $listing): bool
    {
        $userId = $request->user()?->id;

        return is_int($userId) && $userId === (int) $listing->user_id;
    }
}
