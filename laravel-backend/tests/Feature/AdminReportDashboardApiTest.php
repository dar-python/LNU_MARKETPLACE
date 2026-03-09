<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\PostReport;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use App\Models\UserReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminReportDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 9400;

    private int $categoryCounter = 1;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('lnu.allowed_student_id_prefixes', ['230']);
        config()->set('lnu.student_id_prefix_length', 3);

        StudentIdPrefix::query()->updateOrCreate(
            ['prefix' => '230'],
            [
                'enrollment_year' => 2023,
                'is_active' => true,
                'notes' => 'Test prefix',
            ]
        );

        Role::query()->firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ]
        );

        Role::query()->firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'Admin',
                'description' => 'Platform administrator',
                'is_system' => true,
            ]
        );
    }

    public function test_admin_can_access_report_dashboard_summary_endpoint(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report dashboard summary retrieved successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'summary' => [
                        'overall' => [
                            'total_reports',
                            'total_listing_reports',
                            'total_user_reports',
                            'open_reports',
                        ],
                        'by_status',
                        'by_type',
                        'by_reason_category',
                    ],
                ],
                'trace_id',
            ]);
    }

    public function test_non_admin_cannot_access_report_dashboard_summary_endpoint(): void
    {
        $user = $this->createUser();
        $token = $this->createAccessToken($user, ['user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/summary')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_admin_can_access_report_dashboard_reports_endpoint(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);
        $this->createPostReport();
        $this->createUserReport();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reports retrieved successfully.')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(2, 'data.reports')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_non_admin_cannot_access_report_dashboard_reports_endpoint(): void
    {
        $user = $this->createUser();
        $token = $this->createAccessToken($user, ['user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_summary_returns_correct_combined_counts_across_listing_and_user_reports(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->createPostReport(['reason_category' => 'spam']);
        $this->createPostReport(['reason_category' => 'scam']);
        $this->createUserReport(['reason_category' => 'harassment']);
        $this->createUserReport(['reason_category' => 'other']);
        $this->createUserReport(['reason_category' => 'spam']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.overall.total_reports', 5)
            ->assertJsonPath('data.summary.overall.total_listing_reports', 2)
            ->assertJsonPath('data.summary.overall.total_user_reports', 3)
            ->assertJsonPath('data.summary.by_type.listing', 2)
            ->assertJsonPath('data.summary.by_type.user', 3)
            ->assertJsonPath('data.summary.by_reason_category.spam', 2)
            ->assertJsonPath('data.summary.by_reason_category.scam', 1)
            ->assertJsonPath('data.summary.by_reason_category.harassment', 1)
            ->assertJsonPath('data.summary.by_reason_category.other', 1);
    }

    public function test_summary_returns_correct_status_counts(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->createPostReport(['status' => PostReport::STATUS_SUBMITTED]);
        $this->createPostReport(['status' => PostReport::STATUS_PENDING]);
        $this->createUserReport(['status' => UserReport::STATUS_UNDER_REVIEW]);
        $this->createUserReport(['status' => UserReport::STATUS_RESOLVED]);
        $this->createUserReport(['status' => UserReport::STATUS_REJECTED]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.by_status.submitted', 1)
            ->assertJsonPath('data.summary.by_status.pending', 1)
            ->assertJsonPath('data.summary.by_status.under_review', 1)
            ->assertJsonPath('data.summary.by_status.resolved', 1)
            ->assertJsonPath('data.summary.by_status.rejected', 1)
            ->assertJsonPath('data.summary.overall.open_reports', 3);
    }

    public function test_dashboard_reports_can_be_filtered_by_type(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);
        $listingReport = $this->createPostReport(['description' => 'Listing dashboard item.']);
        $this->createUserReport(['description' => 'User dashboard item.']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?type=listing');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $listingReport->id)
            ->assertJsonPath('data.reports.0.type', 'listing')
            ->assertJsonPath('data.reports.0.listing.id', $listingReport->listing_id)
            ->assertJsonPath('data.reports.0.user', null);
    }

    public function test_dashboard_reports_can_be_filtered_by_status(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->createPostReport(['status' => PostReport::STATUS_SUBMITTED, 'description' => 'Submitted item']);
        $resolvedReport = $this->createUserReport(['status' => UserReport::STATUS_RESOLVED, 'description' => 'Resolved item']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?status=resolved');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $resolvedReport->id)
            ->assertJsonPath('data.reports.0.type', 'user')
            ->assertJsonPath('data.reports.0.status', UserReport::STATUS_RESOLVED);
    }

    public function test_dashboard_reports_can_be_filtered_by_reason_category(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->createPostReport(['reason_category' => 'spam', 'description' => 'Spam item']);
        $matchingReport = $this->createUserReport(['reason_category' => 'harassment', 'description' => 'Harassment item']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?reason_category=harassment');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $matchingReport->id)
            ->assertJsonPath('data.reports.0.reason_category', 'harassment');
    }

    public function test_dashboard_reports_can_be_filtered_by_date_range(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $oldReport = $this->createPostReport(['description' => 'Old listing report']);
        $recentReport = $this->createUserReport(['description' => 'Recent user report']);

        $this->setReportTimestamp($oldReport, CarbonImmutable::now()->subDays(5));
        $this->setReportTimestamp($recentReport, CarbonImmutable::now()->subDay());

        $dateFrom = CarbonImmutable::now()->subDays(2)->format('Y-m-d');
        $dateTo = CarbonImmutable::now()->format('Y-m-d');

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?date_from='.$dateFrom.'&date_to='.$dateTo);

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $recentReport->id)
            ->assertJsonPath('data.reports.0.type', 'user');
    }

    public function test_dashboard_reports_are_ordered_newest_first_by_default(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $oldest = $this->createPostReport(['description' => 'Oldest report']);
        $middle = $this->createPostReport(['description' => 'Middle report']);
        $newest = $this->createUserReport(['description' => 'Newest report']);

        $this->setReportTimestamp($oldest, CarbonImmutable::now()->subDays(3));
        $this->setReportTimestamp($middle, CarbonImmutable::now()->subDay());
        $this->setReportTimestamp($newest, CarbonImmutable::now());

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports');

        $descriptions = array_column($response->json('data.reports'), 'description');

        $this->assertSame([
            'Newest report',
            'Middle report',
            'Oldest report',
        ], $descriptions);
    }

    public function test_dashboard_reports_pagination_uses_existing_meta_structure(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        foreach (range(1, 12) as $index) {
            $this->createPostReport([
                'description' => 'Paginated listing report '.$index,
            ]);
        }

        foreach (range(1, 3) as $index) {
            $this->createUserReport([
                'description' => 'Paginated user report '.$index,
            ]);
        }

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?per_page=5&page=2')
            ->assertOk()
            ->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.per_page', 5)
            ->assertJsonPath('data.meta.total', 15)
            ->assertJsonPath('data.meta.last_page', 3)
            ->assertJsonCount(5, 'data.reports');
    }

    public function test_dashboard_endpoints_use_expected_api_response_envelope_and_messages(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);
        $this->createPostReport(['description' => 'Envelope listing report']);
        $this->createUserReport(['description' => 'Envelope user report']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report dashboard summary retrieved successfully.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['summary'],
                'trace_id',
            ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reports retrieved successfully.')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'reports' => [
                        [
                            'id',
                            'type',
                            'status',
                            'reason_category',
                            'description',
                            'reporter_user_id',
                            'listing_id',
                            'reported_user_id',
                            'evidence_path',
                            'created_at',
                            'updated_at',
                            'listing',
                            'user',
                        ],
                    ],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
                'trace_id',
            ]);
    }

    public function test_dashboard_reports_support_safe_search_on_listing_titles(): void
    {
        $admin = $this->createAdminUser();
        $token = $this->createAccessToken($admin, ['admin']);

        $matchingListing = $this->createListing(['title' => 'Vintage Bike']);
        $otherListing = $this->createListing(['title' => 'Gaming Laptop']);

        $matchingReport = $this->createPostReport([
            'listing' => $matchingListing,
            'description' => 'Bike report',
        ]);

        $this->createPostReport([
            'listing' => $otherListing,
            'description' => 'Laptop report',
        ]);

        $this->createUserReport(['description' => 'User report']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/dashboard/reports?search=Bike');

        $response
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonCount(1, 'data.reports')
            ->assertJsonPath('data.reports.0.id', $matchingReport->id)
            ->assertJsonPath('data.reports.0.type', 'listing')
            ->assertJsonPath('data.reports.0.listing.title', 'Vintage Bike');
    }

    private function setReportTimestamp(PostReport|UserReport $report, CarbonImmutable $timestamp): void
    {
        DB::table($report->getTable())
            ->where('id', $report->id)
            ->update([
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    private function createAdminUser(): User
    {
        return $this->createUser(true);
    }

    private function createUser(bool $isAdmin = false, bool $isDisabled = false): User
    {
        $studentSuffix = str_pad((string) $this->studentIdCounter, 4, '0', STR_PAD_LEFT);
        $studentId = '230'.$studentSuffix;
        $this->studentIdCounter++;

        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => $isAdmin ? 'Admin' : 'Report',
            'last_name' => 'User',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        if (Schema::hasColumn('users', 'role')) {
            $attributes['role'] = $isAdmin ? 'admin' : 'user';
        }

        if (Schema::hasColumn('users', 'is_approved')) {
            $attributes['is_approved'] = true;
        }

        if (Schema::hasColumn('users', 'approved_at')) {
            $attributes['approved_at'] = now();
        }

        if (Schema::hasColumn('users', 'approved_by_user_id')) {
            $attributes['approved_by_user_id'] = null;
        }

        if (Schema::hasColumn('users', 'declined_at')) {
            $attributes['declined_at'] = null;
        }

        if (Schema::hasColumn('users', 'declined_by_user_id')) {
            $attributes['declined_by_user_id'] = null;
        }

        if (Schema::hasColumn('users', 'declined_reason')) {
            $attributes['declined_reason'] = null;
        }

        if (Schema::hasColumn('users', 'is_disabled')) {
            $attributes['is_disabled'] = $isDisabled;
            $attributes['disabled_at'] = $isDisabled ? now() : null;
        }

        $user = User::query()->create($attributes);
        $roleCode = $isAdmin ? 'admin' : 'user';
        $role = Role::query()->where('code', $roleCode)->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
    }

    /**
     * @param  list<string>  $abilities
     */
    private function createAccessToken(User $user, array $abilities): string
    {
        return $user->createToken('test-token', $abilities)->plainTextToken;
    }

    private function createCategory(): Category
    {
        $index = $this->categoryCounter;
        $this->categoryCounter++;

        return Category::query()->create([
            'name' => 'Category '.$index,
            'slug' => 'category-'.$index,
            'description' => 'Test category '.$index,
            'is_active' => true,
            'sort_order' => $index,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createListing(array $overrides = []): Listing
    {
        $owner = $overrides['owner'] ?? $this->createUser();
        $category = $overrides['category'] ?? $this->createCategory();

        unset($overrides['owner'], $overrides['category']);

        return Listing::query()->create(array_merge([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Listing '.$this->categoryCounter,
            'description' => 'Listing description',
            'price' => '100.00',
            'item_condition' => 'preowned',
            'quantity' => 1,
            'is_negotiable' => false,
            'campus_location' => 'LNU Main Campus',
            'listing_status' => 'available',
            'is_flagged' => false,
            'approved_at' => now(),
            'approved_by_user_id' => $owner->id,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createPostReport(array $overrides = []): PostReport
    {
        $reporter = $overrides['reporter'] ?? $this->createUser();
        $targetListing = $overrides['listing'] ?? $this->createListing();

        unset($overrides['reporter'], $overrides['listing']);

        return PostReport::query()->create(array_merge([
            'listing_id' => $targetListing->id,
            'reporter_user_id' => $reporter->id,
            'reason_category' => 'spam',
            'description' => 'Generated listing report.',
            'status' => PostReport::STATUS_SUBMITTED,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createUserReport(array $overrides = []): UserReport
    {
        $reporter = $overrides['reporter'] ?? $this->createUser();
        $targetUser = $overrides['user'] ?? $this->createUser();

        unset($overrides['reporter'], $overrides['user']);

        return UserReport::query()->create(array_merge([
            'reported_user_id' => $targetUser->id,
            'reporter_user_id' => $reporter->id,
            'reason_category' => 'spam',
            'description' => 'Generated user report.',
            'status' => UserReport::STATUS_SUBMITTED,
        ], $overrides));
    }
}
