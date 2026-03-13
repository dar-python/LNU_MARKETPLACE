@extends('admin.layout', [
    'title'      => 'Reports',
    'heading'    => 'Reports',
    'subheading' => 'All listing and user reports submitted by marketplace users.',
])

@push('styles')
<style>
    :root {
        --navy:      #0D1B6E;
        --dark-navy: #080F45;
        --gold:      #F5C518;
        --blue:      #2E75B6;
        --green:     #1a7d44;
        --green-lt:  #d4edda;
        --orange:    #b35c00;
        --orange-lt: #fff3cd;
        --red:       #c62828;
        --red-lt:    #f8d7da;
        --purple:    #6a1b9a;
        --purple-lt: #f3e5f5;
        --gray:      #6c757d;
        --border:    #E2E8F8;
        --shadow:    0 2px 12px rgba(13,27,110,.07);
        --radius:    14px;
    }

    .stat-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 1.1rem 1.4rem;
        display: flex; align-items: center; gap: 1rem;
    }
    .stat-icon {
        width: 44px; height: 44px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; flex-shrink: 0;
    }
    .stat-icon.orange { background: var(--orange-lt); color: var(--orange); }
    .stat-icon.purple { background: var(--purple-lt); color: var(--purple); }
    .stat-icon.green  { background: var(--green-lt);  color: var(--green);  }
    .stat-icon.red    { background: var(--red-lt);    color: var(--red);    }
    .stat-value { font-size: 1.4rem; font-weight: 800; color: var(--dark-navy); line-height: 1; }
    .stat-label { font-size: .78rem; color: var(--gray); margin-top: .15rem; }

    .filter-bar {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        padding: 1rem 1.25rem;
        margin-bottom: 1.25rem;
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    }
    .filter-bar .form-control,
    .filter-bar select {
        font-size: .85rem; border-radius: 8px;
        border: 1px solid var(--border); height: 36px;
        padding: 0 .75rem; color: var(--dark-navy);
    }
    .filter-bar input[type=text] { min-width: 200px; }
    .filter-bar select { min-width: 140px; }
    .btn-filter {
        height: 36px; padding: 0 1rem;
        background: var(--navy); color: #fff; border: none;
        border-radius: 8px; font-size: .85rem; font-weight: 600;
        cursor: pointer; white-space: nowrap;
    }
    .btn-filter:hover { background: var(--dark-navy); }
    .btn-reset {
        height: 36px; padding: 0 .85rem;
        background: #F0F3FB; color: var(--navy); border: none;
        border-radius: 8px; font-size: .85rem; cursor: pointer;
        white-space: nowrap; text-decoration: none;
        display: inline-flex; align-items: center;
    }
    .btn-reset:hover { background: var(--border); color: var(--dark-navy); text-decoration: none; }

    .table-card {
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        box-shadow: var(--shadow);
        overflow: hidden;
    }
    .table-card-header {
        padding: .9rem 1.25rem;
        border-bottom: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
    }
    .table-card-header .title { font-weight: 700; font-size: .95rem; color: var(--dark-navy); }
    .table-card-header .count { font-size: .8rem; color: var(--gray); }

    table.reports-table { width: 100%; border-collapse: collapse; }
    table.reports-table thead th {
        background: #F4F6FF;
        padding: .65rem 1rem;
        font-size: .72rem; font-weight: 700;
        letter-spacing: .07em; text-transform: uppercase;
        color: var(--gray); border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }
    table.reports-table tbody tr {
        border-bottom: 1px solid #f0f2fb;
        transition: background .1s;
    }
    table.reports-table tbody tr:last-child { border-bottom: none; }
    table.reports-table tbody tr:hover { background: #f8f9ff; }
    table.reports-table td {
        padding: .75rem 1rem; font-size: .86rem;
        color: var(--dark-navy); vertical-align: middle;
    }

    .type-badge {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .7rem; font-weight: 700; border-radius: 6px;
        padding: .25rem .6rem; white-space: nowrap;
    }
    .type-listing { background: #EFF6FF; color: #1D4ED8; }
    .type-user    { background: var(--purple-lt); color: var(--purple); }

    .status-pill {
        display: inline-flex; align-items: center; gap: .3rem;
        font-size: .72rem; font-weight: 600;
        border-radius: 999px; padding: .28rem .75rem;
        white-space: nowrap;
    }
    .status-pill .dot { width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .s-submitted    { background: #e3f2fd; color: #1565c0; }
    .s-submitted .dot    { background: #1565c0; }
    .s-pending      { background: var(--orange-lt); color: var(--orange); }
    .s-pending .dot      { background: var(--orange); }
    .s-under_review { background: var(--purple-lt); color: var(--purple); }
    .s-under_review .dot { background: var(--purple); }
    .s-resolved     { background: var(--green-lt); color: var(--green); }
    .s-resolved .dot     { background: var(--green); }
    .s-rejected     { background: var(--red-lt); color: var(--red); }
    .s-rejected .dot     { background: var(--red); }

    .reason-chip {
        display: inline-block;
        background: #fff3e0; color: #e65100;
        border: 1px solid #ffcc80;
        font-size: .7rem; font-weight: 600;
        border-radius: .25rem; padding: .2rem .5rem;
        text-transform: capitalize; white-space: nowrap;
    }

    .user-cell { display: flex; align-items: center; gap: .55rem; }
    .mini-avatar {
        width: 28px; height: 28px; border-radius: 8px;
        background: #E8EEF9; color: var(--navy);
        display: flex; align-items: center; justify-content: center;
        font-weight: 700; font-size: .72rem; flex-shrink: 0;
    }
    .avatar-listing { background: #EFF6FF; color: #1D4ED8; }
    .avatar-user    { background: var(--purple-lt); color: var(--purple); }
    .user-cell-name  { font-size: .85rem; font-weight: 600; line-height: 1.2; }
    .user-cell-sub   { font-size: .74rem; color: var(--gray); }

    .btn-view {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .3rem .75rem; border-radius: 8px;
        background: #F0F3FB; color: var(--navy);
        font-size: .8rem; font-weight: 600; border: none;
        text-decoration: none; white-space: nowrap;
        transition: background .15s;
    }
    .btn-view:hover { background: var(--border); color: var(--dark-navy); text-decoration: none; }

    .empty-state {
        text-align: center; padding: 3.5rem 1rem; color: var(--gray);
    }
    .empty-state i { font-size: 2.5rem; opacity: .25; display: block; margin-bottom: .75rem; }
    .empty-state p { font-size: .9rem; margin: 0; }

    .pagination-wrap {
        padding: .85rem 1.25rem;
        border-top: 1px solid var(--border);
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: .5rem;
    }
    .pagination-wrap .pg-info { font-size: .82rem; color: var(--gray); }
    .pagination { margin: 0; }
    .page-link {
        border-radius: 8px !important; margin: 0 2px;
        font-size: .82rem; color: var(--navy);
        border-color: var(--border);
    }
    .page-item.active .page-link {
        background: var(--navy); border-color: var(--navy);
    }
</style>
@endpush

@section('content')

{{-- Summary stats --}}
<div class="row mb-4" style="row-gap:.75rem;">
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon orange"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <div class="stat-value">{{ $stats['open'] }}</div>
                <div class="stat-label">Open / Pending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-search"></i></div>
            <div>
                <div class="stat-value">{{ $stats['under_review'] }}</div>
                <div class="stat-label">Under Review</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div>
                <div class="stat-value">{{ $stats['resolved'] }}</div>
                <div class="stat-label">Resolved</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-flag-fill"></i></div>
            <div>
                <div class="stat-value">{{ $stats['total'] }}</div>
                <div class="stat-label">Total Reports</div>
            </div>
        </div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('admin.reports.index') }}">
    <div class="filter-bar">
        <input type="text" name="search" class="form-control"
               placeholder="Search reporter or subject..."
               value="{{ request('search') }}">

        <select name="type" class="form-control">
            <option value="">All Types</option>
            <option value="listing" {{ request('type') === 'listing' ? 'selected' : '' }}>Listing Reports</option>
            <option value="user"    {{ request('type') === 'user'    ? 'selected' : '' }}>User Reports</option>
        </select>

        <select name="status" class="form-control">
            <option value="">All Statuses</option>
            <option value="submitted"    {{ request('status') === 'submitted'    ? 'selected' : '' }}>Submitted</option>
            <option value="pending"      {{ request('status') === 'pending'      ? 'selected' : '' }}>Pending</option>
            <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>Under Review</option>
            <option value="resolved"     {{ request('status') === 'resolved'     ? 'selected' : '' }}>Resolved</option>
            <option value="rejected"     {{ request('status') === 'rejected'     ? 'selected' : '' }}>Rejected</option>
        </select>

        <select name="category" class="form-control">
            <option value="">All Categories</option>
            <option value="scam"                  {{ request('category') === 'scam'                  ? 'selected' : '' }}>Scam</option>
            <option value="inappropriate_content"  {{ request('category') === 'inappropriate_content'  ? 'selected' : '' }}>Inappropriate Content</option>
            <option value="prohibited_item"       {{ request('category') === 'prohibited_item'       ? 'selected' : '' }}>Prohibited Item</option>
            <option value="harassment"            {{ request('category') === 'harassment'            ? 'selected' : '' }}>Harassment</option>
            <option value="impersonation"         {{ request('category') === 'impersonation'         ? 'selected' : '' }}>Impersonation</option>
            <option value="spam"                  {{ request('category') === 'spam'                  ? 'selected' : '' }}>Spam</option>
            <option value="other"                 {{ request('category') === 'other'                 ? 'selected' : '' }}>Other</option>
        </select>

        <button type="submit" class="btn-filter">
            <i class="bi bi-funnel mr-1"></i> Filter
        </button>

        @if(request()->hasAny(['search', 'type', 'status', 'category']))
            <a href="{{ route('admin.reports.index') }}" class="btn-reset">
                <i class="bi bi-x mr-1"></i> Reset
            </a>
        @endif
    </div>
</form>

{{-- Table --}}
<div class="table-card">
    <div class="table-card-header">
        <span class="title">All Reports</span>
        <span class="count">{{ $reports->total() }} {{ Str::plural('report', $reports->total()) }}</span>
    </div>

    @if($reports->isEmpty())
        <div class="empty-state">
            <i class="bi bi-flag"></i>
            <p>No reports found{{ request()->hasAny(['search','type','status','category']) ? ' matching your filters' : '' }}.</p>
        </div>
    @else
        <div class="table-responsive">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Type</th>
                        <th>Subject</th>
                        <th>Reporter</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reports as $report)
                    <tr>
                        <td class="text-monospace" style="color:var(--gray); font-size:.8rem;">
                            #{{ $report['id'] }}
                        </td>
                        <td>
                            @if($report['type'] === 'listing')
                                <span class="type-badge type-listing"><i class="bi bi-shop"></i> Listing</span>
                            @else
                                <span class="type-badge type-user"><i class="bi bi-person"></i> User</span>
                            @endif
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="{{ $report['type'] === 'listing' ? 'mini-avatar avatar-listing' : 'mini-avatar avatar-user' }}">
                                    {{ strtoupper(substr($report['subject_name'] ?? 'U', 0, 1)) }}
                                </div>
                                <div>
                                    <div class="user-cell-name" style="max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ $report['subject_name'] ?? '—' }}
                                    </div>
                                    @if($report['type'] === 'listing' && !empty($report['subject_price']))
                                        <div class="user-cell-sub">₱{{ number_format($report['subject_price'], 2) }}</div>
                                    @elseif($report['type'] === 'user' && !empty($report['subject_id_str']))
                                        <div class="user-cell-sub">{{ $report['subject_id_str'] }}</div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="user-cell">
                                <div class="mini-avatar">{{ strtoupper(substr($report['reporter_name'] ?? '?', 0, 1)) }}</div>
                                <div class="user-cell-name">{{ $report['reporter_name'] ?? 'Unknown' }}</div>
                            </div>
                        </td>
                        <td>
                            <span class="reason-chip">
                                {{ ucfirst(str_replace('_', ' ', $report['reason_category'])) }}
                            </span>
                        </td>
                        <td>
                            <span class="status-pill s-{{ $report['status'] }}">
                                <span class="dot"></span>
                                {{ ucfirst(str_replace('_', ' ', $report['status'])) }}
                            </span>
                        </td>
                        <td style="color:var(--gray); font-size:.82rem; white-space:nowrap;">
                            {{ $report['created_at']->format('M d, Y') }}<br>
                            <span style="font-size:.76rem;">{{ $report['created_at']->diffForHumans() }}</span>
                        </td>
                        <td>
                            <a href="{{ $report['type'] === 'listing'
                                ? route('admin.reports.listings.show', $report['id'])
                                : route('admin.reports.users.show',    $report['id']) }}"
                               class="btn-view">
                                <i class="bi bi-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($reports->hasPages())
        <div class="pagination-wrap">
            <span class="pg-info">
                Showing {{ $reports->firstItem() }}–{{ $reports->lastItem() }} of {{ $reports->total() }}
            </span>
            {{ $reports->appends(request()->query())->links() }}
        </div>
        @endif
    @endif
</div>

@endsection