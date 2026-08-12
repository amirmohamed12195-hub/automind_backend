@php
    $locale = in_array(request()->query('lang'), ['en', 'ar'], true) ? request()->query('lang') : 'en';
    $rtl = $locale === 'ar';
    $title = $rtl ? 'شروط الاستخدام' : 'Terms of Use';
@endphp
@extends('public.layout')

@section('content')
<article class="legal-document">
    <header class="legal-hero"><span>{{ $rtl ? 'اتفاق استخدام AutoMind' : 'AutoMind service agreement' }}</span><h1>{{ $title }}</h1><p>{{ $rtl ? 'يرجى قراءة هذه الشروط قبل إنشاء حساب أو شراء منتج أو استخدام التشخيص.' : 'Please read these terms before creating an account, buying a product, or using diagnostics.' }}</p><small>{{ $rtl ? 'تاريخ السريان' : 'Effective' }}: {{ config('public.effective_date') }}</small></header>
    @if ($rtl)
        <section><h2>1. قبول الشروط</h2><p>باستخدام AutoMind فإنك توافق على هذه الشروط وعلى سياسة الخصوصية. يجب أن يكون عمرك {{ config('public.minimum_age') }} عامًا على الأقل، أو أن تستخدم الخدمة تحت إشراف ولي الأمر حيث يسمح القانون.</p></section>
        <section><h2>2. طبيعة الخدمة</h2><p>يقدم AutoMind إرشادات تقديرية مدعومة بالذكاء الاصطناعي اعتمادًا على المعلومات التي تدخلها. الخدمة ليست فحصًا ميكانيكيًا معتمدًا ولا تضمن تحديد العطل أو تكلفة الإصلاح.</p></section>
        <section class="safety-callout"><h2>السلامة أولًا</h2><p>لا تعتمد على التطبيق عند وجود دخان أو حريق أو تسرب وقود أو سخونة شديدة أو خلل في الفرامل أو التوجيه أو أي خطر فوري. توقف في مكان آمن واطلب الطوارئ أو المساعدة المهنية.</p></section>
        <section><h2>3. حسابك</h2><p>أنت مسؤول عن صحة البيانات وحماية جهازك وبيانات الدخول. لا يجوز انتحال شخصية الآخرين أو محاولة الوصول إلى حساب أو سيارة لا تخصك.</p></section>
        <section><h2>4. الاستخدام المقبول</h2><p>لا يجوز إساءة استخدام API أو تجاوز الحماية أو رفع محتوى غير قانوني أو ضار أو انتهاك حقوق الآخرين أو استخدام الخدمة لاتخاذ إجراء خطير. يجوز لنا تقييد الحسابات المخالفة لحماية المستخدمين والمنصة.</p></section>
        <section><h2>5. المشتريات والاشتراكات</h2><p>تُعالج المدفوعات بواسطة Apple App Store أو Google Play. الاشتراكات تتجدد تلقائيًا حتى تلغيها من إعدادات المتجر قبل موعد التجديد. السعر والضرائب وسياسة الاسترداد المعروضة في المتجر هي المرجع. رصيد التقرير الواحد يُستخدم مرة واحدة وفق الخطة المعروضة ولا يمثل مبلغًا نقديًا.</p></section>
        <section><h2>6. المحتوى والملكية</h2><p>تحتفظ بحقوقك في المحتوى الذي ترفعه، وتمنحنا ترخيصًا محدودًا لمعالجته وتخزينه بالقدر اللازم لتقديم الخدمة والأمان والدعم. تبقى البرمجيات والعلامة والتصميمات مملوكة لمشغل AutoMind ومرخصيه.</p></section>
        <section><h2>7. الميكانيكيون والأسعار</h2><p>ملفات الميكانيكيين ومواعيدهم والأسعار التقديرية هي معلومات مساعدة وليست ضمانًا للتوفر أو الجودة أو السعر النهائي. أي اتفاق إصلاح يكون بينك وبين مقدم الخدمة، مع بقاء حقوق المستهلك الإلزامية.</p></section>
        <section><h2>8. حدود المسؤولية</h2><p>إلى الحد الذي يسمح به القانون، لا نتحمل أضرارًا ناتجة عن تجاهل تحذيرات السلامة أو معلومات ناقصة أو استخدام غير صحيح. لا تستبعد هذه الشروط أي مسؤولية أو حق لا يسمح القانون باستبعاده.</p></section>
        <section><h2>9. التعليق والتغييرات</h2><p>يمكنك التوقف وحذف حسابك في أي وقت. قد نعلق الخدمة لأسباب أمنية أو قانونية أو صيانة. سننشر التعديلات المهمة ونطلب قبولًا جديدًا عندما يلزم.</p></section>
        <section><h2>10. التواصل والقانون</h2><p>تطبق قواعد حماية المستهلك والخصوصية الإلزامية في بلدك. للاستفسارات أو الشكاوى تواصل مع <a href="mailto:{{ config('public.support_email') }}">{{ config('public.support_email') }}</a>.</p></section>
    @else
        <section><h2>1. Accepting these terms</h2><p>By using AutoMind, you agree to these terms and the Privacy Policy. You must be at least {{ config('public.minimum_age') }}, or use the service with guardian supervision where law permits.</p></section>
        <section><h2>2. What the service provides</h2><p>AutoMind provides estimated AI-assisted guidance based on the information you submit. It is not a certified mechanical inspection and does not guarantee a fault diagnosis or repair price.</p></section>
        <section class="safety-callout"><h2>Safety comes first</h2><p>Do not rely on the app when there is smoke, fire, a fuel leak, severe overheating, brake or steering failure, or any immediate danger. Stop safely and contact emergency or qualified professional help.</p></section>
        <section><h2>3. Your account</h2><p>You are responsible for accurate information and for protecting your device and credentials. You may not impersonate others or access an account or vehicle that is not yours.</p></section>
        <section><h2>4. Acceptable use</h2><p>You may not abuse the API, bypass safeguards, upload illegal or harmful content, violate others' rights, or use the service to carry out unsafe actions. We may restrict abusive accounts to protect users and the platform.</p></section>
        <section><h2>5. Purchases and subscriptions</h2><p>Payments are processed by Apple App Store or Google Play. Subscriptions renew automatically until cancelled in store settings before renewal. Store-displayed price, tax, and refund rules control. A single-report credit is used once under the displayed plan and has no cash value.</p></section>
        <section><h2>6. Content and ownership</h2><p>You retain rights in content you upload and grant us a limited license to process and store it as needed for the service, security, and support. The software, brand, and designs remain owned by the AutoMind operator and licensors.</p></section>
        <section><h2>7. Mechanics and estimates</h2><p>Mechanic profiles, availability, and price estimates are informational and do not guarantee availability, quality, or final price. Any repair agreement is between you and the provider, subject to mandatory consumer rights.</p></section>
        <section><h2>8. Limits of responsibility</h2><p>To the extent law allows, we are not responsible for harm caused by ignoring safety warnings, incomplete inputs, or misuse. These terms do not exclude any right or liability that law does not allow us to exclude.</p></section>
        <section><h2>9. Suspension and changes</h2><p>You can stop using the service and delete your account at any time. We may suspend service for security, legal, or maintenance reasons. Material updates will be published and renewed acceptance requested where required.</p></section>
        <section><h2>10. Contact and applicable rights</h2><p>Mandatory privacy and consumer rules in your country continue to apply. Questions or complaints can be sent to <a href="mailto:{{ config('public.support_email') }}">{{ config('public.support_email') }}</a>.</p></section>
    @endif
</article>
@endsection
