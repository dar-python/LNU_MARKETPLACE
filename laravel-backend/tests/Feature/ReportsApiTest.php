<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ModerationReport;
use App\Models\ReportEvidence;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportsApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 9100;

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
    }

    public function test_authenticated_user_can_report_another_users_listing(): void
    {
        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, $this->reportPayload([
                'report_category' => 'fraud',
                'description' => 'This listing looks deceptive.',
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report submitted.')
            ->assertJsonPath('data.report.reporter_user_id', $reporter->id)
            ->assertJsonPath('data.report.target_type', ModerationReport::TARGET_TYPE_LISTING)
            ->assertJsonPath('data.report.target_listing_id', $listing->id)
            ->assertJsonPath('data.report.report_category', 'fraud')
            ->assertJsonPath('data.report.description', 'This listing looks deceptive.')
            ->assertJsonPath('data.report.status', ModerationReport::STATUS_PENDING)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'report' => [
                        'id',
                        'reporter_user_id',
                        'target_type',
                        'target_listing_id',
                        'target_user_id',
                        'report_category',
                        'description',
                        'status',
                        'priority',
                        'resolution_action',
                        'listing' => ['id', 'user_id', 'title', 'listing_status'],
                        'user',
                        'evidence',
                    ],
                ],
                'trace_id',
            ]);

        $reportId = (int) $response->json('data.report.id');

        $this->assertDatabaseHas('moderation_reports', [
            'id' => $reportId,
            'reporter_user_id' => $reporter->id,
            'target_type' => ModerationReport::TARGET_TYPE_LISTING,
            'target_listing_id' => $listing->id,
            'target_user_id' => null,
            'report_category' => 'fraud',
            'description' => 'This listing looks deceptive.',
            'status' => ModerationReport::STATUS_PENDING,
        ]);

        $activityLog = ActivityLog::query()
            ->where('action_type', 'report.submitted')
            ->where('subject_id', $reportId)
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertSame($reporter->id, (int) $activityLog->actor_user_id);
        $this->assertSame((new ModerationReport)->getMorphClass(), $activityLog->subject_type);
        $this->assertSame('Report submitted.', $activityLog->description);
    }

    public function test_authenticated_user_can_report_another_user(): void
    {
        $reporter = $this->createUser();
        $target = $this->createUser();
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/users/'.$target->id, $this->reportPayload([
                'report_category' => 'harassment',
                'description' => 'The user sent abusive messages.',
            ]))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report submitted.')
            ->assertJsonPath('data.report.target_type', ModerationReport::TARGET_TYPE_USER)
            ->assertJsonPath('data.report.target_listing_id', null)
            ->assertJsonPath('data.report.target_user_id', $target->id)
            ->assertJsonPath('data.report.user.id', $target->id)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('moderation_reports', [
            'reporter_user_id' => $reporter->id,
            'target_type' => ModerationReport::TARGET_TYPE_USER,
            'target_user_id' => $target->id,
            'target_listing_id' => null,
            'report_category' => 'harassment',
            'description' => 'The user sent abusive messages.',
        ]);
    }

    public function test_user_cannot_report_own_listing(): void
    {
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $owner->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, $this->reportPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing.0', 'You cannot report your own listing.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('moderation_reports', 0);
    }

    public function test_user_cannot_report_self(): void
    {
        $reporter = $this->createUser();
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/users/'.$reporter->id, $this->reportPayload())
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.user.0', 'You cannot report your own account.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('moderation_reports', 0);
    }

    public function test_report_category_is_required_when_submitting_a_report(): void
    {
        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, [
                'description' => 'Missing category.',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['report_category'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('moderation_reports', 0);
    }

    public function test_report_category_must_be_valid_when_submitting_a_report(): void
    {
        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, $this->reportPayload([
                'report_category' => 'scam',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['report_category'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('moderation_reports', 0);
    }

    public function test_description_is_required_when_submitting_a_report(): void
    {
        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/listings/'.$listing->id, [
                'report_category' => 'spam',
                'description' => '   ',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['description'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('moderation_reports', 0);
    }

    public function test_evidence_upload_succeeds_for_a_valid_image(): void
    {
        Storage::fake('public');

        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;
        $image = UploadedFile::fake()->image('evidence.jpg', 900, 900)->size(512);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/reports/listings/'.$listing->id, [
                ...$this->reportPayload([
                    'description' => 'See attached screenshot.',
                ]),
                'evidence' => $image,
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report submitted.')
            ->assertJsonPath('data.report.evidence.mime_type', 'image/jpeg')
            ->assertJsonPath('data.report.evidence.uploaded_by_user_id', $reporter->id)
            ->assertJsonStructure(['trace_id']);

        $reportId = (int) $response->json('data.report.id');
        $storedPath = (string) $response->json('data.report.evidence.file_path');

        $this->assertSame('reports/'.$reportId, dirname($storedPath));
        $this->assertTrue(Storage::disk('public')->exists($storedPath));
        $this->assertDatabaseHas('report_evidence', [
            'moderation_report_id' => $reportId,
            'uploaded_by_user_id' => $reporter->id,
            'file_path' => $storedPath,
            'mime_type' => 'image/jpeg',
        ]);
    }

    public function test_invalid_evidence_type_is_rejected(): void
    {
        Storage::fake('public');

        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;
        $invalidFile = UploadedFile::fake()->create('evidence.pdf', 512, 'application/pdf');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/reports/listings/'.$listing->id, [
                ...$this->reportPayload(),
                'evidence' => $invalidFile,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['evidence'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('report_evidence', 0);
    }

    public function test_oversized_evidence_is_rejected(): void
    {
        Storage::fake('public');

        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;
        $oversizedImage = UploadedFile::fake()->image('oversized.jpg', 2000, 2000)->size(12000);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/reports/listings/'.$listing->id, [
                ...$this->reportPayload(),
                'evidence' => $oversizedImage,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['evidence'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('report_evidence', 0);
    }

    public function test_submitted_report_appears_in_mine_endpoint(): void
    {
        $reporter = $this->createUser();
        $otherReporter = $this->createUser();
        $firstReport = $this->createReport([
            'reporter' => $reporter,
        ]);
        $secondReport = $this->createReport([
            'reporter' => $reporter,
            'target_type' => ModerationReport::TARGET_TYPE_USER,
            'user' => $this->createUser(),
            'report_category' => 'harassment',
        ]);
        $this->createReport([
            'reporter' => $otherReporter,
        ]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/reports/mine');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Reports retrieved successfully.')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(2, 'data.reports')
            ->assertJsonStructure(['trace_id']);

        $ids = array_map('intval', array_column($response->json('data.reports'), 'id'));
        sort($ids);

        $expectedIds = [$firstReport->id, $secondReport->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $ids);
    }

    public function test_reporter_can_view_own_report_detail(): void
    {
        $reporter = $this->createUser();
        $targetUser = $this->createUser();
        $report = $this->createReport([
            'reporter' => $reporter,
            'target_type' => ModerationReport::TARGET_TYPE_USER,
            'user' => $targetUser,
            'report_category' => 'other',
            'description' => 'Detail check.',
        ]);
        $token = $reporter->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/reports/'.$report->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report retrieved successfully.')
            ->assertJsonPath('data.report.id', $report->id)
            ->assertJsonPath('data.report.target_type', ModerationReport::TARGET_TYPE_USER)
            ->assertJsonPath('data.report.target_user_id', $targetUser->id)
            ->assertJsonPath('data.report.user.id', $targetUser->id)
            ->assertJsonPath('data.report.description', 'Detail check.')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_unrelated_user_cannot_view_another_users_report(): void
    {
        $reporter = $this->createUser();
        $outsider = $this->createUser();
        $report = $this->createReport([
            'reporter' => $reporter,
        ]);
        $token = $outsider->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/reports/'.$report->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_report_endpoints_use_the_expected_api_response_envelope_and_messages(): void
    {
        $reporter = $this->createUser();
        $targetUser = $this->createUser();
        $token = $reporter->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/reports/users/'.$targetUser->id, $this->reportPayload([
                'report_category' => 'spam',
                'description' => 'Envelope check.',
            ]));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Report submitted.')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'report' => [
                        'id',
                        'reporter_user_id',
                        'target_type',
                        'target_listing_id',
                        'target_user_id',
                        'report_category',
                        'description',
                        'status',
                        'priority',
                        'resolution_action',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'trace_id',
            ]);
    }

    public function test_uploaded_evidence_path_is_saved_correctly(): void
    {
        Storage::fake('public');

        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;
        $image = UploadedFile::fake()->image('proof.png', 800, 800)->size(300);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/reports/listings/'.$listing->id, [
                ...$this->reportPayload([
                    'report_category' => 'spam',
                    'description' => 'Path check.',
                ]),
                'evidence' => $image,
            ]);

        $response->assertCreated();

        $reportId = (int) $response->json('data.report.id');
        $evidence = ReportEvidence::query()->where('moderation_report_id', $reportId)->firstOrFail();

        $this->assertSame('reports/'.$reportId, dirname($evidence->file_path));
        $this->assertSame($evidence->file_path, $response->json('data.report.evidence.file_path'));
        $this->assertTrue(Storage::disk('public')->exists($evidence->file_path));
    }

    public function test_deleting_a_report_cleans_up_its_evidence_file(): void
    {
        Storage::fake('public');

        $reporter = $this->createUser();
        $owner = $this->createUser();
        $listing = $this->createListing(['owner' => $owner]);
        $token = $reporter->createToken('test-token')->plainTextToken;
        $image = UploadedFile::fake()->image('cleanup.jpg', 640, 640)->size(400);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/reports/listings/'.$listing->id, [
                ...$this->reportPayload([
                    'description' => 'Cleanup check.',
                ]),
                'evidence' => $image,
            ]);

        $response->assertCreated();

        $reportId = (int) $response->json('data.report.id');
        $evidencePath = (string) $response->json('data.report.evidence.file_path');
        $report = ModerationReport::query()->findOrFail($reportId);

        $this->assertTrue(Storage::disk('public')->exists($evidencePath));

        $report->delete();

        $this->assertDatabaseMissing('moderation_reports', ['id' => $reportId]);
        $this->assertDatabaseMissing('report_evidence', ['moderation_report_id' => $reportId]);
        $this->assertFalse(Storage::disk('public')->exists($evidencePath));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function reportPayload(array $overrides = []): array
    {
        return array_merge([
            'report_category' => 'spam',
            'description' => 'This should be reviewed.',
        ], $overrides);
    }

    private function createUser(bool $isDisabled = false): User
    {
        $studentSuffix = str_pad((string) $this->studentIdCounter, 4, '0', STR_PAD_LEFT);
        $studentId = '230'.$studentSuffix;
        $this->studentIdCounter++;

        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Report',
            'last_name' => 'User',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        if (Schema::hasColumn('users', 'is_disabled')) {
            $attributes['is_disabled'] = $isDisabled;
            $attributes['disabled_at'] = $isDisabled ? now() : null;
        }

        $user = User::query()->create($attributes);
        $role = Role::query()->where('code', 'user')->firstOrFail();

        $user->roles()->syncWithoutDetaching([
            $role->id => [
                'assigned_by_user_id' => null,
                'assigned_at' => now(),
            ],
        ]);

        return $user;
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
    private function createReport(array $overrides = []): ModerationReport
    {
        $reporter = $overrides['reporter'] ?? $this->createUser();
        $targetType = $overrides['target_type'] ?? ModerationReport::TARGET_TYPE_LISTING;

        if ($targetType === ModerationReport::TARGET_TYPE_USER) {
            $targetUser = $overrides['user'] ?? $this->createUser();
            $targetListingId = null;
            $targetUserId = $targetUser->id;
        } else {
            $targetListing = $overrides['listing'] ?? $this->createListing();
            $targetListingId = $targetListing->id;
            $targetUserId = null;
        }

        unset($overrides['reporter'], $overrides['listing'], $overrides['user']);

        return ModerationReport::query()->create(array_merge([
            'reporter_user_id' => $reporter->id,
            'target_type' => $targetType,
            'target_listing_id' => $targetListingId,
            'target_user_id' => $targetUserId,
            'report_category' => 'spam',
            'description' => 'Generated report.',
            'status' => ModerationReport::STATUS_PENDING,
            'priority' => ModerationReport::PRIORITY_MEDIUM,
            'resolution_action' => ModerationReport::RESOLUTION_ACTION_NONE,
        ], $overrides));
    }
}
