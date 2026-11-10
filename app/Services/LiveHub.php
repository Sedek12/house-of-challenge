<?php

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Models\LivePost;
use App\Models\LiveSubscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Règles de l'annuaire des lives TikTok (toutes réglables dans Admin → Paramètres) :
 * - abonnement : prix, durée (30 jours) et quota de lives (15) ; seul un live validé est décompté ;
 * - 1 live validé par jour et 1 seul live en attente ou affiché à la fois ;
 * - envoi du lien seulement pendant les permanences (8 h–11 h, 18 h–21 h, heure de Lomé) ;
 * - une demande non traitée en 1 h s'annule ; un live validé reste affiché 2 h, prolongeable une fois d'1 h.
 */
class LiveHub
{
    public const TIKTOK_HOSTS = '/^((www|m|vm|vt)\.)?tiktok\.com$/';

    private static function tz(): string
    {
        return (string) config('app.display_timezone', 'Africa/Lome');
    }

    /* ---------- Permanences de vérification ---------- */

    /** @return array<int, array{0:string,1:string}> ex. [['08:00','11:00'], ['18:00','21:00']] */
    public static function windows(): array
    {
        preg_match_all('/(\d{1,2}):?(\d{2})?\s*-\s*(\d{1,2}):?(\d{2})?/', (string) Settings::get('live_windows'), $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $w) {
            $out[] = [sprintf('%02d:%02d', $w[1], $w[2] ?: 0), sprintf('%02d:%02d', $w[3], $w[4] ?: 0)];
        }

        return $out;
    }

    /** Permanence en cours (et sa fin) ou prochaine permanence (et son début), à l'heure de Lomé. */
    public static function windowState(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->setTimezone(self::tz());
        $next = null;
        foreach ([0, 1] as $day) {
            foreach (self::windows() as [$a, $b]) {
                $start = $now->copy()->addDays($day)->setTimeFromTimeString($a);
                $end = $now->copy()->addDays($day)->setTimeFromTimeString($b);
                if ($now->gte($start) && $now->lt($end)) {
                    return ['open' => true, 'until' => $end->toIso8601String(), 'next' => null, 'label' => self::windowsLabel()];
                }
                if ($start->gt($now) && (! $next || $start->lt($next))) {
                    $next = $start;
                }
            }
        }

        return ['open' => false, 'until' => null, 'next' => $next?->toIso8601String(), 'label' => self::windowsLabel()];
    }

    /** « 8 h – 11 h et 18 h – 21 h » */
    public static function windowsLabel(): string
    {
        $h = fn (string $t) => ltrim(substr($t, 0, 2), '0').' h'.(substr($t, 3) === '00' ? '' : ' '.substr($t, 3));

        return collect(self::windows())->map(fn ($w) => $h($w[0] === '00:00' ? '0:00' : $w[0]).' – '.$h($w[1]))->join(', ', ' et ');
    }

    /* ---------- Abonnement ---------- */

    /** Abonnement utilisable : actif, non échu, avec des lives restants (le plus ancien d'abord). */
    public function activeSubscription(User $u): ?LiveSubscription
    {
        return $u->liveSubscriptions()->where('status', 'ACTIVE')->where('ends_at', '>', now())->orderBy('ends_at')->get()
            ->first(fn (LiveSubscription $s) => $s->remaining() > 0);
    }

    /** Crée (ou reprend) la demande d'abonnement en attente de paiement. */
    public function subscribe(User $u): LiveSubscription
    {
        if (! $u->isVendor()) {
            throw new BusinessException('Seul un compte vendeur peut s\'abonner.');
        }
        $pending = $u->liveSubscriptions()->where('status', 'EN_ATTENTE_PAIEMENT')->latest('id')->first();
        $values = ['amount' => Settings::int('live_price'), 'quota' => Settings::int('live_quota'), 'days' => Settings::int('live_days')];
        if ($pending) {
            $pending->update($values);

            return $pending;
        }

        return $u->liveSubscriptions()->create($values + ['status' => 'EN_ATTENTE_PAIEMENT']);
    }

    /** Paiement reçu : l'abonnement démarre maintenant pour sa durée. Idempotent. */
    public function activate(LiveSubscription $s): LiveSubscription
    {
        $done = DB::transaction(function () use ($s) {
            $s = LiveSubscription::lockForUpdate()->find($s->id);
            if ($s->status !== 'EN_ATTENTE_PAIEMENT') {
                return false;
            }
            $s->update(['status' => 'ACTIVE', 'starts_at' => now(), 'ends_at' => now()->addDays($s->days)]);

            return true;
        });
        $s->refresh();
        if ($done) {
            Notifier::liveSubscriptionActivated($s);
        }

        return $s;
    }

    public function cancelPending(LiveSubscription $s): void
    {
        LiveSubscription::whereKey($s->id)->where('status', 'EN_ATTENTE_PAIEMENT')->update(['status' => 'ANNULEE']);
    }

    /* ---------- Envoi du lien ---------- */

    /** Live en attente ou affiché du vendeur (un seul à la fois). */
    public function current(User $u): ?LivePost
    {
        return $u->livePosts()->where(fn ($q) => $q->where('status', 'EN_ATTENTE')->orWhere(fn ($q) => $q->onAir()))->latest('id')->first();
    }

    public function approvedToday(User $u): int
    {
        $start = now()->setTimezone(self::tz())->startOfDay()->utc();

        return $u->livePosts()->where('approved_at', '>=', $start)->count();
    }

    /**
     * Ce qui empêche le vendeur d'envoyer un live maintenant (null : il peut).
     *
     * @return null|array{code:string, message:string}
     */
    public function blocker(User $u): ?array
    {
        $sub = $this->activeSubscription($u);
        $any = $u->liveSubscriptions()->where('status', 'ACTIVE')->where('ends_at', '>', now())->exists();

        return match (true) {
            ! $sub && $any => ['code' => 'quota', 'message' => 'Vous avez utilisé tous les lives de votre abonnement. Renouvelez-le pour continuer.'],
            ! $sub => ['code' => 'subscription', 'message' => 'Activez votre abonnement pour mettre vos lives en avant.'],
            $this->current($u) !== null => ['code' => 'current', 'message' => 'Vous avez déjà un live en cours de vérification ou affiché.'],
            $this->approvedToday($u) >= Settings::int('live_per_day') => ['code' => 'today', 'message' => 'Vous avez déjà eu votre live aujourd\'hui. Revenez demain !'],
            ! self::windowState()['open'] => ['code' => 'closed', 'message' => 'L\'équipe vérifie les lives de '.self::windowsLabel().'. Revenez à ce moment-là.'],
            default => null,
        };
    }

    /** @param array{seller_name:string, phone:string, country:string, category:string, tiktok_url:string} $d */
    public function submit(User $u, array $d, ?UploadedFile $photo = null): LivePost
    {
        if ($b = $this->blocker($u)) {
            throw new BusinessException($b['message']);
        }
        [$url, $handle] = self::normalizeUrl($d['tiktok_url']);
        $last = $u->livePosts()->latest('id')->first();

        return $u->livePosts()->create([
            'live_subscription_id' => $this->activeSubscription($u)->id,
            'seller_name' => trim($d['seller_name']), 'phone' => $d['phone'], 'country' => $d['country'], 'category' => $d['category'],
            'tiktok_url' => $url, 'tiktok_handle' => $handle,
            'photo_path' => $photo ? UploadService::storeImage($photo, 'lives', 400) : $last?->photo_path,
            'status' => 'EN_ATTENTE',
        ]);
    }

    /* ---------- Modération (administrateur) ---------- */

    public function approve(User $admin, LivePost $p): LivePost
    {
        $expired = DB::transaction(function () use ($p, $admin) {
            $p = LivePost::lockForUpdate()->find($p->id);
            if ($p->status !== 'EN_ATTENTE') {
                throw new BusinessException('Ce live a déjà été traité.');
            }
            if ($p->created_at->lt(now()->subMinutes(Settings::int('live_pending_minutes')))) {
                $p->update(['status' => 'EXPIRE']); // enregistré : l'erreur est levée après la transaction

                return true;
            }
            $sub = $p->subscription;
            if (! $sub->isActive() || $sub->remaining() < 1) {
                throw new BusinessException('L\'abonnement du vendeur est épuisé ou terminé.');
            }
            $p->update([
                'status' => 'EN_LIGNE', 'approved_at' => now(), 'reviewed_by_id' => $admin->id,
                'live_until' => now()->addMinutes(Settings::int('live_display_minutes')),
            ]);

            return false;
        });
        if ($expired) {
            throw new BusinessException('Demande trop ancienne : elle a expiré. Le vendeur peut renvoyer son lien.');
        }
        $p->refresh();
        Audit::log('LIVE_APPROVE', 'LivePost', $p->id);
        Notifier::liveApproved($p);

        return $p;
    }

    public function reject(User $admin, LivePost $p, string $reason): LivePost
    {
        if ($p->status !== 'EN_ATTENTE') {
            throw new BusinessException('Ce live a déjà été traité.');
        }
        $p->update(['status' => 'REFUSE', 'reject_reason' => $reason, 'reviewed_by_id' => $admin->id]);
        Audit::log('LIVE_REJECT', 'LivePost', $p->id, ['reason' => $reason]);
        Notifier::liveRejected($p);

        return $p;
    }

    /** Retrait par l'administrateur, à tout moment. */
    public function remove(User $admin, LivePost $p): void
    {
        if ($p->isOnAir()) {
            $p->update(['status' => 'TERMINE', 'ended_at' => now(), 'live_until' => now()]);
            Audit::log('LIVE_REMOVE', 'LivePost', $p->id, ['by' => $admin->id]);
        }
    }

    /* ---------- Actions du vendeur sur son live affiché ---------- */

    public function extend(User $u, LivePost $p): LivePost
    {
        if ($p->user_id !== $u->id || ! $p->isOnAir()) {
            throw new BusinessException('Ce live n\'est plus affiché.');
        }
        if ($p->extended) {
            throw new BusinessException('Vous avez déjà prolongé ce live.');
        }
        $p->update(['extended' => true, 'live_until' => $p->live_until->addMinutes(Settings::int('live_extend_minutes'))]);

        return $p;
    }

    public function finish(User $u, LivePost $p): void
    {
        if ($p->user_id !== $u->id) {
            throw new BusinessException('Live introuvable.');
        }
        if ($p->status === 'EN_ATTENTE') {
            $p->update(['status' => 'EXPIRE', 'ended_at' => now()]); // demande retirée avant vérification : ne compte pas
        } elseif ($p->isOnAir()) {
            $p->update(['status' => 'TERMINE', 'ended_at' => now(), 'live_until' => now()]);
        }
    }

    /* ---------- Tâche planifiée ---------- */

    public function maintenance(): array
    {
        $expired = LivePost::where('status', 'EN_ATTENTE')->where('created_at', '<', now()->subMinutes(Settings::int('live_pending_minutes')))->update(['status' => 'EXPIRE']);
        $ended = LivePost::where('status', 'EN_LIGNE')->where('live_until', '<=', now())->update(['status' => 'TERMINE', 'ended_at' => now()]);
        $subs = LiveSubscription::where('status', 'ACTIVE')->where('ends_at', '<=', now())->update(['status' => 'EXPIREE']);
        LiveSubscription::where('status', 'EN_ATTENTE_PAIEMENT')->where('created_at', '<', now()->subDay())->update(['status' => 'ANNULEE']);

        $reminders = 0;
        LiveSubscription::with('user')->where('status', 'ACTIVE')->whereNull('reminder_sent_at')
            ->whereBetween('ends_at', [now(), now()->addDays(Settings::int('live_reminder_days'))])
            ->each(function (LiveSubscription $s) use (&$reminders) {
                $s->update(['reminder_sent_at' => now()]);
                Notifier::liveSubscriptionEnding($s);
                $reminders++;
            });

        return ['lives_expired' => $expired, 'lives_ended' => $ended, 'live_subscriptions_expired' => $subs, 'live_reminders' => $reminders];
    }

    /* ---------- Lien TikTok ---------- */

    /**
     * Accepte ce que le vendeur colle : le texte de partage de TikTok (« Regarde le LIVE… https://vm.tiktok.com/… »),
     * un lien tiktok.com ou simplement « @compte ». Rend l'adresse du live du compte : https://www.tiktok.com/@compte/live.
     *
     * @return array{0:string, 1:?string} [adresse, compte]
     */
    public static function normalizeUrl(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^@?([A-Za-z0-9._]{2,24})$/', $raw, $m) && str_starts_with($raw, '@')) {
            return ['https://www.tiktok.com/@'.$m[1].'/live', $m[1]];
        }
        if (! preg_match('~https?://\S+~i', $raw, $m)) {
            if (! preg_match('~(?:^|\s)((?:www\.|m\.|vm\.|vt\.)?tiktok\.com/\S+)~i', $raw, $m2)) {
                throw new BusinessException('Collez le lien de votre live TikTok (il contient « tiktok.com »).');
            }
            $m = ['https://'.$m2[1]];
        }
        $url = rtrim($m[0], '.,;!)»"\'');
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (! preg_match(self::TIKTOK_HOSTS, $host)) {
            throw new BusinessException('Ce lien n\'est pas un lien TikTok.');
        }
        if (in_array($host, ['vm.tiktok.com', 'vt.tiktok.com'], true)) {
            $url = self::expand($url) ?? $url;
        }
        if (preg_match('~tiktok\.com/@([A-Za-z0-9._]{2,24})~i', $url, $h)) {
            return ['https://www.tiktok.com/@'.$h[1].'/live', $h[1]];
        }

        return [strtok($url, '?#'), null];
    }

    /** Suit les redirections d'un lien court TikTok, uniquement vers tiktok.com (3 au plus). */
    private static function expand(string $url): ?string
    {
        try {
            for ($i = 0; $i < 3; $i++) {
                $res = Http::withOptions(['allow_redirects' => false])->withUserAgent('Mozilla/5.0 (OCD Live)')->timeout(5)->get($url);
                $loc = $res->header('Location');
                if (! $res->redirect() || ! $loc) {
                    return $i > 0 ? $url : null;
                }
                if (! preg_match(self::TIKTOK_HOSTS, strtolower((string) parse_url($loc, PHP_URL_HOST)))) {
                    return null;
                }
                $url = $loc;
                if (str_contains($url, '/@')) {
                    return $url;
                }
            }
        } catch (\Throwable $e) {
            Log::info('Lien TikTok court non résolu : '.$e->getMessage());
        }

        return null;
    }
}
