<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Login — LNU Marketplace</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <style>
        :root {
            --navy:      #0D1B6E;
            --dark-navy: #080F45;
            --gold:      #F5C518;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0; min-height: 100vh;
            display: flex; align-items: center; justify-content: center;
            background: linear-gradient(135deg, var(--dark-navy) 0%, var(--navy) 55%, #1a2f9e 100%);
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        .login-wrap { width: 100%; max-width: 420px; padding: 16px; }

        .login-header {
            background: linear-gradient(135deg, var(--dark-navy), var(--navy));
            border-radius: 22px 22px 0 0;
            padding: 36px 28px 28px;
            text-align: center;
            border: 1px solid rgba(255,255,255,.08);
            border-bottom: none;
        }

        .logo-circle {
            width: 68px; height: 68px;
            background: var(--gold); border-radius: 50%;
            border: 3px solid #fff;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 14px;
            font-size: 1.6rem; font-weight: 900; color: var(--dark-navy);
        }

        .login-header h1 { color: #fff; font-size: 1.2rem; font-weight: 800; margin: 0 0 4px; }
        .login-header span { color: var(--gold); font-size: .72rem; letter-spacing: .12em; text-transform: uppercase; }

        .login-body {
            background: #fff;
            border-radius: 0 0 22px 22px;
            padding: 28px 28px 32px;
            box-shadow: 0 24px 60px rgba(8,15,69,.35);
        }

        .login-body h2 { font-size: 1.15rem; font-weight: 800; color: var(--navy); margin: 0 0 4px; }
        .login-body p  { font-size: .83rem; color: #9aA3c2; margin-bottom: 22px; }

        label { font-size: .82rem; font-weight: 600; color: var(--navy); margin-bottom: 6px; display: block; }

        .field-wrap {
            background: #fff; border: 1px solid #DDE3F7;
            border-radius: 12px;
            display: flex; align-items: center; padding: 0 14px;
            box-shadow: 0 2px 8px rgba(13,27,110,.05);
            margin-bottom: 16px;
            transition: border-color .15s, box-shadow .15s;
        }

        .field-wrap:focus-within {
            border-color: var(--navy);
            box-shadow: 0 0 0 3px rgba(13,27,110,.1);
        }

        .field-wrap .bi { color: var(--navy); font-size: .95rem; flex-shrink: 0; }

        .field-wrap input {
            border: none; outline: none;
            font-size: .9rem; color: var(--navy);
            padding: 13px 10px; flex: 1; background: transparent;
        }

        .field-wrap input::placeholder { color: #b0b8d8; font-size: .85rem; }

        .btn-signin {
            width: 100%; padding: 13px;
            background: var(--navy); color: #fff;
            border: none; border-radius: 14px;
            font-size: .95rem; font-weight: 700;
            cursor: pointer; margin-top: 4px;
            transition: background .15s, transform .1s, color .15s;
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }

        .btn-signin:hover { background: var(--dark-navy); color: var(--gold); transform: translateY(-1px); }
        .btn-signin:disabled { opacity: .65; }

        .spinner {
            width: 18px; height: 18px;
            border: 2.5px solid rgba(255,255,255,.4);
            border-top-color: #fff; border-radius: 50%;
            animation: spin .7s linear infinite;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .lnu-error {
            background: #FDE9E7; border: 1px solid #EFC2BD;
            color: #A12A2A; border-radius: 10px;
            padding: 10px 14px; font-size: .84rem;
            margin-bottom: 18px;
            display: flex; gap: 8px; align-items: flex-start;
        }
    </style>
</head>
<body>
<div class="login-wrap">

    <div class="login-header">
        <div class="logo-circle">L</div>
        <h1>LNU Marketplace</h1>
        <span>Leyte Normal University</span>
    </div>

    <div class="login-body">
        <h2>Welcome Back!</h2>
        <p>Sign in with your admin credentials</p>

        @if($errors->any())
        <div class="lnu-error">
            <i class="bi bi-exclamation-circle-fill mt-1"></i>
            <div>
                @foreach($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- POST to admin.login.store — LoginRequest expects 'email' or 'student_id' --}}
        <form method="POST" action="{{ route('admin.login.store') }}" id="loginForm">
            @csrf

            {{-- LoginRequest validated fields --}}
            <input type="hidden" name="email"      id="field_email">
            <input type="hidden" name="student_id" id="field_student_id">

            <label for="identifier">LNU Student ID or Email</label>
            <div class="field-wrap">
                <i class="bi bi-person"></i>
                <input id="identifier" type="text"
                       value="{{ old('identifier') }}"
                       placeholder="Enter your student ID or email"
                       autocomplete="username" required>
            </div>

            <label for="password">Password</label>
            <div class="field-wrap">
                <i class="bi bi-lock"></i>
                <input id="password" name="password" type="password"
                       placeholder="Enter your password"
                       autocomplete="current-password" required>
            </div>

            <button type="submit" class="btn-signin" id="submitBtn">
                Sign In
            </button>
        </form>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('loginForm').addEventListener('submit', function () {
        var val = document.getElementById('identifier').value.trim();

        if (val.includes('@')) {
            document.getElementById('field_email').value      = val;
            document.getElementById('field_student_id').value = '';
        } else {
            document.getElementById('field_student_id').value = val;
            document.getElementById('field_email').value      = '';
        }

        var btn = document.getElementById('submitBtn');
        btn.disabled = true;
        btn.innerHTML = '<div class="spinner"></div> Signing in…';
    });
</script>
</body>
</html>