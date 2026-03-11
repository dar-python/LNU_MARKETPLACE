<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ListingModerationController extends Controller
{
    public function index(): View
    {
        $pendingListings = Listing::query()
            ->with([
                'category:id,name',
                'user:id,student_id,first_name,middle_name,last_name,email',
            ])
            ->where('listing_status', 'pending_review')
            ->latest('created_at')
            ->paginate(12, ['*'], 'pending_page');

        $recentlyModerated = Listing::query()
            ->with([
                'category:id,name',
                'user:id,student_id,first_name,middle_name,last_name,email',
            ])
            ->whereIn('listing_status', ['available', 'rejected'])
            ->latest('updated_at')
            ->limit(8)
            ->get();

        $summary = [
            'pending' => Listing::query()->where('listing_status', 'pending_review')->count(),
            'approved' => Listing::query()->where('listing_status', 'available')->count(),
            'rejected' => Listing::query()->where('listing_status', 'rejected')->count(),
        ];

        return view('admin.listings.index', [
            'pendingListings' => $pendingListings,
            'recentlyModerated' => $recentlyModerated,
            'summary' => $summary,
        ]);
    }

    public function approve(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'moderation_note' => ['nullable', 'string', 'max:255'],
        ]);

        $moderationNote = $this->normalizeModerationNote($validated['moderation_note'] ?? null);

        $listing->fill([
            'listing_status' => 'available',
            'approved_by_user_id' => (int) $request->user()->id,
            'approved_at' => now(),
            'moderation_note' => $moderationNote,
        ])->save();

        return back()->with('status', "Approved '{$listing->title}'.");
    }

    public function decline(Request $request, Listing $listing): RedirectResponse
    {
        $validated = $request->validate([
            'moderation_note' => ['nullable', 'string', 'max:255'],
        ]);

        $moderationNote = $this->normalizeModerationNote($validated['moderation_note'] ?? null);

        $listing->fill([
            'listing_status' => 'rejected',
            'approved_by_user_id' => null,
            'approved_at' => null,
            'moderation_note' => $moderationNote,
        ])->save();

        return back()->with('status', "Declined '{$listing->title}'.");
    }

    private function normalizeModerationNote(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
