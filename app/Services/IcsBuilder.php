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
        $b->loadMissing('slot', 'project');
        $fmt = fn ($d) => $d->copy()->utc()->format('Ymd\THis\Z');
        $where = $b->mode === 'SUR_PLACE' ? Settings::get('studio_address') : 'TikTok (live à distance)';
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//House of Challenge//FR', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:booking-'.$b->id.'@houseofchallenge',
            'DTSTAMP:'.$fmt(now()),
            'DTSTART:'.$fmt($b->slot->starts_at),
            'DTEND:'.$fmt($b->slot->ends_at),
            'SUMMARY:'.self::esc('Passage House of Challenge — '.$b->project->title),
            'LOCATION:'.self::esc((string) $where),
            'END:VEVENT', 'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }
}
