@php
    $locale = $locale ?? (in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en');
    $rtl = $locale === 'ar';
    $pageTitle = $title ?? 'AutoMind';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07101f">
    @hasSection('noindex')<meta name="robots" content="noindex, nofollow">@endif
    <title>{{ $pageTitle }} — AutoMind</title>
    @vite(['resources/css/app.css'])
</head>
<body class="public-page">
    <header class="public-header">
        <a class="brand" href="{{ route('landing') }}" aria-label="AutoMind home">
            <span class="brand-mark"><img src="{{ asset('images/automind-logo.png') }}" alt=""></span>
            <span class="brand-name">AUTO<span>MIND</span></span>
        </a>
        <nav aria-label="{{ $rtl ? 'الصفحات العامة' : 'Public pages' }}">
            <a href="{{ route('privacy', ['lang' => $locale]) }}">{{ $rtl ? 'الخصوصية' : 'Privacy' }}</a>
            <a href="{{ route('terms', ['lang' => $locale]) }}">{{ $rtl ? 'الشروط' : 'Terms' }}</a>
            <a href="{{ route('support', ['lang' => $locale]) }}">{{ $rtl ? 'الدعم' : 'Support' }}</a>
            <a class="language-link" href="{{ url()->current() }}?lang={{ $rtl ? 'en' : 'ar' }}">{{ $rtl ? 'English' : 'العربية' }}</a>
        </nav>
    </header>

    <main class="public-shell">
        @if (session('status'))
            <div class="public-alert success" role="status">{{ session('status') }}</div>
        @endif
        @if (isset($errors) && $errors->any())
            <div class="public-alert error" role="alert">
                <strong>{{ $rtl ? 'تعذر إكمال الطلب:' : 'We could not complete the request:' }}</strong>
                <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>

    <footer class="public-footer">
        <span>© {{ date('Y') }} {{ config('public.operator_name') }}.</span>
        <a href="mailto:{{ $supportEmail ?? config('public.support_email') }}">{{ $supportEmail ?? config('public.support_email') }}</a>
    </footer>
</body>
</html>
