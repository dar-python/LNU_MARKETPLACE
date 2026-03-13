@extends('admin.layout', [
    'title'      => 'Inquiries',
    'heading'    => 'Inquiries',
    'subheading' => 'Monitor all buyer-to-seller inquiries across the marketplace.',
])

@section('content')
    <style>
        .tab-bar {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 8px 18px;
            border-radius: 999px;
            font-size: 0.88rem;
            font-weight: 600;
            border: 1.5px solid var(--border);
            background: var(--panel);
            color: var(--muted);
            text-decoration: none;
            transition: all .15s;
        }

        .tab:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .tab.active {
            background: var(--accent);
            color: #0D1B6E;
            border-color: var(--accent);
        }

        .tab .count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-left: 6px;
            padding: 1px 7px;
            border-radius: 999px;
            font-size: 0.78rem;
            background: rgba(0,0,0,0.12);
        }

        .tab.active .count {
            background: rgba(13,27,110,0.15);
        }

        .summary-card {
            padding: 18px 22px;
        }

        .inquiry-card {
            padding: 22px 24px;
            display: grid;
            gap: 18px;
        }

        .inquiry-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
        }

        .inquiry-head h2 {
            margin: 0 0 6px;
            font-size: 1.15rem;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            white-space: nowrap;
        }

        .status-pending  { background: #fff8e1; color: #b45309; }
        .status-accepted { background: #e8f5e9; color: #2e7d32; }
        .status-declined { background: #fce4ec; color: #c62828; }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .detail-box {
            padding: 12px 14px;
            border-radius: 14px;
            background: var(--panel-alt);
            border: 1px solid var(--border);
        }

        .detail-box dt {
            margin: 0 0 5px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .detail-box dd {
            margin: 0;
            line-height: 1.45;
            font-size: 0.95rem;
        }

        .message-box {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: #fff;
            line-height: 1.6;
            white-space: pre-wrap;
            font-size: 0.95rem;
        }

        .response-box {
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid #c8e6c9;
            background: #f1f8e9;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .empty-state {
            padding: 40px 28px;
            text-align: center;
        }

        .pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 680px) {
            .inquiry-head { flex-direction: column; }
            .pager { flex-direction: column; align-items: stretch; }
        }
    </style>

    {{-- Tab bar --}}
    <section class="panel summary-card">
        <div class="tab-bar">
            <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => 1]) }}"
               class="tab {{ $status === 'all' ? 'active' : '' }}">
                All <span class="count">{{ $counts['all'] }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'pending', 'page' => 1]) }}"
               class="tab {{ $status === 'pending' ? 'active' : '' }}">
                Pending <span class="count">{{ $counts['pending'] }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'accepted', 'page' => 1]) }}"
               class="tab {{ $status === 'accepted' ? 'active' : '' }}">
                Accepted <span class="count">{{ $counts['accepted'] }}</span>
            </a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'declined', 'page' => 1]) }}"
               class="tab {{ $status === 'declined' ? 'active' : '' }}">
                Declined <span class="count">{{ $counts['declined'] }}</span>
            </a>
        </div>
    </section>

    @forelse ($inquiries as $inquiry)
        <section class="panel inquiry-card">
            <div class="inquiry-head">
                <div>
                    <h2>{{ $inquiry->subject ?: 'No subject' }}</h2>
                    <p class="subtitle">
                        Sent {{ optional($inquiry->created_at)->format('M j, Y g:i A') ?? 'Unknown date' }}
                    </p>
                </div>
                <span class="status-badge status-{{ $inquiry->status }}">
                    {{ ucfirst($inquiry->status) }}
                </span>
            </div>

            <dl class="details-grid">
                <div class="detail-box">
                    <dt>From (Buyer)</dt>
                    <dd>
                        {{ $inquiry->sender?->fullName() ?? 'Unknown' }}<br>
                        <span class="muted">{{ $inquiry->sender?->email ?? '—' }}</span><br>
                        <span class="muted">{{ $inquiry->sender?->student_id ?? '—' }}</span>
                    </dd>
                </div>
                <div class="detail-box">
                    <dt>To (Seller)</dt>
                    <dd>
                        {{ $inquiry->recipient?->fullName() ?? 'Unknown' }}<br>
                        <span class="muted">{{ $inquiry->recipient?->email ?? '—' }}</span><br>
                        <span class="muted">{{ $inquiry->recipient?->student_id ?? '—' }}</span>
                    </dd>
                </div>
                <div class="detail-box">
                    <dt>Listing</dt>
                    <dd>
                        {{ $inquiry->listing?->title ?? 'Unknown listing' }}<br>
                        <span class="muted">{{ \Illuminate\Support\Str::of((string) $inquiry->listing?->listing_status)->replace('_', ' ')->title() }}</span>
                    </dd>
                </div>
                <div class="detail-box">
                    <dt>Preferred Contact</dt>
                    <dd>{{ \Illuminate\Support\Str::of((string) $inquiry->preferred_contact_method)->replace('_', ' ')->title() ?: '—' }}</dd>
                </div>
                @if ($inquiry->decided_at)
                    <div class="detail-box">
                        <dt>Decided at</dt>
                        <dd>{{ $inquiry->decided_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
                @if ($inquiry->responded_at)
                    <div class="detail-box">
                        <dt>Responded at</dt>
                        <dd>{{ $inquiry->responded_at->format('M j, Y g:i A') }}</dd>
                    </div>
                @endif
            </dl>

            <div>
                <p class="eyebrow" style="margin-bottom: 8px;">Message</p>
                <div class="message-box">{{ $inquiry->message ?: 'No message.' }}</div>
            </div>

            @if ($inquiry->response_note)
                <div>
                    <p class="eyebrow" style="margin-bottom: 8px;">Seller Response</p>
                    <div class="response-box">{{ $inquiry->response_note }}</div>
                </div>
            @endif
        </section>
    @empty
        <section class="panel empty-state">
            <h2 style="margin-top: 0;">No inquiries found</h2>
            <p class="muted" style="margin-bottom: 0;">
                {{ $status === 'all' ? 'No inquiries have been made yet.' : 'No ' . $status . ' inquiries found.' }}
            </p>
        </section>
    @endforelse

    @if ($inquiries->hasPages())
        <section class="panel" style="padding: 16px 20px;">
            <div class="pager">
                <span class="muted">
                    Showing {{ $inquiries->firstItem() }} to {{ $inquiries->lastItem() }} of {{ $inquiries->total() }} inquiries
                </span>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    @if ($inquiries->previousPageUrl())
                        <a class="button button-secondary" href="{{ $inquiries->previousPageUrl() }}">Previous</a>
                    @endif
                    @if ($inquiries->nextPageUrl())
                        <a class="button button-secondary" href="{{ $inquiries->nextPageUrl() }}">Next</a>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection