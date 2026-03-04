<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ListingController extends Controller
{
    /**
     * @var list<string>
     */
    private const ITEM_CONDITIONS = [
        'new',
        'like_new',
        'good',
        'fair',
        'poor',
    ];

    /**
     * @var list<string>
     */
    private const LISTING_STATUSES = [
        'pending_review',
        'available',
        'reserved',
        'sold',
        'rejected',
        'suspended',
        'archived',
    ];

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->validationRules());

        $listing = Listing::query()->create([
            'user_id' => (int) $request->user()->id,
            'category_id' => (int) $validated['category_id'],
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => (string) $validated['item_condition'],
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'campus_location' => $validated['campus_location'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);

        return ApiResponse::success('Listing created.', [
            'listing' => $listing->fresh(),
        ], 201);
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        $validated = $request->validate($this->validationRules());

        $listing->fill([
            'category_id' => (int) $validated['category_id'],
            'title' => (string) $validated['title'],
            'description' => (string) $validated['description'],
            'price' => $validated['price'],
            'item_condition' => (string) $validated['item_condition'],
            'quantity' => (int) ($validated['quantity'] ?? 1),
            'is_negotiable' => (bool) ($validated['is_negotiable'] ?? false),
            'campus_location' => $validated['campus_location'] ?? null,
            'listing_status' => $this->resolveListingStatus($validated),
        ]);
        $listing->save();

        return ApiResponse::success('Listing updated.', [
            'listing' => $listing->fresh(),
        ]);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        if (! $this->isListingOwner($request, $listing)) {
            return ApiResponse::error('Forbidden.', null, 403);
        }

        DB::transaction(function () use ($listing): void {
            $images = $listing->listingImages()->get(['id', 'image_path']);

            foreach ($images as $image) {
                if (is_string($image->image_path) && $image->image_path !== '') {
                    Storage::disk('public')->delete($image->image_path);
                }
            }

            $listing->listingImages()->delete();
            $listing->delete();
        });

        return ApiResponse::success('Listing deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validationRules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'item_condition' => ['required', 'string', Rule::in(self::ITEM_CONDITIONS)],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'is_negotiable' => ['nullable', 'boolean'],
            'campus_location' => ['nullable', 'string', 'max:120'],
            'listing_status' => ['nullable', 'string', Rule::in(self::LISTING_STATUSES)],
            'status' => ['nullable', 'string', Rule::in(self::LISTING_STATUSES)],
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveListingStatus(array $validated): string
    {
        $status = $validated['listing_status'] ?? $validated['status'] ?? null;

        if (is_string($status) && $status !== '') {
            return $status;
        }

        return 'pending_review';
    }

    private function isListingOwner(Request $request, Listing $listing): bool
    {
        $userId = $request->user()?->id;

        return is_int($userId) && $userId === (int) $listing->user_id;
    }
}
