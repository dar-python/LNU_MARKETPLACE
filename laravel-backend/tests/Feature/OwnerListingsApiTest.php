<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OwnerListingsApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 8100;

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
    }

    public function test_owner_listings_requires_authentication(): void
    {
        $this->getJson('/api/v1/listings/mine')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_owner_listings_returns_only_the_authenticated_users_posts_with_moderation_details(): void
    {
        $owner = $this->createUser();
        $reviewer = $this->createUser();
        $outsider = $this->createUser();
        $token = $owner->createToken('owner-listings')->plainTextToken;

        $pendingListing = $this->createListing([
            'owner' => $owner,
            'title' => 'Pending listing',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
            'moderation_note' => null,
            'created_at' => now()->subMinutes(10),
        ]);

        $approvedListing = $this->createListing([
            'owner' => $owner,
            'title' => 'Approved listing',
            'listing_status' => 'available',
            'approved_at' => now()->subMinutes(5),
            'approved_by_user_id' => $reviewer->id,
            'created_at' => now()->subMinutes(5),
        ]);

        $declinedListing = $this->createListing([
            'owner' => $owner,
            'title' => 'Declined listing',
            'listing_status' => 'rejected',
            'approved_at' => null,
            'approved_by_user_id' => null,
            'moderation_note' => 'Missing item proof in the photos.',
            'created_at' => now()->subMinute(),
        ]);

        $this->createListing([
            'owner' => $outsider,
            'title' => 'Other users listing',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/listings/mine?per_page=10');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Owner listings retrieved successfully.')
            ->assertJsonCount(3, 'data.listings')
            ->assertJsonPath('data.meta.total', 3)
            ->assertJsonStructure([
                'data' => [
                    'listings' => [[
                        'id',
                        'user_id',
                        'title',
                        'price',
                        'listing_status',
                        'item_status',
                        'moderation_status',
                        'moderation_label',
                        'admin_note',
                        'reviewed_at',
                        'reviewed_by_name',
                    ]],
                    'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                ],
                'trace_id',
            ]);

        $listingsById = collect($response->json('data.listings'))->keyBy('id');

        $this->assertSame('pending', $listingsById[$pendingListing->id]['moderation_status']);
        $this->assertSame('Under Review - wait for approval', $listingsById[$pendingListing->id]['moderation_label']);
        $this->assertNull($listingsById[$pendingListing->id]['admin_note']);
        $this->assertNull($listingsById[$pendingListing->id]['reviewed_at']);

        $this->assertSame('approved', $listingsById[$approvedListing->id]['moderation_status']);
        $this->assertSame('available', $listingsById[$approvedListing->id]['item_status']);
        $this->assertNotNull($listingsById[$approvedListing->id]['reviewed_at']);
        $this->assertSame($reviewer->fullName(), $listingsById[$approvedListing->id]['reviewed_by_name']);

        $this->assertSame('declined', $listingsById[$declinedListing->id]['moderation_status']);
        $this->assertSame('Declined', $listingsById[$declinedListing->id]['moderation_label']);
        $this->assertSame('Missing item proof in the photos.', $listingsById[$declinedListing->id]['admin_note']);
    }

    private function createUser(): User
    {
        $studentSuffix = str_pad((string) $this->studentIdCounter, 4, '0', STR_PAD_LEFT);
        $studentId = '230'.$studentSuffix;
        $this->studentIdCounter++;

        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Owner',
            'last_name' => 'User '.$this->studentIdCounter,
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
        }

        return User::query()->create($attributes);
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
     * @param array<string, mixed> $overrides
     */
    private function createListing(array $overrides = []): Listing
    {
        $owner = $overrides['owner'] ?? $this->createUser();
        $category = $overrides['category'] ?? $this->createCategory();
        $createdAt = $overrides['created_at'] ?? null;

        unset($overrides['owner'], $overrides['category'], $overrides['created_at']);

        $listing = Listing::query()->create(array_merge([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Listing '.$this->studentIdCounter,
            'description' => 'Listing description',
            'price' => '100.00',
            'item_condition' => 'brandnew',
            'quantity' => 1,
            'is_negotiable' => false,
            'campus_location' => 'LNU Main Campus',
            'listing_status' => 'pending_review',
            'is_flagged' => false,
            'moderation_note' => null,
            'approved_at' => null,
            'approved_by_user_id' => null,
        ], $overrides));

        if ($createdAt !== null) {
            $listing->timestamps = false;
            $listing->forceFill([
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ])->save();
            $listing->timestamps = true;
            $listing->refresh();
        }

        return $listing;
    }
}
