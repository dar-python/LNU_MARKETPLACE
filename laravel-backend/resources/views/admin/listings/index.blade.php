<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LNU Admin Dashboard</title>
    <style>
        :root {
            color-scheme: light;
            --navy: #10245f;
            --navy-deep: #0a153f;
            --gold: #f3c94a;
            --bg: #f3f6ff;
            --panel: #ffffff;
            --muted: #5f6b8a;
            --border: #d5def5;
            --success: #027a48;
            --success-bg: #ecfdf3;
            --danger: #b42318;
            --danger-bg: #fef3f2;
            --shadow: 0 18px 48px rgba(16, 36, 95, 0.12);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(180deg, #edf3ff 0%, #f8faff 100%);
            color: var(--navy-deep);
        }
        .shell {
            max-width: 1200px;
            margin: 0 auto;
            padding: 28px 18px 48px;
        }
        .hero {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            margin-bottom: 22px;
        }
        .hero-card {
            flex: 1;
            background: linear-gradient(135deg, var(--navy-deep), var(--navy));
            color: #fff;
            border-radius: 24px;
            padding: 24px;
            box-shadow: var(--shadow);
        }
        .hero-card p {
            color: rgba(255, 255, 255, 0.82);
            margin: 8px 0 0;
            line-height: 1.5;
        }
        .logout-form button {
            border: 0;
            border-radius: 14px;
            padding: 12px 16px;
            background: #fff;
            color: var(--navy);
            font-weight: 800;
            cursor: pointer;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat {
            background: var(--panel);
            border-radius: 18px;
            padding: 18px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .stat-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .stat-value {
            margin-top: 10px;
            font-size: 32px;
            font-weight: 900;
        }
        .grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
            gap: 20px;
        }
        .panel {
            background: var(--panel);
            border-radius: 22px;
            padding: 20px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .panel h2 {
            margin: 0 0 6px;
            font-size: 22px;
        }
        .panel-subtitle {
            margin: 0 0 20px;
            color: var(--muted);
        }
        .flash {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 12px;
            font-weight: 700;
        }
        .flash-status {
            background: #eff8ff;
            color: #175cd3;
            border: 1px solid #b2ddff;
        }
        .flash-error {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid #fecdca;
        }
        .listing-card,
        .recent-card {
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 16px;
            background: #fcfdff;
        }
        .listing-card + .listing-card,
        .recent-card + .recent-card {
            margin-top: 14px;
        }
        .listing-meta,
        .recent-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 10px;
            color: var(--muted);
            font-size: 13px;
        }
        .badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(16, 36, 95, 0.08);
            color: var(--navy);
            font-size: 12px;
            font-weight: 800;
        }
        .listing-card h3,
        .recent-card h3 {
            margin: 0;
            font-size: 18px;
        }
        .listing-card p,
        .recent-card p {
            color: var(--muted);
            line-height: 1.5;
        }
        .actions {
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 10px;
            margin-top: 14px;
        }
        textarea {
            width: 100%;
            min-height: 72px;
            padding: 12px;
            resize: vertical;
            border-radius: 12px;
            border: 1px solid var(--border);
            font: inherit;
        }
        button {
            border: 0;
            border-radius: 12px;
            padding: 12px 14px;
            font-weight: 800;
            cursor: pointer;
        }
        .approve {
            background: var(--success-bg);
            color: var(--success);
        }
        .decline {
            background: var(--danger-bg);
            color: var(--danger);
        }
        .empty {
            padding: 18px;
            border-radius: 16px;
            background: #f8fafc;
            color: var(--muted);
            border: 1px dashed var(--border);
        }
        .pagination {
            margin-top: 20px;
        }
        @media (max-width: 920px) {
            .grid {
                grid-template-columns: 1fr;
            }
            .actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <div class="hero">
            <div class="hero-card">
                <div class="badge">Admin Dashboard</div>
                <h1 style="margin:12px 0 0;font-size:32px;">Pending Listings</h1>
                <p>Review new marketplace posts, approve valid items, and reject anything that should not go live.</p>
            </div>
            <form class="logout-form" method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit">Sign Out</button>
            </form>
        </div>

        <div class="stats">
            <div class="stat">
                <div class="stat-label">Pending Review</div>
                <div class="stat-value">{{ $summary['pending'] }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Approved</div>
                <div class="stat-value">{{ $summary['approved'] }}</div>
            </div>
            <div class="stat">
                <div class="stat-label">Rejected</div>
                <div class="stat-value">{{ $summary['rejected'] }}</div>
            </div>
        </div>

        <div class="grid">
            <section class="panel">
                <h2>Listings Waiting for Review</h2>
                <p class="panel-subtitle">Approve to make a listing visible in browse results, or reject it with a moderation note.</p>

                @if (session('status'))
                    <div class="flash flash-status">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="flash flash-error">{{ $errors->first() }}</div>
                @endif

                @forelse ($pendingListings as $listing)
                    <article class="listing-card">
                        <div class="badge">{{ $listing->category?->name ?? 'Uncategorized' }}</div>
                        <h3>{{ $listing->title }}</h3>
                        <div class="listing-meta">
                            <span>Seller: {{ $listing->user?->fullName() ?? 'Unknown user' }}</span>
                            <span>Student ID: {{ $listing->user?->student_id ?? 'N/A' }}</span>
                            <span>Price: PHP {{ number_format((float) $listing->price, 2) }}</span>
                            <span>Condition: {{ str_replace('_', ' ', $listing->item_condition) }}</span>
                        </div>
                        <p>{{ $listing->description }}</p>

                        <form method="POST" action="{{ route('admin.listings.approve', $listing) }}">
                            @csrf
                            <div class="actions">
                                <textarea name="moderation_note" placeholder="Optional note for approval or moderation history">{{ $listing->moderation_note }}</textarea>
                                <button class="approve" type="submit">Approve</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.listings.decline', $listing) }}" style="margin-top:10px;">
                            @csrf
                            <div class="actions">
                                <textarea name="moderation_note" placeholder="Reason for rejection">{{ $listing->moderation_note }}</textarea>
                                <button class="decline" type="submit">Decline</button>
                            </div>
                        </form>
                    </article>
                @empty
                    <div class="empty">No listings are currently waiting for review.</div>
                @endforelse

                <div class="pagination">
                    {{ $pendingListings->links() }}
                </div>
            </section>

            <aside class="panel">
                <h2>Recently Moderated</h2>
                <p class="panel-subtitle">A quick view of the latest approved and rejected listings.</p>

                @forelse ($recentlyModerated as $listing)
                    <article class="recent-card">
                        <div class="badge">{{ strtoupper($listing->listing_status) }}</div>
                        <h3>{{ $listing->title }}</h3>
                        <div class="recent-meta">
                            <span>{{ $listing->category?->name ?? 'Uncategorized' }}</span>
                            <span>{{ $listing->user?->fullName() ?? 'Unknown user' }}</span>
                        </div>
                        @if ($listing->moderation_note)
                            <p>{{ $listing->moderation_note }}</p>
                        @endif
                    </article>
                @empty
                    <div class="empty">No moderated listings yet.</div>
                @endforelse
            </aside>
        </div>
    </div>
</body>
</html>
