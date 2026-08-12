@php $title = $locale === 'ar' ? 'تأكيد حذف الحساب' : 'Confirm account deletion'; @endphp
@extends('public.layout')
@section('noindex', 'true')

@section('content')
<article class="legal-document form-document">
    <header class="legal-hero danger-hero"><span>{{ $locale === 'ar' ? 'إجراء مهم' : 'Important action' }}</span><h1>{{ $title }}</h1><p>{{ $locale === 'ar' ? 'سيتم تعطيل الحساب وبدء حذف بياناته. لا تضغط الزر إذا لم تطلب ذلك.' : 'This disables the account and starts deleting its data. Do not continue if you did not request this.' }}</p></header>
    <form class="public-form" method="POST" action="{{ request()->fullUrl() }}">
        @csrf
        <p><strong>{{ $locale === 'ar' ? 'الحساب:' : 'Account:' }}</strong> {{ $user->email }}</p>
        <button class="public-button danger" type="submit">{{ $locale === 'ar' ? 'نعم، احذف حسابي' : 'Yes, delete my account' }}</button>
        <a class="public-button secondary" href="{{ route('support', ['lang' => $locale]) }}">{{ $locale === 'ar' ? 'إلغاء والعودة للدعم' : 'Cancel and return to support' }}</a>
    </form>
</article>
@endsection
