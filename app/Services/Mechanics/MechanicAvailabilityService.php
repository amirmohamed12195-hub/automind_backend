<?php

namespace App\Services\Mechanics;

use App\Models\Mechanic;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MechanicAvailabilityService
{
    public function withinWorkingHours(Mechanic $mechanic, CarbonImmutable $startUtc, CarbonImmutable $endUtc): bool
    {
        if ($endUtc->lessThanOrEqualTo($startUtc)) {
            return false;
        }
        $timezone = $mechanic->timezone ?: 'UTC';
        $start = $startUtc->setTimezone($timezone);
        $end = $endUtc->setTimezone($timezone);
        $window = $this->windowFor($mechanic, $start);

        return $window !== null
            && $start->greaterThanOrEqualTo($window[0])
            && $end->lessThanOrEqualTo($window[1]);
    }

    /** @return array<int, array{start: string, end: string}> */
    public function slots(Mechanic $mechanic, CarbonImmutable $fromUtc, CarbonImmutable $toUtc, Collection $busy, int $minutes = 60): array
    {
        $timezone = $mechanic->timezone ?: 'UTC';
        $cursor = $fromUtc->setTimezone($timezone)->startOfDay();
        $lastDay = $toUtc->setTimezone($timezone)->startOfDay();
        $slots = [];
        while ($cursor->lessThanOrEqualTo($lastDay)) {
            $window = $this->windowFor($mechanic, $cursor);
            if ($window !== null) {
                [$open, $close] = $window;
                for ($start = $open; $start->addMinutes($minutes)->lessThanOrEqualTo($close); $start = $start->addMinutes($minutes)) {
                    $end = $start->addMinutes($minutes);
                    $startUtc = $start->utc();
                    $endUtc = $end->utc();
                    if ($startUtc->lessThan($fromUtc) || $endUtc->greaterThan($toUtc) || $startUtc->isPast()) {
                        continue;
                    }
                    $conflict = $busy->contains(fn ($appointment) => $startUtc->lessThan($appointment->requested_end_at) && $endUtc->greaterThan($appointment->requested_start_at));
                    if (! $conflict) {
                        $slots[] = ['start' => $startUtc->toIso8601ZuluString(), 'end' => $endUtc->toIso8601ZuluString()];
                    }
                }
            }
            $cursor = $cursor->addDay();
        }

        return $slots;
    }

    /** @return array{CarbonImmutable, CarbonImmutable}|null */
    private function windowFor(Mechanic $mechanic, CarbonImmutable $localDay): ?array
    {
        $key = strtolower($localDay->format('D'));
        $hours = $mechanic->working_hours_json[$key] ?? null;
        if (! is_array($hours) || count($hours) !== 2 || ! is_string($hours[0]) || ! is_string($hours[1])) {
            return null;
        }
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hours[0]) || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hours[1])) {
            return null;
        }
        $open = CarbonImmutable::parse($localDay->toDateString().' '.$hours[0], $localDay->timezone);
        $close = CarbonImmutable::parse($localDay->toDateString().' '.$hours[1], $localDay->timezone);
        if ($close->lessThanOrEqualTo($open)) {
            $close = $close->addDay();
        }

        return [$open, $close];
    }
}
