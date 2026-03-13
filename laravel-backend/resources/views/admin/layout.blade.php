<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $title ?? 'Admin Panel') — LNU Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --navy:      #0D1B6E;
            --dark-navy: #080F45;
            --gold:      #F5C518;
            --light-bg:  #F4F6FF;
            --card-bg:   #FFFFFF;
            --border:    #E2E8F8;
            --muted:     #667085;
            --sidebar-w: 256px;
            --topbar-h:  60px;
            --shadow:    0 2px 12px rgba(13,27,110,.07);
            --radius:    14px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--light-bg);
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: #1A2340;
            min-height: 100vh;
        }

        /* ── Sidebar ───────────────────────────────────────────── */
        #sidebar {
            position: fixed;
            top: 0; left: 0; bottom: 0;
            width: var(--sidebar-w);
            background: linear-gradient(180deg, var(--dark-navy) 0%, var(--navy) 100%);
            z-index: 1040;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            transition: transform .25s ease;
        }

        .sb-brand {
            display: flex; align-items: center; gap: 12px;
            padding: 22px 20px 18px;
            border-bottom: 1px solid rgba(255,255,255,.08);
            text-decoration: none;
        }

        .sb-logo {
            width: 42px; height: 42px;
            background: var(--gold); border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 900; font-size: 1.1rem; color: var(--dark-navy);
            flex-shrink: 0;
        }

        .sb-brand-name { font-size: .92rem; font-weight: 700; color: #fff; line-height: 1.2; }
        .sb-brand-sub  { font-size: .7rem; color: var(--gold); letter-spacing: .1em; text-transform: uppercase; }

        .sb-section-label {
            padding: 18px 20px 6px;
            font-size: .65rem; letter-spacing: .14em;
            text-transform: uppercase; color: rgba(255,255,255,.3);
        }

        .sb-link {
            display: flex; align-items: center; gap: 11px;
            padding: 10px 20px;
            color: rgba(255,255,255,.65);
            text-decoration: none; font-size: .88rem;
            transition: background .15s, color .15s;
            position: relative;
        }

        .sb-link:hover { background: rgba(245,197,24,.1); color: #fff; text-decoration: none; }

        .sb-link.active {
            background: rgba(245,197,24,.18);
            color: var(--gold); font-weight: 600;
        }

        .sb-link.active::before {
            content: ''; position: absolute;
            left: 0; top: 7px; bottom: 7px;
            width: 3px; background: var(--gold);
            border-radius: 0 3px 3px 0;
        }

        .sb-link .bi { font-size: .95rem; width: 18px; text-align: center; flex-shrink: 0; }

        .sb-badge {
            margin-left: auto;
            background: var(--gold); color: var(--dark-navy);
            font-size: .65rem; font-weight: 800;
            padding: 2px 7px; border-radius: 999px;
        }

        /* Report badge uses red to signal urgency */
        .sb-badge-red {
            margin-left: auto;
            background: #e53e3e; color: #fff;
            font-size: .65rem; font-weight: 800;
            padding: 2px 7px; border-radius: 999px;
        }

        .sb-footer {
            margin-top: auto; padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,.08);
        }

        .sb-signout {
            display: flex; align-items: center; gap: 9px;
            width: 100%; padding: 9px 14px;
            background: rgba(255,255,255,.06); border: none;
            border-radius: 10px; color: rgba(255,255,255,.6);
            font-size: .85rem; cursor: pointer;
            transition: background .15s, color .15s;
        }

        .sb-signout:hover { background: rgba(255,255,255,.12); color: #fff; }

        /* ── Topbar ────────────────────────────────────────────── */
        #topbar {
            position: fixed;
            top: 0; left: var(--sidebar-w); right: 0;
            height: var(--topbar-h);
            background: var(--card-bg);
            border-bottom: 1px solid var(--border);
            z-index: 1030;
            display: flex; align-items: center;
            padding: 0 24px; gap: 14px;
        }

        .topbar-title { flex: 1; font-weight: 700; font-size: .98rem; color: var(--dark-navy); }

        .topbar-avatar {
            width: 34px; height: 34px;
            background: var(--navy); color: var(--gold);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: .82rem; flex-shrink: 0;
        }

        /* ── Main ──────────────────────────────────────────────── */
        #main { margin-left: var(--sidebar-w); padding-top: var(--topbar-h); min-height: 100vh; }
        .page-body { padding: 28px 26px 52px; }

        .page-header { margin-bottom: 24px; }
        .page-header h2 { font-size: 1.35rem; font-weight: 800; color: var(--dark-navy); margin: 0 0 3px; }
        .page-header p  { color: var(--muted); font-size: .88rem; margin: 0; }

        /* ── Cards ─────────────────────────────────────────────── */
        .lnu-card {
            background: var(--card-bg); border: 1px solid var(--border);
            border-radius: var(--radius); box-shadow: var(--shadow);
        }

        .lnu-card-header {
            padding: 16px 20px; border-bottom: 1px solid var(--border);
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }

        .lnu-card-title { font-size: .93rem; font-weight: 700; color: var(--dark-navy); margin: 0; }

        /* ── Alerts ────────────────────────────────────────────── */
        .lnu-alert {
            padding: 12px 16px; border-radius: 12px;
            border: 1px solid transparent;
            margin-bottom: 20px; font-size: .88rem;
            display: flex; align-items: flex-start; gap: 8px;
        }
        .lnu-alert-success { background: #E6F9F0; border-color: #C6E6D1; color: #1A7F4E; }
        .lnu-alert-danger  { background: #FDE9E7; border-color: #EFC2BD; color: #A12A2A; }
        .lnu-alert-warning { background: #FFFBEB; border-color: #FDE68A; color: #92400E; }
        .lnu-alert-info    { background: #EFF6FF; border-color: #BFDBFE; color: #1D4ED8; }

        /* dismiss button inside lnu-alert */
        .lnu-alert .lnu-alert-close {
            margin-left: auto; background: none; border: none;
            font-size: 1rem; line-height: 1; cursor: pointer;
            opacity: .5; color: inherit; padding: 0 2px;
            flex-shrink: 0;
        }
        .lnu-alert .lnu-alert-close:hover { opacity: 1; }

        /* ── Badges ────────────────────────────────────────────── */
        .lnu-badge {
            display: inline-flex; align-items: center; gap: 4px;
            padding: 4px 10px; border-radius: 999px;
            font-size: .73rem; font-weight: 700;
        }
        .badge-pending  { background: #FFF8E1; color: #B45309; }
        .badge-approved { background: #E6F9F0; color: #1A7F4E; }
        .badge-rejected { background: #FDE9E7; color: #A12A2A; }

        /* ── Buttons ───────────────────────────────────────────── */
        .btn-navy {
            background: var(--navy); color: #fff; border: none;
            border-radius: 10px; font-weight: 600; font-size: .88rem;
        }
        .btn-navy:hover { background: var(--dark-navy); color: var(--gold); }

        .btn-ghost {
            background: #F0F3FB; color: var(--navy); border: none;
            border-radius: 10px; font-size: .82rem;
        }
        .btn-ghost:hover { background: var(--border); }

        .btn-danger-soft {
            background: #FDE9E7; color: #A12A2A; border: none;
            border-radius: 10px; font-weight: 600; font-size: .88rem;
        }
        .btn-danger-soft:hover { background: #f5c6c2; color: #7a1f1f; }

        /* ── User avatar ───────────────────────────────────────── */
        .user-avatar-sm {
            width: 32px; height: 32px;
            background: var(--navy); color: var(--gold);
            border-radius: 9px;
            display: inline-flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: .78rem; flex-shrink: 0;
        }

        /* ── Responsive ────────────────────────────────────────── */
        #sb-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(8,15,69,.45); z-index: 1039;
        }

        @media (max-width: 991px) {
            #sidebar { transform: translateX(-100%); }
            #sidebar.sb-open { transform: translateX(0); }
            #sb-overlay.sb-open { display: block; }
            #topbar { left: 0; }
            #main { margin-left: 0; }
            .page-body { padding: 20px 14px 40px; }
        }
    </style>
    @stack('styles')
</head>
<body>

<div id="sb-overlay" onclick="sbClose()"></div>

{{-- ── Sidebar ── --}}
<nav id="sidebar">
    <a href="{{ route('admin.listings.index') }}" class="sb-brand">
        <div class="sb-logo">L</div>
        <div>
            <div class="sb-brand-name">LNU Marketplace</div>
            <div class="sb-brand-sub">Admin Panel</div>
        </div>
    </a>

    <div style="flex:1;">
        <div class="sb-section-label">Moderation</div>

        <a class="sb-link {{ request()->routeIs('admin.listings.*') ? 'active' : '' }}"
           href="{{ route('admin.listings.index') }}">
            <i class="bi bi-shop"></i> Listings
            @if(!empty($pendingCount) && $pendingCount > 0)
                <span class="sb-badge">{{ $pendingCount }}</span>
            @endif
        </a>

        <a class="sb-link {{ request()->routeIs('admin.inquiries.*') ? 'active' : '' }}"
           href="{{ route('admin.inquiries.index') }}">
            <i class="bi bi-chat-dots"></i> Inquiries
        </a>

          <a class="sb-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}"
              href="{{ url('/admin/reports') }}">
            <i class="bi bi-flag"></i> Reports
            @if(!empty($openReportsCount) && $openReportsCount > 0)
                <span class="sb-badge-red">{{ $openReportsCount }}</span>
            @endif
        </a>

        <div class="sb-section-label">Accounts</div>

        <a class="sb-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
           href="{{ route('admin.users.index') }}">
            <i class="bi bi-people"></i> Users
        </a>

        <div class="sb-section-label">Settings</div>

        <a class="sb-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
           href="{{ route('admin.categories.index') }}">
            <i class="bi bi-tags"></i> Categories
        </a>
    </div>

    <div class="sb-footer">
        @auth
        <div class="mb-3 px-1">
            <div class="d-flex align-items-center" style="gap:10px;">
                <div class="user-avatar-sm">
                    {{ strtoupper(substr(auth()->user()->first_name ?? 'A', 0, 1)) }}
                </div>
                <div style="overflow:hidden;">
                    <div style="font-size:.82rem;font-weight:600;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ auth()->user()->fullName() }}
                    </div>
                    <div style="font-size:.72rem;color:rgba(255,255,255,.45);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        {{ auth()->user()->email }}
                    </div>
                </div>
            </div>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="sb-signout">
                <i class="bi bi-box-arrow-left"></i> Sign out
            </button>
        </form>
        @endauth
    </div>
</nav>

{{-- ── Topbar ── --}}
<header id="topbar">
    <button class="btn btn-sm btn-ghost d-lg-none mr-2" onclick="sbOpen()">
        <i class="bi bi-list" style="font-size:1.2rem;"></i>
    </button>
    <span class="topbar-title">{{ $heading ?? 'Admin' }}</span>
    @auth
    <div class="d-flex align-items-center" style="gap:10px;">
        <div class="topbar-avatar">
            {{ strtoupper(substr(auth()->user()->first_name ?? 'A', 0, 1)) }}
        </div>
        <span class="d-none d-md-block" style="font-size:.85rem;font-weight:600;color:var(--dark-navy);">
            {{ auth()->user()->fullName() }}
        </span>
    </div>
    @endauth
</header>

{{-- ── Main ── --}}
<div id="main">
    <div class="page-body">

        {{-- success (used by report views + general) --}}
        @if(session('success'))
        <div class="lnu-alert lnu-alert-success" id="flash-success">
            <i class="bi bi-check-circle-fill mt-1" style="flex-shrink:0;"></i>
            <span>{{ session('success') }}</span>
            <button class="lnu-alert-close" onclick="dismissFlash('flash-success')">&times;</button>
        </div>
        @endif

        {{-- error (used by report views) --}}
        @if(session('error'))
        <div class="lnu-alert lnu-alert-danger" id="flash-error">
            <i class="bi bi-exclamation-circle-fill mt-1" style="flex-shrink:0;"></i>
            <span>{{ session('error') }}</span>
            <button class="lnu-alert-close" onclick="dismissFlash('flash-error')">&times;</button>
        </div>
        @endif

        {{-- status (legacy key kept for backward compat) --}}
        @if(session('status'))
        <div class="lnu-alert lnu-alert-success" id="flash-status">
            <i class="bi bi-check-circle-fill mt-1" style="flex-shrink:0;"></i>
            <span>{{ session('status') }}</span>
            <button class="lnu-alert-close" onclick="dismissFlash('flash-status')">&times;</button>
        </div>
        @endif

        {{-- warning --}}
        @if(session('warning'))
        <div class="lnu-alert lnu-alert-warning" id="flash-warning">
            <i class="bi bi-exclamation-triangle-fill mt-1" style="flex-shrink:0;"></i>
            <span>{{ session('warning') }}</span>
            <button class="lnu-alert-close" onclick="dismissFlash('flash-warning')">&times;</button>
        </div>
        @endif

        {{-- validation errors --}}
        @if($errors->any())
        <div class="lnu-alert lnu-alert-danger" id="flash-validation">
            <i class="bi bi-exclamation-circle-fill mt-1" style="flex-shrink:0;"></i>
            <ul class="mb-0 pl-3">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
            <button class="lnu-alert-close" onclick="dismissFlash('flash-validation')">&times;</button>
        </div>
        @endif

        @yield('content')
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function sbOpen()  {
        document.getElementById('sidebar').classList.add('sb-open');
        document.getElementById('sb-overlay').classList.add('sb-open');
    }
    function sbClose() {
        document.getElementById('sidebar').classList.remove('sb-open');
        document.getElementById('sb-overlay').classList.remove('sb-open');
    }

    function dismissFlash(id) {
        const el = document.getElementById(id);
        if (el) { el.style.transition = 'opacity .2s'; el.style.opacity = '0'; setTimeout(() => el.remove(), 200); }
    }

    // Auto-dismiss flash messages after 5 seconds
    setTimeout(() => {
        ['flash-success', 'flash-status', 'flash-warning', 'flash-info'].forEach(id => dismissFlash(id));
    }, 5000);
</script>
@stack('scripts')
</body>
</html>