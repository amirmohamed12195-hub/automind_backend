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
    <x-store-badges class="download-page-badges" />
</article>
@endsection
