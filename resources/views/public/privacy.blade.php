@php
    $locale = in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en';
    $rtl = $locale === 'ar';
    $title = $rtl ? 'سياسة الخصوصية' : 'Privacy Policy';
@endphp
@extends('public.layout')

@section('content')
<article class="legal-document">
    <header class="legal-hero">
        <span>{{ $rtl ? 'الخصوصية وحماية البيانات' : 'Privacy & data protection' }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $rtl ? 'توضح هذه السياسة كيف يجمع AutoMind بياناتك ويستخدمها ويحميها ويتيح لك التحكم فيها.' : 'This policy explains how AutoMind collects, uses, protects, and lets you control your information.' }}</p>
        <small>{{ $rtl ? 'تاريخ السريان' : 'Effective' }}: {{ config('public.effective_date') }}</small>
    </header>

    @if ($rtl)
        <section><h2>1. من يدير الخدمة</h2><p>تُدار خدمة AutoMind بواسطة {{ config('public.operator_name') }}. للاستفسارات المتعلقة بالخصوصية تواصل معنا على <a href="mailto:{{ config('public.privacy_email') }}">{{ config('public.privacy_email') }}</a>.</p></section>
        <section><h2>2. البيانات التي نجمعها</h2><ul><li>بيانات الحساب مثل الاسم والبريد والهاتف واللغة والدولة.</li><li>بيانات السيارة مثل النوع والموديل والسنة وVIN والمسافة المقطوعة وسجل الصيانة.</li><li>مدخلات التشخيص مثل وصف الأعراض والصور وتسجيلات الصوت وقراءات OBD.</li><li>الموقع التقريبي أو الدقيق عندما تطلب البحث عن ميكانيكي قريب وتمنح الإذن.</li><li>بيانات التشغيل مثل رمز الجهاز والإشعارات وعنوان IP والسجلات الفنية والأخطاء.</li><li>بيانات الشراء والاشتراك الواردة من Apple أو Google. لا يستقبل AutoMind رقم بطاقتك البنكية.</li></ul></section>
        <section><h2>3. لماذا نستخدم البيانات</h2><p>نستخدم البيانات لإنشاء الحساب وتشغيل التشخيص وتقديم التقارير وحفظ سجل السيارة وإرسال التذكيرات والتحقق من المشتريات ومنع الاحتيال وتحسين الأمان والاستجابة للدعم والالتزام بالقانون.</p></section>
        <section><h2>4. الذكاء الاصطناعي ومقدمو الخدمة</h2><p>قد تُرسل مدخلات التشخيص اللازمة، ومنها نصوص أو صور أو صوت، إلى مزود ذكاء اصطناعي لمعالجتها. كما نستخدم خدمات Apple وGoogle وFirebase ومزودي الاستضافة والبريد والتخزين. نشارك الحد الأدنى اللازم لتقديم الخدمة ولا نبيع بياناتك الشخصية.</p></section>
        <section><h2>5. الأساس القانوني والموافقة</h2><p>نعالج البيانات لتنفيذ طلبك وعقد الخدمة، وبموافقتك عندما يلزم ذلك، ولأغراض الأمان والمصلحة المشروعة والالتزامات القانونية. يمكنك سحب أذونات الكاميرا والميكروفون والموقع وBluetooth من إعدادات الجهاز.</p></section>
        <section><h2>6. الاحتفاظ والحذف</h2><ul><li>الوسائط الخام: عادة حتى {{ config('automind.retention.raw_media_days') }} يومًا.</li><li>بيانات AI الفنية: عادة حتى {{ config('automind.retention.ai_metadata_days') }} يومًا.</li><li>سجلات التدقيق والأمان: عادة حتى {{ config('automind.retention.audit_days') }} يومًا.</li><li>بعد طلب حذف الحساب توجد مهلة استرداد/تنفيذ تصل إلى {{ config('automind.retention.deleted_account_grace_days') }} يومًا قبل الحذف النهائي، مع الاحتفاظ بما يفرضه القانون أو مكافحة الاحتيال.</li></ul></section>
        <section><h2>7. حقوقك</h2><p>يمكنك الوصول إلى بيانات الحساب وتصحيحها وحذف الحساب من داخل التطبيق. ويمكنك كذلك تقديم طلب حذف من <a href="{{ route('account-deletion.show', ['lang' => 'ar']) }}">صفحة حذف الحساب</a>. قد تكون لك حقوق إضافية في الاعتراض أو التقييد أو نقل البيانات أو الشكوى لدى جهة حماية البيانات.</p></section>
        <section><h2>8. الحماية والنقل الدولي</h2><p>نستخدم HTTPS وضوابط وصول وتشفيرًا مناسبًا وعزلًا للأسرار. قد تُعالج البيانات في دول أخرى لدى مزودي الخدمة مع تطبيق وسائل الحماية المتاحة. لا توجد خدمة إلكترونية خالية تمامًا من المخاطر.</p></section>
        <section><h2>9. الأطفال والتغييرات</h2><p>الخدمة غير موجهة لمن يقل عمره عن {{ config('public.minimum_age') }} عامًا. قد نحدّث هذه السياسة مع تغير الخدمة أو القانون، وسننشر التاريخ الجديد هنا ونطلب موافقة جديدة عند الحاجة.</p></section>
    @else
        <section><h2>1. Who operates the service</h2><p>AutoMind is operated by {{ config('public.operator_name') }}. For privacy questions, contact <a href="mailto:{{ config('public.privacy_email') }}">{{ config('public.privacy_email') }}</a>.</p></section>
        <section><h2>2. Information we collect</h2><ul><li>Account details such as name, email, phone, language, and country.</li><li>Vehicle details such as make, model, year, VIN, mileage, and maintenance history.</li><li>Diagnostic inputs such as symptom descriptions, photos, audio recordings, and OBD readings.</li><li>Approximate or precise location when you request nearby mechanics and grant permission.</li><li>Operational data such as device and push tokens, IP address, technical logs, and errors.</li><li>Purchase and subscription status supplied by Apple or Google. AutoMind does not receive your payment-card number.</li></ul></section>
        <section><h2>3. How we use information</h2><p>We use information to create accounts, perform diagnostics, deliver reports, maintain vehicle history, send reminders, verify purchases, prevent abuse, improve security, answer support requests, and comply with law.</p></section>
        <section><h2>4. AI and service providers</h2><p>Required diagnostic inputs, which may include text, images, or audio, may be sent to an AI provider for processing. We also use Apple, Google, Firebase, hosting, email, and storage providers. We share only what is needed to provide the service and do not sell personal information.</p></section>
        <section><h2>5. Legal grounds and consent</h2><p>We process information to fulfill your request and service contract, with consent where required, for security and legitimate interests, and to meet legal duties. Camera, microphone, location, and Bluetooth permissions can be withdrawn in device settings.</p></section>
        <section><h2>6. Retention and deletion</h2><ul><li>Raw diagnostic media: normally up to {{ config('automind.retention.raw_media_days') }} days.</li><li>Technical AI metadata: normally up to {{ config('automind.retention.ai_metadata_days') }} days.</li><li>Security and audit records: normally up to {{ config('automind.retention.audit_days') }} days.</li><li>After an account-deletion request, a processing/recovery period of up to {{ config('automind.retention.deleted_account_grace_days') }} days applies before final deletion, except records required by law or fraud prevention.</li></ul></section>
        <section><h2>7. Your choices and rights</h2><p>You can access and correct account information and delete your account in the app. You can also use our <a href="{{ route('account-deletion.show') }}">web deletion page</a>. Depending on your location, you may also have rights to object, restrict, export, or complain to a data-protection authority.</p></section>
        <section><h2>8. Security and international processing</h2><p>We use HTTPS, access controls, appropriate encryption, and secret isolation. Providers may process information in other countries using available safeguards. No online service can eliminate every risk.</p></section>
        <section><h2>9. Children and changes</h2><p>The service is not directed to anyone under {{ config('public.minimum_age') }}. We may update this policy as the service or law changes. We will publish the new date here and request renewed consent where required.</p></section>
    @endif
</article>
@endsection
