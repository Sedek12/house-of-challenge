<?php

namespace App\Services;

use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Vue jour / semaine du planning (A4), partagée par l'équipe et l'administrateur. */
class Planning
{
    public const STATUSES = ['CONFIRMEE', 'EFFECTUEE', 'ABSENT'];

    /** @return array{view:string, date:string, from:Carbon, to:Carbon} */
    public static function range(Request $request): array
    {
        $tz = config('app.display_timezone');
        // Sans choix, on montre tous les passages à venir (60 jours) : la page d'accueil du planning n'est jamais vide quand il y a des réservations.
        $view = in_array($request->query('view'), ['day', 'week'], true) ? $request->query('view') : 'upcoming';
        try {
            $date = Carbon::createFromFormat('Y-m-d', (string) $request->query('date', now($tz)->format('Y-m-d')), $tz)->startOfDay();
        } catch (\Throwable) {
            $date = now($tz)->startOfDay();
        }
        $from = $view === 'week' ? $date->copy()->startOfWeek() : $date->copy();
        $to = match ($view) {
            'week' => $from->copy()->addWeek(),
            'upcoming' => $from->copy()->addDays(60),
            default => $from->copy()->addDay(),
        };

        return ['view' => $view, 'date' => $date->format('Y-m-d'), 'from' => $from->utc(), 'to' => $to->utc()];
    }

    /** Réservations payées du créneau demandé, dans l'ordre de passage. */
    public static function bookings(Carbon $from, Carbon $to): Collection
    {
        return Booking::with('slot', 'package')->withCount('products')
            ->whereIn('status', self::STATUSES)
            ->whereHas('slot', fn ($q) => $q->where('starts_at', '>=', $from)->where('starts_at', '<', $to))
            ->get()->sortBy(fn (Booking $b) => $b->slot->starts_at)->values();
    }

    public static function row(Booking $b): array
    {
        return [
            'id' => $b->id, 'reference' => $b->reference, 'status' => $b->status, 'mode' => $b->mode,
            'starts_at' => $b->slot->starts_at->toIso8601String(), 'shop' => $b->shop_name, 'name' => $b->full_name,
            'package' => $b->package->name, 'products' => $b->products_count ?? $b->products()->count(),
            'whatsapp' => $b->whatsappDigits(), 'tiktok' => $b->tiktok,
        ];
    }

    /** Page du planning : mêmes données pour l'équipe (lecture seule) et l'admin. */
    public static function props(Request $request): array
    {
        $r = self::range($request);

        return [
            'view' => $r['view'], 'date' => $r['date'],
            'rows' => self::bookings($r['from'], $r['to'])->map(fn (Booking $b) => self::row($b))->values(),
        ];
    }

    /** Fiche live : identifiants précédent / suivant dans la même journée (heure de Lomé). */
    public static function neighbours(Booking $b): array
    {
        $tz = config('app.display_timezone');
        $day = $b->slot->starts_at->copy()->setTimezone($tz)->startOfDay();
        $ids = self::bookings($day->copy()->utc(), $day->copy()->addDay()->utc())->pluck('id')->values();
        $i = $ids->search($b->id);

        return [
            'prev' => $i !== false && $i > 0 ? $ids[$i - 1] : null,
            'next' => $i !== false && $i < $ids->count() - 1 ? $ids[$i + 1] : null,
            'position' => $i === false ? null : $i + 1, 'total' => $ids->count(),
        ];
    }
}
