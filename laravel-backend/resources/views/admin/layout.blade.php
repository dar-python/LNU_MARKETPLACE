<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Admin Moderation' }}</title>
    <style>
        :root {
            color-scheme: light;
            --bg: #f4f1ea;
            --panel: #fffdfa;
            --panel-alt: #f8f4ed;
            --border: #d8cfc0;
            --text: #1f2933;
            --muted: #667085;
            --accent: #7c4d24;
            --accent-soft: #eedfc9;
            --success: #1f6f43;
            --danger: #a12a2a;
            --danger-soft: #fde9e7;
            --shadow: 0 12px 36px rgba(47, 43, 36, 0.08);
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            background:
                radial-gradient(circle at top right, rgba(124, 77, 36, 0.12), transparent 28%),
                linear-gradient(180deg, #f8f4ed 0%, var(--bg) 100%);
            color: var(--text);
        }

        a {
            color: inherit;
        }

        .shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px 48px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
        }

        .brand {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .eyebrow {
            font-size: 0.8rem;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .title {
            margin: 0;
            font-size: clamp(1.75rem, 3vw, 2.5rem);
            line-height: 1.1;
        }

        .subtitle {
            margin: 0;
            color: var(--muted);
        }

        .panel {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }

        .alert,
        .errors {
            margin-bottom: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            border: 1px solid transparent;
        }

        .alert {
            background: #e9f5ed;
            border-color: #c6e6d1;
            color: var(--success);
        }

        .errors {
            background: var(--danger-soft);
            border-color: #efc2bd;
            color: var(--danger);
        }

        .errors ul {
            margin: 0;
            padding-left: 18px;
        }

        .button,
        button {
            appearance: none;
            border: 0;
            border-radius: 999px;
            padding: 11px 18px;
            font: inherit;
            font-size: 0.95rem;
            cursor: pointer;
            background: var(--accent);
            color: #fffdf7;
            transition: transform 120ms ease, opacity 120ms ease;
            text-decoration: none;
        }

        .button:hover,
        button:hover {
            transform: translateY(-1px);
            opacity: 0.96;
        }

        .button-secondary {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }

        .button-danger {
            background: var(--danger);
        }

        .logout-form {
            margin: 0;
        }

        .content {
            display: grid;
            gap: 20px;
        }

        input,
        textarea {
            width: 100%;
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 12px 14px;
            font: inherit;
            background: #fff;
            color: var(--text);
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .field {
            display: grid;
            gap: 8px;
        }

        .helper {
            color: var(--muted);
            font-size: 0.92rem;
        }

        .muted {
            color: var(--muted);
        }

        @media (max-width: 720px) {
            .shell {
                padding: 24px 14px 36px;
            }

            .topbar {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <span class="eyebrow">LNU Marketplace</span>
                <h1 class="title">{{ $heading ?? 'Admin Moderation' }}</h1>
                @isset($subheading)
                    <p class="subtitle">{{ $subheading }}</p>
                @endisset
            </div>

            @auth
                <form class="logout-form" method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="button button-secondary">Sign out</button>
                </form>
            @endauth
        </header>

        @if (session('status'))
            <div class="alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="errors">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <main class="content">
            @yield('content')
        </main>
    </div>
</body>
</html>
