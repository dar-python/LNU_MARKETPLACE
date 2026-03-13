@extends('layouts.admin')

@section('title', 'User Report #' . $report->id)

@push('styles')
<style>
    /* ── Variables ─────────────────────────────────────────────── */
    :root {
        --navy:      #1E3A5F;
        --blue:      #2E75B6;
        --blue-lt:   #D5E8F0;
        --green:     #1a7d44;
        --green-lt:  #d4edda;
        --orange:    #b35c00;
        --orange-lt: #fff3cd;
        --red:       #c62828;
        --red-lt:    #f8d7da;
        --purple:    #6a1b9a;
        --purple-lt: #f3e5f5;
        --gray:      #6c757d;
        --gray-lt:   #f8f9fa;
        --border:    #dee2e6;
        --shadow:    0 1px 4px rgba(0,0,0,.08);
    }

    /* ── Layout ────────────────────────────────────────────────── */
    .report-header {
        background: linear-gradient(135deg, #4a1569 0%, var(--purple) 100%);
        color: #fff;
        border-radius: .5rem;
        padding: 1.5rem 2rem;
        margin-bottom: 1.5rem;
    }
    .report-header .badge-type {
        background: rgba(255,255,255,.2);
        color: #fff;
        font-size: .7rem;
        font-weight: 600;
        letter-spacing: .08em;
        text-transform: uppercase;
        border-radius: 3rem;
        padding: .25rem .75rem;
    }
    .report-header h4 { font-weight: 700; margin: .4rem 0 0; }
    .report-header small { opacity: .75; }

    /* ── Cards ─────────────────────────────────────────────────── */
    .detail-card {
        border: 1px solid var(--border);
        border-radius: .5rem;
        box-shadow: var(--shadow);
        background: #fff;
        margin-bottom: 1.5rem;
    }
    .detail-card .card-header {
        background: var(--gray-lt);
        border-bottom: 1px solid var(--border);
        border-radius: .5rem .5rem 0 0;
        padding: .75rem 1.25rem;
        font-weight: 600;
        font-size: .85rem;
        color: var(--navy);
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .detail-card .card-header i { margin-right: .4rem; color: var(--purple); }
    .detail-card .card-body { padding: 1.25rem; }

    /* ── Meta grid ─────────────────────────────────────────────── */
    .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: .75rem 1.5rem; }
    @media (max-width: 576px) { .meta-grid { grid-template-columns: 1fr; } }
    .meta-item label {
        display: block;
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .08em;
        text-transform: uppercase;
        color: var(--gray);
        margin-bottom: .2rem;
    }
    .meta-item span { font-size: .92rem; color: #212529; }

    /* ── Status badges ─────────────────────────────────────────── */
    .status-badge {
        display: inline-flex; align-items: center; gap: .35rem;
        font-size: .75rem; font-weight: 600;
        border-radius: 3rem; padding: .3rem .8rem;
    }
    .status-badge .dot {
        width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0;
    }
    .status-submitted    { background: #e3f2fd; color: #1565c0; }
    .status-submitted .dot    { background: #1565c0; }
    .status-pending      { background: var(--orange-lt); color: var(--orange); }
    .status-pending .dot      { background: var(--orange); }
    .status-under_review  { background: var(--purple-lt); color: var(--purple); }
    .status-under_review .dot { background: var(--purple); }
    .status-resolved     { background: var(--green-lt); color: var(--green); }
    .status-resolved .dot     { background: var(--green); }
    .status-rejected     { background: var(--red-lt); color: var(--red); }
    .status-rejected .dot     { background: var(--red); }

    /* ── User account status badges ────────────────────────────── */
    .acct-approved  { background: var(--green-lt); color: var(--green); }
    .acct-approved .dot  { background: var(--green); }
    .acct-pending   { background: var(--orange-lt); color: var(--orange); }
    .acct-pending .dot   { background: var(--orange); }
    .acct-suspended { background: var(--red-lt); color: var(--red); }
    .acct-suspended .dot { background: var(--red); }

    /* ── Reason badge ──────────────────────────────────────────── */
    .reason-badge {
        display: inline-block;
        background: #fff3e0; color: #e65100;
        border: 1px solid #ffcc80;
        font-size: .75rem; font-weight: 600;
        border-radius: .25rem; padding: .25rem .6rem;
        text-transform: capitalize;
    }

    /* ── Evidence ──────────────────────────────────────────────── */
    .evidence-box {
        border: 1px dashed var(--border);
        border-radius: .375rem;
        padding: 1rem;
        background: var(--gray-lt);
        display: flex; align-items: center; gap: .75rem;
    }
    .evidence-box i { font-size: 1.5rem; color: var(--purple); }
    .evidence-box a { font-weight: 600; font-size: .9rem; }

    /* ── User profile card ─────────────────────────────────────── */
    .user-profile-card {
        display: flex; align-items: flex-start; gap: 1rem;
    }
    .user-avatar {
        width: 56px; height: 56px; border-radius: 50%;
        background: var(--purple-lt); display: flex; align-items: center;
        justify-content: center; flex-shrink: 0; font-weight: 700;
        color: var(--purple); font-size: 1.2rem;
        border: 2px solid #ce93d8;
    }
    .user-avatar img { width: 56px; height: 56px; border-radius: 50%; object-fit: cover; }
    .user-avatar.reporter-av {
        background: var(--blue-lt); color: var(--blue); border-color: #90caf9;
    }
    .user-details .user-name { font-weight: 700; font-size: 1rem; margin-bottom: .15rem; }
    .user-details .user-sub  { font-size: .82rem; color: var(--gray); }

    /* ── Status history timeline ───────────────────────────────── */
    .timeline { position: relative; padding-left: 2rem; }
    .timeline::before {
        content: ''; position: absolute; left: .65rem; top: 0; bottom: 0;
        width: 2px; background: var(--border);
    }
    .timeline-item { position: relative; margin-bottom: 1.25rem; }
    .timeline-item:last-child { margin-bottom: 0; }
    .timeline-dot {
        position: absolute; left: -1.65rem; top: .2rem;
        width: 14px; height: 14px; border-radius: 50%;
        border: 2px solid #fff; box-shadow: 0 0 0 2px var(--purple);
        background: var(--purple); z-index: 1;
    }
    .timeline-dot.dot-resolved   { background: var(--green);  box-shadow: 0 0 0 2px var(--green); }
    .timeline-dot.dot-rejected   { background: var(--red);    box-shadow: 0 0 0 2px var(--red); }
    .timeline-dot.dot-under_review { background: var(--purple); box-shadow: 0 0 0 2px var(--purple); }
    .timeline-dot.dot-pending    { background: var(--orange); box-shadow: 0 0 0 2px var(--orange); }
    .timeline-dot.dot-submitted  { background: #1565c0;       box-shadow: 0 0 0 2px #1565c0; }

    .timeline-content {
        background: var(--gray-lt);
        border: 1px solid var(--border);
        border-radius: .375rem;
        padding: .75rem 1rem;
    }
    .timeline-content .tl-header {
        display: flex; align-items: center; gap: .5rem;
        margin-bottom: .35rem; flex-wrap: wrap;
    }
    .timeline-content .tl-note {
        font-size: .85rem; color: #495057;
        margin-top: .35rem;
        border-left: 3px solid var(--purple-lt);
        padding-left: .6rem;
    }
    .timeline-content .tl-by { font-size: .78rem; color: var(--gray); }

    /* ── Action panel ──────────────────────────────────────────── */
    .action-panel {
        border: 1px solid var(--border);
        border-radius: .5rem;
        overflow: hidden;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
    }
    .action-panel .ap-header {
        background: #4a1569;
        color: #fff;
        padding: .75rem 1.25rem;
        font-weight: 600;
        font-size: .85rem;
        letter-spacing: .03em;
        text-transform: uppercase;
    }
    .action-panel .ap-header i { margin-right: .4rem; }
    .action-panel .ap-body { background: #fff; padding: 1.25rem; }

    .btn-action {
        width: 100%; margin-bottom: .5rem;
        display: flex; align-items: center; gap: .5rem;
        font-weight: 600; font-size: .85rem; padding: .55rem 1rem;
        border-radius: .375rem; border: none; cursor: pointer;
        transition: opacity .15s, transform .1s;
    }
    .btn-action:hover { opacity: .88; transform: translateY(-1px); }
    .btn-action:last-child { margin-bottom: 0; }
    .btn-action.btn-review   { background: var(--purple-lt); color: var(--purple); border: 1px solid #ce93d8; }
    .btn-action.btn-resolve  { background: var(--green-lt);  color: var(--green);  border: 1px solid #a5d6a7; }
    .btn-action.btn-reject   { background: var(--red-lt);    color: var(--red);    border: 1px solid #ef9a9a; }
    .btn-action.btn-suspend  { background: #fce4ec; color: #880e4f; border: 1px solid #f48fb1; }
    .btn-action.btn-disabled-state { opacity: .45; cursor: not-allowed; transform: none !important; }

    /* ── Update status form ────────────────────────────────────── */
    .status-form { display: none; margin-top: 1rem; }
    .status-form.active { display: block; }
    .status-form textarea { font-size: .85rem; resize: vertical; min-height: 80px; }
    .status-form .form-label { font-size: .8rem; font-weight: 600; color: var(--navy); }

    /* ── Back link ─────────────────────────────────────────────── */
    .back-link {
        display: inline-flex; align-items: center; gap: .35rem;
        font-size: .85rem; color: var(--purple); margin-bottom: 1rem;
        text-decoration: none; font-weight: 500;
    }
    .back-link:hover { text-decoration: underline; color: #4a1569; }
</style>
@endpush

@section('content')

{{-- Back link --}}
<a href="{{ route('admin.reports.index') }}" class="back-link">
    <i class="fas fa-arrow-left"></i> Back to Reports
</a>

{{-- Flash messages --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

{{-- Header --}}
<div class="report-header">
    <span class="badge-type"><i class="fas fa-user-slash mr-1"></i>User Report</span>
    <h4>Report #{{ $report->id }}</h4>
    <small>Submitted {{ $report->created_at->format('M d, Y \a\t g:i A') }}</small>
</div>

<div class="row">

    {{-- ── LEFT COLUMN ────────────────────────────────────────── --}}
    <div class="col-lg-8">

        {{-- Report Details --}}
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-info-circle"></i>Report Details</div>
            <div class="card-body">
                <div class="meta-grid mb-3">
                    <div class="meta-item">
                        <label>Status</label>
                        <span>
                            <span class="status-badge status-{{ $report->status }}">
                                <span class="dot"></span>
                                {{ ucfirst(str_replace('_', ' ', $report->status)) }}
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <label>Reason Category</label>
                        <span>
                            <span class="reason-badge">
                                {{ ucfirst(str_replace('_', ' ', $report->reason_category)) }}
                            </span>
                        </span>
                    </div>
                    <div class="meta-item">
                        <label>Report ID</label>
                        <span class="text-monospace">#{{ $report->id }}</span>
                    </div>
                    <div class="meta-item">
                        <label>Submitted</label>
                        <span>{{ $report->created_at->format('M d, Y g:i A') }}</span>
                    </div>
                    @if($report->updated_at->ne($report->created_at))
                    <div class="meta-item">
                        <label>Last Updated</label>
                        <span>{{ $report->updated_at->format('M d, Y g:i A') }}</span>
                    </div>
                    @endif
                </div>

                <div class="mb-0">
                    <label class="meta-item"><label>Description</label></label>
                    <p class="mb-0" style="font-size:.92rem; line-height:1.6; color:#212529;">
                        {{ $report->description ?? '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- Evidence --}}
        @if($report->evidence_path)
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-paperclip"></i>Evidence</div>
            <div class="card-body">
                <div class="evidence-box">
                    <i class="fas fa-file-image"></i>
                    <div>
                        <div><a href="{{ Storage::url($report->evidence_path) }}" target="_blank">
                            View Attached Evidence
                        </a></div>
                        <small class="text-muted">{{ basename($report->evidence_path) }}</small>
                    </div>
                    <a href="{{ Storage::url($report->evidence_path) }}" target="_blank"
                       class="btn btn-sm btn-outline-secondary ml-auto">
                        <i class="fas fa-external-link-alt mr-1"></i> Open
                    </a>
                </div>
            </div>
        </div>
        @endif

        {{-- Reported User --}}
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-user-alt-slash"></i>Reported User</div>
            <div class="card-body">
                @if($report->reportedUser)
                @php $reportedUser = $report->reportedUser; @endphp
                <div class="user-profile-card mb-3">
                    <div class="user-avatar">
                        @if($reportedUser->profile_photo_path)
                            <img src="{{ Storage::url($reportedUser->profile_photo_path) }}" alt="Avatar">
                        @else
                            {{ strtoupper(substr($reportedUser->first_name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="user-details">
                        <div class="user-name">{{ $reportedUser->fullName() }}</div>
                        <div class="user-sub">{{ $reportedUser->email ?? '—' }}</div>
                        <div class="user-sub">Student ID: {{ $reportedUser->student_id }}</div>
                        <div class="mt-1">
                            <span class="status-badge acct-{{ $reportedUser->apiStatus() }}" style="font-size:.68rem; padding:.15rem .5rem;">
                                <span class="dot"></span>
                                Account: {{ ucfirst($reportedUser->apiStatus()) }}
                            </span>
                            @if($reportedUser->is_disabled)
                                <span class="status-badge status-rejected ml-1" style="font-size:.68rem; padding:.15rem .5rem;">
                                    <span class="dot"></span> Disabled
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Reported user stats --}}
                <div class="row text-center" style="font-size:.82rem;">
                    <div class="col-4">
                        <div class="font-weight-700" style="font-size:1.1rem;">
                            {{ $reportedUser->listings()->count() }}
                        </div>
                        <div class="text-muted">Listings</div>
                    </div>
                    <div class="col-4">
                        <div class="font-weight-700" style="font-size:1.1rem;">
                            {{ $reportedUser->userReportsReceived()->count() }}
                        </div>
                        <div class="text-muted">Reports Received</div>
                    </div>
                    <div class="col-4">
                        <div class="font-weight-700" style="font-size:1.1rem;">
                            {{ $reportedUser->created_at->diffInDays(now()) }}d
                        </div>
                        <div class="text-muted">Account Age</div>
                    </div>
                </div>
                @else
                    <p class="text-muted mb-0">
                        <i class="fas fa-exclamation-triangle mr-1 text-warning"></i>
                        Reported user account no longer exists.
                    </p>
                @endif
            </div>
        </div>

        {{-- Reporter --}}
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-user"></i>Reported By</div>
            <div class="card-body">
                @if($report->reporter)
                <div class="user-profile-card">
                    <div class="user-avatar reporter-av">
                        @if($report->reporter->profile_photo_path)
                            <img src="{{ Storage::url($report->reporter->profile_photo_path) }}" alt="Avatar">
                        @else
                            {{ strtoupper(substr($report->reporter->first_name, 0, 1)) }}
                        @endif
                    </div>
                    <div class="user-details">
                        <div class="user-name">{{ $report->reporter->fullName() }}</div>
                        <div class="user-sub">{{ $report->reporter->email ?? $report->reporter->student_id }}</div>
                        <div class="mt-1">
                            <span class="status-badge acct-{{ $report->reporter->apiStatus() }}" style="font-size:.68rem; padding:.15rem .5rem;">
                                <span class="dot"></span>
                                {{ ucfirst($report->reporter->apiStatus()) }}
                            </span>
                        </div>
                    </div>
                </div>
                @else
                    <p class="text-muted mb-0">Reporter account no longer exists.</p>
                @endif
            </div>
        </div>

        {{-- Status History Timeline --}}
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-history"></i>Status History</div>
            <div class="card-body">
                @if($report->statusHistories->isEmpty())
                    <p class="text-muted mb-0 text-center py-2">
                        <i class="fas fa-clock mr-1"></i>No status changes recorded yet.
                    </p>
                @else
                    <div class="timeline">
                        @foreach($report->statusHistories->sortByDesc('created_at') as $history)
                        <div class="timeline-item">
                            <div class="timeline-dot dot-{{ $history->status }}"></div>
                            <div class="timeline-content">
                                <div class="tl-header">
                                    <span class="status-badge status-{{ $history->status }}" style="font-size:.72rem; padding:.2rem .6rem;">
                                        <span class="dot"></span>
                                        {{ ucfirst(str_replace('_', ' ', $history->status)) }}
                                    </span>
                                    <span class="text-muted" style="font-size:.78rem;">
                                        {{ $history->created_at->format('M d, Y g:i A') }}
                                        &bull; {{ $history->created_at->diffForHumans() }}
                                    </span>
                                </div>
                                @if($history->admin_note)
                                    <div class="tl-note">{{ $history->admin_note }}</div>
                                @endif
                                @if($history->changedBy)
                                    <div class="tl-by mt-1">
                                        <i class="fas fa-user-shield mr-1"></i>
                                        Changed by {{ $history->changedBy->fullName() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

    </div>{{-- /col-lg-8 --}}

    {{-- ── RIGHT COLUMN ────────────────────────────────────────── --}}
    <div class="col-lg-4">

        {{-- Actions Panel --}}
        <div class="action-panel">
            <div class="ap-header"><i class="fas fa-cog"></i>Actions</div>
            <div class="ap-body">

                @php $resolved = in_array($report->status, ['resolved', 'rejected']); @endphp

                {{-- Mark Under Review --}}
                @if($report->status !== 'under_review' && !$resolved)
                <button class="btn-action btn-review" onclick="toggleForm('form-review')">
                    <i class="fas fa-search"></i> Mark Under Review
                </button>
                <div id="form-review" class="status-form">
                    <form method="POST" action="{{ route('admin.reports.users.status', $report->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="under_review">
                        <div class="form-group">
                            <label class="form-label">Admin Note <span class="text-muted font-weight-normal">(optional)</span></label>
                            <textarea name="admin_note" class="form-control" placeholder="Add a note..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary btn-block">Confirm</button>
                    </form>
                </div>
                @endif

                {{-- Resolve --}}
                @if($report->status !== 'resolved' && !$resolved)
                <button class="btn-action btn-resolve" onclick="toggleForm('form-resolve')">
                    <i class="fas fa-check-circle"></i> Mark as Resolved
                </button>
                <div id="form-resolve" class="status-form">
                    <form method="POST" action="{{ route('admin.reports.users.status', $report->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="resolved">
                        <div class="form-group">
                            <label class="form-label">Admin Note <span class="text-muted font-weight-normal">(optional)</span></label>
                            <textarea name="admin_note" class="form-control" placeholder="Resolution summary..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-success btn-block">Confirm</button>
                    </form>
                </div>
                @endif

                {{-- Reject --}}
                @if($report->status !== 'rejected' && !$resolved)
                <button class="btn-action btn-reject" onclick="toggleForm('form-reject')">
                    <i class="fas fa-times-circle"></i> Reject Report
                </button>
                <div id="form-reject" class="status-form">
                    <form method="POST" action="{{ route('admin.reports.users.status', $report->id) }}">
                        @csrf @method('PATCH')
                        <input type="hidden" name="status" value="rejected">
                        <div class="form-group">
                            <label class="form-label">Reason <span class="text-muted font-weight-normal">(optional)</span></label>
                            <textarea name="admin_note" class="form-control" placeholder="Why is this report rejected?"></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-danger btn-block">Confirm</button>
                    </form>
                </div>
                @endif

                {{-- Suspend User --}}
                @if($report->reportedUser)
                    @if(!$report->reportedUser->is_disabled)
                    <button class="btn-action btn-suspend {{ $resolved ? 'btn-disabled-state' : '' }}"
                        onclick="{{ $resolved ? 'void(0)' : 'toggleForm(\'form-suspend\')' }}">
                        <i class="fas fa-user-lock"></i> Suspend User
                        @if($resolved) <small class="ml-auto text-muted">Report closed</small> @endif
                    </button>
                    <div id="form-suspend" class="status-form">
                        <form method="POST" action="{{ route('admin.reports.users.suspend', $report->id) }}">
                            @csrf
                            <div class="alert alert-danger py-2 px-3" style="font-size:.82rem;">
                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                This will <strong>disable</strong> the reported user's account and auto-resolve this report.
                            </div>
                            <div class="form-group">
                                <label class="form-label">Admin Note <span class="text-muted font-weight-normal">(optional)</span></label>
                                <textarea name="admin_note" class="form-control" placeholder="Reason for suspension..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-sm btn-danger btn-block">
                                <i class="fas fa-user-lock mr-1"></i> Confirm Suspension
                            </button>
                        </form>
                    </div>
                    @else
                    <button class="btn-action btn-suspend btn-disabled-state" disabled>
                        <i class="fas fa-user-lock"></i> User Already Suspended
                    </button>
                    @endif
                @endif

                @if($resolved)
                    <div class="alert alert-secondary mt-2 mb-0 py-2 px-3" style="font-size:.82rem;">
                        <i class="fas fa-lock mr-1"></i>
                        This report is <strong>{{ $report->status }}</strong> — no further actions available.
                    </div>
                @endif

            </div>
        </div>

        {{-- Quick Info --}}
        <div class="detail-card">
            <div class="card-header"><i class="fas fa-clipboard-list"></i>Quick Info</div>
            <div class="card-body p-0">
                <table class="table table-sm table-borderless mb-0" style="font-size:.85rem;">
                    <tbody>
                        <tr>
                            <td class="text-muted pl-3">Report ID</td>
                            <td class="text-right pr-3 font-weight-600">#{{ $report->id }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Type</td>
                            <td class="text-right pr-3">User Report</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Category</td>
                            <td class="text-right pr-3">{{ ucfirst(str_replace('_', ' ', $report->reason_category)) }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Status Changes</td>
                            <td class="text-right pr-3">{{ $report->statusHistories->count() }}</td>
                        </tr>
                        <tr>
                            <td class="text-muted pl-3">Has Evidence</td>
                            <td class="text-right pr-3">
                                @if($report->evidence_path)
                                    <span class="text-success"><i class="fas fa-check"></i> Yes</span>
                                @else
                                    <span class="text-muted">No</span>
                                @endif
                            </td>
                        </tr>
                        @if($report->reportedUser)
                        <tr>
                            <td class="text-muted pl-3">User Status</td>
                            <td class="text-right pr-3">
                                <span class="acct-{{ $report->reportedUser->apiStatus() }} status-badge" style="font-size:.68rem; padding:.15rem .5rem;">
                                    <span class="dot"></span>
                                    {{ ucfirst($report->reportedUser->apiStatus()) }}
                                </span>
                            </td>
                        </tr>
                        @endif
                        <tr>
                            <td class="text-muted pl-3">Submitted</td>
                            <td class="text-right pr-3">{{ $report->created_at->diffForHumans() }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>{{-- /col-lg-4 --}}

</div>{{-- /row --}}

@endsection

@push('scripts')
<script>
    function toggleForm(id) {
        document.querySelectorAll('.status-form').forEach(f => {
            if (f.id !== id) f.classList.remove('active');
        });
        document.getElementById(id)?.classList.toggle('active');
    }
</script>
@endpush