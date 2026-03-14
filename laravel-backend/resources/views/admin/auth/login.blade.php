@extends('admin.layout', [
    'title' => 'Admin Login',
    'heading' => 'Admin Sign In',
    'subheading' => 'Use an existing admin account to review pending marketplace listings.',
])

@section('content')
    <section class="panel" style="max-width: 520px; padding: 28px; margin: 0 auto;">
        <form method="POST" action="{{ route('admin.login.store') }}" style="display: grid; gap: 18px;">
            @csrf

            <div class="field">
                <label for="identifier">Email or student ID</label>
                <input
                    id="identifier"
                    name="identifier"
                    type="text"
                    value="{{ old('identifier') }}"
                    autocomplete="username"
                    required
                >
                <p class="helper">Use an existing admin user.</p>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    id="password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button type="submit">Sign in</button>
        </form>
    </section>
@endsection
