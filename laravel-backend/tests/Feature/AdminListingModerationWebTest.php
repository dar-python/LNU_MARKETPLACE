<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Listing;
use App\Models\ListingImage;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminListingModerationWebTest extends TestCase
{
    use RefreshDatabase;

    private int $studentIdCounter = 9600;

    private int $categoryCounter = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        config()->set('lnu.allowed_student_id_prefixes', ['230']);
        config()->set('lnu.student_id_prefix_length', 3);
        config()->set('session.driver', 'array');

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

    public function test_guest_is_redirected_to_admin_login(): void
    {
        $this->get('/admin/listings')
            ->assertRedirect('/admin/login');
    }

    public function test_non_admin_cannot_access_moderation_page_or_actions(): void
    {
        $user = $this->createUser();
        $listing = $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $this->actingAs($user)
            ->get('/admin/listings')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/admin/listings/'.$listing->id.'/approve')
            ->assertForbidden();

        $this->actingAs($user)
            ->post('/admin/listings/'.$listing->id.'/decline', [
                'moderation_note' => 'Should not be allowed.',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
    }

    public function test_admin_can_view_pending_listings_only(): void
    {
        $admin = $this->createAdminUser();
        $seller = $this->createUser();
        $category = $this->createCategory();

        $pendingListing = $this->createListing([
            'owner' => $seller,
            'category' => $category,
            'title' => 'Pending Calculus Book',
            'description' => 'Pending description for moderation.',
            'price' => '350.00',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $image = ListingImage::query()->create([
            'listing_id' => $pendingListing->id,
            'image_path' => 'listings/'.$pendingListing->id.'/cover.jpg',
            'sort_order' => 0,
            'is_primary' => true,
            'uploaded_by_user_id' => $seller->id,
        ]);

        $this->createListing([
            'title' => 'Approved Listing',
            'listing_status' => 'available',
            'approved_at' => now(),
        ]);

        $this->createListing([
            'title' => 'Declined Listing',
            'listing_status' => 'rejected',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $response = $this->actingAs($admin)
            ->get('/admin/listings');

        $response
            ->assertOk()
            ->assertSeeText('Pending Calculus Book')
            ->assertSeeText($seller->fullName())
            ->assertSeeText($category->name)
            ->assertSeeText('Pending description for moderation.')
            ->assertSeeText('PHP 350.00')
            ->assertSee(Storage::disk('public')->url($image->image_path), false)
            ->assertDontSeeText('Approved Listing')
            ->assertDontSeeText('Declined Listing');
    }

    public function test_admin_can_approve_a_pending_listing(): void
    {
        $admin = $this->createAdminUser();
        $listing = $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
            'moderation_note' => 'Old note',
        ]);

        $this->actingAs($admin)
            ->post('/admin/listings/'.$listing->id.'/approve')
            ->assertRedirect('/admin/listings');

        $listing->refresh();

        $this->assertSame('available', (string) $listing->listing_status);
        $this->assertSame($admin->id, (int) $listing->approved_by_user_id);
        $this->assertNotNull($listing->approved_at);
        $this->assertNull($listing->moderation_note);

        $activityLog = ActivityLog::query()
            ->where('action_type', 'listing.approved')
            ->where('subject_type', $listing->getMorphClass())
            ->where('subject_id', $listing->id)
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertSame($admin->id, (int) $activityLog->actor_user_id);
        $this->assertSame('Listing approved.', $activityLog->description);
        $this->assertSame('pending_review', $activityLog->metadata['listing_status_from']);
        $this->assertSame('available', $activityLog->metadata['listing_status_to']);
        $this->assertNull($activityLog->metadata['moderation_note']);
    }

    public function test_admin_can_decline_a_pending_listing(): void
    {
        $admin = $this->createAdminUser();
        $listing = $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/listings/'.$listing->id.'/decline', [
                'moderation_note' => 'Please upload clearer proof of ownership.',
            ])
            ->assertRedirect('/admin/listings');

        $listing->refresh();

        $this->assertSame('rejected', (string) $listing->listing_status);
        $this->assertNull($listing->approved_at);
        $this->assertNull($listing->approved_by_user_id);
        $this->assertSame('Please upload clearer proof of ownership.', $listing->moderation_note);

        $activityLog = ActivityLog::query()
            ->where('action_type', 'listing.declined')
            ->where('subject_type', $listing->getMorphClass())
            ->where('subject_id', $listing->id)
            ->first();

        $this->assertNotNull($activityLog);
        $this->assertSame($admin->id, (int) $activityLog->actor_user_id);
        $this->assertSame('Listing declined.', $activityLog->description);
        $this->assertSame('pending_review', $activityLog->metadata['listing_status_from']);
        $this->assertSame('rejected', $activityLog->metadata['listing_status_to']);
        $this->assertSame('Please upload clearer proof of ownership.', $activityLog->metadata['moderation_note']);
    }

    public function test_decline_reason_is_required(): void
    {
        $admin = $this->createAdminUser();
        $listing = $this->createListing([
            'title' => 'Needs validation',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->from('/admin/listings')
            ->post('/admin/listings/'.$listing->id.'/decline', [
                'moderation_note' => '   ',
                'decline_listing_id' => $listing->id,
            ])
            ->assertRedirect('/admin/listings')
            ->assertSessionHasErrors('moderation_note');

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'listing_status' => 'pending_review',
            'moderation_note' => null,
        ]);
    }

    public function test_approved_and_declined_listings_no_longer_appear_in_pending_list(): void
    {
        $admin = $this->createAdminUser();
        $approvedListing = $this->createListing([
            'title' => 'Approve me',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
        $declinedListing = $this->createListing([
            'title' => 'Decline me',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
        $remainingListing = $this->createListing([
            'title' => 'Still pending',
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);

        $this->actingAs($admin)
            ->post('/admin/listings/'.$approvedListing->id.'/approve')
            ->assertRedirect('/admin/listings');

        $this->actingAs($admin)
            ->post('/admin/listings/'.$declinedListing->id.'/decline', [
                'moderation_note' => 'Insufficient listing details.',
            ])
            ->assertRedirect('/admin/listings');

        $this->actingAs($admin)
            ->get('/admin/listings')
            ->assertOk()
            ->assertSeeText($remainingListing->title)
            ->assertDontSeeText($approvedListing->title)
            ->assertDontSeeText($declinedListing->title);
    }

    private function createAdminUser(): User
    {
        return $this->createUser(true);
    }

    private function createUser(bool $isAdmin = false): User
    {
        $studentSuffix = str_pad((string) $this->studentIdCounter, 4, '0', STR_PAD_LEFT);
        $studentId = '230'.$studentSuffix;
        $this->studentIdCounter++;

        $attributes = [
            'student_id' => $studentId,
            'student_id_prefix' => '230',
            'email' => $studentId.'@lnu.edu.ph',
            'password' => 'Safe!Pass123',
            'first_name' => $isAdmin ? 'Admin' : 'Seller',
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
            $attributes['is_disabled'] = false;
            $attributes['disabled_at'] = null;
        }

        $user = User::query()->create($attributes);
        $role = Role::query()->where('code', $isAdmin ? 'admin' : 'user')->firstOrFail();

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
    }
}
