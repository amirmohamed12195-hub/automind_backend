@extends('public.layout', ['title' => 'AutoMind'])

@section('content')
    <section>
        <h1>{{ request('lang') === 'ar' ? 'ارجع إلى أوتومايند' : 'Return to AutoMind' }}</h1>
        <p>{{ request('lang') === 'ar' ? 'يمكنك إغلاق هذه الصفحة والعودة إلى التطبيق للتحقق من حالة الدفع.' : 'You can close this page and return to the app to check your payment status.' }}</p>
    </section>
@endsection
