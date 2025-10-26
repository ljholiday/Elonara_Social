#!/usr/bin/env php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Support\RecurrenceFormatter;

function test(string $name, callable $fn): void {
    echo "\n🧪 Testing: {$name}\n";
    try {
        $result = $fn();
        if ($result === true) {
            echo "✅ PASS\n";
        } else {
            echo "❌ FAIL: " . ($result ?: 'returned false') . "\n";
        }
    } catch (Throwable $e) {
        echo "❌ FAIL: " . $e->getMessage() . "\n";
    }
}

echo "=== Recurrence Formatter Tests ===\n";

test('Returns empty string for non-recurring events', function (): bool {
    $summary = RecurrenceFormatter::describe(['recurrence_type' => 'none']);
    return $summary === '';
});

test('Formats simple daily recurrence', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'daily',
        'recurrence_interval' => 1,
    ]);
    return $summary === 'Repeats daily';
});

test('Formats multi-interval daily recurrence', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'daily',
        'recurrence_interval' => 3,
    ]);
    return $summary === 'Repeats every 3 days';
});

test('Formats weekly recurrence with weekdays', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 1,
        'recurrence_days' => 'wed,mon',
    ]);
    return $summary === 'Repeats weekly on Monday and Wednesday';
});

test('Formats multi-interval weekly recurrence', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'weekly',
        'recurrence_interval' => 2,
        'recurrence_days' => 'mon,wed,fri',
    ]);
    return $summary === 'Repeats every 2 weeks on Monday, Wednesday, and Friday';
});

test('Formats monthly recurrence by day of month', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 1,
        'monthly_type' => 'date',
        'monthly_day' => '15',
    ]);
    return $summary === 'Repeats monthly on day 15';
});

test('Formats monthly recurrence by weekday pattern', function (): bool {
    $summary = RecurrenceFormatter::describe([
        'recurrence_type' => 'monthly',
        'recurrence_interval' => 3,
        'monthly_type' => 'weekday',
        'monthly_week' => 'first',
        'monthly_day' => 'mon',
    ]);
    return $summary === 'Repeats every 3 months on the first Monday of the month';
});
