@php
    $locale = app()->getLocale();
    $rtl = $locale === 'ar';
    $title = $rtl ? 'باقات أوتومايند' : 'AutoMind plans';
@endphp
@extends('public.layout')
@section('noindex', '1')
@section('content')
<article class="legal-document billing-document">
    <header class="legal-hero"><span>{{ $rtl ? 'دفع آمن عبر كاشير' : 'Secure checkout with Kashier' }}</span><h1>{{ $title }}</h1><p>{{ $rtl ? 'اشترِ باستخدام حسابك في أوتومايند، ثم افتح التطبيق للاستفادة من باقتك.' : 'Purchase with your existing AutoMind account, then open the app to use your benefits.' }}</p></header>
    @if ($mode === 'test')
        <div class="public-alert" role="status">{{ $rtl ? 'وضع تجريبي — استخدم بيانات اختبار كاشير فقط. المدفوعات التجريبية لا تمنح مزايا مدفوعة على الموقع الفعلي.' : 'Test mode — use Kashier test payment details only. Test payments do not unlock paid benefits on the live website.' }}</div>
    @endif
    @if (! $enabled)
        <p>{{ $rtl ? 'الدفع عبر الموقع غير متاح حالياً. يمكنك متابعة استخدام مشتريات متجر التطبيقات.' : 'Website checkout is currently unavailable. App store purchases remain available.' }}</p>
    @elseif (! $user)
        <section class="public-form-panel">
            <h2>{{ $rtl ? 'سجّل الدخول إلى حسابك' : 'Sign in to your account' }}</h2>
            <form method="post" action="{{ route('billing.login') }}" class="public-form">
                @csrf
                <input type="hidden" name="lang" value="{{ $locale }}">
                <label for="email">{{ $rtl ? 'البريد الإلكتروني' : 'Email' }}</label>
                <input id="email" name="email" type="email" autocomplete="username" required maxlength="255" value="{{ old('email') }}" dir="ltr">
                <label for="password">{{ $rtl ? 'كلمة المرور' : 'Password' }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required maxlength="255">
                <button type="submit" class="public-button">{{ $rtl ? 'تسجيل الدخول' : 'Sign in' }}</button>
            </form>
            <p>{{ $rtl ? 'استخدم حساب التطبيق نفسه. إذا كنت تستخدم تسجيل الدخول بآبل أو جوجل، يمكنك تعيين كلمة مرور عبر «نسيت كلمة المرور» في التطبيق.' : 'Use the same account as the app. If you signed up with Apple or Google, set a password using Forgot Password in the app.' }}</p>
        </section>
    @else
        <p>{{ $rtl ? 'الحساب:' : 'Account:' }} <strong>{{ $user->email }}</strong></p>
        <form method="post" action="{{ route('billing.logout') }}">@csrf<button type="submit" class="public-button">{{ $rtl ? 'تسجيل الخروج' : 'Sign out' }}</button></form>
        @if ($access['hasSubscription'])<p>{{ $rtl ? 'لديك باقة نشطة. يمكنك شراء باقة جديدة بعد انتهاء الحالية.' : 'You have an active plan. You can purchase another after it expires.' }}</p>@endif
        <div class="support-grid billing-plan-grid">
            @foreach ($plans as $plan)
                <section>
                    <h2>{{ $plan['name'] }}</h2>
                    @if ($plan['amount'])<p class="billing-price">{{ $plan['amount'] }} <span>{{ $rtl ? 'ج.م' : 'EGP' }}</span></p>@endif
                    <p>{{ match($plan['code']) { 'PLUS_MONTHLY' => $rtl ? 'دفعة واحدة لمدة شهر. بدون تجديد تلقائي.' : 'One payment for one month. No automatic renewal.', 'PLUS_YEARLY' => $rtl ? 'دفعة واحدة لمدة سنة. بدون تجديد تلقائي.' : 'One payment for one year. No automatic renewal.', default => $rtl ? 'رصيد تقرير كامل واحد، لا تنتهي صلاحيته.' : 'One full report credit. Does not expire.' } }}</p>
                    <ul>@foreach ($plan['features'] as $feature)<li>{{ $feature }}</li>@endforeach</ul>
                    @if ($plan['available'] && (! $access['hasSubscription'] || $plan['code'] === 'SINGLE_FULL_REPORT'))
                        <form method="post" action="{{ route('billing.checkout') }}">
                            @csrf
                            <input type="hidden" name="plan" value="{{ $plan['code'] }}">
                            <input type="hidden" name="lang" value="{{ $locale }}">
                            <input type="hidden" name="idempotencyKey" value="{{ Illuminate\Support\Str::uuid() }}">
                            <button type="submit" class="public-button">{{ $rtl ? 'الدفع عبر كاشير' : 'Pay with Kashier' }}</button>
                        </form>
                    @endif
                </section>
            @endforeach
        </div>
        <p>{{ $rtl ? 'ستُدخل بيانات الدفع في صفحة كاشير الآمنة. لا يخزن أوتومايند بيانات بطاقتك. المدفوعات بالجنيه المصري.' : 'Enter payment details on Kashier’s secure checkout. AutoMind does not store your card details. Payments are in Egyptian pounds.' }}</p>
        <p><a href="{{ route('terms', ['lang' => $locale]) }}">{{ $rtl ? 'الشروط' : 'Terms' }}</a> · <a href="{{ route('privacy', ['lang' => $locale]) }}">{{ $rtl ? 'الخصوصية' : 'Privacy' }}</a> · <a href="{{ route('refunds', ['lang' => $locale]) }}">{{ $rtl ? 'الاسترجاع' : 'Refunds' }}</a></p>
        @if ($orders->isNotEmpty())
            <h2>{{ $rtl ? 'طلباتك الأخيرة' : 'Recent orders' }}</h2>
            <ul>@foreach ($orders as $order)<li><a href="{{ route('billing.order', ['order' => $order->id, 'lang' => $locale]) }}">{{ $order->created_at->format('Y-m-d H:i') }} — {{ number_format($order->amount_minor / 100, 2) }} EGP — {{ __('kashier.status.'.$order->status) }}</a></li>@endforeach</ul>
        @endif
    @endif
</article>
@endsection
