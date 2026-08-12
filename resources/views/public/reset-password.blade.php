@php $title = $locale === 'ar' ? 'تغيير كلمة المرور' : 'Reset password'; @endphp
@extends('public.layout')
@section('noindex', 'true')

@section('content')
<article class="legal-document form-document">
    <header class="legal-hero"><span>{{ $locale === 'ar' ? 'أمان الحساب' : 'Account security' }}</span><h1>{{ $title }}</h1><p>{{ $locale === 'ar' ? 'اختر كلمة مرور من 8 أحرف على الأقل وتحتوي حروفًا وأرقامًا.' : 'Choose a password with at least 8 characters, including letters and numbers.' }}</p></header>
    @if (session('status'))
        <a class="public-button" href="{{ route('landing') }}">{{ $locale === 'ar' ? 'العودة إلى AutoMind' : 'Return to AutoMind' }}</a>
    @elseif ($token !== '' && $email !== '')
        <form class="public-form" method="POST" action="{{ route('password.reset.store') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="lang" value="{{ $locale }}">
            <label><span>{{ $locale === 'ar' ? 'البريد الإلكتروني' : 'Email' }}</span><input type="email" name="email" value="{{ old('email', $email) }}" required autocomplete="email"></label>
            <label><span>{{ $locale === 'ar' ? 'كلمة المرور الجديدة' : 'New password' }}</span><input type="password" name="password" required autocomplete="new-password" minlength="8"></label>
            <label><span>{{ $locale === 'ar' ? 'تأكيد كلمة المرور' : 'Confirm password' }}</span><input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"></label>
            <button class="public-button" type="submit">{{ $locale === 'ar' ? 'حفظ كلمة المرور' : 'Save password' }}</button>
        </form>
    @else
        <div class="public-alert error">{{ $locale === 'ar' ? 'الرابط ناقص أو غير صالح. اطلب رابطًا جديدًا من شاشة نسيت كلمة المرور في التطبيق.' : 'The link is incomplete or invalid. Request a new link from Forgot Password in the app.' }}</div>
    @endif
</article>
@endsection
