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
                'name' => 'Electronics',
                'slug' => 'electronics',
                'description' => 'Phones, laptops, and gadgets.',
                'sort_order' => 1,
            ],
            [
                'name' => 'Books',
                'slug' => 'books',
                'description' => 'Textbooks, reviewers, and reference books.',
                'sort_order' => 2,
            ],
            [
                'name' => 'School Supplies',
                'slug' => 'school-supplies',
                'description' => 'Pens, paper, calculators, and related items.',
                'sort_order' => 3,
            ],
            [
                'name' => 'Uniforms',
                'slug' => 'uniforms',
                'description' => 'LNU uniforms and PE attire.',
                'sort_order' => 4,
            ],
            [
                'name' => 'Dorm Essentials',
                'slug' => 'dorm-essentials',
                'description' => 'Appliances, organizers, and living essentials.',
                'sort_order' => 5,
            ],
            [
                'name' => 'Others',
                'slug' => 'others',
                'description' => 'Miscellaneous student marketplace items.',
                'sort_order' => 6,
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
                'electronics',
                'books',
                'school-supplies',
                'uniforms',
                'dorm-essentials',
                'others',
            ])
            ->delete();
    }
};
