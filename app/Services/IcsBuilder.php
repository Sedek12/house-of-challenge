<?php

namespace App\Services;

use App\Models\Booking;

class IcsBuilder
{
    private static function esc(string $s): string
    {
        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
    }

    public static function forBooking(Booking $b): string
    {
        $b->loadMissing('slot');
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $where = $b->mode === 'SUR_PLACE' ? Settings::get('studio_address') : 'TikTok (live à distance)';
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//OCD Live//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$b->reference.'@ocdlive',
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($b->slot->starts_at),
            'DTEND:'.$fmt($b->slot->ends_at),
            'SUMMARY:'.self::esc('Passage OCD Live — '.$b->shop_name),
            'LOCATION:'.self::esc((string) $where),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }
}
