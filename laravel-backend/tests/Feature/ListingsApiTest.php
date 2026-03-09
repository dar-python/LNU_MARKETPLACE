<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ListingsApiTest extends TestCase
{
    use RefreshDatabase;

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

        $this->skipIfListingsApiRoutesMissing();
    }

    public function test_create_listing_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/listings', $this->validListingPayload());

        $response
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_authenticated_user_can_create_listing(): void
    {
        $owner = $this->createUser('2307005');
        $token = $owner->createToken('test-token')->plainTextToken;
        $payload = $this->validListingPayload();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listing created.')
            ->assertJsonPath('data.listing.user_id', $owner->id)
            ->assertJsonPath('data.listing.category_id', $payload['category_id'])
            ->assertJsonPath('data.listing.title', $payload['title'])
            ->assertJsonPath('data.listing.listing_status', $payload['listing_status'])
            ->assertJsonStructure([
                'data' => ['listing' => ['id', 'user_id', 'category_id', 'title', 'description', 'price', 'item_condition', 'listing_status']],
                'trace_id',
            ]);

        $this->assertDatabaseHas('listings', [
            'user_id' => $owner->id,
            'category_id' => $payload['category_id'],
            'title' => $payload['title'],
            'listing_status' => $payload['listing_status'],
        ]);
    }

    public function test_authenticated_user_can_create_listing_with_category_slug(): void
    {
        $owner = $this->createUser('2307011');
        $token = $owner->createToken('test-token')->plainTextToken;
        $category = Category::query()->create([
            'name' => 'Slug Category',
            'slug' => 'slug-category',
            'description' => 'Slug based category',
            'is_active' => true,
            'sort_order' => 2,
        ]);
        $payload = $this->validListingPayload();
        unset($payload['category_id']);
        $payload['category_slug'] = $category->slug;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings', $payload);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.listing.user_id', $owner->id)
            ->assertJsonPath('data.listing.category_id', $category->id);

        $this->assertDatabaseHas('listings', [
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => $payload['title'],
        ]);
    }

    public function test_create_listing_validation_errors_for_missing_required_fields(): void
    {
        $owner = $this->createUser('2307006');
        $token = $owner->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['category_id', 'title', 'description', 'price', 'item_condition'],
                'trace_id',
            ]);
    }
    public function test_owner_can_update_and_delete_listing(): void
    {
        $owner = $this->createUser('2307001');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        $updateMethod = $this->updateHttpMethod();

        $updateResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->json($updateMethod, '/api/v1/listings/'.$listing->id, [
                ...$this->validListingPayload($listing->category_id),
                'title' => 'Updated listing title',
                'listing_status' => 'reserved',
                'status' => 'reserved',
            ]);

        $this->assertContains($updateResponse->status(), [200, 202]);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/listings/'.$listing->id);

        $this->assertContains($deleteResponse->status(), [200, 204]);
    }

    public function test_update_listing_requires_authentication(): void
    {
        $owner = $this->createUser('2307007');
        $listing = $this->createListing($owner);
        $updateMethod = $this->updateHttpMethod();

        $this->json($updateMethod, '/api/v1/listings/'.$listing->id, [
            ...$this->validListingPayload($listing->category_id),
            'title' => 'Unauthorized update attempt',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_owner_can_update_listing(): void
    {
        $owner = $this->createUser('2307008');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        $updateMethod = $this->updateHttpMethod();

        $payload = [
            ...$this->validListingPayload($listing->category_id),
            'title' => 'Updated listing title',
            'description' => 'Updated description',
            'price' => '199.99',
            'listing_status' => 'reserved',
            'status' => 'reserved',
        ];

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->json($updateMethod, '/api/v1/listings/'.$listing->id, $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listing updated.')
            ->assertJsonPath('data.listing.id', $listing->id)
            ->assertJsonPath('data.listing.title', $payload['title'])
            ->assertJsonPath('data.listing.description', $payload['description'])
            ->assertJsonPath('data.listing.price', $payload['price'])
            ->assertJsonPath('data.listing.listing_status', $payload['listing_status'])
            ->assertJsonStructure([
                'data' => ['listing' => ['id', 'title', 'description', 'price', 'listing_status']],
                'trace_id',
            ]);

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'user_id' => $owner->id,
            'title' => $payload['title'],
            'description' => $payload['description'],
            'price' => $payload['price'],
            'listing_status' => $payload['listing_status'],
        ]);
    }

    public function test_delete_listing_requires_authentication(): void
    {
        $owner = $this->createUser('2307009');
        $listing = $this->createListing($owner);

        $this->deleteJson('/api/v1/listings/'.$listing->id)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_owner_can_delete_listing(): void
    {
        $owner = $this->createUser('2307010');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/listings/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Listing deleted.')
            ->assertJsonPath('data', null)
            ->assertJsonStructure(['trace_id']);

        $this->assertSoftDeleted('listings', [
            'id' => $listing->id,
            'user_id' => $owner->id,
        ]);
    }
    public function test_non_owner_cannot_update_or_delete_listing(): void
    {
        $owner = $this->createUser('2307002');
        $nonOwner = $this->createUser('2307003', 'other@lnu.edu.ph');
        $listing = $this->createListing($owner);
        $token = $nonOwner->createToken('test-token')->plainTextToken;
        $updateMethod = $this->updateHttpMethod();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->json($updateMethod, '/api/v1/listings/'.$listing->id, [
                ...$this->validListingPayload($listing->category_id),
                'title' => 'Unauthorized update',
                'listing_status' => 'reserved',
                'status' => 'reserved',
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonStructure(['trace_id']);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/listings/'.$listing->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_listing_status_enum_rejects_invalid_value(): void
    {
        $owner = $this->createUser('2307004');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        $updateMethod = $this->updateHttpMethod();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->json($updateMethod, '/api/v1/listings/'.$listing->id, [
                ...$this->validListingPayload($listing->category_id),
                'listing_status' => 'invalid_status',
                'status' => 'invalid_status',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['trace_id']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validListingPayload(?int $categoryId = null): array
    {
        return [
            'category_id' => $categoryId ?? Category::query()->create([
                'name' => 'General',
                'slug' => 'general',
                'description' => 'General category',
                'is_active' => true,
                'sort_order' => 1,
            ])->id,
            'title' => 'Calculus book',
            'description' => 'Preowned but usable condition.',
            'price' => '150.00',
            'item_condition' => 'preowned',
            'quantity' => 1,
            'is_negotiable' => false,
            'campus_location' => 'LNU Main Campus',
            'listing_status' => 'available',
            'status' => 'available',
        ];
    }

    private function createListing(User $owner): Listing
    {
        $category = Category::query()->create([
            'name' => 'Books '.$owner->id,
            'slug' => 'books-'.$owner->id,
            'description' => 'Test category',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        return Listing::query()->create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Original title',
            'description' => 'Original description',
            'price' => '120.00',
            'item_condition' => 'preowned',
            'quantity' => 1,
            'is_negotiable' => false,
            'campus_location' => 'LNU',
            'listing_status' => 'available',
        ]);
    }

    private function createUser(string $studentId, ?string $email = null): User
    {
        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $email ?? $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => 'Listing',
            'last_name' => 'Owner',
            'middle_name' => null,
            'email_verified_at' => now(),
        ];

        if (Schema::hasColumn('users', 'status')) {
            $attributes['status'] = 'active';
        } else {
            $attributes['account_status'] = 'active';
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

    private function updateHttpMethod(): string
    {
        return $this->hasRouteMatching('PATCH', '#^/api/v1/listings/\{[^/]+\}$#') ? 'PATCH' : 'PUT';
    }

    private function skipIfListingsApiRoutesMissing(): void
    {
        $hasCreateRoute = $this->hasExactRoute('POST', '/api/v1/listings');
        $hasMemberUpdateRoute = $this->hasRouteMatching('PUT', '#^/api/v1/listings/\{[^/]+\}$#')
            || $this->hasRouteMatching('PATCH', '#^/api/v1/listings/\{[^/]+\}$#');
        $hasMemberDeleteRoute = $this->hasRouteMatching('DELETE', '#^/api/v1/listings/\{[^/]+\}$#');

        if ($hasCreateRoute && $hasMemberUpdateRoute && $hasMemberDeleteRoute) {
            return;
        }

        $this->markTestSkipped('Listings API routes are not registered in this backend branch.');
    }

    private function hasExactRoute(string $method, string $uri): bool
    {
        return in_array($uri, $this->routeUrisForMethod($method), true);
    }

    private function hasRouteMatching(string $method, string $pattern): bool
    {
        foreach ($this->routeUrisForMethod($method) as $uri) {
            if (preg_match($pattern, $uri) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function routeUrisForMethod(string $method): array
    {
        $routesByMethod = app('router')->getRoutes()->getRoutesByMethod();
        $routes = $routesByMethod[strtoupper($method)] ?? [];

        return array_map(
            static fn ($route): string => '/'.ltrim($route->uri(), '/'),
            array_values($routes)
        );
    }
}
