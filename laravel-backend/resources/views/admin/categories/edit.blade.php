{{-- resources/views/admin/categories/edit.blade.php --}}
@extends('admin.layouts.layout')

@section('title', 'Edit — ' . $category->name)

@section('content')

{{-- Breadcrumb --}}
<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb" style="background:none;padding:0;font-size:.85rem;">
        <li class="breadcrumb-item">
            <a href="{{ route('admin.categories.index') }}" style="color:var(--navy);">Categories</a>
        </li>
        <li class="breadcrumb-item active text-muted">{{ $category->name }}</li>
    </ol>
</nav>

<div class="page-header">
    <h2>Edit Category</h2>
    <p>Update the details for <strong>{{ $category->name }}</strong>.</p>
</div>

<div class="lnu-card" style="max-width:680px;">
    <div class="lnu-card-header">
        <span class="lnu-card-title">Category Details</span>
        @if ($category->is_active)
            <span class="lnu-badge badge-approved"><i class="bi bi-circle-fill" style="font-size:.4rem;"></i> Active</span>
        @else
            <span class="lnu-badge" style="background:#F0F3FB;color:var(--muted);"><i class="bi bi-circle" style="font-size:.4rem;"></i> Inactive</span>
        @endif
    </div>

    <div class="p-4">
        <form method="POST" action="{{ route('admin.categories.update', $category) }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label class="font-weight-bold" style="font-size:.88rem;">Name <span class="text-danger">*</span></label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}"
                    class="form-control @error('name') is-invalid @enderror" required>
                @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="font-weight-bold" style="font-size:.88rem;">
                    Slug <span class="text-muted font-weight-normal">(leave blank to keep current)</span>
                </label>
                <input type="text" name="slug" value="{{ old('slug', $category->slug) }}"
                    class="form-control @error('slug') is-invalid @enderror"
                    style="font-family:monospace;">
                @error('slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="font-weight-bold" style="font-size:.88rem;">Description</label>
                <textarea name="description" rows="3"
                    class="form-control @error('description') is-invalid @enderror">{{ old('description', $category->description) }}</textarea>
                @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>

            <div class="row">
                <div class="col-md-7">
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:.88rem;">Parent Category</label>
                        <select name="parent_id" class="form-control @error('parent_id') is-invalid @enderror">
                            <option value="">— None (root) —</option>
                            @foreach ($parentOptions as $parent)
                                <option value="{{ $parent->id }}"
                                    {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
                <div class="col-md-5">
                    <div class="form-group">
                        <label class="font-weight-bold" style="font-size:.88rem;">Sort Order</label>
                        <input type="number" name="sort_order"
                            value="{{ old('sort_order', $category->sort_order) }}" min="0"
                            class="form-control @error('sort_order') is-invalid @enderror">
                        @error('sort_order') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>

            <div class="form-group">
                <div class="custom-control custom-checkbox">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" class="custom-control-input" id="is_active_edit"
                        name="is_active" value="1"
                        {{ old('is_active', $category->is_active) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_active_edit" style="font-size:.88rem;">
                        Active <span class="text-muted">(visible to users)</span>
                    </label>
                </div>
            </div>

            {{-- Meta info --}}
            <div class="rounded p-3 mb-4" style="background:#F8FAFF;border:1px solid var(--border);font-size:.82rem;color:var(--muted);">
                <div class="row">
                    <div class="col-6">
                        <div><span class="font-weight-bold">Listings:</span> {{ $category->listings()->count() }}</div>
                        <div><span class="font-weight-bold">Sub-categories:</span> {{ $category->children()->count() }}</div>
                    </div>
                    <div class="col-6">
                        <div><span class="font-weight-bold">Created:</span> {{ $category->created_at->format('M d, Y g:i A') }}</div>
                        <div><span class="font-weight-bold">Updated:</span> {{ $category->updated_at->format('M d, Y g:i A') }}</div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="{{ route('admin.categories.index') }}" class="btn btn-ghost btn-sm px-3">
                    <i class="bi bi-arrow-left mr-1"></i> Back
                </a>
                <button type="submit" class="btn btn-navy btn-sm px-4">
                    <i class="bi bi-check2 mr-1"></i> Save Changes
                </button>
            </div>

        </form>
    </div>
</div>

@endsection