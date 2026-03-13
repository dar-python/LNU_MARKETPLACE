@extends('admin.layout', [
    'title'      => 'User Management',
    'heading'    => 'User Management',
    'subheading' => 'View, approve, and manage student accounts.',
])

@section('content')
    <style>
        .tab-bar { display: flex; gap: 6px; flex-wrap: wrap; }

        .tab {
            padding: 8px 18px; border-radius: 999px;
            font-size: .88rem; font-weight: 600;
            border: 1.5px solid var(--border);
            background: var(--card-bg); color: var(--muted);
            text-decoration: none; transition: all .15s;
        }
        .tab:hover { border-color: var(--navy); color: var(--navy); text-decoration: none; }
        .tab.active { background: var(--navy); color: #fff; border-color: var(--navy); }
        .tab .count {
            display: inline-flex; align-items: center; justify-content: center;
            margin-left: 6px; padding: 1px 7px; border-radius: 999px;
            font-size: .78rem; background: rgba(0,0,0,0.12);
        }
        .tab.active .count { background: rgba(255,255,255,0.2); }

        .summary-card { padding: 18px 22px; }

        .users-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .users-table th {
            padding: 11px 14px; text-align: left;
            font-size: .72rem; letter-spacing: .08em;
            text-transform: uppercase; color: var(--muted);
            border-bottom: 1px solid var(--border);
            background: #fafbff;
        }
        .users-table td {
            padding: 13px 14px; border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .users-table tr:last-child td { border-bottom: none; }
        .users-table tr:hover td { background: #f8f9ff; }

        .user-avatar {
            width: 34px; height: 34px;
            background: var(--navy); color: var(--gold);
            border-radius: 10px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: .82rem; flex-shrink: 0;
        }

        .status-pill {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 4px 10px; border-radius: 999px;
            font-size: .75rem; font-weight: 700;
        }
        .pill-approved { background: #e6f9f0; color: #1a7f4e; }
        .pill-pending  { background: #fff8e1; color: #b45309; }
        .pill-disabled { background: #fde9e7; color: #a12a2a; }

        .action-btns { display: flex; gap: 6px; flex-wrap: wrap; }

        .btn-approve {
            padding: 6px 14px; border-radius: 8px; font-size: .8rem; font-weight: 600;
            background: #e6f9f0; color: #1a7f4e; border: none; cursor: pointer;
            transition: background .15s;
        }
        .btn-approve:hover { background: #c6e6d1; }

        .btn-disable {
            padding: 6px 14px; border-radius: 8px; font-size: .8rem; font-weight: 600;
            background: #fde9e7; color: #a12a2a; border: none; cursor: pointer;
            transition: background .15s;
        }
        .btn-disable:hover { background: #f5c6c2; }

        .btn-enable {
            padding: 6px 14px; border-radius: 8px; font-size: .8rem; font-weight: 600;
            background: #e8f0fe; color: #1a56db; border: none; cursor: pointer;
            transition: background .15s;
        }
        .btn-enable:hover { background: #c7d7fc; }

        .empty-state { padding: 40px 28px; text-align: center; }

        .pager {
            display: flex; justify-content: space-between;
            align-items: center; gap: 12px;
        }

        @media (max-width: 768px) {
            .users-table thead { display: none; }
            .users-table td {
                display: block; padding: 8px 14px;
                border-bottom: none;
            }
            .users-table tr { border-bottom: 1px solid var(--border); display: block; padding: 10px 0; }
            .pager { flex-direction: column; align-items: stretch; }
        }
    </style>

    {{-- Tab bar --}}
    <section class="lnu-card mb-4">
        <div class="p-3">
            <div class="tab-bar">
                <a href="{{ request()->fullUrlWithQuery(['status' => 'all', 'page' => 1]) }}"
                   class="tab {{ $status === 'all' ? 'active' : '' }}">
                    All <span class="count">{{ $counts['all'] }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'pending', 'page' => 1]) }}"
                   class="tab {{ $status === 'pending' ? 'active' : '' }}">
                    Pending <span class="count">{{ $counts['pending'] }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'approved', 'page' => 1]) }}"
                   class="tab {{ $status === 'approved' ? 'active' : '' }}">
                    Approved <span class="count">{{ $counts['approved'] }}</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['status' => 'disabled', 'page' => 1]) }}"
                   class="tab {{ $status === 'disabled' ? 'active' : '' }}">
                    Disabled <span class="count">{{ $counts['disabled'] }}</span>
                </a>
            </div>
        </div>
    </section>

    <section class="lnu-card">
        @if ($users->isEmpty())
            <div class="empty-state">
                <h2 style="margin-top:0; color: var(--dark-navy);">No users found</h2>
                <p class="text-muted mb-0">
                    {{ $status === 'all' ? 'No student accounts exist yet.' : 'No ' . $status . ' accounts found.' }}
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center" style="gap: 10px;">
                                        <div class="user-avatar">
                                            {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--dark-navy);">
                                                {{ $user->first_name }}
                                                {{ $user->middle_name ? $user->middle_name . ' ' : '' }}
                                                {{ $user->last_name }}
                                            </div>
                                            <div style="font-size: .78rem; color: var(--muted);">
                                                ID #{{ $user->id }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td style="font-weight: 600;">{{ $user->student_id ?? '—' }}</td>
                                <td style="color: var(--muted);">{{ $user->email }}</td>
                                <td>
                                    @if ($user->is_disabled)
                                        <span class="status-pill pill-disabled">
                                            <i class="bi bi-slash-circle"></i> Disabled
                                        </span>
                                    @elseif ($user->is_approved)
                                        <span class="status-pill pill-approved">
                                            <i class="bi bi-check-circle"></i> Approved
                                        </span>
                                    @else
                                        <span class="status-pill pill-pending">
                                            <i class="bi bi-clock"></i> Pending
                                        </span>
                                    @endif
                                </td>
                                <td style="color: var(--muted); font-size: .82rem;">
                                    {{ optional($user->created_at)->format('M j, Y') ?? '—' }}
                                </td>
                                <td>
                                    <div class="action-btns">
                                        @if (!$user->is_approved && !$user->is_disabled)
                                            <form method="POST" action="{{ route('admin.users.approve', $user) }}">
                                                @csrf
                                                <button type="submit" class="btn-approve">
                                                    <i class="bi bi-check-lg"></i> Approve
                                                </button>
                                            </form>
                                        @endif

                                        @if (!$user->is_disabled)
                                            <form method="POST" action="{{ route('admin.users.disable', $user) }}">
                                                @csrf
                                                <button type="submit" class="btn-disable"
                                                    onclick="return confirm('Disable account for {{ addslashes($user->first_name) }}?')">
                                                    <i class="bi bi-slash-circle"></i> Disable
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.users.enable', $user) }}">
                                                @csrf
                                                <button type="submit" class="btn-enable">
                                                    <i class="bi bi-arrow-counterclockwise"></i> Re-enable
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($users->hasPages())
                <div class="p-3 border-top">
                    <div class="pager">
                        <span style="font-size: .85rem; color: var(--muted);">
                            Showing {{ $users->firstItem() }} to {{ $users->lastItem() }} of {{ $users->total() }} users
                        </span>
                        <div style="display: flex; gap: 8px;">
                            @if ($users->previousPageUrl())
                                <a class="btn btn-sm btn-ghost" href="{{ $users->previousPageUrl() }}">Previous</a>
                            @endif
                            @if ($users->nextPageUrl())
                                <a class="btn btn-sm btn-ghost" href="{{ $users->nextPageUrl() }}">Next</a>
                            @endif
                        </div>
                    </div>
                </div>
            @endif
        @endif
    </section>
@endsection