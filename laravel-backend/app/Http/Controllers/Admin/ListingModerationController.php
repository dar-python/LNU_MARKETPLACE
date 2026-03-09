<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeclineListingRequest;
use App\Models\ActivityLog;
use App\Models\Listing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ListingModerationController extends Controller
{
    private const PENDING_STATUS = 'pending_review';

    public function index(): View
    {
        $listings = Listing::query()
            ->with([
                'user:id,student_id,email,first_name,middle_name,last_name',
                'category:id,name',
                'listingImages' => static function ($query): void {
                    $query
                        ->select(['id', 'listing_id', 'image_path', 'sort_order', 'is_primary'])
                        ->orderByDesc('is_primary')
                        ->orderBy('sort_order')
                        ->orderBy('id');
                },
            ])
            ->where('listing_status', self::PENDING_STATUS)
            ->orderBy('created_at')
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        return view('admin.listings.index', [
            'listings' => $listings,
        ]);
    }

    public function approve(Request $request, Listing $listing): RedirectResponse
    {
        $this->ensureListingIsPending($listing);

        DB::transaction(function () use ($request, $listing): void {
            $statusBefore = (string) $listing->listing_status;

            $listing->forceFill([
                'listing_status' => 'available',
                'approved_at' => now(),
                'approved_by_user_id' => $request->user()?->id,
                'moderation_note' => null,
            ])->save();

            $this->logModerationActivity(
                $request,
                $listing,
                'listing.approved',
                'Listing approved.',
                $statusBefore,
                'available',
                null
            );
        });

        return redirect()
            ->route('admin.listings.index')
            ->with('status', 'Listing approved.');
    }

    public function decline(DeclineListingRequest $request, Listing $listing): RedirectResponse
    {
        $this->ensureListingIsPending($listing);

        $moderationNote = (string) $request->validated('moderation_note');

        DB::transaction(function () use ($request, $listing, $moderationNote): void {
            $statusBefore = (string) $listing->listing_status;

            $listing->forceFill([
                'listing_status' => 'rejected',
                'approved_at' => null,
                'approved_by_user_id' => null,
                'moderation_note' => $moderationNote,
            ])->save();

            $this->logModerationActivity(
                $request,
                $listing,
                'listing.declined',
                'Listing declined.',
                $statusBefore,
                'rejected',
                $moderationNote
            );
        });

        return redirect()
            ->route('admin.listings.index')
            ->with('status', 'Listing declined.');
    }

    private function ensureListingIsPending(Listing $listing): void
    {
        if ((string) $listing->listing_status === self::PENDING_STATUS) {
            return;
        }

        throw ValidationException::withMessages([
            'listing' => ['Only pending listings can be moderated.'],
        ]);
    }

    private function logModerationActivity(
        Request $request,
        Listing $listing,
        string $actionType,
        string $description,
        string $statusFrom,
        string $statusTo,
        ?string $moderationNote
    ): void {
        ActivityLog::query()->create([
            'actor_user_id' => $request->user()?->id,
            'action_type' => $actionType,
            'subject_type' => $listing->getMorphClass(),
            'subject_id' => $listing->id,
            'description' => $description,
            'metadata' => [
                'listing_id' => $listing->id,
                'seller_user_id' => (int) $listing->user_id,
                'listing_status_from' => $statusFrom,
                'listing_status_to' => $statusTo,
                'moderation_note' => $moderationNote,
            ],
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 512),
        ]);
    }
}
