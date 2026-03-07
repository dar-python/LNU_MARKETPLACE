<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FavoritesApiTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 7100;

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

    public function test_authenticated_user_can_add_a_favorite(): void
    {
        $user = $this->createUser();
        $listing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => $listing->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Favorite added.')
            ->assertJsonPath('data.listing.id', $listing->id)
            ->assertJsonPath('data.listing.user_id', $listing->user_id)
            ->assertJsonStructure([
                'data' => ['listing' => ['id', 'user_id', 'category_id', 'title', 'listing_status']],
                'trace_id',
            ]);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_authenticated_user_cannot_duplicate_the_same_favorite(): void
    {
        $user = $this->createUser();
        $listing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => $listing->id,
            ])
            ->assertCreated();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => $listing->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing_id.0', 'The listing has already been favorited.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('favorites', 1);
    }

    public function test_authenticated_user_can_remove_a_favorite(): void
    {
        $user = $this->createUser();
        $listing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        Favorite::query()->create([
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/favorites/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Favorite removed.')
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_authenticated_user_can_list_their_favorites(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $firstListing = $this->createListing();
        $secondListing = $this->createListing();
        $otherUsersListing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        Favorite::query()->create([
            'user_id' => $user->id,
            'listing_id' => $firstListing->id,
        ]);
        Favorite::query()->create([
            'user_id' => $user->id,
            'listing_id' => $secondListing->id,
        ]);
        Favorite::query()->create([
            'user_id' => $otherUser->id,
            'listing_id' => $otherUsersListing->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/favorites');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Favorites retrieved successfully.')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(2, 'data.listings')
            ->assertJsonStructure(['trace_id']);

        $ids = array_map('intval', array_column($response->json('data.listings'), 'id'));
        sort($ids);

        $expectedIds = [$firstListing->id, $secondListing->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $ids);
    }

    public function test_guest_cannot_access_favorites_endpoints(): void
    {
        $listing = $this->createListing();

        $this->getJson('/api/v1/favorites')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->postJson('/api/v1/favorites', [
            'listing_id' => $listing->id,
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->deleteJson('/api/v1/favorites/'.$listing->id)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_user_cannot_favorite_a_non_existent_listing(): void
    {
        $user = $this->createUser();
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => 999999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing_id.0', 'The selected listing does not exist.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('favorites', 0);
    }

    public function test_user_cannot_see_another_users_favorites(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $listing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        Favorite::query()->create([
            'user_id' => $otherUser->id,
            'listing_id' => $listing->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/favorites')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.meta.total', 0)
            ->assertJsonCount(0, 'data.listings')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_unavailable_listing_cannot_be_favorited(): void
    {
        $user = $this->createUser();
        $listing = $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => $listing->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing_id.0', 'The selected listing is not available for favoriting.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_user_cannot_remove_another_users_favorite(): void
    {
        $user = $this->createUser();
        $otherUser = $this->createUser();
        $listing = $this->createListing();
        $token = $user->createToken('test-token')->plainTextToken;

        Favorite::query()->create([
            'user_id' => $otherUser->id,
            'listing_id' => $listing->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/favorites/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Favorite removed.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('favorites', [
            'user_id' => $otherUser->id,
            'listing_id' => $listing->id,
        ]);
    }

    public function test_disabled_owner_listing_cannot_be_favorited_when_supported(): void
    {
        if (! Schema::hasColumn('users', 'is_disabled')) {
            $this->markTestSkipped('users.is_disabled column is not available in this schema.');
        }

        $user = $this->createUser();
        $disabledOwner = $this->createUser(true);
        $listing = $this->createListing([
            'owner' => $disabledOwner,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/favorites', [
                'listing_id' => $listing->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing_id.0', 'The selected listing is not available for favoriting.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseMissing('favorites', [
            'user_id' => $user->id,
            'listing_id' => $listing->id,
        ]);
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
            'first_name' => 'Favorite',
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
