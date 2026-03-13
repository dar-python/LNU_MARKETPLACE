<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): View
    {
        $filter = $request->query('filter', 'all');

        $query = Category::query()
            ->with('parent:id,name')
            ->withCount('listings')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($filter === 'active') {
            $query->where('is_active', true);
        } elseif ($filter === 'inactive') {
            $query->where('is_active', false);
        } elseif ($filter === 'root') {
            $query->whereNull('parent_id');
        }

        $categories = $query->paginate(15)->withQueryString();

        $counts = [
            'all'      => Category::count(),
            'active'   => Category::where('is_active', true)->count(),
            'inactive' => Category::where('is_active', false)->count(),
            'root'     => Category::whereNull('parent_id')->count(),
        ];

        // For parent dropdown in create form
        $parentOptions = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.categories.index', compact('categories', 'filter', 'counts', 'parentOptions'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['nullable', 'string', 'max:120', 'unique:categories,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id'   => ['nullable', 'integer', 'exists:categories,id'],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name']
        );

        DB::transaction(function () use ($request, $validated, $slug): void {
            $category = Category::query()->create([
                'name'        => $validated['name'],
                'slug'        => $slug,
                'description' => $validated['description'] ?? null,
                'parent_id'   => $validated['parent_id'] ?? null,
                'is_active'   => (bool) ($validated['is_active'] ?? true),
                'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            ]);

            ActivityLog::query()->create([
                'actor_user_id' => $request->user()?->id,
                'action_type'   => 'category.created',
                'subject_type'  => $category->getMorphClass(),
                'subject_id'    => $category->id,
                'description'   => "Category \"{$category->name}\" created.",
                'metadata'      => [
                    'category_id' => $category->id,
                    'name'        => $category->name,
                    'slug'        => $category->slug,
                    'parent_id'   => $category->parent_id,
                ],
                'ip_address'    => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 512),
            ]);
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$validated['name']}\" created.");
    }

    public function edit(Category $category): View
    {
        $parentOptions = Category::query()
            ->whereNull('parent_id')
            ->where('is_active', true)
            ->where('id', '!=', $category->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('admin.categories.edit', compact('category', 'parentOptions'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:100'],
            'slug'        => ['nullable', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'parent_id'   => [
                'nullable', 'integer',
                Rule::exists('categories', 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($category): void {
                    if ((int) $value === $category->id) {
                        $fail('A category cannot be its own parent.');
                    }
                },
            ],
            'is_active'   => ['nullable', 'boolean'],
            'sort_order'  => ['nullable', 'integer', 'min:0'],
        ]);

        $slug = $this->resolveSlug(
            $validated['slug'] ?? null,
            $validated['name'],
            $category->slug
        );

        DB::transaction(function () use ($request, $category, $validated, $slug): void {
            $before = $category->only(['name', 'slug', 'parent_id', 'is_active', 'sort_order']);

            $category->forceFill([
                'name'        => $validated['name'],
                'slug'        => $slug,
                'description' => $validated['description'] ?? null,
                'parent_id'   => $validated['parent_id'] ?? null,
                'is_active'   => (bool) ($validated['is_active'] ?? true),
                'sort_order'  => (int) ($validated['sort_order'] ?? 0),
            ])->save();

            ActivityLog::query()->create([
                'actor_user_id' => $request->user()?->id,
                'action_type'   => 'category.updated',
                'subject_type'  => $category->getMorphClass(),
                'subject_id'    => $category->id,
                'description'   => "Category \"{$category->name}\" updated.",
                'metadata'      => [
                    'category_id' => $category->id,
                    'before'      => $before,
                    'after'       => $category->only(['name', 'slug', 'parent_id', 'is_active', 'sort_order']),
                ],
                'ip_address'    => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 512),
            ]);
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$category->name}\" updated.");
    }

    public function toggleActive(Request $request, Category $category): RedirectResponse
    {
        $newState = ! $category->is_active;

        DB::transaction(function () use ($request, $category, $newState): void {
            $category->forceFill(['is_active' => $newState])->save();

            ActivityLog::query()->create([
                'actor_user_id' => $request->user()?->id,
                'action_type'   => $newState ? 'category.activated' : 'category.deactivated',
                'subject_type'  => $category->getMorphClass(),
                'subject_id'    => $category->id,
                'description'   => "Category \"{$category->name}\" " . ($newState ? 'activated.' : 'deactivated.'),
                'metadata'      => [
                    'category_id' => $category->id,
                    'is_active'   => $newState,
                ],
                'ip_address'    => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 512),
            ]);
        });

        $label = $newState ? 'activated' : 'deactivated';

        return back()->with('status', "Category \"{$category->name}\" {$label}.");
    }

    public function destroy(Request $request, Category $category): RedirectResponse
    {
        if ($category->listings()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['This category cannot be deleted because it has listings attached to it.'],
            ]);
        }

        if ($category->children()->exists()) {
            throw ValidationException::withMessages([
                'category' => ['This category cannot be deleted because it has sub-categories. Remove or reassign them first.'],
            ]);
        }

        $name = $category->name;

        DB::transaction(function () use ($request, $category, $name): void {
            ActivityLog::query()->create([
                'actor_user_id' => $request->user()?->id,
                'action_type'   => 'category.deleted',
                'subject_type'  => $category->getMorphClass(),
                'subject_id'    => $category->id,
                'description'   => "Category \"{$name}\" deleted.",
                'metadata'      => [
                    'category_id' => $category->id,
                    'name'        => $name,
                    'slug'        => $category->slug,
                ],
                'ip_address'    => $request->ip(),
                'user_agent'    => substr((string) $request->userAgent(), 0, 512),
            ]);

            $category->delete();
        });

        return redirect()
            ->route('admin.categories.index')
            ->with('status', "Category \"{$name}\" deleted.");
    }

    private function resolveSlug(?string $provided, string $name, ?string $existing = null): string
    {
        if (is_string($provided) && $provided !== '') {
            return Str::slug($provided);
        }

        // Keep the existing slug on update when none is provided
        if ($existing !== null) {
            return $existing;
        }

        // Auto-generate from name on create; append random suffix if taken
        $base = Str::slug($name);
        $slug = $base;
        $i = 1;

        while (Category::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}