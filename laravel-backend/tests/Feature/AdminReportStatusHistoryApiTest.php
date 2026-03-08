<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\PostReport;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminReportStatusHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 9300;

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

    public function test_admin_can_update_listing_report_status(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => PostReport::STATUS_UNDER_REVIEW,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report status updated.')
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.status', PostReport::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.history.status', PostReport::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.history.changed_by', $admin->id)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('post_reports', [
            'id' => $report->id,
            'status' => PostReport::STATUS_UNDER_REVIEW,
        ]);
    }

    public function test_admin_can_update_user_report_status(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createUserReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/users/'.$report->id.'/status', [
                'status' => PostReport::STATUS_RESOLVED,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report status updated.')
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.status', PostReport::STATUS_RESOLVED)
            ->assertJsonPath('data.history.status', PostReport::STATUS_RESOLVED)
            ->assertJsonPath('data.history.changed_by', $admin->id)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('user_reports', [
            'id' => $report->id,
            'status' => PostReport::STATUS_RESOLVED,
        ]);
    }

    public function test_non_admin_cannot_update_listing_report_status(): void
    {
        $user = $this->createUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($user, ['user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => PostReport::STATUS_UNDER_REVIEW,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('post_reports', [
            'id' => $report->id,
            'status' => PostReport::STATUS_SUBMITTED,
        ]);
    }

    public function test_non_admin_cannot_update_user_report_status(): void
    {
        $user = $this->createUser();
        $report = $this->createUserReport();
        $token = $this->createAccessToken($user, ['user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/users/'.$report->id.'/status', [
                'status' => PostReport::STATUS_UNDER_REVIEW,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('user_reports', [
            'id' => $report->id,
            'status' => PostReport::STATUS_SUBMITTED,
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => 'dismissed',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['status'],
                'trace_id',
            ]);
    }

    public function test_optional_admin_note_is_accepted_and_persisted(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => PostReport::STATUS_RESOLVED,
                'admin_note' => 'Escalated and resolved by moderator.',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.history.admin_note', 'Escalated and resolved by moderator.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('post_report_status_histories', [
            'post_report_id' => $report->id,
            'status' => PostReport::STATUS_RESOLVED,
            'admin_note' => 'Escalated and resolved by moderator.',
            'changed_by' => $admin->id,
        ]);
    }

    public function test_history_row_is_created_when_listing_report_status_changes(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => PostReport::STATUS_UNDER_REVIEW,
            ])
            ->assertOk();

        $this->assertDatabaseHas('post_report_status_histories', [
            'post_report_id' => $report->id,
            'status' => PostReport::STATUS_UNDER_REVIEW,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_history_row_is_created_when_user_report_status_changes(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createUserReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/users/'.$report->id.'/status', [
                'status' => PostReport::STATUS_REJECTED,
            ])
            ->assertOk();

        $this->assertDatabaseHas('user_report_status_histories', [
            'user_report_id' => $report->id,
            'status' => PostReport::STATUS_REJECTED,
            'changed_by' => $admin->id,
        ]);
    }

    public function test_history_endpoint_returns_timeline_entries_in_expected_order(): void
    {
        $admin = $this->createAdminUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $adminToken = $this->createAccessToken($admin, ['admin']);

        $submission = $this->postJson(
            '/api/v1/reports/listings/'.$listing->id,
            $this->reportPayload([
                'reason_category' => 'scam',
                'description' => 'Needs moderation.',
            ]),
            ['Authorization' => 'Bearer '.$adminToken]
        );

        $submission->assertCreated();

        $reportId = (int) $submission->json('data.report.id');
        $report = PostReport::query()->findOrFail($reportId);
        $firstFollowUpAt = now()->addSecond();
        $secondFollowUpAt = now()->addSeconds(2);

        $report->statusHistories()->create([
            'status' => PostReport::STATUS_UNDER_REVIEW,
            'admin_note' => 'Queued for review.',
            'changed_by' => $admin->id,
            'created_at' => $firstFollowUpAt,
            'updated_at' => $firstFollowUpAt,
        ]);

        $report->statusHistories()->create([
            'status' => PostReport::STATUS_RESOLVED,
            'admin_note' => 'Closed after action.',
            'changed_by' => $admin->id,
            'created_at' => $secondFollowUpAt,
            'updated_at' => $secondFollowUpAt,
        ]);

        $report->forceFill([
            'status' => PostReport::STATUS_RESOLVED,
        ])->save();

        $historyResponse = $this->getJson(
            '/api/v1/admin/reports/listings/'.$reportId.'/history',
            ['Authorization' => 'Bearer '.$adminToken]
        );

        $historyResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report history retrieved successfully.')
            ->assertJsonCount(3, 'data.history')
            ->assertJsonPath('data.history.0.status', PostReport::STATUS_SUBMITTED)
            ->assertJsonPath('data.history.1.status', PostReport::STATUS_UNDER_REVIEW)
            ->assertJsonPath('data.history.2.status', PostReport::STATUS_RESOLVED)
            ->assertJsonPath('data.history.0.admin_note', null)
            ->assertJsonPath('data.history.1.admin_note', 'Queued for review.')
            ->assertJsonPath('data.history.2.admin_note', 'Closed after action.')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_history_endpoint_is_admin_only(): void
    {
        $reporter = $this->createUser();
        $report = $this->createPostReport([
            'reporter' => $reporter,
        ]);
        $token = $this->createAccessToken($reporter, ['user']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/admin/reports/listings/'.$report->id.'/history')
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_status_update_response_uses_expected_json_envelope_and_message(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createUserReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/users/'.$report->id.'/status', [
                'status' => PostReport::STATUS_UNDER_REVIEW,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report status updated.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'report' => [
                        'id',
                        'type',
                        'reported_user_id',
                        'reporter_user_id',
                        'reason_category',
                        'description',
                        'status',
                        'evidence_path',
                        'created_at',
                        'updated_at',
                    ],
                    'history' => [
                        'id',
                        'status',
                        'admin_note',
                        'changed_by',
                        'changed_by_user',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'trace_id',
            ]);
    }

    public function test_status_change_writes_activity_log(): void
    {
        $admin = $this->createAdminUser();
        $report = $this->createPostReport();
        $token = $this->createAccessToken($admin, ['admin']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/admin/reports/listings/'.$report->id.'/status', [
                'status' => PostReport::STATUS_RESOLVED,
                'admin_note' => 'Removed offending listing.',
            ])
            ->assertOk();

        $activityLog = ActivityLog::query()
            ->where('action_type', 'report.status_changed')
            ->where('subject_type', (new PostReport)->getMorphClass())
            ->where('subject_id', $report->id)
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertSame($admin->id, (int) $activityLog->actor_user_id);
        $this->assertSame('Report status updated.', $activityLog->description);
        $this->assertSame(PostReport::STATUS_SUBMITTED, $activityLog->metadata['status_from']);
        $this->assertSame(PostReport::STATUS_RESOLVED, $activityLog->metadata['status_to']);
        $this->assertSame('Removed offending listing.', $activityLog->metadata['admin_note']);
    }

    public function test_initial_history_row_is_created_when_listing_report_is_submitted(): void
    {
        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $this->createAccessToken($reporter, ['user']);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, $this->reportPayload());

        $response->assertCreated();

        $reportId = (int) $response->json('data.report.id');

        $this->assertDatabaseHas('post_report_status_histories', [
            'post_report_id' => $reportId,
            'status' => PostReport::STATUS_SUBMITTED,
            'admin_note' => null,
            'changed_by' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'reason_category' => 'spam',
            'description' => 'This should be reviewed.',
        ], $overrides);
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
            'description' => 'Generated report.',
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
