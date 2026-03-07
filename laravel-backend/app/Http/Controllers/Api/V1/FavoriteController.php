<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\FavoriteIndexRequest;
use App\Http\Requests\StoreFavoriteRequest;
use App\Models\Favorite;
use App\Models\Listing;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class FavoriteController extends Controller
{
    /**
     * @var list<string>
     */
    private const FAVORITABLE_STATUSES = [
        'available',
        'reserved',
        'sold',
    ];

    public function index(FavoriteIndexRequest $request): JsonResponse
    {
        $perPage = (int) ($request->validated('per_page') ?? 10);
        $userId = (int) $request->user()->id;

        $paginator = $this->favoriteListingsQuery($userId)
            ->orderByDesc('favorites.created_at')
            ->orderByDesc('favorites.id')
            ->paginate($perPage);

        return ApiResponse::success('Favorites retrieved successfully.', [
            'listings' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function store(StoreFavoriteRequest $request): JsonResponse
    {
        $listing = $this->findFavoritableListing((int) $request->validated('listing_id'));

        if (! $listing) {
            throw ValidationException::withMessages([
                'listing_id' => ['The selected listing is not available for favoriting.'],
            ]);
        }

        $favorite = Favorite::query()->firstOrCreate([
            'user_id' => (int) $request->user()->id,
            'listing_id' => $listing->id,
        ]);

        if (! $favorite->wasRecentlyCreated) {
            throw ValidationException::withMessages([
                'listing_id' => ['The listing has already been favorited.'],
            ]);
        }

        return ApiResponse::success('Favorite added.', [
            'listing' => $listing,
        ], 201);
    }

    public function destroy(Request $request, Listing $listing): JsonResponse
    {
        Favorite::query()
            ->where('user_id', (int) $request->user()->id)
            ->where('listing_id', $listing->id)
            ->delete();

        return ApiResponse::success('Favorite removed.');
    }

    private function favoriteListingsQuery(int $userId): Builder
    {
        $query = Listing::query()
            ->select('listings.*')
            ->join('favorites', function (JoinClause $join) use ($userId): void {
                $join->on('favorites.listing_id', '=', 'listings.id')
                    ->where('favorites.user_id', '=', $userId);
            });

        return $this->applyFavoritableConstraints($query, 'listings.');
    }

    private function findFavoritableListing(int $listingId): ?Listing
    {
        return $this->applyFavoritableConstraints(
            Listing::query()->whereKey($listingId)
        )->first();
    }

    private function applyFavoritableConstraints(Builder $query, string $columnPrefix = ''): Builder
    {
        $query
            ->whereIn($columnPrefix.'listing_status', self::FAVORITABLE_STATUSES)
            ->where($columnPrefix.'is_flagged', false);

        if (Schema::hasColumn('listings', 'approved_at')) {
            $query->whereNotNull($columnPrefix.'approved_at');
        }

        if (Schema::hasColumn('users', 'is_disabled')) {
            $query->whereHas('user', static function (Builder $builder): void {
                $builder->where('is_disabled', false);
            });
        }

        return $query;
    }
}
