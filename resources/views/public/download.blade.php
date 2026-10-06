@php
    $locale = in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en';
    $rtl = $locale === 'ar';
    $title = $rtl ? 'تحميل أوتومايند' : 'Download AutoMind';
@endphp
@extends('public.layout')

@section('content')
<article class="legal-document">
    <header class="legal-hero">
        <span>AutoMind</span>
        <h1>{{ $title }}</h1>
        <p>{{ $rtl ? 'اختر متجر جهازك لتحميل التطبيق.' : 'Choose your device’s store to download the app.' }}</p>
    </header>
    <div class="support-grid">
        <section>
            <h2>App Store</h2>
            <a class="public-button" href="{{ config('public.app_store_url') }}">{{ $rtl ? 'تحميل من App Store' : 'Download on the App Store' }}</a>
        </section>
        <section>
            <h2>Google Play</h2>
            <a class="public-button" href="{{ config('public.play_store_url') }}">{{ $rtl ? 'تحميل من Google Play' : 'Get it on Google Play' }}</a>
        </section>
    </div>
</article>
@endsection
