@php
    $locale = $locale ?? 'en';
    $rtl = $locale === 'ar';
    $title = $report['title'] ?: ($rtl ? 'تقرير تشخيص AutoMind' : 'AutoMind diagnostic report');
    $severityLabels = $rtl ? ['low' => 'منخفض', 'moderate' => 'متوسط', 'high' => 'مرتفع', 'critical' => 'حرج'] : ['low' => 'Low', 'moderate' => 'Moderate', 'high' => 'High', 'critical' => 'Critical'];
@endphp
@extends('public.layout')
@section('noindex', 'true')

@section('content')
<article class="legal-document report-document">
    <header class="legal-hero report-hero">
        <span>{{ $rtl ? 'تقرير تشخيص مشترك' : 'Shared diagnostic report' }}</span>
        <h1>{{ $title }}</h1>
        <p>{{ $report['vehicleName'] }} · {{ $rtl ? 'الثقة' : 'Confidence' }} {{ round($report['confidence'] * 100) }}%</p>
        <div class="report-badges"><b class="severity {{ $report['severity'] }}">{{ $severityLabels[$report['severity']] ?? $report['severity'] }}</b><b>{{ $report['drivingRecommendation'] }}</b></div>
    </header>
    <section class="safety-callout"><h2>{{ $rtl ? 'إرشادات القيادة والسلامة' : 'Driving and safety guidance' }}</h2><p>{{ $report['drivingAdvice'] ?: ($rtl ? 'اطلب فحصًا مهنيًا إذا استمرت الأعراض أو شعرت أن السيارة غير آمنة.' : 'Seek a professional inspection if symptoms continue or the vehicle feels unsafe.') }}</p></section>
    <section><h2>{{ $rtl ? 'الملخص' : 'Summary' }}</h2><p>{{ $report['summary'] }}</p></section>
    @if ($report['suspectedFaults'] !== [])
        <section><h2>{{ $rtl ? 'الأعطال المحتملة' : 'Suspected faults' }}</h2><div class="report-list">@foreach ($report['suspectedFaults'] as $fault)<article><h3>{{ $fault['title'] ?: $fault['code'] }}</h3><small>{{ $fault['obdCode'] }} · {{ round($fault['confidence'] * 100) }}%</small><p>{{ $fault['description'] }}</p>@if ($fault['possibleCauses'] !== [])<strong>{{ $rtl ? 'أسباب محتملة' : 'Possible causes' }}</strong><ul>@foreach ($fault['possibleCauses'] as $cause)<li>{{ $cause }}</li>@endforeach</ul>@endif</article>@endforeach</div></section>
    @endif
    @if ($report['recommendedActions'] !== [])
        <section><h2>{{ $rtl ? 'الخطوات المقترحة' : 'Recommended next steps' }}</h2><ol>@foreach ($report['recommendedActions'] as $action)<li>{{ $action['text'] }} @if ($action['professionalRequired'])<strong>({{ $rtl ? 'يتطلب مختصًا' : 'Professional required' }})</strong>@endif</li>@endforeach</ol></section>
    @endif
    @if ($report['serviceEstimate'])
        <section><h2>{{ $rtl ? 'نطاق التكلفة التقديري' : 'Estimated cost range' }}</h2><p class="estimate-value">{{ $report['serviceEstimate']['currency'] }} {{ $report['serviceEstimate']['low'] }} – {{ $report['serviceEstimate']['high'] }}</p><p>{{ $report['serviceEstimate']['disclaimer'] }}</p></section>
    @endif
    @if ($report['limitations'] !== [])
        <section><h2>{{ $rtl ? 'حدود التقرير' : 'Report limitations' }}</h2><ul>@foreach ($report['limitations'] as $limitation)<li>{{ $limitation }}</li>@endforeach</ul></section>
    @endif
    <section class="report-disclaimer"><p>{{ $report['disclaimer'] ?: config("automind.disclaimer.$locale") }}</p><small>{{ $rtl ? 'هذا الرابط مؤقت ولا يعرض حساب مالك السيارة.' : 'This temporary link does not expose the vehicle owner account.' }}</small></section>
</article>
@endsection
