@php
    $locale = in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en';
    $rtl = $locale === 'ar';
    $title = $rtl ? 'الدعم والمساعدة' : 'Support & Help';
@endphp
@extends('public.layout')

@section('content')
<article class="legal-document support-document">
    <header class="legal-hero"><span>{{ $rtl ? 'نحن هنا للمساعدة' : 'We are here to help' }}</span><h1>{{ $title }}</h1><p>{{ $rtl ? 'إذا واجهت مشكلة في الحساب أو التشخيص أو الاشتراك، أرسل لنا تفاصيلها. لا ترسل كلمة المرور أو أي رموز سرية.' : 'For account, diagnostic, or subscription issues, send the details without including passwords or secret keys.' }}</p></header>
    <div class="support-grid">
        <section><h2>{{ $rtl ? 'تواصل معنا' : 'Contact us' }}</h2><p><a class="support-email" href="mailto:{{ $supportEmail ?? config('public.support_email') }}">{{ $supportEmail ?? config('public.support_email') }}</a></p><p><a href="tel:{{ config('public.support_phone') }}" dir="ltr">{{ config('public.support_phone') }}</a></p><p>{{ $rtl ? 'أرسل إصدار التطبيق ونوع الجهاز ووقت المشكلة ورقم التقرير أو الطلب إن وجد.' : 'Include the app version, device model, approximate issue time, and report or order reference when available.' }}</p></section>
        <section><h2>{{ $rtl ? 'الحساب والبيانات' : 'Account & data' }}</h2><p>{{ $rtl ? 'يمكن حذف الحساب من الملف الشخصي داخل التطبيق أو من صفحة الحذف العامة.' : 'Delete your account from the in-app profile or from the public deletion page.' }}</p><a class="public-button" href="{{ route('account-deletion.show', ['lang' => $locale]) }}">{{ $rtl ? 'فتح صفحة حذف الحساب' : 'Open account deletion' }}</a></section>
        <section><h2>{{ $rtl ? 'الشراء والاشتراك' : 'Purchases & subscriptions' }}</h2><p>{{ $rtl ? 'لمشتريات المتجر، استخدم استعادة المشتريات وإدارة الاسترجاع من حساب آبل أو جوجل الذي اشتريت به. لمشتريات كاشير على الموقع، حدّث باقتك في التطبيق أو تواصل مع الدعم برقم الطلب.' : 'For app store purchases, use Restore Purchases and manage refunds through the purchasing store account. For Kashier website purchases, refresh your plan in the app or contact support with your order reference.' }}</p><a class="public-button" href="{{ route('refunds', ['lang' => $locale]) }}">{{ $rtl ? 'سياسة الاسترجاع والإلغاء' : 'Refund & cancellation policy' }}</a></section>
        <section><h2>{{ $rtl ? 'مشكلة سلامة عاجلة' : 'Urgent safety issue' }}</h2><p>{{ $rtl ? 'إذا كانت السيارة غير آمنة أو يوجد دخان أو تسرب أو خلل بالفرامل أو التوجيه، توقف بأمان واتصل بالطوارئ أو بخدمة مساعدة الطريق. لا تستخدم البريد الإلكتروني لطلب مساعدة طارئة.' : 'If the vehicle is unsafe or there is smoke, a leak, or brake or steering failure, stop safely and call emergency or roadside help. Email is not an emergency channel.' }}</p></section>
    </div>
</article>
@endsection
