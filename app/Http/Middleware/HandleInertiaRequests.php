<?php

namespace App\Http\Middleware;

use App\Services\CreditService;
use App\Services\Settings;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => $user->first_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'email_verified_at' => $user->email_verified_at,
                    'tiktok_username' => $user->tiktok_username,
                    'credits' => app(CreditService::class)->available($user),
                ] : null,
            ],
            'site' => [
                'whatsapp' => Settings::get('contact_whatsapp'),
                'tiktok' => Settings::get('tiktok_show_url'),
                'timezone' => config('app.display_timezone'),
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
            ],
        ];
    }
}
