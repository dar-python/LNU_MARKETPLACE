<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->enum('item_condition', ['new', 'used'])->nullable();
            $table->string('service_type')->nullable();
            $table->enum('service_mode', ['onsite', 'remote', 'meetup'])->nullable();
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->boolean('is_negotiable')->default(false);
            $table->string('meetup_arrangement', 120)->nullable();
            $table->enum('listing_status', ['pending_review', 'available', 'reserved', 'sold', 'rejected', 'suspended', 'archived'])
                ->default('pending_review');
            $table->boolean('is_flagged')->default(false);
            $table->string('moderation_note')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'listing_status', 'created_at']);
            $table->index(['user_id', 'listing_status', 'created_at']);
            $table->index(['listing_status', 'price']);
            $table->index(['item_condition', 'listing_status']);
            $table->fullText(['title', 'description']);
        });

        DB::statement('ALTER TABLE listings ADD CONSTRAINT chk_listings_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE listings ADD CONSTRAINT chk_listings_quantity_positive CHECK (quantity >= 1)');
        DB::statement('ALTER TABLE listings ADD CONSTRAINT chk_listings_view_count_non_negative CHECK (view_count >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};

