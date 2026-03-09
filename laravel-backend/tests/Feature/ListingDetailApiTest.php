<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListingDetailApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 7200;

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

    public function test_can_fetch_a_visible_listing_detail_with_expected_envelope_and_fields(): void
    {
        $category = $this->createCategory();
        $listing = $this->createListing([
            'category' => $category,
            'title' => 'Calculus Book',
            'description' => 'A clean preowned calculus book.',
            'price' => '350.00',
            'item_condition' => 'preowned',
            'campus_location' => 'LNU Main Campus',
            'listing_status' => 'available',
        ]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listing retrieved successfully.')
            ->assertJsonPath('data.listing.id', $listing->id)
            ->assertJsonPath('data.listing.user_id', $listing->user_id)
            ->assertJsonPath('data.listing.category_id', $category->id)
            ->assertJsonPath('data.listing.title', 'Calculus Book')
            ->assertJsonPath('data.listing.description', 'A clean preowned calculus book.')
            ->assertJsonPath('data.listing.price', '350.00')
            ->assertJsonPath('data.listing.item_condition', 'preowned')
            ->assertJsonPath('data.listing.listing_status', 'available')
            ->assertJsonPath('data.listing.campus_location', 'LNU Main Campus')
            ->assertJsonPath('data.listing.category.id', $category->id)
            ->assertJsonPath('data.listing.category.name', $category->name)
            ->assertJsonStructure([
                'data' => [
                    'listing' => [
                        'id',
                        'user_id',
                        'category_id',
                        'title',
                        'description',
                        'price',
                        'item_condition',
                        'listing_status',
                        'campus_location',
                        'category' => ['id', 'name', 'slug'],
                        'images',
                        'created_at',
                        'updated_at',
                    ],
                ],
                'trace_id',
            ]);
    }

    public function test_listing_detail_includes_listing_images(): void
    {
        $owner = $this->createUser();
        $listing = $this->createListing([
            'owner' => $owner,
        ]);

        $firstImage = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_path' => 'listings/'.$listing->id.'/cover.jpg',
            'sort_order' => 0,
            'is_primary' => true,
            'uploaded_by_user_id' => $owner->id,
        ]);

        $secondImage = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_path' => 'listings/'.$listing->id.'/detail.jpg',
            'sort_order' => 1,
            'is_primary' => false,
            'uploaded_by_user_id' => $owner->id,
        ]);

        $response = $this->getJson('/api/v1/listings/'.$listing->id);

        $response
            ->assertOk()
            ->assertJsonCount(2, 'data.listing.images')
            ->assertJsonPath('data.listing.images.0.id', $firstImage->id)
            ->assertJsonPath('data.listing.images.0.image_path', $firstImage->image_path)
            ->assertJsonPath('data.listing.images.0.sort_order', 0)
            ->assertJsonPath('data.listing.images.0.is_primary', true)
            ->assertJsonPath('data.listing.images.1.id', $secondImage->id)
            ->assertJsonPath('data.listing.images.1.image_path', $secondImage->image_path)
            ->assertJsonPath('data.listing.images.1.sort_order', 1)
            ->assertJsonPath('data.listing.images.1.is_primary', false);
    }

    public function test_non_public_listing_detail_is_blocked_by_existing_visibility_rules(): void
    {
        $blockedListings = [
            $this->createListing([
                'is_flagged' => true,
            ]),
            $this->createListing([
                'listing_status' => 'suspended',
            ]),
            $this->createListing([
                'listing_status' => 'available',
                'approved_at' => null,
                'approved_by_user_id' => null,
            ]),
            $this->createListing([
                'listing_status' => 'pending_review',
                'approved_at' => null,
                'approved_by_user_id' => null,
            ]),
        ];

        foreach ($blockedListings as $listing) {
            $this->getJson('/api/v1/listings/'.$listing->id)
                ->assertNotFound()
                ->assertJsonPath('success', false)
                ->assertJsonPath('message', 'Resource not found.')
                ->assertJsonPath('errors', null)
                ->assertJsonStructure(['trace_id']);
        }
    }

    public function test_listing_detail_is_blocked_when_owned_by_disabled_user_if_supported(): void
    {
        if (! Schema::hasColumn('users', 'is_disabled')) {
            $this->markTestSkipped('users.is_disabled column is not available in this schema.');
        }

        $disabledOwner = $this->createUser(true);
        $listing = $this->createListing([
            'owner' => $disabledOwner,
            'approved_by_user_id' => $disabledOwner->id,
        ]);

        $this->getJson('/api/v1/listings/'.$listing->id)
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_nonexistent_listing_detail_returns_not_found(): void
    {
        $this->getJson('/api/v1/listings/999999')
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Resource not found.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
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
            'first_name' => 'Detail',
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
     * @param array<string, mixed> $overrides
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
}
