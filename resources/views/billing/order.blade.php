@php
    $locale = app()->getLocale(); $rtl = $locale === 'ar';
    $title = $rtl ? 'حالة الدفع' : 'Payment status';
@endphp
@extends('public.layout')
@section('noindex', '1')
@section('content')
<article class="legal-document billing-document">
    <header class="legal-hero"><span>Kashier</span><h1>{{ __('kashier.status.'.$order->status) }}</h1></header>
    <p>{{ $rtl ? 'رقم الطلب:' : 'Order reference:' }} <strong dir="ltr">{{ $order->id }}</strong></p>
    <p>{{ number_format($order->amount_minor / 100, 2) }} {{ $rtl ? 'ج.م' : 'EGP' }}</p>
    @if ($verificationPending)<div class="public-alert" role="status">{{ $rtl ? 'تعذر تحديث حالة الدفع الآن. لا تدفع مرة أخرى؛ أعد التحقق بعد قليل.' : 'We could not refresh the payment status. Do not pay again; check back shortly.' }}</div>@endif
    @if ($order->status === 'paid')
        <p>{{ $order->mode === 'test' ? ($rtl ? 'تم تأكيد الدفعة التجريبية. لا تمنح الدفعات التجريبية مزايا على الموقع الفعلي.' : 'Test payment verified. Test payments do not unlock benefits on the live website.') : ($rtl ? 'تم تأكيد الدفع. افتح التطبيق وحدّث باقتك لعرض المزايا.' : 'Payment verified. Open the app and refresh your plan to see your benefits.') }}</p>
        @if ($order->access_ends_at)<p>{{ $rtl ? 'تنتهي الباقة في:' : 'Access expires:' }} {{ $order->access_ends_at->format('Y-m-d') }}. {{ $rtl ? 'بدون تجديد تلقائي.' : 'No automatic renewal.' }}</p>@endif
    @elseif (in_array($order->status, ['creating', 'pending']))
        <p>{{ $rtl ? 'لم يتم تأكيد الدفع بعد. إذا دفعت بالفعل، انتظر قليلاً ثم حدّث الصفحة.' : 'Payment has not been confirmed yet. If you already paid, wait briefly and refresh this page.' }}</p>
        @if ($order->checkout_url && $order->expires_at->isFuture())<a class="public-button" rel="noreferrer" href="{{ $order->checkout_url }}">{{ $rtl ? 'متابعة الدفع' : 'Continue checkout' }}</a>@endif
    @elseif ($order->status === 'partially_refunded')
        <p>{{ $rtl ? 'تم استرداد جزء من المبلغ. تواصل مع الدعم إذا احتجت مساعدة.' : 'Part of this payment was refunded. Contact support if you need help.' }}</p>
    @endif
    <p><a class="public-button" href="{{ route('billing.order', ['order' => $order->id, 'lang' => $locale]) }}">{{ $rtl ? 'تحديث الحالة' : 'Refresh status' }}</a></p>
    <p><a href="{{ route('billing.index', ['lang' => $locale]) }}">{{ $rtl ? 'العودة إلى الباقات' : 'Back to plans' }}</a> · <a href="{{ route('support', ['lang' => $locale]) }}">{{ $rtl ? 'الدعم' : 'Support' }}</a></p>
</article>
@endsection
