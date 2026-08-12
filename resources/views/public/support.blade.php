@php
    $locale = in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en';
    $rtl = $locale === 'ar';
    $title = $rtl ? 'الدعم والمساعدة' : 'Support & Help';
@endphp
@extends('public.layout')

@section('content')
<article class="legal-document support-document">
    <header class="legal-hero"><span>{{ $rtl ? 'نحن هنا للمساعدة' : 'We are here to help' }}</span><h1>{{ $title }}</h1><p>{{ $rtl ? 'للمشكلات المتعلقة بالحساب أو التشخيص أو الاشتراك، أرسل تفاصيل المشكلة دون إرسال كلمات مرور أو مفاتيح سرية.' : 'For account, diagnostic, or subscription issues, send the details without including passwords or secret keys.' }}</p></header>
    <div class="support-grid">
        <section><h2>{{ $rtl ? 'تواصل معنا' : 'Contact us' }}</h2><p><a class="support-email" href="mailto:{{ config('public.support_email') }}">{{ config('public.support_email') }}</a></p><p>{{ $rtl ? 'أرسل إصدار التطبيق ونوع الجهاز ووقت المشكلة ورقم التقرير أو الطلب إن وجد.' : 'Include the app version, device model, approximate issue time, and report or order reference when available.' }}</p></section>
        <section><h2>{{ $rtl ? 'الحساب والبيانات' : 'Account & data' }}</h2><p>{{ $rtl ? 'يمكن حذف الحساب من الملف الشخصي داخل التطبيق أو من صفحة الحذف العامة.' : 'Delete your account from the in-app profile or from the public deletion page.' }}</p><a class="public-button" href="{{ route('account-deletion.show', ['lang' => $locale]) }}">{{ $rtl ? 'فتح صفحة حذف الحساب' : 'Open account deletion' }}</a></section>
        <section><h2>{{ $rtl ? 'الشراء والاشتراك' : 'Purchases & subscriptions' }}</h2><p>{{ $rtl ? 'استخدم زر استعادة المشتريات داخل التطبيق أولاً. الإلغاء والاسترداد تتم إدارتهما من حساب Apple أو Google المستخدم للشراء.' : 'Use Restore Purchases in the app first. Cancellation and refunds are managed by the Apple or Google account used for purchase.' }}</p></section>
        <section><h2>{{ $rtl ? 'مشكلة سلامة عاجلة' : 'Urgent safety issue' }}</h2><p>{{ $rtl ? 'إذا كانت السيارة غير آمنة أو يوجد دخان أو تسرب أو خلل بالفرامل أو التوجيه، توقف بأمان واتصل بالطوارئ أو بخدمة مساعدة الطريق. البريد الإلكتروني ليس قناة طوارئ.' : 'If the vehicle is unsafe or there is smoke, a leak, or brake or steering failure, stop safely and call emergency or roadside help. Email is not an emergency channel.' }}</p></section>
    </div>
</article>
@endsection
