<?php
/**
 * Appointment booking: configuration defaults and slot computation.
 *
 * Pure date/interval logic, no network I/O, so everything here can be
 * exercised from the CLI with handcrafted busy fixtures. The Google
 * Calendar calls live in includes/google-calendar.php.
 *
 * All wall-clock reasoning happens in the configured booking timezone with
 * DateTimeImmutable; availability comparisons happen on unix timestamps,
 * which makes them DST-proof.
 */

declare(strict_types=1);

/**
 * Merge the booking config with safe defaults so a config.php that predates
 * the booker never fatals.
 */
function vdvBookingConfig(array $config): array
{
    $defaults = [
        'booking_calendar_id'    => 'info@vandervolpi.com',
        'booking_impersonate'    => 'info@vandervolpi.com',
        'booking_timezone'       => 'Europe/Brussels',
        'booking_days'           => [1, 2, 3, 4, 5], // ISO weekdays, Mon=1
        'booking_window_start'   => '09:00',
        'booking_window_end'     => '17:00',
        'booking_lead_hours'     => 48,
        'booking_horizon_days'   => 28,
        'booking_buffer_minutes' => 30,
        'booking_types'          => [
            'intake'   => ['label' => 'Intake call',      'duration' => 20, 'price' => 'Free'],
            'training' => ['label' => 'Training booking', 'duration' => 60, 'price' => 'Free'],
            'legal'    => ['label' => 'Legal session',    'duration' => 60, 'price' => '€170/hour'],
        ],
        'booking_disable_google' => false,
    ];
    return array_merge($defaults, $config);
}

/**
 * Sort and coalesce busy intervals ([['start' => ts, 'end' => ts], ...]).
 * freeBusy usually returns them merged already; merging defensively keeps
 * the overlap check simple and handles all-day events like any other block.
 */
function vdvBookingMergeBusy(array $busy): array
{
    usort($busy, static function (array $a, array $b): int {
        return $a['start'] <=> $b['start'];
    });

    $merged = [];
    foreach ($busy as $interval) {
        if ($interval['end'] <= $interval['start']) {
            continue;
        }
        $last = count($merged) - 1;
        if ($last >= 0 && $interval['start'] <= $merged[$last]['end']) {
            $merged[$last]['end'] = max($merged[$last]['end'], $interval['end']);
        } else {
            $merged[] = $interval;
        }
    }
    return $merged;
}

/**
 * True when [startTs, endTs) overlaps none of the (merged) busy intervals,
 * half-open on both sides so back-to-back intervals do not collide.
 */
function vdvBookingIsFree(int $startTs, int $endTs, array $mergedBusy): bool
{
    foreach ($mergedBusy as $interval) {
        if ($interval['start'] < $endTs && $interval['end'] > $startTs) {
            return false;
        }
    }
    return true;
}

/**
 * Generate every candidate slot start for one call type, ignoring calendar
 * busy times: allowed weekday, hourly grid inside the daily window, at least
 * lead_hours away, within the horizon, and the event itself must fit the
 * window. Returns DateTimeImmutable[] in the booking timezone.
 *
 * $now is injectable for tests.
 */
function vdvBookingCandidateStarts(array $cfg, string $typeKey, ?DateTimeImmutable $now = null): array
{
    $tz  = new DateTimeZone($cfg['booking_timezone']);
    $now = $now ? $now->setTimezone($tz) : new DateTimeImmutable('now', $tz);

    $type            = $cfg['booking_types'][$typeKey];
    $durationMinutes = (int) $type['duration'];

    $earliest   = $now->add(new DateInterval('PT' . (int) $cfg['booking_lead_hours'] . 'H'));
    $horizonEnd = $now->add(new DateInterval('P' . (int) $cfg['booking_horizon_days'] . 'D'))
        ->setTime(23, 59, 59);

    [$startHour, $startMinute] = array_map('intval', explode(':', $cfg['booking_window_start']));
    [$endHour, $endMinute]     = array_map('intval', explode(':', $cfg['booking_window_end']));

    $starts = [];
    $day    = $now->setTime(0, 0);
    while ($day <= $horizonEnd) {
        if (in_array((int) $day->format('N'), $cfg['booking_days'], true)) {
            // Build each start from its wall-clock time on this day (never by
            // adding hours across midnight) so 09:00 means 09:00 local on
            // every day, DST transitions included.
            $windowEnd = $day->setTime($endHour, $endMinute);
            for ($hour = $startHour; $hour < 24; $hour++) {
                $start = $day->setTime($hour, $startMinute);
                $end   = $start->add(new DateInterval('PT' . $durationMinutes . 'M'));
                if ($end > $windowEnd) {
                    break;
                }
                // The buffer is deliberately not applied at the window edges:
                // it only protects against calendar events. The edges of the
                // working day are the owner's to manage.
                if ($start >= $earliest && $start <= $horizonEnd) {
                    $starts[] = $start;
                }
            }
        }
        $day = $day->add(new DateInterval('P1D'))->setTime(0, 0);
    }

    return $starts;
}

/**
 * Filter candidate starts against merged busy intervals: a slot survives when
 * [start - buffer, start + duration + buffer] touches no busy time.
 * Returns the surviving DateTimeImmutable[].
 */
function vdvBookingFilterBusy(array $cfg, string $typeKey, array $candidateStarts, array $busy): array
{
    $durationSeconds = ((int) $cfg['booking_types'][$typeKey]['duration']) * 60;
    $bufferSeconds   = ((int) $cfg['booking_buffer_minutes']) * 60;
    $mergedBusy      = vdvBookingMergeBusy($busy);

    return array_values(array_filter($candidateStarts, static function (DateTimeImmutable $start) use ($durationSeconds, $bufferSeconds, $mergedBusy): bool {
        $ts = $start->getTimestamp();
        return vdvBookingIsFree($ts - $bufferSeconds, $ts + $durationSeconds + $bufferSeconds, $mergedBusy);
    }));
}

/**
 * Group available slots per day for the API response. Every allowed weekday
 * in the bookable range is listed, including days without slots, so the UI
 * can gray them out.
 *
 * @param DateTimeImmutable[] $availableStarts
 */
function vdvBookingGroupByDay(array $cfg, array $availableStarts, ?DateTimeImmutable $now = null): array
{
    $tz  = new DateTimeZone($cfg['booking_timezone']);
    $now = $now ? $now->setTimezone($tz) : new DateTimeImmutable('now', $tz);

    $byDate = [];
    foreach ($availableStarts as $start) {
        $byDate[$start->format('Y-m-d')][] = [
            'start' => $start->format('Y-m-d H:i'),
            'label' => $start->format('H:i'),
        ];
    }

    // The listed range starts on the first allowed weekday that could hold a
    // slot (>= lead time) and runs to the horizon.
    $earliestDay = $now->add(new DateInterval('PT' . (int) $cfg['booking_lead_hours'] . 'H'))->setTime(0, 0);
    $horizonDay  = $now->add(new DateInterval('P' . (int) $cfg['booking_horizon_days'] . 'D'))->setTime(0, 0);

    $days = [];
    for ($day = $earliestDay; $day <= $horizonDay; $day = $day->add(new DateInterval('P1D'))) {
        if (!in_array((int) $day->format('N'), $cfg['booking_days'], true)) {
            continue;
        }
        $date   = $day->format('Y-m-d');
        $days[] = [
            'date'  => $date,
            'label' => $day->format('D j M'),
            'slots' => $byDate[$date] ?? [],
        ];
    }
    return $days;
}

/**
 * Strictly parse a visitor-submitted slot string ('Y-m-d H:i') and confirm
 * the generator could have emitted it (weekday, grid, window, lead time,
 * horizon). Returns the start as DateTimeImmutable, or null when the value
 * is forged, off-grid, in the past or out of range.
 */
function vdvBookingParseSlot(array $cfg, string $typeKey, string $slot, ?DateTimeImmutable $now = null): ?DateTimeImmutable
{
    $tz    = new DateTimeZone($cfg['booking_timezone']);
    $start = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $slot, $tz);
    if ($start === false || $start->format('Y-m-d H:i') !== $slot) {
        return null;
    }

    foreach (vdvBookingCandidateStarts($cfg, $typeKey, $now) as $candidate) {
        if ($candidate->getTimestamp() === $start->getTimestamp()) {
            return $start;
        }
    }
    return null;
}
