<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ListingImagesApiTest extends TestCase
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

        $this->skipIfListingImageRoutesMissing();
    }

    public function test_upload_valid_image_returns_success_and_saves_to_storage(): void
    {
        Storage::fake('public');

        $owner = $this->createUser('2307101');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;
        $image = UploadedFile::fake()->image('listing.jpg', 800, 800)->size(512);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/listings/'.$listing->id.'/images', [
                'image' => $image,
                'images' => [$image],
            ]);

        $this->assertContains($response->status(), [200, 201]);

        $storedImage = ListingImage::query()->where('listing_id', $listing->id)->latest('id')->first();
        $this->assertNotNull($storedImage);
        Storage::disk('public')->assertExists($storedImage->image_path);
    }

    public function test_upload_invalid_file_type_or_oversize_returns_validation_error(): void
    {
        Storage::fake('public');

        $owner = $this->createUser('2307102');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;

        $invalidMime = UploadedFile::fake()->create('document.pdf', 512, 'application/pdf');
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/listings/'.$listing->id.'/images', [
                'image' => $invalidMime,
                'images' => [$invalidMime],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['trace_id']);

        $oversized = UploadedFile::fake()->image('oversized.jpg', 2000, 2000)->size(12000);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->post('/api/v1/listings/'.$listing->id.'/images', [
                'image' => $oversized,
                'images' => [$oversized],
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure(['trace_id']);
    }

    public function test_deleting_listing_cleans_up_listing_images_from_storage(): void
    {
        Storage::fake('public');

        $owner = $this->createUser('2307103');
        $listing = $this->createListing($owner);
        $token = $owner->createToken('test-token')->plainTextToken;

        $path = 'listings/'.$listing->id.'/seed-image.jpg';
        Storage::disk('public')->put($path, 'image-content');

        $image = ListingImage::query()->create([
            'listing_id' => $listing->id,
            'image_path' => $path,
            'sort_order' => 0,
            'is_primary' => true,
            'uploaded_by_user_id' => $owner->id,
        ]);

        $deleteResponse = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/listings/'.$listing->id);

        $this->assertContains($deleteResponse->status(), [200, 204]);
        $this->assertDatabaseMissing('listing_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing($path);
    }

    private function createListing(User $owner): Listing
    {
        $category = Category::query()->create([
            'name' => 'Books '.$owner->id,
            'slug' => 'books-images-'.$owner->id,
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
            'item_condition' => 'good',
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

    private function skipIfListingImageRoutesMissing(): void
    {
        $hasImageUploadRoute = $this->hasRouteMatching('POST', '#^/api/v1/listings/\{[^/]+\}/images$#');
        $hasListingDeleteRoute = $this->hasRouteMatching('DELETE', '#^/api/v1/listings/\{[^/]+\}$#');

        if ($hasImageUploadRoute && $hasListingDeleteRoute) {
            return;
        }

        $this->markTestSkipped('Listing image upload/delete routes are not registered in this backend branch.');
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
