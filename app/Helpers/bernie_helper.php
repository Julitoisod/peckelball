<?php

/**
 * Formatting used across views. Kept small on purpose — anything with real
 * logic belongs in app/Services.
 */

use CodeIgniter\View\View;

if (! function_exists('partial')) {
    /**
     * Render a partial with its own data.
     *
     * `$this->include()` cannot do this — its second argument is render
     * *options*, not view data, so `$this->include('x', ['a' => 1])` silently
     * drops `$a` and the partial dies on an undefined variable. A fresh View
     * instance also keeps the partial from clobbering the section/layout state
     * of the render already in progress.
     *
     * @param array<string, mixed> $data
     */
    function partial(string $view, array $data = []): string
    {
        static $renderer = null;
        $renderer ??= new View(config('View'), config('Paths')->viewDirectory);

        return $renderer->setData($data, 'raw')->render($view, null, false);
    }
}

if (! function_exists('peso')) {
    /** `peso(1234.5)` → `₱1,235`; `peso(1234.5, true)` → `₱1,234.50` */
    function peso(float|int|string|null $amount, bool $decimals = false): string
    {
        // Reads the setting rather than hardcoding the glyph, so the symbol on
        // the Settings screen is a control and not a decoration.
        return setting('pricing.currency_symbol', '₱') . number_format((float) $amount, $decimals ? 2 : 0);
    }
}

if (! function_exists('clock')) {
    /**
     * `clock('18:30:00')` → `6:30 PM`. Twelve-hour, because that is how the
     * desk and the players say it out loud.
     *
     * `$axis` is for the board's time row, where 32 columns cannot each carry
     * a meridiem: the hour marks are labelled `6 AM`, `7 AM` and the half-hours
     * between them stay bare, which no one can misread.
     */
    function clock(?string $time, bool $axis = false): string
    {
        if ($time === null || $time === '') {
            return '--:--';
        }

        $stamp = strtotime($time);
        if ($stamp === false) {
            return substr($time, 0, 5);
        }

        if ($axis) {
            return date('i', $stamp) === '00' ? date('g A', $stamp) : date('g:i', $stamp);
        }

        return date('g:i A', $stamp);
    }
}

if (! function_exists('unsplash')) {
    /**
     * Build a sized Unsplash URL from a stored photo id.
     * Ids are verified at seed time; a missing one returns null so callers can
     * fall back rather than ship a broken <img>.
     */
    function unsplash(?string $photoId, int $w = 800, ?int $h = null, int $q = 72): ?string
    {
        if (empty($photoId)) {
            return null;
        }

        $params = ['auto' => 'format', 'fit' => 'crop', 'w' => $w, 'q' => $q];
        if ($h !== null) {
            $params['h'] = $h;
        }

        return 'https://images.unsplash.com/' . $photoId . '?' . http_build_query($params);
    }
}

if (! function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        return service('siteSettings')->get($key, $default);
    }
}

if (! function_exists('status_meta')) {
    /**
     * Availability states carry a glyph and a label as well as a hue — colour
     * alone is not an accessible signal, and green/red is the worst pair to
     * rely on. See DESIGN.md § Color.
     *
     * @return array{tone:string, glyph:string, label:string}
     */
    function status_meta(string $state): array
    {
        return match ($state) {
            'open'        => ['tone' => 'open',    'glyph' => '▲', 'label' => 'Open'],
            'filling'     => ['tone' => 'filling', 'glyph' => '◆', 'label' => 'Filling'],
            'booked'      => ['tone' => 'booked',  'glyph' => '■', 'label' => 'Booked'],
            'maintenance' => ['tone' => 'filling', 'glyph' => '⚑', 'label' => 'Maintenance'],
            'closed'      => ['tone' => 'closed',  'glyph' => '✕', 'label' => 'Closed'],
            'past'        => ['tone' => 'closed',  'glyph' => '·', 'label' => 'Past'],
            default       => ['tone' => 'closed',  'glyph' => '·', 'label' => ucfirst($state)],
        };
    }
}

if (! function_exists('booking_status_meta')) {
    /** @return array{tone:string, label:string} */
    function booking_status_meta(string $status): array
    {
        return match ($status) {
            'pending'    => ['tone' => 'filling', 'label' => 'Pending'],
            'confirmed'  => ['tone' => 'open',    'label' => 'Confirmed'],
            'checked_in' => ['tone' => 'live',    'label' => 'On court'],
            'completed'  => ['tone' => 'closed',  'label' => 'Completed'],
            'cancelled'  => ['tone' => 'booked',  'label' => 'Cancelled'],
            'no_show'    => ['tone' => 'booked',  'label' => 'No-show'],
            default      => ['tone' => 'closed',  'label' => ucfirst($status)],
        };
    }
}

if (! function_exists('initials')) {
    function initials(string $first, string $last = ''): string
    {
        return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }
}

if (! function_exists('pipes')) {
    /** Seeded multi-values are pipe-delimited. @return list<string> */
    function pipes(?string $value): array
    {
        return $value === null || $value === '' ? [] : explode('|', $value);
    }
}

if (! function_exists('business_date')) {
    /**
     * The session date "today" means for bookings. The facility can run past
     * midnight (16:00 → 06:00), and at 02:00 the players on court booked last
     * night's date. Use this, not date('Y-m-d'), for anything operational.
     */
    function business_date(): string
    {
        return service('availability')->businessDate();
    }
}

if (! function_exists('has_peak')) {
    /** Peak start == end in Settings means one flat hourly rate all session. */
    function has_peak(): bool
    {
        return setting('pricing.peak_start', '17:00') !== setting('pricing.peak_end', '21:00');
    }
}

if (! function_exists('rate_label')) {
    /** `₱250` for a flat rate, `₱380–₱480` when peak differs. */
    function rate_label(array $court): string
    {
        $base = peso($court['hourly_rate']);

        return has_peak() && (float) $court['peak_rate'] !== (float) $court['hourly_rate']
            ? $base . '–' . peso($court['peak_rate'])
            : $base;
    }
}

if (! function_exists('day_label')) {
    /** `day_label('2026-08-05')` → `Wed 05 Aug`, or `Today` / `Tomorrow`. */
    function day_label(string $date): string
    {
        $today = business_date();
        if ($date === $today) {
            return 'Today';
        }
        if ($date === date('Y-m-d', strtotime($today . ' +1 day'))) {
            return 'Tomorrow';
        }

        return date('D d M', strtotime($date));
    }
}
