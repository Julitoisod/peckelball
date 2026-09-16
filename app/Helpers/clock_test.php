<?php

/**
 * Self-check for clock(). No framework, no PHPUnit:
 *
 *     php app/Helpers/clock_test.php
 *
 * Noon and midnight are the two the 12-hour clock always gets wrong, so they
 * are the reason this file exists.
 */

require __DIR__ . '/bernie_helper.php';

$cases = [
    // [input, axis?, expected]
    ['06:00', true,  '6 AM'],
    ['06:30', true,  '6:30'],
    ['12:00', true,  '12 PM'],
    ['00:00', true,  '12 AM'],
    ['17:00', true,  '5 PM'],
    ['21:30', true,  '9:30'],
    ['06:00:00', false, '6:00 AM'],
    ['12:00:00', false, '12:00 PM'],
    ['00:30:00', false, '12:30 AM'],
    ['13:30', false, '1:30 PM'],
    ['2026-08-08 21:00:00', false, '9:00 PM'],
    [null, false, '--:--'],
    ['', false, '--:--'],
];

$failed = 0;

foreach ($cases as [$input, $axis, $expected]) {
    $actual = clock($input, $axis);
    $ok     = $actual === $expected;
    $failed += $ok ? 0 : 1;

    printf(
        "%s clock(%s%s) = %-9s %s\n",
        $ok ? 'ok  ' : 'FAIL',
        var_export($input, true),
        $axis ? ', axis' : '',
        $actual,
        $ok ? '' : "expected {$expected}"
    );
}

echo $failed === 0 ? "\nAll " . count($cases) . " passed.\n" : "\n{$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
