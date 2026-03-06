<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListingBrowseApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 7000;

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

    public function test_returns_paginated_listings_successfully(): void
    {
        foreach (range(1, 12) as $index) {
            $this->createListing([
                'title' => 'Listing '.$index,
            ]);
        }

        $response = $this->getJson('/api/v1/listings');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listings retrieved successfully.')
            ->assertJsonCount(10, 'data.listings')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 12)
            ->assertJsonPath('data.meta.last_page', 2)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_category_filter_works(): void
    {
        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory();

        $matchingListing = $this->createListing([
            'category' => $categoryA,
            'title' => 'Category A listing',
        ]);

        $this->createListing([
            'category' => $categoryB,
            'title' => 'Category B listing',
        ]);

        $response = $this->getJson('/api/v1/listings?category_id='.$categoryA->id);

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$matchingListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_price_range_filter_works(): void
    {
        $this->createListing([
            'title' => 'Cheap',
            'price' => '100.00',
        ]);

        $mid = $this->createListing([
            'title' => 'Mid',
            'price' => '200.00',
        ]);

        $this->createListing([
            'title' => 'Expensive',
            'price' => '300.00',
        ]);

        $response = $this->getJson('/api/v1/listings?min_price=150&max_price=250');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$mid->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_condition_brandnew_filter_works(): void
    {
        $brandnewListing = $this->createListing([
            'item_condition' => 'brandnew',
            'title' => 'Brandnew listing',
        ]);

        $this->createListing([
            'item_condition' => 'preowned',
            'title' => 'Preowned listing',
        ]);

        $response = $this->getJson('/api/v1/listings?condition=brandnew');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$brandnewListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_condition_preowned_filter_works(): void
    {
        $this->createListing([
            'item_condition' => 'brandnew',
            'title' => 'Brandnew listing',
        ]);

        $preownedListing = $this->createListing([
            'item_condition' => 'preowned',
            'title' => 'Preowned listing',
        ]);

        $response = $this->getJson('/api/v1/listings?condition=preowned');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$preownedListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_status_available_filter_works(): void
    {
        $availableListing = $this->createListing([
            'listing_status' => 'available',
        ]);

        $this->createListing([
            'listing_status' => 'reserved',
        ]);

        $this->createListing([
            'listing_status' => 'sold',
        ]);

        $response = $this->getJson('/api/v1/listings?status=available');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$availableListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_status_reserved_filter_works(): void
    {
        $this->createListing([
            'listing_status' => 'available',
        ]);

        $reservedListing = $this->createListing([
            'listing_status' => 'reserved',
        ]);

        $this->createListing([
            'listing_status' => 'sold',
        ]);

        $response = $this->getJson('/api/v1/listings?status=reserved');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$reservedListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_status_sold_filter_works(): void
    {
        $this->createListing([
            'listing_status' => 'available',
        ]);

        $this->createListing([
            'listing_status' => 'reserved',
        ]);

        $soldListing = $this->createListing([
            'listing_status' => 'sold',
        ]);

        $response = $this->getJson('/api/v1/listings?status=sold');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$soldListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_keyword_search_works(): void
    {
        $titleMatch = $this->createListing([
            'title' => 'Alpha Atlas Notebook',
            'description' => 'Regular description',
            'campus_location' => 'Main Gate',
        ]);

        $descriptionMatch = $this->createListing([
            'title' => 'Math notebook',
            'description' => 'Includes an atlas section',
            'campus_location' => 'Science Building',
        ]);

        $locationMatch = $this->createListing([
            'title' => 'Physics notebook',
            'description' => 'Regular description',
            'campus_location' => 'Atlas Hall',
        ]);

        $this->createListing([
            'title' => 'No keyword listing',
            'description' => 'No keyword here',
            'campus_location' => 'Library',
        ]);

        $response = $this->getJson('/api/v1/listings?q=ATLAS');

        $ids = array_map('intval', array_column($response->json('data.listings'), 'id'));
        sort($ids);

        $expected = [$titleMatch->id, $descriptionMatch->id, $locationMatch->id];
        sort($expected);

        $response->assertOk();
        $this->assertSame($expected, $ids);
    }

    public function test_default_sort_works(): void
    {
        $oldest = $this->createListing([
            'title' => 'Old listing',
            'created_at' => now()->subDays(3),
        ]);

        $middle = $this->createListing([
            'title' => 'Middle listing',
            'created_at' => now()->subDays(2),
        ]);

        $newest = $this->createListing([
            'title' => 'Newest listing',
            'created_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk();
        $this->assertSame(
            [$newest->id, $middle->id, $oldest->id],
            array_slice(array_map('intval', array_column($response->json('data.listings'), 'id')), 0, 3)
        );
    }

    public function test_allowed_sort_values_work(): void
    {
        $oldest = $this->createListing([
            'title' => 'Banana',
            'price' => '300.00',
            'created_at' => now()->subDays(3),
        ]);

        $middle = $this->createListing([
            'title' => 'Apple',
            'price' => '100.00',
            'created_at' => now()->subDays(2),
        ]);

        $newest = $this->createListing([
            'title' => 'Cherry',
            'price' => '200.00',
            'created_at' => now()->subDay(),
        ]);

        $expectedBySort = [
            'newest' => [$newest->id, $middle->id, $oldest->id],
            'oldest' => [$oldest->id, $middle->id, $newest->id],
            'price_asc' => [$middle->id, $newest->id, $oldest->id],
            'price_desc' => [$oldest->id, $newest->id, $middle->id],
            'title_asc' => [$middle->id, $oldest->id, $newest->id],
            'title_desc' => [$newest->id, $oldest->id, $middle->id],
        ];

        foreach ($expectedBySort as $sortBy => $expectedIds) {
            $response = $this->getJson('/api/v1/listings?sort_by='.$sortBy);

            $response->assertOk();
            $this->assertSame(
                $expectedIds,
                array_slice(array_map('intval', array_column($response->json('data.listings'), 'id')), 0, 3),
                'Unexpected order for sort_by='.$sortBy
            );
        }
    }

    public function test_invalid_condition_returns_422(): void
    {
        $response = $this->getJson('/api/v1/listings?condition=used');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['condition'], 'trace_id']);
    }

    public function test_invalid_sort_by_returns_422(): void
    {
        $response = $this->getJson('/api/v1/listings?sort_by=created_at');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['sort_by'], 'trace_id']);
    }

    public function test_invalid_sort_dir_returns_422(): void
    {
        $response = $this->getJson('/api/v1/listings?sort_dir=down');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['sort_dir'], 'trace_id']);
    }

    public function test_per_page_over_50_returns_422(): void
    {
        $response = $this->getJson('/api/v1/listings?per_page=51');

        $response
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['errors' => ['per_page'], 'trace_id']);
    }

    public function test_flagged_listings_are_excluded(): void
    {
        $visibleListing = $this->createListing([
            'is_flagged' => false,
            'listing_status' => 'available',
        ]);

        $this->createListing([
            'is_flagged' => true,
            'listing_status' => 'available',
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$visibleListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_disabled_listings_are_excluded(): void
    {
        if (! Schema::hasColumn('users', 'is_disabled')) {
            $this->markTestSkipped('users.is_disabled column is not available in this schema.');
        }

        $activeOwner = $this->createUser(false);
        $disabledOwner = $this->createUser(true);

        $visibleListing = $this->createListing([
            'owner' => $activeOwner,
            'listing_status' => 'available',
        ]);

        $this->createListing([
            'owner' => $disabledOwner,
            'listing_status' => 'available',
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$visibleListing->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
    }

    public function test_pending_unapproved_and_non_public_listings_are_excluded(): void
    {
        $visible = $this->createListing([
            'listing_status' => 'available',
            'approved_at' => now()->subHour(),
        ]);

        $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $this->createListing([
            'listing_status' => 'rejected',
        ]);

        $this->createListing([
            'listing_status' => 'suspended',
        ]);

        $this->createListing([
            'listing_status' => 'archived',
        ]);

        $this->createListing([
            'listing_status' => 'available',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $response = $this->getJson('/api/v1/listings');

        $response->assertOk()->assertJsonCount(1, 'data.listings');
        $this->assertSame(
            [$visible->id],
            array_map('intval', array_column($response->json('data.listings'), 'id'))
        );
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
            'first_name' => 'Browse',
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
            'listing_status' => 'available',
            'is_flagged' => false,
            'approved_at' => now(),
            'approved_by_user_id' => $owner->id,
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
