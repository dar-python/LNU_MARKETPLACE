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

class InquiriesApiTest extends TestCase
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

    public function test_authenticated_user_can_submit_an_inquiry_to_another_users_listing(): void
    {
        $sender = $this->createUser();
        $listing = $this->createListing();
        $token = $sender->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => 'Is this still available for pickup tomorrow?',
                'preferred_contact_method' => 'email',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inquiry submitted.')
            ->assertJsonPath('data.inquiry.listing_id', $listing->id)
            ->assertJsonPath('data.inquiry.sender_user_id', $sender->id)
            ->assertJsonPath('data.inquiry.recipient_user_id', $listing->user_id)
            ->assertJsonPath('data.inquiry.message', 'Is this still available for pickup tomorrow?')
            ->assertJsonPath('data.inquiry.preferred_contact_method', 'email')
            ->assertJsonPath('data.inquiry.inquiry_status', 'new')
            ->assertJsonStructure([
                'data' => [
                    'inquiry' => [
                        'id',
                        'listing_id',
                        'sender_user_id',
                        'recipient_user_id',
                        'message',
                        'preferred_contact_method',
                        'inquiry_status',
                        'listing' => ['id', 'user_id', 'title', 'listing_status'],
                        'sender' => ['id', 'full_name'],
                        'recipient' => ['id', 'full_name'],
                    ],
                ],
                'trace_id',
            ]);

        $this->assertDatabaseHas('inquiries', [
            'listing_id' => $listing->id,
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $listing->user_id,
            'message' => 'Is this still available for pickup tomorrow?',
            'preferred_contact_method' => 'email',
            'inquiry_status' => 'new',
        ]);
    }

    public function test_user_cannot_inquire_on_their_own_listing(): void
    {
        $owner = $this->createUser();
        $listing = $this->createListing([
            'owner' => $owner,
        ]);
        $token = $owner->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => 'Can I ask myself about this?',
                'preferred_contact_method' => 'email',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing.0', 'You cannot send an inquiry for your own listing.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_message_is_required_when_submitting_an_inquiry(): void
    {
        $sender = $this->createUser();
        $listing = $this->createListing();
        $token = $sender->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => '   ',
                'preferred_contact_method' => 'email',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['message'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_preferred_contact_method_must_be_valid_when_submitting_an_inquiry(): void
    {
        $sender = $this->createUser();
        $listing = $this->createListing();
        $token = $sender->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => 'Can we meet near the library?',
                'preferred_contact_method' => 'sms',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonStructure([
                'errors' => ['preferred_contact_method'],
                'trace_id',
            ]);

        $this->assertDatabaseCount('inquiries', 0);
    }

    public function test_guest_cannot_access_inquiry_endpoints(): void
    {
        $listing = $this->createListing();
        $inquiry = $this->createInquiry();

        $this->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
            'message' => 'Guest attempt',
            'preferred_contact_method' => 'email',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->getJson('/api/v1/inquiries/sent')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->getJson('/api/v1/inquiries/received')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);

        $this->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_sender_can_list_own_sent_inquiries(): void
    {
        $sender = $this->createUser();
        $otherSender = $this->createUser();
        $firstInquiry = $this->createInquiry([
            'sender' => $sender,
        ]);
        $secondInquiry = $this->createInquiry([
            'sender' => $sender,
        ]);
        $this->createInquiry([
            'sender' => $otherSender,
        ]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/inquiries/sent');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Sent inquiries retrieved successfully.')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(2, 'data.inquiries')
            ->assertJsonStructure(['trace_id']);

        $ids = array_map('intval', array_column($response->json('data.inquiries'), 'id'));
        sort($ids);

        $expectedIds = [$firstInquiry->id, $secondInquiry->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $ids);
    }

    public function test_seller_can_list_inquiries_received_for_their_own_listings(): void
    {
        $seller = $this->createUser();
        $otherSeller = $this->createUser();
        $firstInquiry = $this->createInquiry([
            'listing' => $this->createListing(['owner' => $seller]),
        ]);
        $secondInquiry = $this->createInquiry([
            'listing' => $this->createListing(['owner' => $seller]),
        ]);
        $this->createInquiry([
            'listing' => $this->createListing(['owner' => $otherSeller]),
        ]);
        $token = $seller->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/inquiries/received');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Received inquiries retrieved successfully.')
            ->assertJsonPath('data.meta.current_page', 1)
            ->assertJsonPath('data.meta.per_page', 10)
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 1)
            ->assertJsonCount(2, 'data.inquiries')
            ->assertJsonStructure(['trace_id']);

        $ids = array_map('intval', array_column($response->json('data.inquiries'), 'id'));
        sort($ids);

        $expectedIds = [$firstInquiry->id, $secondInquiry->id];
        sort($expectedIds);

        $this->assertSame($expectedIds, $ids);
    }

    public function test_unrelated_user_cannot_view_another_inquiry(): void
    {
        $inquiry = $this->createInquiry();
        $outsider = $this->createUser();
        $token = $outsider->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden.')
            ->assertJsonPath('errors', null)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_inquiry_detail_is_visible_to_the_sender_and_seller_only(): void
    {
        $seller = $this->createUser();
        $sender = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $inquiry = $this->createInquiry([
            'listing' => $listing,
            'sender' => $sender,
            'preferred_contact_method' => 'phone',
            'message' => 'Please contact me after 5 PM.',
        ]);

        $senderToken = $sender->createToken('sender-token')->plainTextToken;
        $sellerToken = $seller->createToken('seller-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$senderToken)
            ->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inquiry retrieved successfully.')
            ->assertJsonPath('data.inquiry.id', $inquiry->id)
            ->assertJsonPath('data.inquiry.sender_user_id', $sender->id)
            ->assertJsonPath('data.inquiry.recipient_user_id', $seller->id)
            ->assertJsonPath('data.inquiry.preferred_contact_method', 'phone')
            ->assertJsonPath('data.inquiry.listing.id', $listing->id)
            ->assertJsonStructure(['trace_id']);

        $this->withHeader('Authorization', 'Bearer '.$sellerToken)
            ->getJson('/api/v1/inquiries/'.$inquiry->id)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Inquiry retrieved successfully.')
            ->assertJsonPath('data.inquiry.id', $inquiry->id)
            ->assertJsonPath('data.inquiry.sender.id', $sender->id)
            ->assertJsonPath('data.inquiry.recipient.id', $seller->id)
            ->assertJsonStructure(['trace_id']);
    }

    public function test_inquiry_endpoints_use_the_expected_api_response_envelope(): void
    {
        $sender = $this->createUser();
        $seller = $this->createUser();
        $listing = $this->createListing(['owner' => $seller]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => 'Envelope check',
                'preferred_contact_method' => 'in_app',
            ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'inquiry' => [
                        'id',
                        'listing_id',
                        'sender_user_id',
                        'recipient_user_id',
                        'message',
                        'preferred_contact_method',
                        'inquiry_status',
                    ],
                ],
                'trace_id',
            ]);
    }

    public function test_listing_must_be_publicly_available_to_receive_an_inquiry(): void
    {
        $sender = $this->createUser();
        $listing = $this->createListing([
            'listing_status' => 'pending_review',
            'approved_at' => null,
            'approved_by_user_id' => null,
        ]);
        $token = $sender->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/listings/'.$listing->id.'/inquiries', [
                'message' => 'Can I inquire about this hidden listing?',
                'preferred_contact_method' => 'email',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed.')
            ->assertJsonPath('errors.listing.0', 'The selected listing is not available for inquiries.')
            ->assertJsonStructure(['trace_id']);

        $this->assertDatabaseCount('inquiries', 0);
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
    private function createInquiry(array $overrides = []): Inquiry
    {
        $listing = $overrides['listing'] ?? $this->createListing();
        $sender = $overrides['sender'] ?? $this->createUser();

        unset($overrides['listing'], $overrides['sender']);

        return Inquiry::query()->create(array_merge([
            'listing_id' => $listing->id,
            'sender_user_id' => $sender->id,
            'recipient_user_id' => $listing->user_id,
            'message' => 'Default inquiry message',
            'preferred_contact_method' => 'email',
            'inquiry_status' => 'new',
        ], $overrides));
    }
}
