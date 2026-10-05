@php $title = $locale === 'ar' ? 'حذف حساب أوتومايند' : 'Delete your AutoMind account'; @endphp
@extends('public.layout')
@section('noindex', 'true')

@section('content')
<article class="legal-document form-document">
    <header class="legal-hero"><span>{{ $locale === 'ar' ? 'التحكم في بياناتك' : 'Control your data' }}</span><h1>{{ $title }}</h1><p>{{ $locale === 'ar' ? 'يمكنك حذف الحساب فورًا من الملف الشخصي داخل التطبيق، أو طلب رابط تأكيد على بريدك من هنا.' : 'Delete immediately from the in-app profile, or request an email confirmation link here.' }}</p></header>
    <section>
        <h2>{{ $locale === 'ar' ? 'ما الذي يحدث عند التأكيد؟' : 'What happens after confirmation?' }}</h2>
        <ul>
            <li>{{ $locale === 'ar' ? 'سيُسجّل خروجك من جميع الأجهزة وتتوقف الإشعارات المرتبطة بحسابك.' : 'All sessions are signed out and push tokens are disabled.' }}</li>
            <li>{{ $locale === 'ar' ? 'سيُلغى أي تشخيص جارٍ ويتوقف حسابك عن العمل.' : 'Active diagnostics are cancelled and the account is disabled.' }}</li>
            <li>{{ $locale === 'ar' ? 'يبدأ حذف البيانات وفق سياسة الاحتفاظ، عادة خلال ' . config('automind.retention.deleted_account_grace_days') . ' يومًا.' : 'Data deletion begins under the retention policy, normally within ' . config('automind.retention.deleted_account_grace_days') . ' days.' }}</li>
            <li>{{ $locale === 'ar' ? 'حذف الحساب لا يلغي اشتراكك تلقائيًا. ألغِ الاشتراك أيضًا من حسابك في متجر آبل أو جوجل بلاي.' : 'Deleting the account does not automatically cancel a store subscription; cancel it in Apple or Google too.' }}</li>
        </ul>
    </section>
    <form class="public-form" method="POST" action="{{ route('account-deletion.request') }}">
        @csrf
        <input type="hidden" name="lang" value="{{ $locale }}">
        <label><span>{{ $locale === 'ar' ? 'البريد المرتبط بالحساب' : 'Account email' }}</span><input type="email" name="email" value="{{ old('email') }}" required autocomplete="email" maxlength="255"></label>
        <button class="public-button danger" type="submit">{{ $locale === 'ar' ? 'إرسال رابط التأكيد' : 'Send confirmation link' }}</button>
    </form>
</article>
@endsection
