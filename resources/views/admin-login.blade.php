<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#06101e">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin sign in — AutoMind</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-login-page">
    <main class="admin-login-shell">
        <section class="admin-login-brand">
            <a class="brand" href="/" aria-label="Go to AutoMind website">
                <span class="brand-mark"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span>
                <span class="brand-name">AUTO<span>MIND</span></span>
            </a>
            <div>
                <span class="admin-login-eyebrow"><i></i> SECURE COMMAND CENTER</span>
                <h1>Operate AutoMind<br>with confidence.</h1>
                <p>Review diagnostics, manage platform content, and monitor production operations from one protected workspace.</p>
            </div>
            <small>Authorized administrators only</small>
        </section>

        <section class="admin-login-panel">
            <form method="POST" action="{{ route('admin.login.store') }}" class="admin-login-card">
                @csrf
                <div class="admin-login-icon">⌘</div>
                <span>ADMINISTRATOR ACCESS</span>
                <h2>Welcome back</h2>
                <p>Enter your administrator credentials to continue.</p>

                @if ($errors->any())
                    <div class="admin-login-error" role="alert">{{ $errors->first() }}</div>
                @endif

                <label>
                    <span>Username</span>
                    <input name="username" type="text" value="{{ old('username', 'admin') }}" autocomplete="username" required autofocus>
                </label>
                <label>
                    <span>Password</span>
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>

                <button type="submit">Sign in to dashboard <span>→</span></button>
                <small>Protected by encrypted server-side sessions and rate limiting.</small>
            </form>
        </section>
    </main>
</body>
</html>
