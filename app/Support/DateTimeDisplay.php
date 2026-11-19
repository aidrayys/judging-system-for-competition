<?php

namespace App\Support;

use Carbon\CarbonInterface;
use DateTimeInterface;

/**
 * Central, timezone safe formatting of every timestamp shown in the UI.
 *
 * The application stores every timestamp in UTC (config('app.timezone')).
 * Every timestamp is displayed in the configured display timezone
 * (APP_DISPLAY_TIMEZONE, UTC by default) with its short zone name.
 *
 * The conversion happens on the value, never on the stored data: the real
 * event time is never rewritten. The English month abbreviations are
 * applied explicitly, so the output does not depend on the (optional)
 * translator of the PHP installation.
 */
class DateTimeDisplay
{
    /**
     * English month abbreviations.
     *
     * @var array<int, string>
     */
    private const MONTHS = [
        1 => 'Jan',
        2 => 'Feb',
        3 => 'Mar',
        4 => 'Apr',
        5 => 'May',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Aug',
        9 => 'Sep',
        10 => 'Oct',
        11 => 'Nov',
        12 => 'Dec',
    ];

    /**
     * The timezone every timestamp is displayed in.
     */
    public static function timezone(): string
    {
        return (string) config('app.display_timezone', 'UTC');
    }

    /**
     * The short timezone name, e.g. "UTC".
     */
    public static function abbreviation(): string
    {
        return strtoupper(now(self::timezone())->format('T'));
    }

    /**
     * Convert a stored timestamp into the display timezone.
     */
    public static function convert(DateTimeInterface|string|null $date): ?CarbonInterface
    {
        if ($date === null || $date === '') {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($date)->setTimezone(self::timezone());
    }

    /**
     * Full timestamp, e.g. "22 Oct 2026, 10:04:30 UTC".
     */
    public static function format(DateTimeInterface|string|null $date): string
    {
        $converted = self::convert($date);

        if ($converted === null) {
            return '-';
        }

        return $converted->format('d').' '.self::MONTHS[(int) $converted->format('n')].' '.$converted->format('Y, H:i:s')
            .' '.self::abbreviation();
    }

    /**
     * Timestamp without the seconds, e.g. "22 Oct 2026, 10:04 UTC".
     */
    public static function formatShort(DateTimeInterface|string|null $date): string
    {
        $converted = self::convert($date);

        if ($converted === null) {
            return '-';
        }

        return $converted->format('d').' '.self::MONTHS[(int) $converted->format('n')].' '.$converted->format('Y, H:i')
            .' '.self::abbreviation();
    }

    /**
     * The ISO 8601 representation in the display timezone. It is used by the
     * JavaScript side, which formats the value with Intl.DateTimeFormat and
     * the explicit timeZone option.
     */
    public static function iso(DateTimeInterface|string|null $date): ?string
    {
        $converted = self::convert($date);

        return $converted?->format(\DateTimeInterface::ATOM);
    }
}
