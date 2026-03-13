<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostReport;
use App\Models\UserReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class ReportModerationController extends Controller
{
    public function __construct()
    {
        // Share open reports count with the admin layout for the sidebar badge
        View::share(
            'openReportsCount',
            PostReport::whereIn('status', ['submitted', 'pending', 'under_review'])->count() +
            UserReport::whereIn('status', ['submitted', 'pending', 'under_review'])->count()
        );
    }

    // ── Index ───────────────────────────────────────────────────────────────

    /**
     * Show reports list for admin.
     */
    public function index(Request $request)
    {
        $stats = [
            'open' => PostReport::whereIn('status', ['submitted', 'pending'])->count()
                + UserReport::whereIn('status', ['submitted', 'pending'])->count(),

            'under_review' => PostReport::where('status', 'under_review')->count()
                + UserReport::where('status', 'under_review')->count(),

            'resolved' => PostReport::where('status', 'resolved')->count()
                + UserReport::where('status', 'resolved')->count(),

            'total' => PostReport::count() + UserReport::count(),
        ];

        $listingReports = PostReport::with('listing', 'reporter')
            ->latest()
            ->paginate(10);

        $reports = $listingReports->through(fn ($r) => [
            'id' => $r->id,
            'type' => 'listing',
            'subject_name' => optional($r->listing)->title,
            'subject_price' => optional($r->listing)->price,
            'subject_id_str' => null,
            'reporter_name' => optional($r->reporter)->fullName(),
            'reason_category' => $r->reason_category,
            'status' => $r->status,
            'created_at' => $r->created_at,
        ]);

        return view('admin.reports.index', compact('reports', 'stats'));
    }

    // ── Listing Report ─────────────────────────────────────────────────────

    public function showListing(PostReport $postReport)
    {
        $postReport->load([
            'listing.listingImages',
            'listing.user',
            'reporter',
            'statusHistories.changedBy',
        ]);

        return view('admin.reports.listing-show', ['report' => $postReport]);
    }

    public function updateListingStatus(Request $request, PostReport $postReport)
    {
        $request->validate([
            'status' => ['required', 'in:' . implode(',', PostReport::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($postReport->status === $request->status) {
            return back()->with('error', 'Report is already in that status.');
        }

        $adminId = Auth::id();

        DB::transaction(function () use ($request, $postReport, $adminId) {
            $postReport->update(['status' => $request->status]);

            $postReport->statusHistories()->create([
                'status' => $request->status,
                'admin_note' => $request->admin_note,
                'changed_by' => $adminId,
            ]);
        });

        return back()->with('success', 'Report status updated.');
    }

    public function disableListing(Request $request, PostReport $postReport)
    {
        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$postReport->listing) {
            return back()->with('error', 'The reported listing no longer exists.');
        }

        $adminId = Auth::id();

        DB::transaction(function () use ($request, $postReport, $adminId) {

            $postReport->listing->update([
                'listing_status' => 'suspended',
                'moderation_note' => $request->admin_note,
            ]);

            $postReport->update(['status' => PostReport::STATUS_RESOLVED]);

            $postReport->statusHistories()->create([
                'status' => PostReport::STATUS_RESOLVED,
                'admin_note' => 'Listing disabled. ' . $request->admin_note,
                'changed_by' => $adminId,
            ]);
        });

        return back()->with('success', 'Listing suspended and report resolved.');
    }

    // ── User Report ────────────────────────────────────────────────────────

    public function showUser(UserReport $userReport)
    {
        $userReport->load([
            'reportedUser',
            'reporter',
            'statusHistories.changedBy',
        ]);

        return view('admin.reports.user-show', ['report' => $userReport]);
    }

    public function updateUserStatus(Request $request, UserReport $userReport)
    {
        $request->validate([
            'status' => ['required', 'in:' . implode(',', PostReport::STATUSES)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($userReport->status === $request->status) {
            return back()->with('error', 'Report is already in that status.');
        }

        $adminId = Auth::id();

        DB::transaction(function () use ($request, $userReport, $adminId) {
            $userReport->update(['status' => $request->status]);

            $userReport->statusHistories()->create([
                'status' => $request->status,
                'admin_note' => $request->admin_note,
                'changed_by' => $adminId,
            ]);
        });

        return back()->with('success', 'Report status updated.');
    }

    public function suspendUser(Request $request, UserReport $userReport)
    {
        $request->validate([
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        if (!$userReport->reportedUser) {
            return back()->with('error', 'The reported user no longer exists.');
        }

        if ($userReport->reportedUser->is_disabled) {
            return back()->with('error', 'This user is already suspended.');
        }

        if ($userReport->reportedUser->role === 'admin') {
            return back()->with('error', 'Admin accounts cannot be suspended.');
        }

        $adminId = Auth::id();

        DB::transaction(function () use ($request, $userReport, $adminId) {

            $userReport->reportedUser->update([
                'is_disabled' => true,
                'disabled_at' => now(),
            ]);

            $userReport->update(['status' => PostReport::STATUS_RESOLVED]);

            $userReport->statusHistories()->create([
                'status' => PostReport::STATUS_RESOLVED,
                'admin_note' => 'User suspended. ' . $request->admin_note,
                'changed_by' => $adminId,
            ]);
        });

        return back()->with('success', 'User suspended and report resolved.');
    }
}