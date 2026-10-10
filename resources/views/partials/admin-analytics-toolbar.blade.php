<div class="analytics-toolbar">
    <div><span class="section-overline">PLATFORM PERFORMANCE</span><p>{{ $analyticsStart->format('M j') }} – {{ $analyticsEnd->format('M j, Y') }} <span>· {{ config('app.timezone') }}</span></p></div>
    <nav class="range-switch" aria-label="Analytics date range">
        @foreach ([7, 30] as $range)
            <a href="{{ route('admin.dashboard', ['range' => $range]) }}#overview" @class(['active' => $analyticsDays === $range]) @if($analyticsDays === $range) aria-current="true" @endif>Last {{ $range }} days</a>
        @endforeach
    </nav>
</div>
