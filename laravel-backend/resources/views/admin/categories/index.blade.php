@extends('admin.layouts.layout', [
    'title'   => 'Categories',
    'heading' => 'Categories',
])

@section('content')
<style>
    .cat-tab-active { color: var(--navy) !important; border-bottom: 2px solid var(--navy); }
</style>

<div class="page-header d-flex align-items-start justify-content-between">
    <div>
        <h2>Categories</h2>
        <p>Manage listing categories and sub-categories.</p>
    </div>
    <button type="button" class="btn btn-navy btn-sm px-3" data-toggle="modal" data-target="#createModal">
        <i class="bi bi-plus-lg mr-1"></i> New Category
    </button>
</div>

{{-- Filter tabs --}}
<ul class="nav nav-tabs mb-3">
    @foreach ([
        'all'      => 'All ('      . $counts['all']      . ')',
        'active'   => 'Active ('   . $counts['active']   . ')',
        'inactive' => 'Inactive (' . $counts['inactive'] . ')',
        'root'     => 'Root ('     . $counts['root']     . ')',
    ] as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $filter === $key ? 'active font-weight-bold cat-tab-active' : 'text-muted' }}"
               href="{{ route('admin.categories.index', ['filter' => $key]) }}">
                {{ $label }}
            </a>
        </li>
    @endforeach
</ul>

{{-- Table --}}
<div class="lnu-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.88rem;">
            <thead style="background:#F8FAFF;">
                <tr>
                    <th class="border-top-0 pl-4" style="color:var(--muted);font-weight:600;">Name</th>
                    <th class="border-top-0" style="color:var(--muted);font-weight:600;">Slug</th>
                    <th class="border-top-0" style="color:var(--muted);font-weight:600;">Parent</th>
                    <th class="border-top-0 text-center" style="color:var(--muted);font-weight:600;">Listings</th>
                    <th class="border-top-0 text-center" style="color:var(--muted);font-weight:600;">Order</th>
                    <th class="border-top-0 text-center" style="color:var(--muted);font-weight:600;">Status</th>
                    <th class="border-top-0 text-right pr-4" style="color:var(--muted);font-weight:600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td class="pl-4 align-middle">
                            <div class="font-weight-bold" style="color:var(--dark-navy);">{{ $category->name }}</div>
                            @if ($category->description)
                                <div class="text-muted" style="font-size:.78rem;max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                    {{ $category->description }}
                                </div>
                            @endif
                        </td>
                        <td class="align-middle">
                            <code style="font-size:.78rem;background:#F0F3FB;padding:2px 7px;border-radius:5px;color:var(--navy);">
                                {{ $category->slug }}
                            </code>
                        </td>
                        <td class="align-middle text-muted">{{ $category->parent?->name ?? '—' }}</td>
                        <td class="align-middle text-center">{{ number_format($category->listings_count) }}</td>
                        <td class="align-middle text-center text-muted">{{ $category->sort_order }}</td>
                        <td class="align-middle text-center">
                            @if ($category->is_active)
                                <span class="lnu-badge badge-approved">
                                    <i class="bi bi-circle-fill" style="font-size:.4rem;"></i> Active
                                </span>
                            @else
                                <span class="lnu-badge" style="background:#F0F3FB;color:var(--muted);">
                                    <i class="bi bi-circle" style="font-size:.4rem;"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="align-middle text-right pr-4">
                            <div class="d-flex align-items-center justify-content-end" style="gap:6px;">

                                {{-- Toggle active --}}
                                <form method="POST" action="{{ route('admin.categories.toggle-active', $category) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-sm btn-ghost px-2 py-1"
                                        title="{{ $category->is_active ? 'Deactivate' : 'Activate' }}">
                                        @if ($category->is_active)
                                            <i class="bi bi-toggle-on" style="color:var(--navy);font-size:1rem;"></i>
                                        @else
                                            <i class="bi bi-toggle-off" style="color:var(--muted);font-size:1rem;"></i>
                                        @endif
                                    </button>
                                </form>

                                {{-- Edit --}}
                                <a href="{{ route('admin.categories.edit', $category) }}"
                                   class="btn btn-sm btn-ghost px-2 py-1" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>

                                {{-- Delete --}}
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                                      onsubmit="return confirm('Delete \'{{ addslashes($category->name) }}\'? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger-soft px-2 py-1" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>

                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-tags d-block mb-2" style="font-size:2rem;opacity:.3;"></i>
                            No categories found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@if ($categories->hasPages())
    <div class="mt-3">{{ $categories->links() }}</div>
@endif


{{-- ── Create Modal ── --}}
<div class="modal fade" id="createModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius:var(--radius);border:1px solid var(--border);">
            <div class="modal-header" style="border-bottom:1px solid var(--border);">
                <h5 class="modal-title font-weight-bold" style="color:var(--dark-navy);">New Category</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>

            <form method="POST" action="{{ route('admin.categories.store') }}">
                @csrf
                <div class="modal-body">

                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:.88rem;">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                            class="form-control form-control-sm @error('name') is-invalid @enderror"
                            placeholder="e.g. Books &amp; Stationery" required>
                        @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:.88rem;">
                            Slug <span class="text-muted font-weight-normal">(auto-generated if empty)</span>
                        </label>
                        <input type="text" name="slug" value="{{ old('slug') }}"
                            class="form-control form-control-sm @error('slug') is-invalid @enderror"
                            placeholder="books-stationery" style="font-family:monospace;">
                        @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:.88rem;">Description</label>
                        <textarea name="description" rows="2"
                            class="form-control form-control-sm @error('description') is-invalid @enderror"
                            placeholder="Optional short description">{{ old('description') }}</textarea>
                        @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row">
                        <div class="col-7">
                            <div class="form-group">
                                <label class="font-weight-bold" style="font-size:.88rem;">Parent Category</label>
                                <select name="parent_id" class="form-control form-control-sm @error('parent_id') is-invalid @enderror">
                                    <option value="">— None (root) —</option>
                                    @foreach ($parentOptions as $parent)
                                        <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                            {{ $parent->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                        <div class="col-5">
                            <div class="form-group">
                                <label class="font-weight-bold" style="font-size:.88rem;">Sort Order</label>
                                <input type="number" name="sort_order" value="{{ old('sort_order', 0) }}" min="0"
                                    class="form-control form-control-sm @error('sort_order') is-invalid @enderror">
                                @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" class="custom-control-input" id="is_active_create"
                            name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                        <label class="custom-control-label" for="is_active_create" style="font-size:.88rem;">
                            Active <span class="text-muted">(visible to users)</span>
                        </label>
                    </div>

                </div>
                <div class="modal-footer" style="border-top:1px solid var(--border);">
                    <button type="button" class="btn btn-ghost btn-sm px-3" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-navy btn-sm px-4">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@if ($errors->any() && old('name'))
<script>$(function(){ $('#createModal').modal('show'); });</script>
@endif
@endpush