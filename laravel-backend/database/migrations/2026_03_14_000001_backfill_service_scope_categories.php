<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = now();

        $categories = [
            [
                'name' => 'Tutoring',
                'slug' => 'tutoring',
                'description' => 'One-on-one or group academic tutoring services.',
                'sort_order' => 7,
            ],
            [
                'name' => 'Editing',
                'slug' => 'editing',
                'description' => 'Proofreading, revision, and academic editing services.',
                'sort_order' => 8,
            ],
            [
                'name' => 'Design',
                'slug' => 'design',
                'description' => 'Layout, poster, presentation, and related design work.',
                'sort_order' => 9,
            ],
            [
                'name' => 'Commission',
                'slug' => 'commission',
                'description' => 'Creative or original commissioned work for students.',
                'sort_order' => 10,
            ],
            [
                'name' => 'Repair',
                'slug' => 'repair',
                'description' => 'Student repair services for allowed academic items.',
                'sort_order' => 11,
            ],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->updateOrInsert(
                ['slug' => $category['slug']],
                [
                    'parent_id' => null,
                    'name' => $category['name'],
                    'description' => $category['description'],
                    'is_active' => true,
                    'sort_order' => $category['sort_order'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('categories')
            ->whereIn('slug', [
                'tutoring',
                'editing',
                'design',
                'commission',
                'repair',
            ])
            ->delete();
    }
};
