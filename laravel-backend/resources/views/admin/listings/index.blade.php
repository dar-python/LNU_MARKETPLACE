@extends('admin.layout', [
    'title' => 'Pending Listings',
    'heading' => 'Pending Listings',
    'subheading' => 'Approve or decline new marketplace posts. Only listings waiting for moderation appear here.',
])

@section('content')
    <style>
        .summary-card {
            padding: 22px 24px;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
        }

        .summary-stat {
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 16px;
            background: var(--panel-alt);
        }

        .summary-stat strong {
            display: block;
            font-size: 1.8rem;
            margin-bottom: 6px;
        }

        .listing-card {
            padding: 24px;
            display: grid;
            gap: 22px;
        }

        .listing-head {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: flex-start;
        }

        .listing-head h2 {
            margin: 0 0 10px;
            font-size: 1.6rem;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 7px 12px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 0.88rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            white-space: nowrap;
        }

        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .detail-box {
            padding: 14px 16px;
            border-radius: 16px;
            background: var(--panel-alt);
            border: 1px solid var(--border);
        }

        .detail-box dt {
            margin: 0 0 6px;
            font-size: 0.82rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .detail-box dd {
            margin: 0;
            line-height: 1.45;
        }

        .description {
            padding: 16px 18px;
            border-radius: 16px;
            border: 1px solid var(--border);
            background: #fff;
            line-height: 1.6;
            white-space: pre-wrap;
        }

        .images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: 12px;
        }

        .images-grid img {
            width: 100%;
            height: 120px;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid var(--border);
            background: var(--panel-alt);
        }

        .moderation-actions {
            display: grid;
            gap: 16px;
            grid-template-columns: minmax(170px, 220px) minmax(260px, 1fr);
        }

        .moderation-actions form {
            display: grid;
            gap: 12px;
        }

        .empty-state {
            padding: 34px 28px;
            text-align: center;
        }

        .pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
        }

        @media (max-width: 720px) {
            .listing-card {
                padding: 18px;
            }

            .listing-head {
                flex-direction: column;
            }

            .moderation-actions {
                grid-template-columns: 1fr;
            }

            .pager {
                flex-direction: column;
                align-items: stretch;
            }
        }
    </style>

    <section class="panel summary-card">
        <div class="summary-grid">
            <div class="summary-stat">
                <strong>{{ $listings->total() }}</strong>
                <span class="muted">Pending listings in queue</span>
            </div>
            <div class="summary-stat">
                <strong>{{ $listings->count() }}</strong>
                <span class="muted">Visible on this page</span>
            </div>
        </div>
    </section>

    @forelse ($listings as $listing)
        @php
            $location = $listing->getAttribute('meetup_location') ?: $listing->campus_location;
            $showDeclineErrors = (string) old('decline_listing_id') === (string) $listing->id;
        @endphp

        <section class="panel listing-card">
            <div class="listing-head">
                <div>
                    <h2>{{ $listing->title }}</h2>
                    <p class="subtitle">
                        Submitted {{ optional($listing->created_at)->format('M j, Y g:i A') ?? 'Unknown date' }}
                        by {{ $listing->user?->fullName() ?? 'Unknown seller' }}
                    </p>
                </div>

                <span class="badge">{{ str_replace('_', ' ', $listing->listing_status) }}</span>
            </div>

            <dl class="details-grid">
                <div class="detail-box">
                    <dt>Seller</dt>
                    <dd>
                        {{ $listing->user?->fullName() ?? 'Unknown seller' }}<br>
                        <span class="muted">{{ $listing->user?->email ?? 'No email' }}</span><br>
                        <span class="muted">{{ $listing->user?->student_id ?? 'No student ID' }}</span>
                    </dd>
                </div>
                <div class="detail-box">
                    <dt>Category</dt>
                    <dd>{{ $listing->category?->name ?? 'Uncategorized' }}</dd>
                </div>
                <div class="detail-box">
                    <dt>Price</dt>
                    <dd>PHP {{ number_format((float) $listing->price, 2) }}</dd>
                </div>
                <div class="detail-box">
                    <dt>Condition</dt>
                    <dd>{{ \Illuminate\Support\Str::of((string) $listing->item_condition)->replace('_', ' ')->title() }}</dd>
                </div>
                <div class="detail-box">
                    <dt>Meetup location</dt>
                    <dd>{{ $location ?: 'Not provided' }}</dd>
                </div>
                <div class="detail-box">
                    <dt>Moderation status</dt>
                    <dd>{{ \Illuminate\Support\Str::of((string) $listing->listing_status)->replace('_', ' ')->title() }}</dd>
                </div>
            </dl>

            <div>
                <p class="eyebrow" style="margin-bottom: 10px;">Description</p>
                <div class="description">{{ $listing->description }}</div>
            </div>

            <div>
                <p class="eyebrow" style="margin-bottom: 10px;">Images</p>
                @if ($listing->listingImages->isEmpty())
                    <p class="muted">No images uploaded.</p>
                @else
                    <div class="images-grid">
                        @foreach ($listing->listingImages as $image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($image->image_path) }}" alt="Listing image {{ $loop->iteration }}">
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="moderation-actions">
                <form method="POST" action="{{ route('admin.listings.approve', $listing) }}">
                    @csrf
                    <button type="submit">Approve listing</button>
                    <p class="helper">Approving moves the listing to `available` and makes it visible in the existing browse flow.</p>
                </form>

                <form method="POST" action="{{ route('admin.listings.decline', $listing) }}">
                    @csrf
                    <input type="hidden" name="decline_listing_id" value="{{ $listing->id }}">
                    <div class="field">
                        <label for="moderation_note_{{ $listing->id }}">Decline reason</label>
                        <textarea
                            id="moderation_note_{{ $listing->id }}"
                            name="moderation_note"
                            placeholder="Add a short note explaining why this listing was declined."
                        >{{ $showDeclineErrors ? old('moderation_note') : '' }}</textarea>
                    </div>
                    <button type="submit" class="button-danger">Decline listing</button>
                </form>
            </div>
        </section>
    @empty
        <section class="panel empty-state">
            <h2 style="margin-top: 0;">No pending listings</h2>
            <p class="muted" style="margin-bottom: 0;">Everything in the moderation queue has already been reviewed.</p>
        </section>
    @endforelse

    @if ($listings->hasPages())
        <section class="panel" style="padding: 18px 20px;">
            <div class="pager">
                <span class="muted">
                    Showing {{ $listings->firstItem() }} to {{ $listings->lastItem() }} of {{ $listings->total() }} pending listings
                </span>

                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    @if ($listings->previousPageUrl())
                        <a class="button button-secondary" href="{{ $listings->previousPageUrl() }}">Previous</a>
                    @endif

                    @if ($listings->nextPageUrl())
                        <a class="button button-secondary" href="{{ $listings->nextPageUrl() }}">Next</a>
                    @endif
                </div>
            </div>
        </section>
    @endif
@endsection
