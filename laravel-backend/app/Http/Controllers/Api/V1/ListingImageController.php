<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreListingImageRequest;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ListingImageController extends Controller
{
    public function store(StoreListingImageRequest $request, Listing $listing): JsonResponse
    {
        $image = $request->uploadedImage();

        if (! $image) {
            throw ValidationException::withMessages([
                'image' => ['The image field is required.'],
            ]);
        }

        $path = $image->store('listings/'.$listing->id, 'public');

        try {
            $listingImage = DB::transaction(function () use ($listing, $path, $request): ListingImage {
                return ListingImage::query()->create([
                    'listing_id' => $listing->id,
                    'image_path' => $path,
                    'sort_order' => ((int) ($listing->listingImages()->max('sort_order') ?? -1)) + 1,
                    'is_primary' => ! $listing->listingImages()->exists(),
                    'uploaded_by_user_id' => $request->user()?->id,
                ]);
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        return ApiResponse::success('Listing image uploaded.', [
            'image' => $listingImage,
        ], 201);
    }

    public function destroy(Request $request, Listing $listing, ListingImage $image): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        if ((int) $image->listing_id !== (int) $listing->id) {
            abort(404);
        }

        DB::transaction(function () use ($image): void {
            $image->delete();
        });

        return ApiResponse::success('Listing image deleted.');
    }

    private function isListingOwner(Request $request, Listing $listing): bool
    {
        $userId = $request->user()?->id;

        return is_int($userId) && $userId === (int) $listing->user_id;
    }
}
