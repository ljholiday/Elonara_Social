<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Formats recurring event definitions into human-readable summaries.
 */
final class RecurrenceFormatter
{
    private const WEEKDAY_KEY_MAP = [
        'mon' => 'mon',
        'monday' => 'mon',
        'tue' => 'tue',
        'tues' => 'tue',
        'tuesday' => 'tue',
        'wed' => 'wed',
        'weds' => 'wed',
        'wednesday' => 'wed',
        'thu' => 'thu',
        'thur' => 'thu',
        'thurs' => 'thu',
        'thursday' => 'thu',
        'fri' => 'fri',
        'friday' => 'fri',
        'sat' => 'sat',
        'saturday' => 'sat',
        'sun' => 'sun',
        'sunday' => 'sun',
    ];

    private const WEEKDAY_NAMES = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    private const WEEKDAY_ORDER = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    private const MONTHLY_WEEK_LABELS = [
        'first' => 'first',
        'second' => 'second',
        'third' => 'third',
        'fourth' => 'fourth',
        'last' => 'last',
    ];

    /**
     * Build a human-readable summary of an event's recurrence settings.
     *
     * @param array<string, mixed> $event
     */
    public static function describe(array $event): string
    {
        $type = strtolower((string)($event['recurrence_type'] ?? ''));
        if ($type === '' || $type === 'none') {
            return '';
        }

        $interval = (int)($event['recurrence_interval'] ?? 1);
        if ($interval <= 0) {
            $interval = 1;
        }

        return match ($type) {
            'daily' => self::describeDaily($interval),
            'weekly' => self::describeWeekly($interval, (string)($event['recurrence_days'] ?? '')),
            'monthly' => self::describeMonthly(
                $interval,
                strtolower((string)($event['monthly_type'] ?? 'date')),
                (string)($event['monthly_day'] ?? ''),
                (string)($event['monthly_week'] ?? '')
            ),
            default => '',
        };
    }

    private static function describeDaily(int $interval): string
    {
        return $interval === 1
            ? 'Repeats daily'
            : sprintf('Repeats every %d days', $interval);
    }

    private static function describeWeekly(int $interval, string $daysCsv): string
    {
        $dayNames = self::normalizeWeekdayNames($daysCsv);
        $base = $interval === 1
            ? 'Repeats weekly'
            : sprintf('Repeats every %d weeks', $interval);

        if ($dayNames !== []) {
            $base .= ' on ' . self::joinWithOxfordComma($dayNames);
        }

        return $base;
    }

    private static function describeMonthly(int $interval, string $monthlyType, string $monthlyDay, string $monthlyWeek): string
    {
        $base = $interval === 1
            ? 'Repeats monthly'
            : sprintf('Repeats every %d months', $interval);

        if ($monthlyType === 'weekday') {
            $weekLabel = self::MONTHLY_WEEK_LABELS[$monthlyWeek] ?? '';
            $weekdayKey = self::canonicalWeekdayKey($monthlyDay);
            $weekdayLabel = $weekdayKey !== null ? self::WEEKDAY_NAMES[$weekdayKey] : '';

            if ($weekLabel !== '' && $weekdayLabel !== '') {
                $base .= sprintf(' on the %s %s of the month', $weekLabel, $weekdayLabel);
            }

            return $base;
        }

        $dayNumber = self::parseDayNumber($monthlyDay);
        if ($dayNumber !== null) {
            $base .= sprintf(' on day %d', $dayNumber);
        }

        return $base;
    }

    /**
     * @return array<int, string>
     */
    private static function normalizeWeekdayNames(string $daysCsv): array
    {
        if ($daysCsv === '') {
            return [];
        }

        $parts = array_filter(array_map('trim', explode(',', $daysCsv)));
        $unique = [];

        foreach ($parts as $part) {
            $key = self::canonicalWeekdayKey($part);
            if ($key !== null) {
                $unique[$key] = self::WEEKDAY_NAMES[$key];
            }
        }

        $ordered = [];
        foreach (self::WEEKDAY_ORDER as $weekday) {
            if (isset($unique[$weekday])) {
                $ordered[] = $unique[$weekday];
            }
        }

        return $ordered;
    }

    private static function canonicalWeekdayKey(string $value): ?string
    {
        $key = strtolower(trim($value));
        return self::WEEKDAY_KEY_MAP[$key] ?? null;
    }

    private static function parseDayNumber(string $value): ?int
    {
        $trimmed = trim($value);
        if ($trimmed === '' || !ctype_digit($trimmed)) {
            return null;
        }

        $number = (int)$trimmed;
        return $number > 0 ? $number : null;
    }

    /**
     * @param array<int, string> $items
     */
    private static function joinWithOxfordComma(array $items): string
    {
        $count = count($items);
        if ($count === 0) {
            return '';
        }
        if ($count === 1) {
            return $items[0];
        }
        if ($count === 2) {
            return $items[0] . ' and ' . $items[1];
        }

        $last = array_pop($items);
        return implode(', ', $items) . ', and ' . $last;
    }
}
