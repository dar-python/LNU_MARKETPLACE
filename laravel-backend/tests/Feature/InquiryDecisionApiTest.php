<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\Role;
use App\Models\StudentIdPrefix;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InquiryDecisionApiTest extends TestCase
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

        Role::query()->firstOrCreate(
            ['code' => 'user'],
            [
                'name' => 'User',
                'description' => 'Default student account',
                'is_system' => true,
            ]
        );
    }

    public function test_inquiry_defaults_to_pending_on_creation(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);

        $inquiry = $this->createInquiry($buyer, $listing);

        $this->assertSame(Inquiry::STATUS_PENDING, $inquiry->status);
        $this->assertNull($inquiry->decided_at);
        $this->assertNull($inquiry->decided_by);
    }

    public function test_seller_can_accept_a_pending_inquiry(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inquiry accepted.')
            ->assertJsonPath('data.inquiry.id', $inquiry->id)
            ->assertJsonPath('data.inquiry.status', Inquiry::STATUS_ACCEPTED)
            ->assertJsonPath('data.inquiry.decided_by', $seller->id)
            ->assertJsonStructure([
                'data' => [
                    'inquiry' => [
                        'id',
                        'listing_id',
                        'sender_user_id',
                        'recipient_user_id',
                        'status',
                        'decided_at',
                        'decided_by',
                    ],
                ],
                'trace_id',
            ]);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_ACCEPTED,
            'decided_by' => $seller->id,
            'inquiry_status' => 'resolved',
        ]);
        $this->assertNotNull($response->json('data.inquiry.decided_at'));
        $this->assertNotNull($inquiry->fresh()->responded_at);
    }

    public function test_seller_can_decline_a_pending_inquiry(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_DECLINED,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inquiry declined.')
            ->assertJsonPath('data.inquiry.status', Inquiry::STATUS_DECLINED)
            ->assertJsonPath('data.inquiry.decided_by', $seller->id)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_DECLINED,
            'decided_by' => $seller->id,
            'inquiry_status' => 'closed',
        ]);
    }

    public function test_sender_cannot_decide_their_own_inquiry(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $buyer->createToken('buyer-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_PENDING,
            'decided_at' => null,
            'decided_by' => null,
        ]);
    }

    public function test_unrelated_user_cannot_decide_an_inquiry(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $outsider = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $outsider->createToken('outsider-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_DECLINED,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_deciding_sets_status_decided_at_and_decided_by(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ])
            ->assertOk();

        $inquiry->refresh();

        $this->assertSame(Inquiry::STATUS_ACCEPTED, $inquiry->status);
        $this->assertSame($seller->id, $inquiry->decided_by);
        $this->assertNotNull($inquiry->decided_at);
    }

    public function test_accepted_inquiry_cannot_be_decided_again(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_DECLINED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only pending inquiries can be decided.')
            ->assertJsonPath('errors.status.0', 'The inquiry has already been decided.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_ACCEPTED,
        ]);
    }

    public function test_declined_inquiry_cannot_be_decided_again(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_DECLINED,
            ])
            ->assertOk();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only pending inquiries can be decided.')
            ->assertJsonPath('errors.status.0', 'The inquiry has already been decided.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_DECLINED,
        ]);
    }

    public function test_invalid_status_is_rejected(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => 'pending',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.status.0', 'The selected status is invalid.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseHas('inquiries', [
            'id' => $inquiry->id,
            'status' => Inquiry::STATUS_PENDING,
            'decided_at' => null,
            'decided_by' => null,
        ]);
    }

    public function test_invalid_recipient_owner_relationship_is_forbidden(): void
    {
        $seller = $this->createUser();
        $buyer = $this->createUser();
        $wrongRecipient = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry($buyer, $listing, [
            'recipient' => $wrongRecipient,
        ]);
        $token = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson('/api/v1/inquiries/'.$inquiry->id.'/decision', [
                'status' => Inquiry::STATUS_ACCEPTED,
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
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
            'first_name' => 'Inquiry',
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

    /**
     * @param array<string, mixed> $overrides
     */
    private function createInquiry(User $sender, Listing $listing, array $overrides = []): Inquiry
    {
        $recipient = $overrides['recipient'] ?? $listing->user;

        unset($overrides['recipient']);

        return Inquiry::query()->create(array_merge([
            'listing_id' => $listing->id,
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $recipient->id,
            'subject' => 'Interested buyer',
            'message' => 'Is this still available?',
        ], $overrides))->fresh();
    }
}
