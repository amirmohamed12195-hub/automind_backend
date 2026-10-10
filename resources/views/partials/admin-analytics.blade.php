    <article class="admin-panel activity-panel">
        <div class="panel-title"><div><strong>Growth & activity</strong><span>Daily registrations and diagnostic sessions</span></div><span class="snapshot-label">Updated {{ $analyticsEnd->format('H:i') }}</span></div>
        <div class="activity-totals"><div><i class="legend-dot blue"></i><strong>{{ number_format($activity->sum('diagnostics')) }}</strong><span>diagnostics</span></div><div><i class="legend-dot teal"></i><strong>{{ number_format($activity->sum('users')) }}</strong><span>new users</span></div></div>
        @php
            $chartMax = max(4, (int) ceil(max($activity->max('users'), $activity->max('diagnostics')) / 4) * 4);
            $chartX = fn ($index) => 48 + $index * (632 / max(1, $activity->count() - 1));
            $chartY = fn ($count) => 222 - ($count / $chartMax) * 184;
            $diagnosticPoints = $activity->map(fn ($day, $i) => $chartX($i).','.$chartY($day['diagnostics']))->implode(' ');
            $userPoints = $activity->map(fn ($day, $i) => $chartX($i).','.$chartY($day['users']))->implode(' ');
        @endphp
        <div class="activity-chart" data-activity-chart>
            <svg viewBox="0 0 720 270" role="img" aria-labelledby="activity-title activity-description">
                <title id="activity-title">Daily activity over {{ $analyticsDays }} days</title>
                <desc id="activity-description">Blue shows diagnostic sessions. Teal shows new users, excluding deleted accounts. Exact values are available in the data table below.</desc>
                <defs><linearGradient id="activity-fill" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#3982f7" stop-opacity=".18"/><stop offset="100%" stop-color="#3982f7" stop-opacity=".01"/></linearGradient></defs>
                @foreach (range(0, 4) as $tick)
                    <line x1="48" x2="680" y1="{{ $chartY($chartMax / 4 * $tick) }}" y2="{{ $chartY($chartMax / 4 * $tick) }}" class="chart-gridline"/>
                    <text x="34" y="{{ $chartY($chartMax / 4 * $tick) + 4 }}" text-anchor="end" class="chart-axis">{{ number_format($chartMax / 4 * $tick) }}</text>
                @endforeach
                <polygon points="48,222 {{ $diagnosticPoints }} 680,222" fill="url(#activity-fill)"/>
                <polyline points="{{ $diagnosticPoints }}" fill="none" stroke="#3982f7" stroke-width="3" stroke-linejoin="round"/>
                <polyline points="{{ $userPoints }}" fill="none" stroke="#159b86" stroke-width="2.5" stroke-dasharray="5 5" stroke-linejoin="round"/>
                @foreach ($activity as $day)
                    @if($loop->first || $loop->last || ($analyticsDays === 7 ? true : $loop->index % 5 === 0))
                        <text x="{{ $chartX($loop->index) }}" y="250" text-anchor="middle" class="chart-axis">{{ $day['label'] }}</text>
                    @endif
                    <g class="chart-point" tabindex="0" role="img" aria-label="{{ $day['label'] }}: {{ $day['diagnostics'] }} diagnostics, {{ $day['users'] }} new users" data-chart-point data-label="{{ $day['label'] }}" data-diagnostics="{{ $day['diagnostics'] }}" data-users="{{ $day['users'] }}">
                        <title>{{ $day['label'] }}: {{ $day['diagnostics'] }} diagnostics · {{ $day['users'] }} new users</title>
                        <rect x="{{ $chartX($loop->index) - 10 }}" y="25" width="20" height="200" fill="transparent"/>
                        <line x1="{{ $chartX($loop->index) }}" x2="{{ $chartX($loop->index) }}" y1="30" y2="222" class="chart-crosshair"/>
                        <circle cx="{{ $chartX($loop->index) }}" cy="{{ $chartY($day['diagnostics']) }}" r="4" fill="#3982f7" stroke="white" stroke-width="2"/>
                        <circle cx="{{ $chartX($loop->index) }}" cy="{{ $chartY($day['users']) }}" r="3.5" fill="#159b86" stroke="white" stroke-width="2"/>
                    </g>
                @endforeach
            </svg>
            <div class="chart-tooltip" data-chart-tooltip hidden></div>
        </div>
        @if($activity->sum('users') + $activity->sum('diagnostics') === 0)<p class="chart-empty">No activity in this period. New activity will appear here.</p>@endif
        <details class="chart-data"><summary>View daily data <span>↗</span></summary><div class="admin-table-wrap"><table class="admin-table"><caption class="sr-only">Daily registrations and diagnostic sessions</caption><thead><tr><th>Date</th><th>New users</th><th>Diagnostics</th></tr></thead><tbody>@foreach($activity as $day)<tr><td>{{ $day['date'] }}</td><td>{{ $day['users'] }}</td><td>{{ $day['diagnostics'] }}</td></tr>@endforeach</tbody></table></div></details>
    </article>
    <article class="admin-panel outcomes-panel">
        <div class="panel-title"><div><strong>Diagnostic outcomes</strong><span>Sessions created in this period</span></div><x-icon name="activity" /></div>
        @php
            $outcomeTotal = $diagnosticOutcomes->sum('count');
            $offset = 0;
            $segments = $diagnosticOutcomes->map(function ($outcome) use (&$offset, $outcomeTotal) {
                $start = $offset;
                $offset += $outcomeTotal ? $outcome['count'] / $outcomeTotal * 100 : 0;
                return $outcome['color'].' '.$start.'% '.$offset.'%';
            })->implode(', ');
        @endphp
        <div class="outcome-ring" style="--ring:{{ $outcomeTotal ? 'conic-gradient('.$segments.')' : '#e9eef5' }}" role="img" aria-label="{{ $outcomeTotal }} diagnostic sessions; breakdown below"><div><strong>{{ number_format($outcomeTotal) }}</strong><span>{{ $outcomeTotal ? 'total sessions' : 'no sessions yet' }}</span></div></div>
        <div class="outcome-legend">@foreach($diagnosticOutcomes as $outcome)<div><i style="background:{{ $outcome['color'] }}"></i><span>{{ $outcome['label'] }}</span><strong>{{ number_format($outcome['count']) }}</strong><small>{{ $outcomeTotal ? round($outcome['count'] / $outcomeTotal * 100) : 0 }}%</small></div>@endforeach</div>
    </article>
    <article class="admin-panel account-panel">
        <div class="panel-title"><div><strong>Your user community</strong><span>All accounts · independent of date range</span></div><button class="panel-link" type="button" data-admin-view="users">Manage users →</button></div>
        <div class="community-total"><strong>{{ number_format($overview['users']) }}</strong><span>current users <small>Includes administrators; excludes deleted accounts</small></span></div>
        <div class="account-distribution" role="img" aria-label="{{ $overview['enabledUsers'] }} enabled, {{ $overview['suspendedUsers'] }} suspended, {{ $overview['deletedUsers'] }} deleted">
            @foreach ([['enabledUsers', '#3982f7'], ['suspendedUsers', '#e7af52'], ['deletedUsers', '#b7c3d3']] as [$key, $color])<span style="width:{{ $overview['allAccounts'] ? $overview[$key] / $overview['allAccounts'] * 100 : 0 }}%;background:{{ $color }}"></span>@endforeach
        </div>
        <div class="community-legend"><span><i style="background:#3982f7"></i>{{ $accountStatusAvailable ? 'Enabled' : 'Current' }} <b>{{ number_format($overview['enabledUsers']) }}</b></span><span><i style="background:#e7af52"></i>Suspended <b>{{ $accountStatusAvailable ? number_format($overview['suspendedUsers']) : '—' }}</b></span><span><i style="background:#b7c3d3"></i>Deleted <b>{{ number_format($overview['deletedUsers']) }}</b></span></div>
        <p class="panel-footnote">{{ number_format($overview['administrators']) }} administrators · {{ number_format($overview['newUsers']) }} new users in the last {{ $analyticsDays }} days</p>
    </article>
